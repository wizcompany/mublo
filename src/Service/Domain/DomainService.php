<?php

namespace Mublo\Service\Domain;

use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Core\Event\Domain\DomainCreatedEvent;
use Mublo\Core\Event\Domain\DomainUpdatedEvent;
use Mublo\Core\Event\Domain\DomainDeletedEvent;
use Mublo\Entity\Domain\Domain;
use Mublo\Repository\Domain\DomainRepository;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Service\Member\FieldEncryptionService;
use Mublo\Service\Extension\ExtensionService;
use Mublo\Core\Result\Result;

/**
 * DomainService
 *
 * 도메인 Aggregate 관리 (CRUD, 상태, 검증)
 *
 * 설정(사이트/회사/SEO/테마) 관련은 DomainSettingsService로 분리됨
 *
 * @see DomainSettingsService 도메인별 설정 관리
 */
class DomainService
{
    private DomainRepository $domainRepository;
    private DomainResolver $domainResolver;
    private ?MemberRepository $memberRepository;
    private ?EventDispatcher $eventDispatcher;
    private FieldEncryptionService $encryptionService;
    private ?ExtensionService $extensionService;

    public function __construct(
        DomainRepository $domainRepository,
        DomainResolver $domainResolver,
        ?MemberRepository $memberRepository,
        ?EventDispatcher $eventDispatcher,
        FieldEncryptionService $encryptionService,
        ?ExtensionService $extensionService = null
    ) {
        $this->domainRepository = $domainRepository;
        $this->domainResolver = $domainResolver;
        $this->memberRepository = $memberRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->encryptionService = $encryptionService;
        $this->extensionService = $extensionService;
    }

    // =========================================================================
    // 이벤트 헬퍼
    // =========================================================================

    /**
     * 이벤트 발행
     */
    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }

    // =========================================================================
    // 응답 헬퍼
    // =========================================================================

    private function success(mixed $data = null, string $message = ''): Result
    {
        $resultData = is_array($data) ? $data : [];
        return Result::success($message, $resultData);
    }

    private function fail(string $message, ?array $errors = null): Result
    {
        return Result::failure($message, $errors ? ['errors' => $errors] : []);
    }

    // =========================================================================
    // 조회 메서드
    // =========================================================================

    /**
     * ID로 도메인 조회
     */
    public function findById(int $domainId): ?Domain
    {
        return $this->domainRepository->find($domainId);
    }

    /**
     * 도메인명으로 조회
     */
    public function findByDomain(string $domainName): ?Domain
    {
        return $this->domainRepository->findByDomain($domainName);
    }

    /**
     * 도메인 소유자 회원 정보 조회 (필드 값 포함)
     *
     * @param int $memberId 소유자 회원 ID
     * @return \Mublo\Entity\Member\Member|null
     */
    public function getOwnerMember(int $memberId): ?\Mublo\Entity\Member\Member
    {
        if (!$this->memberRepository) {
            return null;
        }

        $member = $this->memberRepository->find($memberId);
        if (!$member) {
            return null;
        }

        // 추가 필드 값 로드 (이름, 이메일 등) - 암호화된 필드는 복호화
        $fieldValuesRaw = $this->memberRepository->getFieldValues($memberId);
        $fieldValues = [];
        foreach ($fieldValuesRaw as $row) {
            $fieldName = $row['field_name'] ?? "field_{$row['field_id']}";
            $isEncrypted = (bool) ($row['is_encrypted'] ?? false);
            $rawValue = $row['field_value'] ?? null;

            // 암호화된 필드는 복호화하여 저장
            $fieldValues[$fieldName] = $this->encryptionService->readFieldValue(
                $rawValue,
                $isEncrypted
            );
        }
        $member->setFieldValues($fieldValues);

        return $member;
    }

    /**
     * 페이지네이션된 목록 조회
     *
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 항목 수
     * @param array $search 검색 조건
     * @param array $filters 필터 조건
     * @return array
     */
    public function getList(
        int $page = 1,
        int $perPage = 20,
        array $search = [],
        array $filters = []
    ): array {
        return $this->domainRepository->getPaginatedList($page, $perPage, $search, $filters);
    }

    // TODO: [리팩토링 3번] getStatusOptions, getContractTypeOptions →
    //       DomainStatus/ContractType Enum으로 이동 (label(), options() 패턴)
    //       현재는 changeStatus, bulkChangeStatus 내부 검증에서도 사용 중

    /**
     * 상태 옵션 목록
     */
    public function getStatusOptions(): array
    {
        return [
            'active' => '활성',
            'inactive' => '비활성',
            'blocked' => '차단',
        ];
    }

    /**
     * 계약 유형 옵션 목록
     */
    public function getContractTypeOptions(): array
    {
        return [
            'free' => '무료',
            'monthly' => '월간',
            'yearly' => '연간',
            'permanent' => '영구',
        ];
    }

    // =========================================================================
    // 소유자 검증 메서드
    // =========================================================================

    // TODO: [리팩토링 4번] validateDomainOwner → DomainPolicyService로 분리
    //       도메인 그룹 계층 검증, 운영 수 제한 등은 비즈니스 정책이며
    //       CRUD와 별개 관심사. API/CLI 재사용 시 분리 필요

    /**
     * 도메인 소유자 자격 검증
     *
     * 검증 항목:
     * 1. 회원 존재 여부
     * 2. can_operate_domain 권한 보유 여부 (등급 기반 — 도메인 운영 가능한 레벨)
     * 3. 등록자(관리자)와 같은 도메인 그룹 소속 여부
     * 4. 이미 운영 중인 사이트가 없는지 (1개 제한)
     *
     * Note: can_create_site는 생성 작업자(Controller)에서 체크. 소유자에게는 불필요.
     *
     * @param int $domainId 검색 대상 도메인 ID
     * @param string $userId 소유자 회원 아이디
     * @param string $adminDomainGroup 등록자(관리자)의 domain_group
     * @return Result
     */
    public function validateDomainOwner(int $domainId, string $userId, string $adminDomainGroup): Result
    {
        if (empty($userId)) {
            return $this->fail('소유자 회원 아이디를 입력해주세요.');
        }

        if (!$this->memberRepository) {
            return $this->fail('회원 저장소를 사용할 수 없습니다.');
        }

        // 1. 회원 존재 확인 (도메인 스코프 기반)
        $member = $this->memberRepository->findByDomainAndUserId($domainId, $userId);

        if (!$member) {
            return $this->fail('존재하지 않는 회원입니다.');
        }

        // 2. can_operate_domain 권한 확인 (등급 기반)
        if (!$member->canOperateDomain()) {
            $levelName = $member->getLevelName() ?? '알 수 없음';
            return $this->fail("도메인 운영 권한이 없는 등급입니다. (현재: {$levelName})");
        }

        // 3. 등록자와 같은 도메인 그룹 소속 확인
        $memberDomainGroup = $member->getDomainGroup() ?? '';

        // 도메인 그룹 계층 확인: adminDomainGroup이 memberDomainGroup의 상위 또는 같아야 함
        // 예: admin이 "1"이고 member가 "1" 또는 "1/2" 등이면 OK
        if (!empty($adminDomainGroup) && !empty($memberDomainGroup)) {
            // member의 도메인 그룹이 admin의 도메인 그룹으로 시작하거나 같아야 함
            if ($memberDomainGroup !== $adminDomainGroup
                && strpos($memberDomainGroup, $adminDomainGroup . '/') !== 0) {
                return $this->fail('해당 회원은 귀하의 관리 범위에 속하지 않습니다.');
            }
        }

        // 4. 이미 운영 중인 사이트 확인 (1개 제한)
        $ownedDomainCount = $this->domainRepository->countByMemberId($member->getMemberId());

        if ($ownedDomainCount > 0) {
            return $this->fail('해당 회원은 이미 사이트를 운영 중입니다. (최대 1개)');
        }

        // 모든 검증 통과
        return $this->success([
            'member_id' => $member->getMemberId(),
            'user_id' => $member->getUserId(),
            'level_name' => $member->getLevelName(),
            'level_type' => $member->getLevelType(),
        ], '사이트 소유자로 등록 가능합니다.');
    }

    // =========================================================================
    // CRUD 메서드
    // =========================================================================

    /**
     * 도메인 생성
     *
     * domain_group은 자동 생성됨: {parent_domain_group}/{new_domain_id}
     * member_id(소유자)는 필수
     *
     * @param array $data 도메인 데이터
     * @param string $parentDomainGroup 상위 도메인의 domain_group (등록자의 도메인 그룹)
     * @param int|null $createdBy 생성 작업자 회원 ID (소유자와 다를 수 있음, 예: 영업사원)
     */
    public function create(array $data, string $parentDomainGroup = '', ?int $createdBy = null): Result
    {
        // 소유자 회원 ID 필수 검증
        if (empty($data['member_id'])) {
            return $this->fail('소유자 회원을 선택해주세요.');
        }

        // 도메인명 검증
        $validation = $this->validateDomainName($data['domain'] ?? '');
        if ($validation->isFailure()) {
            return $validation;
        }

        // 중복 체크 (포트 제거 후 호스트명 기준 비교)
        $hostOnly = $this->stripPort($data['domain']);
        if ($this->domainRepository->existsByDomain($hostOnly) ||
            $this->domainRepository->existsByDomain($data['domain'])) {
            return $this->fail('이미 등록된 도메인입니다.');
        }

        // 계약 날짜 검증
        $dateValidation = $this->validateContractDates(
            $data['contract_start_date'] ?? null,
            $data['contract_end_date'] ?? null
        );
        if ($dateValidation->isFailure()) {
            return $dateValidation;
        }

        // 데이터 정리 (domain_group은 임시로 빈값)
        $insertData = $this->prepareInsertData($data);
        $insertData['domain_group'] = ''; // 먼저 빈값으로 저장
        $insertData['created_by'] = $createdBy;

        // 저장
        $domainId = $this->domainRepository->create($insertData);

        if (!$domainId) {
            return $this->fail('도메인 생성에 실패했습니다.');
        }

        // domain_group 자동 생성: {parent_domain_group}/{new_domain_id}
        $newDomainGroup = $parentDomainGroup
            ? $parentDomainGroup . '/' . $domainId
            : (string)$domainId;

        // domain_group 업데이트
        $this->domainRepository->update($domainId, ['domain_group' => $newDomainGroup]);

        // 소유자 회원의 domain_id / domain_group 갱신
        // 소유자(member_id)만 새 도메인 소속으로 변경
        // 생성자(created_by)는 자기 소속 유지 (영업사원 등 — 정산 추적만)
        $ownerId = (int)($insertData['member_id'] ?? 0);
        if ($ownerId && $this->memberRepository) {
            $this->memberRepository->update($ownerId, [
                'domain_id'    => $domainId,
                'domain_group' => $newDomainGroup,
            ]);
        }

        // 도메인 생성 이벤트 발행 (기본 약관/메뉴/패키지 시딩 등)
        $this->dispatch(new DomainCreatedEvent(
            $domainId,
            $newDomainGroup,
            $ownerId ?: null,
            $createdBy
        ));

        return $this->success(
            ['domain_id' => $domainId, 'domain_group' => $newDomainGroup],
            '도메인이 생성되었습니다.'
        );
    }

    /**
     * 도메인 수정
     */
    public function update(int $domainId, array $data): Result
    {
        $domain = $this->domainRepository->find($domainId);
        if (!$domain) {
            return $this->fail('도메인을 찾을 수 없습니다.');
        }

        // 도메인명 변경 시 검증
        if (isset($data['domain']) && $data['domain'] !== $domain->getDomain()) {
            $validation = $this->validateDomainName($data['domain']);
            if ($validation->isFailure()) {
                return $validation;
            }

            // 중복 체크 (포트 제거 후 호스트명 기준 비교, 자기 자신 제외)
            $hostOnly = $this->stripPort($data['domain']);
            if ($this->domainRepository->existsByDomainExcept($hostOnly, $domainId) ||
                $this->domainRepository->existsByDomainExcept($data['domain'], $domainId)) {
                return $this->fail('이미 등록된 도메인입니다.');
            }
        }

        // 계약 날짜 검증
        if (isset($data['contract_start_date']) || isset($data['contract_end_date'])) {
            $dateValidation = $this->validateContractDates(
                $data['contract_start_date'] ?? $domain->getContractStartDate(),
                $data['contract_end_date'] ?? $domain->getContractEndDate()
            );
            if ($dateValidation->isFailure()) {
                return $dateValidation;
            }
        }

        // 데이터 정리
        $updateData = $this->prepareUpdateData($data);

        // 업데이트
        $this->domainRepository->update($domainId, $updateData);

        // 캐시 무효화 (기존 도메인명 + 새 도메인명)
        $this->invalidateCache($domain->getDomain());
        if (isset($data['domain']) && $data['domain'] !== $domain->getDomain()) {
            $this->invalidateCache($data['domain']);
        }

        // 도메인 수정 이벤트 발행
        $this->dispatch(new DomainUpdatedEvent($domainId, $domain, $updateData));

        return $this->success(
            ['domain_id' => $domainId],
            '도메인이 수정되었습니다.'
        );
    }

    /**
     * 도메인 삭제
     */
    public function delete(int $domainId): Result
    {
        $domain = $this->domainRepository->find($domainId);
        if (!$domain) {
            return $this->fail('도메인을 찾을 수 없습니다.');
        }

        // 기본 도메인(ID=1) 삭제 방지
        if ($domainId === 1) {
            return $this->fail('기본 도메인은 삭제할 수 없습니다.');
        }

        // 회원 존재 확인 (소유자 제외)
        $ownerId = $domain->getMemberId();
        if ($this->memberRepository) {
            $memberCount = $this->memberRepository->countByDomain($domainId);
            if ($ownerId) {
                $memberCount--; // 소유자 제외
            }
            if ($memberCount > 0) {
                return $this->fail("이 도메인에 {$memberCount}명의 회원이 있습니다. 먼저 회원을 삭제하거나 이동해주세요.");
            }
        }

        // 하위 도메인 존재 확인
        $children = $this->domainRepository->findChildren($domain->getDomainGroup());
        if (!empty($children)) {
            return $this->fail('하위 도메인이 있어 삭제할 수 없습니다. 먼저 하위 도메인을 삭제해주세요.');
        }

        // 소유자를 부모 도메인으로 이동
        if ($ownerId && $this->memberRepository) {
            $domainGroup = $domain->getDomainGroup() ?? '';
            $parentGroup = str_contains($domainGroup, '/')
                ? substr($domainGroup, 0, strrpos($domainGroup, '/'))
                : $domainGroup;
            $parentDomainId = (int) (str_contains($parentGroup, '/')
                ? substr($parentGroup, strrpos($parentGroup, '/') + 1)
                : $parentGroup);

            if ($parentDomainId > 0) {
                $this->memberRepository->update($ownerId, [
                    'domain_id' => $parentDomainId,
                    'domain_group' => $parentGroup,
                ]);
            }
        }

        // 삭제
        $this->domainRepository->delete($domainId);

        // 캐시 무효화
        $this->invalidateCache($domain->getDomain());

        // 도메인 삭제 이벤트 발행 (관련 데이터 정리용)
        $this->dispatch(new DomainDeletedEvent($domainId, $domain));

        return $this->success(null, '도메인이 삭제되었습니다.');
    }

    // =========================================================================
    // 상태 변경 메서드
    // =========================================================================

    /**
     * 단일 도메인 상태 변경
     */
    public function changeStatus(int $domainId, string $status): Result
    {
        $domain = $this->domainRepository->find($domainId);
        if (!$domain) {
            return $this->fail('도메인을 찾을 수 없습니다.');
        }

        // 유효한 상태값 확인
        $validStatuses = array_keys($this->getStatusOptions());
        if (!in_array($status, $validStatuses, true)) {
            return $this->fail('유효하지 않은 상태값입니다.');
        }

        // 기본 도메인(ID=1) 비활성화/차단 방지
        if ($domainId === 1 && $status !== 'active') {
            return $this->fail('기본 도메인은 비활성화하거나 차단할 수 없습니다.');
        }

        $this->domainRepository->update($domainId, ['status' => $status]);
        $this->invalidateCache($domain->getDomain());

        $statusLabel = $this->getStatusOptions()[$status] ?? $status;
        return $this->success(null, "도메인 상태가 '{$statusLabel}'으로 변경되었습니다.");
    }

    /**
     * 일괄 상태 변경
     */
    public function bulkChangeStatus(array $domainIds, string $status): Result
    {
        if (empty($domainIds)) {
            return $this->fail('선택된 도메인이 없습니다.');
        }

        // 유효한 상태값 확인
        $validStatuses = array_keys($this->getStatusOptions());
        if (!in_array($status, $validStatuses, true)) {
            return $this->fail('유효하지 않은 상태값입니다.');
        }

        // 기본 도메인(ID=1) 제외
        if ($status !== 'active' && in_array(1, $domainIds, true)) {
            return $this->fail('기본 도메인은 비활성화하거나 차단할 수 없습니다.');
        }

        // 변경 전 도메인 목록 조회 (캐시 무효화용)
        $domains = [];
        foreach ($domainIds as $id) {
            $domain = $this->domainRepository->find($id);
            if ($domain) {
                $domains[] = $domain;
            }
        }

        // 일괄 업데이트
        $affected = $this->domainRepository->updateStatus($domainIds, $status);

        // 캐시 무효화
        foreach ($domains as $domain) {
            $this->invalidateCache($domain->getDomain());
        }

        $statusLabel = $this->getStatusOptions()[$status] ?? $status;
        return $this->success(
            ['affected' => $affected],
            "{$affected}개 도메인의 상태가 '{$statusLabel}'으로 변경되었습니다."
        );
    }

    // =========================================================================
    // 검증 메서드
    // =========================================================================

    /**
     * 도메인명 검증
     */
    public function validateDomainName(string $domain): Result
    {
        $domain = trim($domain);

        if (empty($domain)) {
            return $this->fail('도메인명을 입력해주세요.');
        }

        // 포트 분리 (host:port 형식 허용) — 호스트명만 검증
        if (preg_match('/^(.+):(\d+)$/', $domain, $m)) {
            $port = (int) $m[2];
            if ($port < 1 || $port > 65535) {
                return $this->fail('유효하지 않은 포트 번호입니다.');
            }
            $domain = $m[1];
        }

        // 도메인 형식 검증 (간단한 정규식)
        // localhost, IP 주소, 일반 도메인 모두 허용
        if ($domain === 'localhost') {
            return $this->success();
        }

        // IP 주소 형식
        if (filter_var($domain, FILTER_VALIDATE_IP)) {
            return $this->success();
        }

        // 도메인 형식 (영문, 숫자, 하이픈, 점)
        if (!preg_match('/^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)*$/', $domain)) {
            return $this->fail('유효하지 않은 도메인 형식입니다.');
        }

        // 길이 제한
        if (strlen($domain) > 253) {
            return $this->fail('도메인명은 253자를 초과할 수 없습니다.');
        }

        return $this->success();
    }

    /**
     * 도메인 중복 체크 (AJAX용)
     */
    public function checkDomainAvailability(string $domain, ?int $excludeId = null): Result
    {
        $validation = $this->validateDomainName($domain);
        if ($validation->isFailure()) {
            return $validation;
        }

        $domain = $this->stripPort($domain);

        $exists = $excludeId
            ? $this->domainRepository->existsByDomainExcept($domain, $excludeId)
            : $this->domainRepository->existsByDomain($domain);

        if ($exists) {
            return $this->fail('이미 등록된 도메인입니다.');
        }

        return $this->success(null, '사용 가능한 도메인입니다.');
    }

    /**
     * 계약 날짜 검증
     */
    private function validateContractDates(?string $startDate, ?string $endDate): Result
    {
        // 둘 다 비어있으면 OK (무제한)
        if (empty($startDate) && empty($endDate)) {
            return $this->success();
        }

        // 날짜 형식 검증
        if ($startDate && !$this->isValidDateFormat($startDate)) {
            return $this->fail('계약 시작일 형식이 올바르지 않습니다. (YYYY-MM-DD)');
        }

        if ($endDate && !$this->isValidDateFormat($endDate)) {
            return $this->fail('계약 종료일 형식이 올바르지 않습니다. (YYYY-MM-DD)');
        }

        // 시작일이 종료일보다 늦으면 안됨
        if ($startDate && $endDate && strtotime($startDate) > strtotime($endDate)) {
            return $this->fail('계약 시작일이 종료일보다 늦을 수 없습니다.');
        }

        return $this->success();
    }

    /**
     * 날짜 형식 검증
     */
    private function isValidDateFormat(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }

    // =========================================================================
    // 데이터 준비 메서드
    // =========================================================================

    /**
     * 생성용 데이터 준비
     */
    private function prepareInsertData(array $data): array
    {
        return [
            'domain' => strtolower(trim($data['domain'])),
            'domain_group' => trim($data['domain_group'] ?? ''),
            'member_id' => !empty($data['member_id']) ? (int)$data['member_id'] : null,
            'status' => $data['status'] ?? 'active',
            'contract_start_date' => !empty($data['contract_start_date']) ? $data['contract_start_date'] : null,
            'contract_end_date' => !empty($data['contract_end_date']) ? $data['contract_end_date'] : null,
            'contract_type' => $data['contract_type'] ?? 'free',
            'storage_limit' => $this->parseStorageLimit($data['storage_limit'] ?? 1, $data['storage_unit'] ?? 'GB'),
            'member_limit' => (int)($data['member_limit'] ?? 0),
            'site_config' => json_encode($this->prepareSiteConfig($data), JSON_UNESCAPED_UNICODE),
            'company_config' => json_encode($this->prepareCompanyConfig($data), JSON_UNESCAPED_UNICODE),
            'seo_config' => json_encode($this->prepareSeoConfig($data), JSON_UNESCAPED_UNICODE),
            'theme_config' => json_encode($this->prepareThemeConfig($data), JSON_UNESCAPED_UNICODE),
            'extension_config' => json_encode($this->prepareExtensionConfig($data), JSON_UNESCAPED_UNICODE),
            'extra_config' => json_encode([], JSON_UNESCAPED_UNICODE),
        ];
    }

    /**
     * 수정용 데이터 준비
     */
    private function prepareUpdateData(array $data): array
    {
        $updateData = [];

        // 기본 필드
        $basicFields = [
            'domain' => fn($v) => strtolower(trim($v)),
            'domain_group' => fn($v) => trim($v),
            'status' => fn($v) => $v,
            'contract_type' => fn($v) => $v,
            'member_limit' => fn($v) => (int)$v,
        ];

        foreach ($basicFields as $field => $transform) {
            if (isset($data[$field])) {
                $updateData[$field] = $transform($data[$field]);
            }
        }

        // member_id (null 허용)
        if (array_key_exists('member_id', $data)) {
            $updateData['member_id'] = !empty($data['member_id']) ? (int)$data['member_id'] : null;
        }

        // 계약 날짜 (null 허용)
        if (array_key_exists('contract_start_date', $data)) {
            $updateData['contract_start_date'] = !empty($data['contract_start_date']) ? $data['contract_start_date'] : null;
        }
        if (array_key_exists('contract_end_date', $data)) {
            $updateData['contract_end_date'] = !empty($data['contract_end_date']) ? $data['contract_end_date'] : null;
        }

        // 저장공간 제한
        if (isset($data['storage_limit'])) {
            $updateData['storage_limit'] = $this->parseStorageLimit(
                $data['storage_limit'],
                $data['storage_unit'] ?? 'GB'
            );
        }

        return $updateData;
    }

    /**
     * 저장공간 제한 파싱 (GB/MB → bytes)
     */
    private function parseStorageLimit(int|float|string $value, string $unit): int
    {
        $value = (float)$value;

        return match (strtoupper($unit)) {
            'TB' => (int)($value * 1099511627776),
            'GB' => (int)($value * 1073741824),
            'MB' => (int)($value * 1048576),
            'KB' => (int)($value * 1024),
            default => (int)$value,
        };
    }

    /**
     * 사이트 설정 준비 (도메인 생성용)
     *
     * Note: logo, favicon은 seo_config에서 관리
     * @see DomainSettingsService::SITE_KEYS
     */
    private function prepareSiteConfig(array $data): array
    {
        return [
            'site_title' => trim($data['site_title'] ?? ''),
            'site_subtitle' => trim($data['site_subtitle'] ?? ''),
            'admin_email' => trim($data['admin_email'] ?? ''),
            'timezone' => $data['timezone'] ?? 'Asia/Seoul',
            'language' => $data['language'] ?? 'ko',
            'editor' => $data['editor'] ?? 'textarea',
            'per_page' => (int) ($data['per_page'] ?? 20),
        ];
    }

    /**
     * 회사 정보 준비 (도메인 생성용)
     *
     * @see DomainSettingsService::COMPANY_KEYS
     */
    private function prepareCompanyConfig(array $data): array
    {
        return [
            'name' => trim($data['name'] ?? ''),
            'owner' => trim($data['owner'] ?? ''),
            'tel' => trim($data['tel'] ?? ''),
            'fax' => trim($data['fax'] ?? ''),
            'email' => trim($data['email'] ?? ''),
            'business_number' => trim($data['business_number'] ?? ''),
            'tongsin_number' => trim($data['tongsin_number'] ?? ''),
            'zipcode' => trim($data['zipcode'] ?? ''),
            'address' => trim($data['address'] ?? ''),
            'address_detail' => trim($data['address_detail'] ?? ''),
            'privacy_officer' => trim($data['privacy_officer'] ?? ''),
            'privacy_email' => trim($data['privacy_email'] ?? ''),
        ];
    }

    /**
     * SEO 설정 준비 (도메인 생성용)
     *
     * @see DomainSettingsService::SEO_KEYS
     */
    private function prepareSeoConfig(array $data): array
    {
        return [
            'logo_pc' => trim($data['logo_pc'] ?? ''),
            'logo_mobile' => trim($data['logo_mobile'] ?? ''),
            'favicon' => trim($data['favicon'] ?? ''),
            'app_icon' => trim($data['app_icon'] ?? ''),
            'og_image' => trim($data['og_image'] ?? ''),
            'meta_title' => trim($data['meta_title'] ?? ''),
            'meta_description' => trim($data['meta_description'] ?? ''),
            'meta_keywords' => trim($data['meta_keywords'] ?? ''),
            'google_analytics' => trim($data['google_analytics'] ?? ''),
            'google_site_verification' => trim($data['google_site_verification'] ?? ''),
            'naver_site_verification' => trim($data['naver_site_verification'] ?? ''),
            'sns_channels' => $data['sns_channels'] ?? [],
        ];
    }

    /**
     * 테마 설정 준비 (도메인 생성용)
     *
     * @see DomainSettingsService::THEME_KEYS
     */
    private function prepareThemeConfig(array $data): array
    {
        $defaultTheme = 'basic';
        return [
            'admin' => $data['admin'] ?? $defaultTheme,
            'frame' => $data['frame'] ?? $defaultTheme,
            'index' => $data['index'] ?? $defaultTheme,
            'board' => $data['board'] ?? $defaultTheme,
            'member' => $data['member'] ?? $defaultTheme,
            'auth' => $data['auth'] ?? $defaultTheme,
            'page' => $data['page'] ?? $defaultTheme,
        ];
    }

    /**
     * 확장 기능 설정 준비
     *
     * default:true 패키지를 자동 포함하여 새 도메인 생성 시에도
     * 기본 패키지가 활성화·설치 상태로 시작되도록 함.
     */
    private function prepareExtensionConfig(array $data): array
    {
        $plugins = $data['plugins'] ?? [];
        $packages = $data['packages'] ?? [];

        // default:true 패키지 자동 포함
        $defaultPackages = $this->getDefaultPackageNames();
        foreach ($defaultPackages as $pkg) {
            if (!in_array($pkg, $packages, true)) {
                $packages[] = $pkg;
            }
        }

        return [
            'plugins' => $plugins,
            'packages' => $packages,
            'installed' => [
                'plugins' => $plugins,
                'packages' => $packages,
            ],
        ];
    }

    /**
     * manifest.json에서 default:true인 패키지명 목록
     */
    private function getDefaultPackageNames(): array
    {
        if (!$this->extensionService) {
            return [];
        }

        $defaults = [];
        foreach ($this->extensionService->getPackageManifests() as $name => $manifest) {
            if (!empty($manifest['default'])) {
                $defaults[] = $name;
            }
        }

        return $defaults;
    }

    // =========================================================================
    // 캐시 관리
    // =========================================================================

    /**
     * 도메인명에서 포트 제거 (test.localhost:8080 → test.localhost)
     */
    private function stripPort(string $domain): string
    {
        if (preg_match('/^(.+):\d+$/', $domain, $m)) {
            return $m[1];
        }
        return $domain;
    }

    /**
     * 캐시 무효화 (메모리 + 파일 캐시 모두 삭제)
     */
    private function invalidateCache(string $domainName): void
    {
        $this->domainResolver->invalidate($domainName);
    }
}
