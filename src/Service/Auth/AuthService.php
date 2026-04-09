<?php
namespace Mublo\Service\Auth;

use Mublo\Core\Session\SessionInterface;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Entity\Member\Member;
use Mublo\Core\Result\Result;
use Mublo\Service\Auth\Event\MemberLoggedInEvent;
use Mublo\Service\Auth\LoginAttemptService;

/**
 * AuthService
 *
 * 인증 서비스
 * - 로그인/로그아웃
 * - 사용자 인증 상태 확인
 * - 권한 체크
 */
class AuthService
{
    private SessionInterface $session;
    private MemberRepository $memberRepository;
    private ?EventDispatcher $eventDispatcher;
    private ?LoginAttemptService $loginAttemptService;
    private ?array $user = null;

    private const SESSION_USER_KEY = 'auth_user';
    private const SESSION_LOGIN_TIME = 'auth_login_time';
    private const SESSION_PROXY_LOGIN = 'proxy_login';

    public function __construct(
        SessionInterface $session,
        MemberRepository $memberRepository,
        ?EventDispatcher $eventDispatcher = null,
        ?LoginAttemptService $loginAttemptService = null
    ) {
        $this->session = $session;
        $this->memberRepository = $memberRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->loginAttemptService = $loginAttemptService;
    }

    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }

    /**
     * 로그인 시도
     *
     * @param int $domainId 도메인 ID (멀티테넌트 환경에서 필수)
     * @param string $userId 사용자 아이디
     * @param string $password 비밀번호
     * @param string $ipAddress 요청 IP (rate limiting용)
     */
    public function attempt(int $domainId, string $userId, string $password, string $ipAddress = ''): Result
    {
        // Rate limit 확인
        if ($this->loginAttemptService && $ipAddress !== '') {
            $check = $this->loginAttemptService->check($domainId, $userId, $ipAddress);
            if (!$check['allowed']) {
                return Result::failure($check['message']);
            }
        }

        // 도메인+아이디로 사용자 조회 (도메인 스코프 적용)
        $member = $this->memberRepository->findByDomainAndUserId($domainId, $userId);

        // 사용자 없음
        if (!$member) {
            $this->recordAttempt($domainId, $userId, $ipAddress, false);
            return Result::failure('아이디 또는 비밀번호가 일치하지 않습니다.');
        }

        // 계정 상태 확인 (DB: active, inactive, dormant, blocked)
        if (!$member->isActive()) {
            $statusMessages = [
                'inactive' => '비활성화된 계정입니다.',
                'dormant' => '휴면 계정입니다. 휴면 해제 후 이용해 주세요.',
                'blocked' => '정지된 계정입니다.',
                'withdrawn' => '탈퇴한 계정입니다.',
            ];

            return Result::failure($statusMessages[$member->getStatus()->value] ?? '로그인할 수 없는 계정입니다.');
        }

        // 비밀번호 검증
        if (!password_verify($password, $member->getPassword())) {
            $this->recordAttempt($domainId, $userId, $ipAddress, false);
            return Result::failure('아이디 또는 비밀번호가 일치하지 않습니다.');
        }

        // 로그인 성공 기록
        $this->recordAttempt($domainId, $userId, $ipAddress, true);

        // 로그인 성공 처리
        $this->loginUser($member);

        // 마지막 로그인 시간 업데이트
        $this->memberRepository->updateLastLogin($member->getMemberId());

        // 로그인 이벤트 발행
        $this->dispatch(new MemberLoggedInEvent($member));

        return Result::success('로그인 성공', ['user' => $this->user]);
    }

    /**
     * 로그인 시도 기록 (내부 헬퍼)
     */
    private function recordAttempt(int $domainId, string $userId, string $ipAddress, bool $success): void
    {
        if ($this->loginAttemptService && $ipAddress !== '') {
            $this->loginAttemptService->record($domainId, $userId, $ipAddress, $success);
        }
    }

    /**
     * Member 객체로 직접 로그인 (SNS 로그인 등 외부 인증용)
     */
    public function loginByMember(Member $member): void
    {
        $this->loginUser($member);
        $this->memberRepository->updateLastLogin($member->getMemberId());
        $this->dispatch(new MemberLoggedInEvent($member));
    }

    /**
     * 사용자 로그인 처리
     */
    private function loginUser(Member $member): void
    {
        // 세션 ID 재생성 (세션 고정 공격 방지)
        $this->session->regenerate(true);

        // 민감 정보 제거된 배열
        $safeUser = $member->toSafeArray();

        // 세션에 저장
        $this->session->set(self::SESSION_USER_KEY, $safeUser);
        $this->session->set(self::SESSION_LOGIN_TIME, time());

        $this->user = $safeUser;
    }

    /**
     * 현재 로그인 세션을 DB 기준으로 갱신
     *
     * 회원 등급/도메인 변경 후 세션 동기화에 사용
     */
    public function refreshSession(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $memberId = (int) ($user['member_id'] ?? 0);
        if ($memberId === 0) {
            return false;
        }

        $member = $this->memberRepository->find($memberId);
        if (!$member) {
            return false;
        }

        $safeUser = $member->toSafeArray();
        $this->session->set(self::SESSION_USER_KEY, $safeUser);
        $this->user = $safeUser;

        return true;
    }

    /**
     * 대리 로그인 정보 세션에 저장
     */
    public function setProxyLogin(int $sourceDomainId, int $adminMemberId, string $adminNickname = '관리자', string $siteName = ''): void
    {
        $this->session->set(self::SESSION_PROXY_LOGIN, [
            'source_domain_id' => $sourceDomainId,
            'admin_member_id' => $adminMemberId,
            'admin_nickname' => $adminNickname,
            'site_name' => $siteName,
        ]);
    }

    /**
     * 대리 로그인 여부 확인
     */
    public function isProxyLogin(): bool
    {
        return $this->session->has(self::SESSION_PROXY_LOGIN);
    }

    /**
     * 대리 로그인 정보 조회
     */
    public function getProxyLogin(): ?array
    {
        return $this->session->get(self::SESSION_PROXY_LOGIN);
    }

    public function logout(): void
    {
        $this->session->remove(self::SESSION_USER_KEY);
        $this->session->remove(self::SESSION_LOGIN_TIME);
        $this->session->remove(self::SESSION_PROXY_LOGIN);
        $this->session->regenerate(true);
        $this->user = null;
    }

    /**
     * 로그인 상태 확인
     */
    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * 게스트(비로그인) 여부
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * 현재 로그인 사용자 정보
     */
    public function user(): ?array
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $this->user = $this->session->get(self::SESSION_USER_KEY);
        return $this->user;
    }

    /**
     * 현재 사용자 ID
     */
    public function id(): ?int
    {
        $user = $this->user();
        return $user['member_id'] ?? null;
    }

    /**
     * 관리자 여부
     */
    public function isAdmin(): bool
    {
        $user = $this->user();
        return $user && ($user['is_admin'] || $user['is_super']);
    }

    /**
     * 최고관리자 여부
     */
    public function isSuper(): bool
    {
        $user = $this->user();
        return $user && $user['is_super'];
    }

    /**
     * 특정 레벨 이상 여부
     */
    public function hasLevel(int $requiredLevel): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return ($user['level_value'] ?? 0) >= $requiredLevel;
    }
}
