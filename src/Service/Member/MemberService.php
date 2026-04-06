<?php
namespace Mublo\Service\Member;

use Mublo\Repository\Member\MemberRepository;
use Mublo\Repository\Member\MemberFieldRepository;
use Mublo\Repository\Member\MemberLevelRepository;
use Mublo\Repository\Domain\DomainRepository;
use Mublo\Entity\Member\Member;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Service\Member\Event\MemberRegisteredByUserEvent;
use Mublo\Service\Member\Event\MemberUpdatedBySelfEvent;
use Mublo\Core\Event\Member\MemberRegisterPreparingEvent;
use Mublo\Core\Result\Result;
use Mublo\Infrastructure\Storage\SecureFileService;
use Mublo\Service\CustomField\CustomFieldValidator;
use Mublo\Service\CustomField\CustomFieldFileHandler;

/**
 * MemberService
 *
 * 회원 관련 공통 비즈니스 로직
 *
 * 책임:
 * - 회원 검증 (아이디, 비밀번호, 필드)
 * - 회원 조회 (단일)
 * - 필드 값 저장/조회
 * - Front 회원가입/수정/탈퇴 (추후 MemberFrontService로 분리 예정)
 *
 * 참고:
 * - Admin 전용 작업은 MemberAdminService 사용
 * - 필드 정의 관리는 MemberFieldService 사용
 */
class MemberService
{
    private MemberRepository $memberRepository;
    private MemberFieldRepository $fieldRepository;
    private FieldEncryptionService $encryptionService;
    private ?MemberFieldService $fieldService = null;
    private ?MemberLevelRepository $levelRepository = null;
    private ?EventDispatcher $eventDispatcher = null;
    private ?CustomFieldFileHandler $fileHandler = null;
    private ?DomainRepository $domainRepository = null;

    public function __construct(
        MemberRepository $memberRepository,
        MemberFieldRepository $fieldRepository,
        FieldEncryptionService $encryptionService,
        ?MemberFieldService $fieldService = null,
        ?MemberLevelRepository $levelRepository = null,
        ?EventDispatcher $eventDispatcher = null,
        ?SecureFileService $secureFileService = null,
        ?DomainRepository $domainRepository = null
    ) {
        $this->memberRepository = $memberRepository;
        $this->fieldRepository = $fieldRepository;
        $this->encryptionService = $encryptionService;
        $this->fieldService = $fieldService;
        $this->levelRepository = $levelRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->fileHandler = $secureFileService ? new CustomFieldFileHandler($secureFileService) : null;
        $this->domainRepository = $domainRepository;
    }

    // =========================================================================
    // 검증 메서드 (Validation Methods)
    // =========================================================================

    /**
     * 아이디 형식 검증
     *
     * 영문, 숫자 4~20자 규칙 검사
     *
     * @param string $userId 검사할 아이디
     * @return Result
     */
    public function validateUserId(string $userId, bool $useEmailAsUserId = false): Result
    {
        if (empty($userId)) {
            return Result::failure($useEmailAsUserId ? '이메일을 입력해주세요.' : '아이디를 입력해주세요.');
        }

        if ($useEmailAsUserId) {
            if (!filter_var($userId, FILTER_VALIDATE_EMAIL)) {
                return Result::failure('올바른 이메일 형식으로 입력해주세요.');
            }
            if (mb_strlen($userId) > 50) {
                return Result::failure('이메일은 50자 이내로 입력해주세요.');
            }
        } else {
            if (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $userId)) {
                return Result::failure('아이디는 영문, 숫자, 밑줄(_) 4~20자로 입력해주세요.');
            }
        }

        return Result::success($useEmailAsUserId ? '사용 가능한 이메일 형식입니다.' : '사용 가능한 아이디 형식입니다.');
    }

    /**
     * 아이디 중복 검사
     *
     * @param int $domainId 도메인 ID
     * @param string $userId 검사할 아이디
     * @return bool 사용 가능하면 true, 중복이면 false
     */
    public function isUserIdAvailable(int $domainId, string $userId): bool
    {
        return !$this->memberRepository->existsByUserId($domainId, $userId);
    }

    /**
     * 아이디 사용 가능 여부 검사 (형식 + 중복)
     *
     * API 응답용: 형식 검증과 중복 검사를 한번에 수행
     *
     * @param int $domainId 도메인 ID
     * @param string $userId 검사할 아이디
     * @return Result
     */
    public function checkUserIdAvailability(int $domainId, string $userId, bool $useEmailAsUserId = false): Result
    {
        $formatResult = $this->validateUserId($userId, $useEmailAsUserId);
        if ($formatResult->isFailure()) {
            return $formatResult;
        }

        if (!$this->isUserIdAvailable($domainId, $userId)) {
            $label = $useEmailAsUserId ? '이메일' : '아이디';
            return Result::failure('이미 사용 중인 ' . $label . '입니다.');
        }

        $label = $useEmailAsUserId ? '이메일' : '아이디';
        return Result::success('사용 가능한 ' . $label . '입니다.');
    }

    /**
     * 닉네임 형식 검증
     *
     * 2~20자 규칙 검사
     *
     * @param string $nickname 검사할 닉네임
     * @return Result
     */
    public function validateNickname(string $nickname): Result
    {
        if (empty($nickname)) {
            return Result::failure('닉네임을 입력해주세요.');
        }

        $length = mb_strlen($nickname);
        if ($length < 2 || $length > 20) {
            return Result::failure('닉네임은 2~20자로 입력해주세요.');
        }

        return Result::success('사용 가능한 닉네임입니다.');
    }

    /**
     * 비밀번호 형식 검증
     *
     * 최소 6자 이상 규칙 검사
     *
     * @param string $password 검사할 비밀번호
     * @return Result
     */
    public function validatePassword(string $password): Result
    {
        if (empty($password)) {
            return Result::failure('비밀번호를 입력해주세요.');
        }

        if (strlen($password) < 6) {
            return Result::failure('비밀번호는 최소 6자 이상이어야 합니다.');
        }

        return Result::success('사용 가능한 비밀번호입니다.');
    }

    /**
     * 레벨 할당 권한 검증
     *
     * 규칙:
     * - 최고관리자만 최고관리자 레벨(is_super=1) 할당 가능
     * - 자신의 level_value보다 높은 등급은 할당 불가 (같거나 낮은 등급은 가능)
     *
     * @param int $domainId 도메인 ID
     * @param int $targetLevelValue 할당하려는 레벨 값
     * @param bool $isCurrentAdminSuper 현재 관리자가 최고관리자인지 여부
     * @param int $currentAdminLevelValue 현재 관리자의 level_value
     * @return Result
     */
    public function validateLevelAssignment(
        int $domainId,
        int $targetLevelValue,
        bool $isCurrentAdminSuper = false,
        int $currentAdminLevelValue = 0
    ): Result {
        if ($isCurrentAdminSuper) {
            return Result::success('레벨 할당이 가능합니다.');
        }

        if ($this->levelRepository === null) {
            return Result::success('레벨 검증을 건너뜁니다.');
        }

        // member_levels는 전역 테이블이므로 level_value로만 조회
        $targetLevel = $this->levelRepository->findByValue($targetLevelValue);

        if ($targetLevel === null) {
            return Result::failure('존재하지 않는 회원 등급입니다.');
        }

        if ($targetLevel->isSuper()) {
            return Result::failure('최고관리자 등급은 최고관리자만 할당할 수 있습니다.');
        }

        // 자신보다 높은 등급은 할당 불가, 같거나 낮은 등급은 허용
        if ($currentAdminLevelValue > 0 && $targetLevelValue > $currentAdminLevelValue) {
            return Result::failure('자신보다 높은 등급은 할당할 수 없습니다.');
        }

        return Result::success('레벨 할당이 가능합니다.');
    }

    /**
     * 통합 중복 체크
     *
     * Core 필드(user_id)와 추가 필드(is_unique=1)를 하나의 메서드로 처리
     * Result::failure()는 중복을 의미, Result::success()는 사용 가능을 의미
     *
     * @param int $domainId 도메인 ID
     * @param string $fieldName 필드명 ('user_id' 또는 추가필드명)
     * @param string $value 검사할 값
     * @param int|null $excludeMemberId 제외할 회원 ID (수정 시 자기 자신 제외)
     * @return Result
     */
    public function checkDuplicate(
        int $domainId,
        string $fieldName,
        string $value,
        ?int $excludeMemberId = null
    ): Result {
        if (empty(trim($value))) {
            return Result::success('값이 입력되지 않았습니다.');
        }

        if ($fieldName === 'user_id') {
            $exists = $this->memberRepository->existsByUserIdExcept($domainId, $value, $excludeMemberId);

            return $exists
                ? Result::failure('이미 사용 중인 아이디입니다.')
                : Result::success('사용 가능한 아이디입니다.');
        }

        if ($fieldName === 'nickname') {
            $exists = $this->memberRepository->existsByNickname($domainId, $value, $excludeMemberId);

            return $exists
                ? Result::failure('이미 사용 중인 닉네임입니다.')
                : Result::success('사용 가능한 닉네임입니다.');
        }

        $field = $this->fieldRepository->findByDomainAndName($domainId, $fieldName);

        if ($field === null) {
            return Result::success('존재하지 않는 필드입니다.');
        }

        $isUnique = (bool) ($field['is_unique'] ?? false);
        if (!$isUnique) {
            return Result::success('중복 체크가 필요하지 않은 필드입니다.');
        }

        $fieldId = (int) $field['field_id'];
        $fieldLabel = $field['field_label'] ?? $fieldName;
        $isEncrypted = (bool) ($field['is_encrypted'] ?? false);

        if ($isEncrypted) {
            $searchIndex = $this->encryptionService->createSearchIndex($value);
            $exists = $this->memberRepository->existsFieldValueBySearchIndex(
                $domainId,
                $fieldId,
                $searchIndex,
                $excludeMemberId
            );
        } else {
            $exists = $this->memberRepository->existsFieldValue(
                $domainId,
                $fieldId,
                $value,
                $excludeMemberId
            );
        }

        return $exists
            ? Result::failure("이미 사용 중인 {$fieldLabel}입니다.")
            : Result::success("사용 가능한 {$fieldLabel}입니다.");
    }

    /**
     * 추가 필드 값 검증
     *
     * @param int $domainId 도메인 ID
     * @param array $fieldValues 필드 값 [field_id => value]
     * @param bool $checkRequired 필수 체크 여부 (수정 시 false로 전달 가능)
     * @return Result (실패시 data['errors'] 포함)
     */
    public function validateFieldValues(int $domainId, array $fieldValues, bool $checkRequired = true): Result
    {
        $fieldDefinitions = $this->fieldRepository->findByDomain($domainId);

        if (empty($fieldDefinitions)) {
            return Result::success('검증할 필드가 없습니다.');
        }

        $errors = [];

        foreach ($fieldDefinitions as $field) {
            $fieldId = $field['field_id'];
            $fieldLabel = $field['field_label'];
            $fieldType = $field['field_type'];
            $isRequired = (bool) ($field['is_required'] ?? false);
            $validationRule = $field['validation_rule'] ?? null;

            $value = $fieldValues[$fieldId] ?? null;

            // 빈값 체크 (모든 타입 통합 — file, address, checkbox 포함)
            if (CustomFieldValidator::isEmpty($fieldType, $value)) {
                if ($checkRequired && $isRequired) {
                    $errors[$fieldId] = "{$fieldLabel}은(는) 필수 입력 항목입니다.";
                }
                continue;
            }

            // file은 빈값이 아니면 추가 검증 불필요
            if ($fieldType === 'file') {
                continue;
            }

            // 타입별 검증
            $typeResult = CustomFieldValidator::validateByType($fieldType, $value, $fieldLabel);
            if ($typeResult->isFailure()) {
                $errors[$fieldId] = $typeResult->getMessage();
                continue;
            }

            // 커스텀 정규식 검증
            $regexResult = CustomFieldValidator::validateRegex($value, $validationRule, $fieldLabel);
            if ($regexResult->isFailure()) {
                $errors[$fieldId] = $regexResult->getMessage();
            }
        }

        if (!empty($errors)) {
            $firstError = reset($errors);
            return Result::failure($firstError, ['errors' => $errors]);
        }

        return Result::success('모든 필드가 유효합니다.');
    }

    /**
     * 필드 타입별 기본 검증
     *
     * @deprecated CustomFieldValidator::validateByType() 사용 권장
     */
    public function validateFieldByType(string $fieldType, mixed $value, string $fieldLabel = '필드'): Result
    {
        return CustomFieldValidator::validateByType($fieldType, $value, $fieldLabel);
    }

    /**
     * 단일 필드 값 검증 (실시간 검증용)
     */
    public function validateSingleFieldValue(int $fieldId, mixed $value): Result
    {
        $field = $this->fieldRepository->find($fieldId);

        if (!$field) {
            return Result::failure('필드를 찾을 수 없습니다.');
        }

        $fieldLabel = $field['field_label'];
        $fieldType = $field['field_type'];
        $isRequired = (bool) ($field['is_required'] ?? false);
        $validationRule = $field['validation_rule'] ?? null;

        if (CustomFieldValidator::isEmpty($fieldType, $value)) {
            if ($isRequired) {
                return Result::failure("{$fieldLabel}은(는) 필수 입력 항목입니다.");
            }
            return Result::success('유효한 값입니다.');
        }

        if ($fieldType === 'file') {
            return Result::success('유효한 값입니다.');
        }

        $typeResult = CustomFieldValidator::validateByType($fieldType, $value, $fieldLabel);
        if ($typeResult->isFailure()) {
            return $typeResult;
        }

        $regexResult = CustomFieldValidator::validateRegex($value, $validationRule, $fieldLabel);
        if ($regexResult->isFailure()) {
            return $regexResult;
        }

        return Result::success('유효한 값입니다.');
    }

    // =========================================================================
    // 조회 메서드 (Query Methods)
    // =========================================================================

    /**
     * 회원 조회
     */
    public function findById(int $memberId): ?Member
    {
        return $this->memberRepository->find($memberId);
    }

    /**
     * 여러 회원 ID로 회원 조회
     *
     * @param array $memberIds 회원 ID 목록
     * @return Member[]
     */
    public function findByIds(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        return $this->memberRepository->findByIds($memberIds);
    }

    /**
     * 아이디로 회원 검색 (자동완성용)
     *
     * @param int $domainId 도메인 ID
     * @param string $keyword 검색 키워드
     * @param int $limit 결과 제한
     * @return Member[]
     */
    public function searchByUserId(int $domainId, string $keyword, int $limit = 10): array
    {
        return $this->memberRepository->searchByUserId($domainId, $keyword, $limit);
    }

    /**
     * 도메인별 추가 필드 정의 조회
     */
    public function getFieldDefinitions(int $domainId, bool $signupOnly = false): array
    {
        if ($signupOnly) {
            return $this->fieldRepository->findSignupVisibleByDomain($domainId);
        }

        return $this->fieldRepository->findFrontByDomain($domainId);
    }

    /**
     * 회원 추가 필드 값 조회
     *
     * 암호화된 필드는 자동 복호화, 주소 타입은 JSON 파싱,
     * file 타입은 JSON 메타데이터 + URL 반환
     */
    public function getFieldValues(int $memberId): array
    {
        $values = $this->memberRepository->getFieldValues($memberId);

        $result = [];
        foreach ($values as $row) {
            $isEncrypted = (bool) ($row['is_encrypted'] ?? false);
            $fieldType = $row['field_type'] ?? 'text';

            // file 타입: JSON 메타 파싱 + 다운로드 URL 추가
            if ($fieldType === 'file') {
                $meta = $this->fileHandler
                    ? $this->fileHandler->parseFileMetaWithUrl($row['field_value'] ?? null)
                    : CustomFieldFileHandler::parseFileMeta($row['field_value'] ?? null);
                $result[] = [
                    'field_id' => $row['field_id'],
                    'field_value' => $meta,
                ];
                continue;
            }

            $value = $this->encryptionService->readFieldValue(
                $row['field_value'],
                $isEncrypted
            );

            if ($fieldType === 'address' && $value !== null) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : $value;
            }

            $result[] = [
                'field_id' => $row['field_id'],
                'field_value' => $value,
            ];
        }

        return $result;
    }

    /**
     * 암호화 필드 검색 (Blind Index 사용)
     *
     * @param int $domainId 도메인 ID
     * @param int $fieldId 검색할 필드 ID
     * @param string $searchValue 검색 값 (원문)
     * @return array 회원 ID 목록
     */
    public function searchByEncryptedField(int $domainId, int $fieldId, string $searchValue): array
    {
        $searchIndex = $this->encryptionService->createSearchIndex($searchValue);

        return $this->memberRepository->findMemberIdsBySearchIndex($domainId, $fieldId, $searchIndex);
    }

    // =========================================================================
    // 필드 값 저장 (Field Value Operations)
    // =========================================================================

    /**
     * 회원 추가 필드 값 저장 (Public - MemberAdminService에서 호출)
     *
     * @param int $memberId 회원 ID
     * @param array $fields 필드 값 [field_id => value]
     */
    public function saveFieldValuesForMember(int $memberId, array $fields): void
    {
        $this->saveFieldValues($memberId, $fields);
    }

    /**
     * 회원 추가 필드 값 저장
     *
     * 필드 설정에 따라 암호화 및 검색 인덱스 처리
     * address 타입 필드는 JSON으로 저장
     * file 타입 필드는 temp→final 이동 후 JSON 메타 저장
     */
    public function saveFieldValues(int $memberId, array $fields): void
    {
        $fieldDefinitions = $this->getFieldDefinitionsById(array_keys($fields));

        foreach ($fields as $fieldId => $value) {
            $fieldDef = $fieldDefinitions[$fieldId] ?? null;
            $fieldType = $fieldDef['field_type'] ?? 'text';

            // file 타입 처리
            if ($fieldType === 'file') {
                $this->saveFileFieldValue($memberId, (int) $fieldId, $value, $fieldDef);
                continue;
            }

            $this->memberRepository->deleteFieldValue($memberId, (int) $fieldId);

            if ($value !== null && $value !== '') {
                $isEncrypted = (bool) ($fieldDef['is_encrypted'] ?? false);
                $isSearchable = (bool) ($fieldDef['is_searched'] ?? false);

                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }

                if ($fieldType === 'address') {
                    $decoded = is_string($value) ? json_decode($value, true) : $value;
                    if (empty($decoded['zipcode']) && empty($decoded['address1']) && empty($decoded['address2'])) {
                        continue;
                    }
                }

                $searchValue = $value;
                if ($fieldType === 'address' && $isSearchable) {
                    $decoded = json_decode($value, true);
                    $searchValue = $decoded['zipcode'] ?? '';
                }

                $processed = $this->encryptionService->processFieldValue(
                    $value,
                    $isEncrypted,
                    $isSearchable,
                    $fieldType === 'address' ? $searchValue : null
                );

                $this->memberRepository->saveFieldValue(
                    $memberId,
                    (int) $fieldId,
                    $processed['field_value'],
                    $processed['search_index']
                );
            }
        }
    }

    /**
     * 파일 필드 값 저장 (CustomFieldFileHandler 사용)
     *
     * - __delete__: 기존 파일 삭제
     * - temp_path 포함 JSON: 임시 → 최종 경로 이동 후 메타 저장
     * - 빈값: 스킵 (기존 값 유지)
     */
    private function saveFileFieldValue(int $memberId, int $fieldId, mixed $value, ?array $fieldDef): void
    {
        if (!$this->fileHandler) {
            return;
        }

        if (empty($fieldDef['domain_id'])) {
            return;
        }

        $domainId = (int) $fieldDef['domain_id'];
        $result = $this->fileHandler->processFileValue($value, $domainId, 'member-fields', (string) $memberId);

        if ($result->isFailure() || $result->get('action') === 'skip') {
            return;
        }

        // delete, save 모두 기존 파일 제거 필요
        $this->deleteExistingFile($memberId, $fieldId);
        $this->memberRepository->deleteFieldValue($memberId, $fieldId);

        if ($result->get('action') === 'save') {
            $this->memberRepository->saveFieldValue($memberId, $fieldId, $result->get('meta_json'), null);
        }
    }

    /**
     * 기존 파일 삭제 (디스크에서)
     */
    private function deleteExistingFile(int $memberId, int $fieldId): void
    {
        if (!$this->fileHandler) {
            return;
        }

        $existingValues = $this->memberRepository->getFieldValues($memberId);

        foreach ($existingValues as $row) {
            if ((int) $row['field_id'] !== $fieldId) {
                continue;
            }
            if (($row['field_type'] ?? '') !== 'file' || empty($row['field_value'])) {
                continue;
            }

            $this->fileHandler->deleteFileByMeta($row['field_value']);
        }
    }

    /**
     * 필드 ID로 필드 정의 조회
     */
    private function getFieldDefinitionsById(array $fieldIds): array
    {
        if (empty($fieldIds)) {
            return [];
        }

        $rows = $this->fieldRepository->findByIds($fieldIds);

        $result = [];
        foreach ($rows as $row) {
            $result[$row['field_id']] = $row;
        }

        return $result;
    }

    // =========================================================================
    // Front 회원가입/수정/탈퇴 (추후 MemberFrontService로 분리 예정)
    // =========================================================================

    /**
     * 회원가입 (Front용)
     *
     * @param array $data 가입 데이터
     *   - domain_id: 도메인 ID (필수)
     *   - user_id: 아이디 (필수)
     *   - password: 비밀번호 (필수)
     *   - nickname: 닉네임
     *   - fields: 추가 필드 [field_id => value]
     *   - agreements: 약관 동의 [policy_id => version]
     *   - ip_address: 동의 IP
     */
    public function register(array $data, ?\Mublo\Core\Context\Context $context = null): Result
    {
        $required = ['user_id', 'password', 'domain_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return Result::failure("{$field}은(는) 필수 입력 항목입니다.");
            }
        }

        $useEmailAsUserId = !empty($data['use_email_as_userid']);
        $userIdCheck = $this->checkUserIdAvailability($data['domain_id'], $data['user_id'], $useEmailAsUserId);
        if ($userIdCheck->isFailure()) {
            return $userIdCheck;
        }

        $passwordCheck = $this->validatePassword($data['password']);
        if ($passwordCheck->isFailure()) {
            return $passwordCheck;
        }

        // 닉네임 검증 (선택: 입력된 경우에만 중복/형식 검사)
        $nickname = trim($data['nickname'] ?? '');
        if (!empty($nickname)) {
            $nicknameCheck = $this->validateNickname($nickname);
            if ($nicknameCheck->isFailure()) {
                return $nicknameCheck;
            }

            $nicknameDup = $this->checkDuplicate($data['domain_id'], 'nickname', $nickname);
            if ($nicknameDup->isFailure()) {
                return $nicknameDup;
            }
        }

        if (!empty($data['fields'])) {
            $fieldValidation = $this->validateFieldValues($data['domain_id'], $data['fields']);
            if ($fieldValidation->isFailure()) {
                return $fieldValidation;
            }
        }

        // 플러그인 데이터 가공 이벤트 (DB 저장 전)
        $pluginData = [];
        if ($context) {
            $preparingEvent = new MemberRegisterPreparingEvent($data, $context);
            $this->dispatch($preparingEvent);
            $data = $preparingEvent->getData();
            $pluginData = $preparingEvent->getAllPluginData();
        }

        $hashedPassword = password_hash($data['password'], PASSWORD_BCRYPT);
        $levelValue = $data['level_value'] ?? 1;

        try {
            $memberId = $this->memberRepository->getDb()->transaction(function () use ($data, $hashedPassword, $nickname, $levelValue) {
                $insertData = [
                    'domain_id' => $data['domain_id'],
                    'domain_group' => $data['domain_group'] ?? null,
                    'user_id' => $data['user_id'],
                    'password' => $hashedPassword,
                    'level_value' => $levelValue,
                    'status' => $data['status'] ?? 'active',
                ];

                if (!empty($nickname)) {
                    $insertData['nickname'] = $nickname;
                }

                $memberId = $this->memberRepository->create($insertData);

                if (!$memberId) {
                    throw new \RuntimeException('회원 생성 실패');
                }

                if (!empty($data['fields'])) {
                    $this->saveFieldValues($memberId, $data['fields']);
                }

                // 약관 동의 저장
                if (!empty($data['agreements'])) {
                    $ip = $data['ip_address'] ?? null;
                    foreach ($data['agreements'] as $policyId => $agreedVersion) {
                        $this->memberRepository->savePolicyAgreement(
                            $memberId,
                            (int) $policyId,
                            $agreedVersion,
                            $ip
                        );
                    }
                }

                return $memberId;
            });

            // 이벤트 발행: 사용자 직접 가입
            $member = $this->memberRepository->find($memberId);
            if ($member) {
                $event = new MemberRegisteredByUserEvent($member);
                if (!empty($pluginData)) {
                    $event->setAllPluginData($pluginData);
                }
                $this->dispatch($event);
            }

            return Result::success('회원가입이 완료되었습니다.', ['member_id' => $memberId]);
        } catch (\Throwable $e) {
            error_log('[MemberService::register] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return Result::failure('회원가입에 실패했습니다.');
        }
    }

    /**
     * 회원정보 수정 (Front용 - 본인)
     */
    public function update(int $memberId, array $data): Result
    {
        $member = $this->memberRepository->find($memberId);

        if (!$member) {
            return Result::failure('회원 정보를 찾을 수 없습니다.');
        }

        $updateData = [];
        $changes = [];

        if (!empty($data['password'])) {
            if (strlen($data['password']) < 6) {
                return Result::failure('비밀번호는 최소 6자 이상이어야 합니다.');
            }
            $updateData['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
            $changes[] = 'password';
        }

        // 닉네임 변경
        if (isset($data['nickname'])) {
            $nickname = trim($data['nickname']);
            if ($nickname !== '') {
                $nicknameCheck = $this->validateNickname($nickname);
                if ($nicknameCheck->isFailure()) {
                    return $nicknameCheck;
                }

                $nicknameDup = $this->checkDuplicate($member->getDomainId(), 'nickname', $nickname, $memberId);
                if ($nicknameDup->isFailure()) {
                    return $nicknameDup;
                }

                $updateData['nickname'] = $nickname;
                $changes[] = 'nickname';
            }
        }

        if (!empty($data['fields'])) {
            $changes[] = 'fields';
        }

        try {
            $this->memberRepository->getDb()->transaction(function () use ($memberId, $updateData, $data) {
                if ($updateData) {
                    $this->memberRepository->update($memberId, $updateData);
                }

                if (!empty($data['fields'])) {
                    $this->saveFieldValues($memberId, $data['fields']);
                }
            });

            // 이벤트 발행: 본인 정보 수정
            if (!empty($changes)) {
                $this->dispatch(new MemberUpdatedBySelfEvent($member, $changes));
            }

            return Result::success('회원정보가 수정되었습니다.');
        } catch (\Throwable $e) {
            return Result::failure('회원정보 수정에 실패했습니다.');
        }
    }

    /**
     * 회원 탈퇴 (Front용 - 본인)
     *
     * 소프트 삭제: status → withdrawn, 개인정보 정리
     * 보존 항목: user_id, created_at, withdrawn_at, withdrawal_reason
     * 도메인 운영자는 탈퇴 불가 (고객센터 문의 안내)
     */
    public function withdraw(int $memberId, string $password, string $reason = ''): Result
    {
        $member = $this->memberRepository->find($memberId);

        if (!$member) {
            return Result::failure('회원 정보를 찾을 수 없습니다.');
        }

        if (!password_verify($password, $member->getPassword())) {
            return Result::failure('비밀번호가 일치하지 않습니다.');
        }

        // 도메인 운영자 탈퇴 차단
        if ($this->domainRepository && $this->domainRepository->countByMemberId($memberId) > 0) {
            return Result::failure('운영 중인 도메인(사이트)이 있어 탈퇴할 수 없습니다. 고객센터로 문의해주세요.');
        }

        try {
            $this->memberRepository->getDb()->transaction(function () use ($memberId, $reason) {
                // 1) 추가 필드 값 삭제 (이메일, 이름, 전화번호 등 개인정보)
                $this->memberRepository->deleteAllFieldValues($memberId);

                // 2) Core 필드 정리 + 상태 전환
                $this->memberRepository->softDelete($memberId, $reason ?: null);
            });

            return Result::success('회원 탈퇴가 완료되었습니다.');
        } catch (\Throwable $e) {
            return Result::failure('회원 탈퇴 처리에 실패했습니다.');
        }
    }

    /**
     * 아이디 중복 검사 (API용)
     */
    public function checkUserId(int $domainId, string $userId): Result
    {
        if ($this->memberRepository->existsByUserId($domainId, $userId)) {
            return Result::failure('이미 사용 중인 아이디입니다.');
        }

        return Result::success('사용 가능한 아이디입니다.');
    }

    // =========================================================================
    // 계정 찾기 (아이디/비밀번호 복구)
    // =========================================================================

    /**
     * 이메일 커스텀 필드 존재 여부 확인
     */
    public function hasEmailField(int $domainId): bool
    {
        $fields = $this->fieldRepository->findByDomain($domainId);

        foreach ($fields as $field) {
            if ($field['field_type'] === 'email') {
                return true;
            }
        }

        return false;
    }

    /**
     * 이메일로 아이디 찾기 (Mode B: 일반 아이디 모드)
     *
     * 이메일 커스텀 필드의 Blind Index로 회원 검색 후
     * 마스킹된 user_id 목록 반환
     *
     * @return Result 마스킹된 아이디 목록
     */
    public function findUserIdsByEmail(int $domainId, string $email): Result
    {
        if (empty($email)) {
            return Result::failure('이메일을 입력해주세요.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return Result::failure('올바른 이메일 형식으로 입력해주세요.');
        }

        // 이메일 필드 찾기
        $emailFieldId = $this->findEmailFieldId($domainId);
        if ($emailFieldId === null) {
            return Result::failure('이메일 필드가 설정되지 않았습니다.');
        }

        // Blind Index 검색
        $members = $this->searchByEncryptedField($domainId, $emailFieldId, $email);

        if (empty($members)) {
            return Result::failure('해당 이메일로 등록된 계정을 찾을 수 없습니다.');
        }

        // user_id 조회 및 마스킹
        $maskedUserIds = [];
        foreach ($members as $row) {
            $member = $this->memberRepository->find($row['member_id']);
            if ($member && $member->getStatus()->value === 'active') {
                $maskedUserIds[] = $this->maskUserId($member->getUserId());
            }
        }

        if (empty($maskedUserIds)) {
            return Result::failure('해당 이메일로 등록된 계정을 찾을 수 없습니다.');
        }

        return Result::success('아이디를 찾았습니다.', ['userIds' => $maskedUserIds]);
    }

    /**
     * 비밀번호 재설정 (즉시 변경)
     *
     * @deprecated PasswordResetService::resetPassword() 사용 (이메일 토큰 기반)
     *
     * 이 메서드는 소유자 검증(토큰/OTP) 없이 아이디+이메일만으로 즉시 변경하므로
     * 프론트에서는 사용하지 않습니다. 관리자 경로 등 내부용으로만 유지합니다.
     *
     * Mode A (이메일=아이디): email → user_id로 회원 조회 → 비밀번호 변경
     * Mode B (일반 아이디): user_id + email → 회원 조회 + 이메일 일치 검증 → 비밀번호 변경
     */
    public function resetPasswordByIdentity(int $domainId, array $data, bool $useEmailAsUserId = false): Result
    {
        $newPassword = $data['new_password'] ?? '';
        $newPasswordConfirm = $data['new_password_confirm'] ?? '';

        if (empty($newPassword)) {
            return Result::failure('새 비밀번호를 입력해주세요.');
        }

        $passwordResult = $this->validatePassword($newPassword);
        if ($passwordResult->isFailure()) {
            return $passwordResult;
        }

        if ($newPassword !== $newPasswordConfirm) {
            return Result::failure('비밀번호가 일치하지 않습니다.');
        }

        if ($useEmailAsUserId) {
            // Mode A: 이메일이 곧 아이디
            $email = trim($data['email'] ?? '');
            if (empty($email)) {
                return Result::failure('이메일을 입력해주세요.');
            }

            $member = $this->memberRepository->findByDomainAndUserId($domainId, $email);
            if (!$member || $member->getStatus()->value !== 'active') {
                return Result::failure('해당 이메일로 등록된 계정을 찾을 수 없습니다.');
            }
        } else {
            // Mode B: 아이디 + 이메일 검증
            $userId = trim($data['user_id'] ?? '');
            $email = trim($data['email'] ?? '');

            if (empty($userId) || empty($email)) {
                return Result::failure('아이디와 이메일을 모두 입력해주세요.');
            }

            $member = $this->memberRepository->findByDomainAndUserId($domainId, $userId);
            if (!$member || $member->getStatus()->value !== 'active') {
                return Result::failure('해당 아이디로 등록된 계정을 찾을 수 없습니다.');
            }

            // 이메일 필드 일치 검증
            if (!$this->verifyMemberEmail($domainId, $member->getMemberId(), $email)) {
                return Result::failure('아이디와 이메일이 일치하지 않습니다.');
            }
        }

        // 비밀번호 변경
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->memberRepository->updatePassword($member->getMemberId(), $hashedPassword);

        return Result::success('비밀번호가 변경되었습니다. 새 비밀번호로 로그인해주세요.');
    }

    /**
     * 이메일 커스텀 필드 ID 조회
     */
    private function findEmailFieldId(int $domainId): ?int
    {
        $fields = $this->fieldRepository->findByDomain($domainId);

        foreach ($fields as $field) {
            if ($field['field_type'] === 'email') {
                return (int) $field['field_id'];
            }
        }

        return null;
    }

    /**
     * 회원의 이메일 커스텀 필드 일치 검증 (외부 호출용)
     *
     * PasswordResetService 등에서 이메일 소유자 검증 시 사용
     */
    public function verifyMemberEmailPublic(int $domainId, int $memberId, string $email): bool
    {
        return $this->verifyMemberEmail($domainId, $memberId, $email);
    }

    /**
     * 회원의 이메일 커스텀 필드 일치 검증 (Blind Index)
     */
    private function verifyMemberEmail(int $domainId, int $memberId, string $email): bool
    {
        $emailFieldId = $this->findEmailFieldId($domainId);
        if ($emailFieldId === null) {
            return false;
        }

        $searchIndex = $this->encryptionService->createSearchIndex($email);
        $members = $this->memberRepository->findMemberIdsBySearchIndex($domainId, $emailFieldId, $searchIndex);

        foreach ($members as $row) {
            if ((int) $row['member_id'] === $memberId) {
                return true;
            }
        }

        return false;
    }

    /**
     * 아이디 마스킹 (앞 3자 표시, 나머지 *)
     */
    private function maskUserId(string $userId): string
    {
        $len = mb_strlen($userId);
        if ($len <= 3) {
            return mb_substr($userId, 0, 1) . str_repeat('*', $len - 1);
        }

        $show = min(3, (int) ceil($len * 0.4));
        return mb_substr($userId, 0, $show) . str_repeat('*', $len - $show);
    }

    // =========================================================================
    // 이벤트 발행 헬퍼
    // =========================================================================

    /**
     * 이벤트 발행
     *
     * EventDispatcher가 없으면 이벤트를 그대로 반환합니다.
     * 호출부에서 직접 new Event()로 생성하여 전달하는 패턴 사용:
     *
     * ```php
     * $this->dispatch(new MemberRegisteredByUserEvent($member));
     * $this->dispatch(new MemberUpdatedBySelfEvent($member, $changes));
     * ```
     */
    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }
}
