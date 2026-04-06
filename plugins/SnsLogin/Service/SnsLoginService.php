<?php
namespace Mublo\Plugin\SnsLogin\Service;

use Mublo\Core\Result\Result;
use Mublo\Core\Session\SessionInterface;
use Mublo\Entity\Member\Member;
use Mublo\Plugin\SnsLogin\Dto\SnsUserInfo;
use Mublo\Plugin\SnsLogin\Repository\SnsAccountRepository;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Service\Auth\AuthService;

/**
 * SNS 로그인 핵심 비즈니스 서비스
 *
 * 콜백 처리 흐름:
 * 1. provider_uid 로 sns_accounts 조회
 * 2-a. 기존 연결 → 로그인
 * 2-b. 신규 → auto_register ON: 즉시 가입+로그인 / OFF: 프로필 완성 페이지로
 */
class SnsLoginService
{
    /** 세션 키: 프로필 완성 페이지로 전달할 SNS 정보 */
    public const SESSION_SNS_PENDING = 'sns_login_pending';

    public function __construct(
        private SnsAccountRepository $accountRepository,
        private MemberRepository     $memberRepository,
        private AuthService          $authService,
        private SnsLoginConfigService $configService,
        private SessionInterface     $session,
    ) {}

    /**
     * OAuth2 콜백 처리
     *
     * @return Result
     *   성공:
     *     data['action'] = 'login'           → 기존 회원 로그인 완료
     *     data['action'] = 'register'        → 자동 가입 후 로그인 완료
     *     data['action'] = 'profile_needed'  → 프로필 완성 페이지로 이동 필요
     */
    public function handleCallback(int $domainId, SnsUserInfo $userInfo, array $tokenData, ?string $domainGroup = null): Result
    {
        // 1. 기존 연결 계정 조회
        $account = $this->accountRepository->findByProvider(
            $domainId,
            $userInfo->provider,
            $userInfo->uid
        );

        if ($account) {
            // 기존 연결 → 토큰 갱신 후 로그인
            $expiresAt = isset($tokenData['expires_in'])
                ? date('Y-m-d H:i:s', time() + (int) $tokenData['expires_in'])
                : null;

            $this->accountRepository->updateTokens(
                $account->getId(),
                $tokenData['access_token'] ?? '',
                $tokenData['refresh_token'] ?? null,
                $expiresAt
            );

            $member = $this->memberRepository->find($account->getMemberId());
            if (!$member || !$member->isActive()) {
                return Result::failure('연결된 계정이 비활성화되었습니다.');
            }

            $this->authService->loginByMember($member);

            return Result::success('로그인 성공', ['action' => 'login']);
        }

        // 2. 신규 연동 처리
        $config = $this->configService->getConfig($domainId);

        if (!empty($config['auto_register'])) {
            return $this->autoRegister($domainId, $userInfo, $tokenData, $config, $domainGroup);
        }

        // 프로필 완성 페이지로 이동
        $this->session->set(self::SESSION_SNS_PENDING, [
            'domain_id'     => $domainId,
            'domain_group'  => $domainGroup,
            'provider'      => $userInfo->provider,
            'uid'           => $userInfo->uid,
            'email'         => $userInfo->email,
            'nickname'      => $userInfo->nickname,
            'profile_image' => $userInfo->profileImage,
            'access_token'  => $tokenData['access_token'] ?? '',
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_in'    => $tokenData['expires_in'] ?? null,
        ]);

        return Result::success('프로필 입력 필요', ['action' => 'profile_needed']);
    }

    /**
     * 자동 가입 + SNS 연결 + 로그인
     */
    private function autoRegister(int $domainId, SnsUserInfo $userInfo, array $tokenData, array $config, ?string $domainGroup = null): Result
    {
        // user_id: sns_{provider}_{uid 앞 8자}_{랜덤 4자}
        $userId   = 'sns_' . $userInfo->provider . '_' . substr($userInfo->uid, 0, 8)
                  . '_' . substr(bin2hex(random_bytes(2)), 0, 4);
        $password = bin2hex(random_bytes(16));
        $nickname = $userInfo->nickname ?: $userInfo->provider . '사용자';

        // 닉네임 중복 방지
        if ($this->memberRepository->existsByNickname($domainId, $nickname)) {
            $nickname .= '_' . substr(bin2hex(random_bytes(2)), 0, 4);
        }

        $levelValue = (int) ($config['register_level'] ?? 1);

        // DB 직접 삽입 (MemberService::register()는 커스텀 필드·파일 등 무거운 로직 포함)
        $memberId = $this->memberRepository->create([
            'domain_id'    => $domainId,
            'domain_group' => $domainGroup,
            'user_id'      => $userId,
            'password'     => password_hash($password, PASSWORD_BCRYPT),
            'nickname'     => $nickname,
            'level_value'  => $levelValue,
            'status'       => 'active',
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ]);

        if (!$memberId) {
            return Result::failure('자동 가입 처리 중 오류가 발생했습니다.');
        }

        // SNS 계정 연결
        $this->linkAccount($memberId, $domainId, $userInfo, $tokenData);

        $member = $this->memberRepository->find($memberId);
        $this->authService->loginByMember($member);

        return Result::success('가입 및 로그인 완료', ['action' => 'register']);
    }

    /**
     * SNS 계정 연결 저장
     */
    public function linkAccount(int $memberId, int $domainId, SnsUserInfo $userInfo, array $tokenData): void
    {
        $expiresAt = isset($tokenData['expires_in'])
            ? date('Y-m-d H:i:s', time() + (int) $tokenData['expires_in'])
            : null;

        $this->accountRepository->create([
            'domain_id'        => $domainId,
            'member_id'        => $memberId,
            'provider'         => $userInfo->provider,
            'provider_uid'     => $userInfo->uid,
            'provider_email'   => $userInfo->email,
            'access_token'     => $tokenData['access_token'] ?? null,
            'refresh_token'    => $tokenData['refresh_token'] ?? null,
            'token_expires_at' => $expiresAt,
        ]);
    }

    /**
     * SNS 연결 해제
     */
    public function unlinkAccount(int $memberId, string $provider): Result
    {
        $deleted = $this->accountRepository->deleteByMemberAndProvider($memberId, $provider);
        return $deleted
            ? Result::success('연결이 해제되었습니다.')
            : Result::failure('연결된 계정이 없습니다.');
    }

    /**
     * 세션의 pending SNS 정보 조회 (삭제 안 함 - 폼 표시용)
     */
    public function getPendingSession(): ?array
    {
        return $this->session->get(self::SESSION_SNS_PENDING);
    }

    /**
     * 세션의 pending SNS 정보 조회 후 삭제 (처리 완료용)
     */
    public function consumePendingSession(): ?array
    {
        $data = $this->session->get(self::SESSION_SNS_PENDING);
        if ($data) {
            $this->session->remove(self::SESSION_SNS_PENDING);
        }
        return $data;
    }

    /**
     * pending SNS 정보 세션에 저장 (외부에서 복원용)
     */
    public function setPendingSession(array $data): void
    {
        $this->session->set(self::SESSION_SNS_PENDING, $data);
    }
}
