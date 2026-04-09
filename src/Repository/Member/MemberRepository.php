<?php
namespace Mublo\Repository\Member;

use Mublo\Entity\Member\Member;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Repository\BaseRepository;

/**
 * Member Repository
 *
 * 회원 데이터베이스 접근 담당
 *
 * 책임:
 * - members 테이블 CRUD
 * - Member Entity 반환
 *
 * 금지:
 * - 비즈니스 로직 (Service 담당)
 * - 비밀번호 해싱 (Service 담당)
 */
class MemberRepository extends BaseRepository
{
    protected string $table = 'members';
    protected string $entityClass = Member::class;
    protected string $primaryKey = 'member_id';
    private string $levelsTable = 'member_levels';

    /** @var array<int, array|null> level_value => level row 인메모리 캐시 */
    private array $levelCache = [];

    public function __construct(?Database $db = null)
    {
        $db = $db ?? DatabaseManager::getInstance()->connect();
        parent::__construct($db);
    }

    /**
     * 사용자명으로 회원 조회 (로그인용)
     */
    public function findByUserId(string $userId): ?Member
    {
        return $this->findOneBy(['user_id' => $userId]);
    }

    /**
     * 도메인+아이디로 회원 조회
     */
    public function findByDomainAndUserId(int $domainId, string $userId): ?Member
    {
        return $this->findOneBy([
            'domain_id' => $domainId,
            'user_id' => $userId,
        ]);
    }

    /**
     * 도메인별 회원 목록 조회
     *
     * @return Member[]
     */
    public function findByDomain(int $domainId, int $limit = 100, int $offset = 0): array
    {
        return $this->findBy(['domain_id' => $domainId], $limit, $offset);
    }

    /**
     * 아이디 중복 검사
     */
    public function existsByUserId(int $domainId, string $userId): bool
    {
        return $this->existsBy([
            'domain_id' => $domainId,
            'user_id' => $userId,
        ]);
    }

    /**
     * 도메인별 회원 수 조회
     */
    public function countByDomain(int $domainId): int
    {
        return $this->countBy(['domain_id' => $domainId]);
    }

    /**
     * 도메인별 회원 검색
     *
     * @param int $domainId 도메인 ID
     * @param array $search 검색 조건 ['keyword' => '검색어', 'field' => '검색필드']
     * @param int $limit 조회 개수
     * @param int $offset 시작 위치
     * @return Member[]
     */
    private const SEARCHABLE_FIELDS = ['user_id', 'nickname', 'email', 'phone'];

    public function searchByDomain(int $domainId, array $search, int $limit = 100, int $offset = 0): array
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId);

        if (!empty($search['keyword']) && !empty($search['field'])) {
            $field = $search['field'];
            if (!in_array($field, self::SEARCHABLE_FIELDS, true)) {
                throw new \InvalidArgumentException("검색 불가 필드: {$field}");
            }
            $keyword = '%' . $search['keyword'] . '%';
            $query->where($field, 'LIKE', $keyword);
        }

        $rows = $query
            ->orderBy('member_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return array_map(fn($row) => $this->toEntity($row), $rows);
    }

    /**
     * 도메인별 검색된 회원 수 조회
     */
    public function countByDomainWithSearch(int $domainId, array $search): int
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId);

        if (!empty($search['keyword']) && !empty($search['field'])) {
            $field = $search['field'];
            if (!in_array($field, self::SEARCHABLE_FIELDS, true)) {
                throw new \InvalidArgumentException("검색 불가 필드: {$field}");
            }
            $keyword = '%' . $search['keyword'] . '%';
            $query->where($field, 'LIKE', $keyword);
        }

        return $query->count();
    }

    /**
     * 추가 필드 검색을 포함한 회원 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @param array $search ['keyword' => string, 'field' => string, 'field_info' => array|null]
     *              field_info: ['field_id' => int, 'is_encrypted' => bool] (추가 필드인 경우)
     * @param string|null $searchIndex 암호화 필드의 경우 search_index 값
     * @param int $limit
     * @param int $offset
     * @return Member[]
     */
    public function searchByDomainWithField(
        int $domainId,
        array $search,
        ?string $searchIndex,
        int $limit = 100,
        int $offset = 0
    ): array {
        $keyword = $search['keyword'] ?? '';
        $field = $search['field'] ?? '';
        $fieldInfo = $search['field_info'] ?? null;

        // 추가 필드가 아닌 경우 (members 테이블 컬럼)
        if ($fieldInfo === null) {
            return $this->searchByDomain($domainId, $search, $limit, $offset);
        }

        // 추가 필드 검색 (member_field_values JOIN)
        $fieldId = $fieldInfo['field_id'];
        $isEncrypted = $fieldInfo['is_encrypted'];

        $db = $this->getDb();
        $membersTable = $db->prefixTable($this->table);
        $valuesTable = $db->prefixTable('member_field_values');

        if ($isEncrypted && $searchIndex) {
            // 암호화 필드: search_index 완전 일치
            $sql = "SELECT m.* FROM {$membersTable} m
                    INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                    WHERE m.domain_id = ? AND v.field_id = ? AND v.search_index = ?
                    ORDER BY m.member_id DESC
                    LIMIT ? OFFSET ?";
            $params = [$domainId, $fieldId, $searchIndex, $limit, $offset];
        } else {
            // 일반 필드: LIKE 검색
            $sql = "SELECT m.* FROM {$membersTable} m
                    INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                    WHERE m.domain_id = ? AND v.field_id = ? AND v.field_value LIKE ?
                    ORDER BY m.member_id DESC
                    LIMIT ? OFFSET ?";
            $params = [$domainId, $fieldId, '%' . $keyword . '%', $limit, $offset];
        }

        $rows = $db->select($sql, $params);

        return array_map(fn($row) => $this->toEntity($row), $rows);
    }

    /**
     * 추가 필드 검색을 포함한 회원 수 조회
     */
    public function countByDomainWithFieldSearch(
        int $domainId,
        array $search,
        ?string $searchIndex
    ): int {
        $keyword = $search['keyword'] ?? '';
        $fieldInfo = $search['field_info'] ?? null;

        // 추가 필드가 아닌 경우 (members 테이블 컬럼)
        if ($fieldInfo === null) {
            return $this->countByDomainWithSearch($domainId, $search);
        }

        // 추가 필드 검색 (member_field_values JOIN)
        $fieldId = $fieldInfo['field_id'];
        $isEncrypted = $fieldInfo['is_encrypted'];

        $db = $this->getDb();
        $membersTable = $db->prefixTable($this->table);
        $valuesTable = $db->prefixTable('member_field_values');

        if ($isEncrypted && $searchIndex) {
            // 암호화 필드: search_index 완전 일치
            $sql = "SELECT COUNT(*) as cnt FROM {$membersTable} m
                    INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                    WHERE m.domain_id = ? AND v.field_id = ? AND v.search_index = ?";
            $params = [$domainId, $fieldId, $searchIndex];
        } else {
            // 일반 필드: LIKE 검색
            $sql = "SELECT COUNT(*) as cnt FROM {$membersTable} m
                    INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                    WHERE m.domain_id = ? AND v.field_id = ? AND v.field_value LIKE ?";
            $params = [$domainId, $fieldId, '%' . $keyword . '%'];
        }

        $result = $db->select($sql, $params);

        return (int) ($result[0]['cnt'] ?? 0);
    }

    /**
     * 마지막 로그인 시간 업데이트
     */
    public function updateLastLogin(int $memberId): bool
    {
        $affected = $this->update($memberId, [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        return $affected > 0;
    }

    /**
     * 회원 소프트 삭제 (탈퇴 처리)
     *
     * 상태를 withdrawn으로 변경하고, 개인정보를 정리한다.
     * 보존: member_id, domain_id, user_id, created_at, withdrawn_at, withdrawal_reason
     */
    public function softDelete(int $memberId, ?string $reason = null): int
    {
        return $this->update($memberId, [
            'status' => 'withdrawn',
            'password' => '',
            'nickname' => null,
            'level_value' => 1,
            'domain_group' => null,
            'can_create_site' => 0,
            'point_balance' => 0,
            'last_login_at' => null,
            'last_login_ip' => null,
            'withdrawn_at' => date('Y-m-d H:i:s'),
            'withdrawal_reason' => $reason,
        ]);
    }

    /**
     * 회원의 추가 필드 값 전체 삭제
     */
    public function deleteAllFieldValues(int $memberId): int
    {
        return $this->db->table($this->fieldValuesTable)
            ->where('member_id', '=', $memberId)
            ->delete();
    }

    /**
     * 비밀번호 업데이트
     */
    public function updatePassword(int $memberId, string $hashedPassword): bool
    {
        $affected = $this->update($memberId, [
            'password' => $hashedPassword,
        ]);

        return $affected > 0;
    }

    /**
     * DB 로우를 Member Entity로 변환 (등급 정보 포함)
     *
     * @override
     */
    protected function toEntity(array $row): Member
    {
        // 회원 등급 정보 조인 (인메모리 캐시로 N+1 방지)
        $levelValue = $row['level_value'] ?? 0;

        if (!array_key_exists($levelValue, $this->levelCache)) {
            $this->levelCache[$levelValue] = $this->getDb()->table($this->levelsTable)
                ->select(['level_name', 'level_type', 'is_super', 'is_admin', 'can_operate_domain'])
                ->where('level_value', '=', $levelValue)
                ->first();
        }

        $level = $this->levelCache[$levelValue];
        if ($level) {
            $row = array_merge($row, $level);
        }

        return Member::fromArray($row);
    }

    /**
     * 생성 타임스탬프 필드명
     */
    protected function getCreatedAtField(): ?string
    {
        return 'created_at';
    }

    /**
     * 수정 타임스탬프 필드명
     */
    protected function getUpdatedAtField(): ?string
    {
        return 'updated_at';
    }

    // ========================================
    // domain_group 계층 기반 조회 메서드
    //
    // 상위 도메인 관리자가 하위 사이트 회원까지 포함하여
    // 조회할 때 사용한다.
    //
    // 조건: domain_group = '{group}' OR domain_group LIKE '{group}/%'
    // ========================================

    /**
     * domain_group 계층 기반 회원 목록 조회
     */
    public function findByDomainGroup(string $domainGroup, int $limit = 100, int $offset = 0): array
    {
        $rows = $this->getDb()->table($this->table)
            ->whereRaw(
                '(domain_group = ? OR domain_group LIKE ?)',
                [$domainGroup, $domainGroup . '/%']
            )
            ->orderBy('member_id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return array_map(fn($row) => $this->toEntity($row), $rows);
    }

    /**
     * domain_group 계층 기반 회원 수 조회
     */
    public function countByDomainGroup(string $domainGroup): int
    {
        return $this->getDb()->table($this->table)
            ->whereRaw(
                '(domain_group = ? OR domain_group LIKE ?)',
                [$domainGroup, $domainGroup . '/%']
            )
            ->count();
    }

    /**
     * domain_group 계층 기반 회원 검색 (members 컬럼 기준)
     */
    public function searchByDomainGroup(string $domainGroup, array $search, int $limit = 100, int $offset = 0): array
    {
        $query = $this->getDb()->table($this->table)
            ->whereRaw(
                '(domain_group = ? OR domain_group LIKE ?)',
                [$domainGroup, $domainGroup . '/%']
            );

        if (!empty($search['keyword']) && !empty($search['field'])) {
            $field = $search['field'];
            if (!in_array($field, self::SEARCHABLE_FIELDS, true)) {
                throw new \InvalidArgumentException("검색 불가 필드: {$field}");
            }
            $query->where($field, 'LIKE', '%' . $search['keyword'] . '%');
        }

        $rows = $query->orderBy('member_id', 'DESC')->limit($limit)->offset($offset)->get();

        return array_map(fn($row) => $this->toEntity($row), $rows);
    }

    /**
     * domain_group 계층 기반 회원 수 조회 (검색 포함)
     */
    public function countByDomainGroupWithSearch(string $domainGroup, array $search): int
    {
        $query = $this->getDb()->table($this->table)
            ->whereRaw(
                '(domain_group = ? OR domain_group LIKE ?)',
                [$domainGroup, $domainGroup . '/%']
            );

        if (!empty($search['keyword']) && !empty($search['field'])) {
            $field = $search['field'];
            if (!in_array($field, self::SEARCHABLE_FIELDS, true)) {
                throw new \InvalidArgumentException("검색 불가 필드: {$field}");
            }
            $query->where($field, 'LIKE', '%' . $search['keyword'] . '%');
        }

        return $query->count();
    }

    /**
     * domain_group 계층 기반 회원 검색 (추가 필드 포함)
     *
     * field_info가 null이면 members 컬럼 검색으로 위임한다.
     */
    public function searchByDomainGroupWithField(
        string $domainGroup,
        array $search,
        ?string $searchIndex,
        int $limit = 100,
        int $offset = 0
    ): array {
        $fieldInfo = $search['field_info'] ?? null;

        if ($fieldInfo === null) {
            return $this->searchByDomainGroup($domainGroup, $search, $limit, $offset);
        }

        $keyword = $search['keyword'] ?? '';
        $fieldId = $fieldInfo['field_id'];
        $isEncrypted = $fieldInfo['is_encrypted'];

        $db = $this->getDb();
        $membersTable = $db->prefixTable($this->table);
        $valuesTable = $db->prefixTable('member_field_values');

        if ($isEncrypted && $searchIndex) {
            $sql = "SELECT m.* FROM {$membersTable} m
                    INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                    WHERE (m.domain_group = ? OR m.domain_group LIKE ?)
                      AND v.field_id = ? AND v.search_index = ?
                    ORDER BY m.member_id DESC
                    LIMIT ? OFFSET ?";
            $params = [$domainGroup, $domainGroup . '/%', $fieldId, $searchIndex, $limit, $offset];
        } else {
            $sql = "SELECT m.* FROM {$membersTable} m
                    INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                    WHERE (m.domain_group = ? OR m.domain_group LIKE ?)
                      AND v.field_id = ? AND v.field_value LIKE ?
                    ORDER BY m.member_id DESC
                    LIMIT ? OFFSET ?";
            $params = [$domainGroup, $domainGroup . '/%', $fieldId, '%' . $keyword . '%', $limit, $offset];
        }

        $rows = $db->select($sql, $params);

        return array_map(fn($row) => $this->toEntity($row), $rows);
    }

    /**
     * domain_group 계층 기반 회원 수 조회 (추가 필드 검색 포함)
     */
    public function countByDomainGroupWithFieldSearch(
        string $domainGroup,
        array $search,
        ?string $searchIndex
    ): int {
        $fieldInfo = $search['field_info'] ?? null;

        if ($fieldInfo === null) {
            return $this->countByDomainGroupWithSearch($domainGroup, $search);
        }

        $keyword = $search['keyword'] ?? '';
        $fieldId = $fieldInfo['field_id'];
        $isEncrypted = $fieldInfo['is_encrypted'];

        $db = $this->getDb();
        $membersTable = $db->prefixTable($this->table);
        $valuesTable = $db->prefixTable('member_field_values');

        if ($isEncrypted && $searchIndex) {
            $sql = "SELECT COUNT(*) as cnt FROM {$membersTable} m
                    INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                    WHERE (m.domain_group = ? OR m.domain_group LIKE ?)
                      AND v.field_id = ? AND v.search_index = ?";
            $params = [$domainGroup, $domainGroup . '/%', $fieldId, $searchIndex];
        } else {
            $sql = "SELECT COUNT(*) as cnt FROM {$membersTable} m
                    INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                    WHERE (m.domain_group = ? OR m.domain_group LIKE ?)
                      AND v.field_id = ? AND v.field_value LIKE ?";
            $params = [$domainGroup, $domainGroup . '/%', $fieldId, '%' . $keyword . '%'];
        }

        $result = $db->select($sql, $params);

        return (int) ($result[0]['cnt'] ?? 0);
    }

    // ========================================
    // member_field_values 관련 메서드
    // ========================================

    private string $fieldValuesTable = 'member_field_values';

    /**
     * 회원의 추가 필드 값 조회
     *
     * @param int $memberId 회원 ID
     * @return array [['field_id' => int, 'field_name' => string, 'field_value' => string, 'is_encrypted' => bool, 'field_type' => string], ...]
     */
    public function getFieldValues(int $memberId): array
    {
        return $this->db->table($this->fieldValuesTable . ' as v')
            ->select(['v.field_id', 'f.field_name', 'v.field_value', 'f.is_encrypted', 'f.field_type'])
            ->join('member_fields as f', 'v.field_id', '=', 'f.field_id')
            ->where('v.member_id', '=', $memberId)
            ->get();
    }

    /**
     * 여러 회원의 추가 필드 값 일괄 조회
     *
     * @param array $memberIds 회원 ID 목록
     * @param array $fieldIds 조회할 필드 ID 목록
     * @return array
     */
    public function getFieldValuesForMembers(array $memberIds, array $fieldIds): array
    {
        if (empty($memberIds) || empty($fieldIds)) {
            return [];
        }

        $placeholdersMember = implode(',', array_fill(0, count($memberIds), '?'));
        $placeholdersField = implode(',', array_fill(0, count($fieldIds), '?'));

        return $this->db->table($this->fieldValuesTable)
            ->whereRaw("member_id IN ({$placeholdersMember})", array_values($memberIds))
            ->whereRaw("field_id IN ({$placeholdersField})", array_values($fieldIds))
            ->get();
    }

    /**
     * 회원의 특정 필드 값 삭제
     */
    public function deleteFieldValue(int $memberId, int $fieldId): int
    {
        return $this->db->table($this->fieldValuesTable)
            ->where('member_id', '=', $memberId)
            ->where('field_id', '=', $fieldId)
            ->delete();
    }

    /**
     * 회원의 추가 필드 값 저장 (upsert)
     */
    public function saveFieldValue(int $memberId, int $fieldId, string $fieldValue, ?string $searchIndex = null): int
    {
        // 기존 값 삭제
        $this->deleteFieldValue($memberId, $fieldId);

        // 새 값 저장
        $insertData = [
            'member_id' => $memberId,
            'field_id' => $fieldId,
            'field_value' => $fieldValue,
        ];

        if ($searchIndex !== null) {
            $insertData['search_index'] = $searchIndex;
        }

        return $this->db->table($this->fieldValuesTable)->insert($insertData);
    }

    /**
     * 암호화 필드 검색 (Blind Index)
     *
     * @param int $domainId 도메인 ID
     * @param int $fieldId 필드 ID
     * @param string $searchIndex 검색 인덱스
     * @return array 회원 ID 목록
     */
    public function findMemberIdsBySearchIndex(int $domainId, int $fieldId, string $searchIndex): array
    {
        $membersTable = $this->db->prefixTable($this->table);
        $valuesTable = $this->db->prefixTable($this->fieldValuesTable);

        $sql = "SELECT m.member_id FROM {$membersTable} m
                INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                WHERE m.domain_id = ? AND v.field_id = ? AND v.search_index = ?";

        return $this->db->select($sql, [$domainId, $fieldId, $searchIndex]);
    }

    /**
     * 추가 필드 값 중복 체크 (일반 필드)
     *
     * @param int $domainId 도메인 ID
     * @param int $fieldId 필드 ID
     * @param string $value 필드 값
     * @param int|null $excludeMemberId 제외할 회원 ID (수정 시 자기 자신 제외)
     * @return bool 중복이면 true
     */
    public function existsFieldValue(int $domainId, int $fieldId, string $value, ?int $excludeMemberId = null): bool
    {
        $membersTable = $this->db->prefixTable($this->table);
        $valuesTable = $this->db->prefixTable($this->fieldValuesTable);

        $sql = "SELECT 1 FROM {$membersTable} m
                INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                WHERE m.domain_id = ? AND v.field_id = ? AND v.field_value = ?";
        $params = [$domainId, $fieldId, $value];

        if ($excludeMemberId !== null) {
            $sql .= " AND m.member_id != ?";
            $params[] = $excludeMemberId;
        }

        $sql .= " LIMIT 1";

        $result = $this->db->select($sql, $params);
        return !empty($result);
    }

    /**
     * 추가 필드 값 중복 체크 (암호화 필드 - search_index 사용)
     *
     * @param int $domainId 도메인 ID
     * @param int $fieldId 필드 ID
     * @param string $searchIndex 검색 인덱스
     * @param int|null $excludeMemberId 제외할 회원 ID (수정 시 자기 자신 제외)
     * @return bool 중복이면 true
     */
    public function existsFieldValueBySearchIndex(int $domainId, int $fieldId, string $searchIndex, ?int $excludeMemberId = null): bool
    {
        $membersTable = $this->db->prefixTable($this->table);
        $valuesTable = $this->db->prefixTable($this->fieldValuesTable);

        $sql = "SELECT 1 FROM {$membersTable} m
                INNER JOIN {$valuesTable} v ON m.member_id = v.member_id
                WHERE m.domain_id = ? AND v.field_id = ? AND v.search_index = ?";
        $params = [$domainId, $fieldId, $searchIndex];

        if ($excludeMemberId !== null) {
            $sql .= " AND m.member_id != ?";
            $params[] = $excludeMemberId;
        }

        $sql .= " LIMIT 1";

        $result = $this->db->select($sql, $params);
        return !empty($result);
    }

    /**
     * 아이디 중복 검사 (수정 시 자기 자신 제외 가능)
     *
     * @param int $domainId 도메인 ID
     * @param string $userId 아이디
     * @param int|null $excludeMemberId 제외할 회원 ID
     * @return bool 중복이면 true
     */
    public function existsByUserIdExcept(int $domainId, string $userId, ?int $excludeMemberId = null): bool
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('user_id', '=', $userId);

        if ($excludeMemberId !== null) {
            $query->where('member_id', '!=', $excludeMemberId);
        }

        return $query->exists();
    }

    // ========================================
    // Bulk 조회 메서드
    // ========================================

    /**
     * 여러 회원 ID로 일괄 조회
     *
     * @param array $memberIds 회원 ID 목록
     * @return Member[]
     */
    public function findByIds(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $rows = $this->getDb()->table($this->table)
            ->whereRaw("member_id IN ({$placeholders})", array_map('intval', $memberIds))
            ->get();

        return array_map(fn($row) => $this->toEntity($row), $rows);
    }

    /**
     * 아이디로 회원 검색 (자동완성용)
     *
     * @param int $domainId 도메인 ID
     * @param string $keyword 검색 키워드
     * @param int $limit 최대 결과 수
     * @return Member[]
     */
    public function searchByUserId(int $domainId, string $keyword, int $limit = 10): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('user_id', 'LIKE', '%' . $keyword . '%')
            ->orderBy('user_id', 'ASC')
            ->limit($limit)
            ->get();

        return array_map(fn($row) => $this->toEntity($row), $rows);
    }

    /**
     * 아이디 또는 닉네임으로 회원 검색
     *
     * 관리자 선택 모달 등에서 사용
     *
     * @param int $domainId 도메인 ID
     * @param string $keyword 검색 키워드 (아이디 또는 닉네임)
     * @param int $limit 최대 결과 수
     * @return Member[]
     */
    public function searchByKeyword(int $domainId, string $keyword, int $limit = 10): array
    {
        $likeKeyword = '%' . $keyword . '%';

        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->whereRaw('(user_id LIKE ? OR nickname LIKE ?)', [$likeKeyword, $likeKeyword])
            ->orderBy('user_id', 'ASC')
            ->limit($limit)
            ->get();

        return array_map(fn($row) => $this->toEntity($row), $rows);
    }

    /**
     * 닉네임 중복 검사
     *
     * @param int $domainId 도메인 ID
     * @param string $nickname 닉네임
     * @param int|null $excludeMemberId 제외할 회원 ID (수정 시 자기 자신 제외)
     * @return bool 중복이면 true
     */
    public function existsByNickname(int $domainId, string $nickname, ?int $excludeMemberId = null): bool
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('nickname', '=', $nickname);

        if ($excludeMemberId !== null) {
            $query->where('member_id', '!=', $excludeMemberId);
        }

        return $query->exists();
    }

    // ========================================
    // 조건 기반 회원 조회 (이벤트 시스템용)
    // ========================================

    /**
     * 조건 기반 회원 목록 조회
     *
     * MemberListQueryEvent 구독자가 사용.
     * member_levels JOIN으로 level_type 필터링 지원.
     *
     * @param int   $domainId 도메인 ID
     * @param array $criteria 조회 조건
     *   - level_type: string (SUPPLIER, PARTNER, SELLER 등)
     *   - level_value: int (정확한 레벨 값)
     *   - status: string (active, inactive 등)
     *   - keyword: string (user_id 또는 nickname LIKE 검색)
     *   - limit: int (최대 결과 수, 기본 1000)
     * @return array 배열 목록 [{member_id, user_id, nickname, level_value, level_name, level_type, ...}, ...]
     */
    public function findByCriteria(int $domainId, array $criteria = []): array
    {
        $db = $this->getDb();
        $membersTable = $db->prefixTable($this->table);
        $levelsTable = $db->prefixTable($this->levelsTable);

        $sql = "SELECT m.member_id, m.user_id, m.nickname, m.level_value, m.status, m.created_at,
                       l.level_name, l.level_type
                FROM {$membersTable} m
                LEFT JOIN {$levelsTable} l ON m.level_value = l.level_value
                WHERE m.domain_id = ?";
        $params = [$domainId];

        // level_type 조건 (member_levels.level_type)
        if (!empty($criteria['level_type'])) {
            $sql .= " AND l.level_type = ?";
            $params[] = $criteria['level_type'];
        }

        // level_value 조건 (정확 매치)
        if (isset($criteria['level_value'])) {
            $sql .= " AND m.level_value = ?";
            $params[] = (int) $criteria['level_value'];
        }

        // status 조건
        if (!empty($criteria['status'])) {
            $sql .= " AND m.status = ?";
            $params[] = $criteria['status'];
        }

        // keyword 조건 (user_id 또는 nickname LIKE)
        if (!empty($criteria['keyword'])) {
            $sql .= " AND (m.user_id LIKE ? OR m.nickname LIKE ?)";
            $like = '%' . $criteria['keyword'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY m.member_id ASC";

        $limit = (int) ($criteria['limit'] ?? 1000);
        $sql .= " LIMIT ?";
        $params[] = $limit;

        return $db->select($sql, $params);
    }

    // ========================================
    // Balance Manager 관련 메서드
    // ========================================

    /**
     * 회원 잔액 조회 (스냅샷)
     *
     * @param int $memberId 회원 ID
     * @param int|null $domainId 도메인 ID (지정 시 도메인 소유 검증 포함)
     * @return int|null 잔액 (회원 없거나 도메인 불일치면 null)
     */
    public function getBalance(int $memberId, ?int $domainId = null): ?int
    {
        $qb = $this->getDb()->table($this->table)
            ->select(['point_balance'])
            ->where('member_id', '=', $memberId);

        if ($domainId !== null) {
            $qb->where('domain_id', '=', $domainId);
        }

        $row = $qb->first();

        return $row !== null ? (int) $row['point_balance'] : null;
    }

    /**
     * 회원 잔액 조회 (행 락킹 - Pessimistic Lock)
     *
     * SELECT ... FOR UPDATE로 동시성 제어
     *
     * @param int $memberId 회원 ID
     * @param int|null $domainId 도메인 ID (지정 시 소유 검증 포함)
     * @return int|null 잔액 (회원 없거나 도메인 불일치면 null)
     */
    public function getBalanceForUpdate(int $memberId, ?int $domainId = null): ?int
    {
        $table = $this->getDb()->prefixTable($this->table);

        if ($domainId !== null) {
            $sql = "SELECT point_balance FROM {$table} WHERE member_id = ? AND domain_id = ? FOR UPDATE";
            $rows = $this->getDb()->select($sql, [$memberId, $domainId]);
        } else {
            $sql = "SELECT point_balance FROM {$table} WHERE member_id = ? FOR UPDATE";
            $rows = $this->getDb()->select($sql, [$memberId]);
        }

        if (empty($rows)) {
            return null;
        }

        return (int) $rows[0]['point_balance'];
    }

    /**
     * 회원 잔액 업데이트 (스냅샷)
     *
     * @return bool 성공 여부
     */
    public function updateBalance(int $memberId, int $newBalance): bool
    {
        $affected = $this->update($memberId, [
            'point_balance' => $newBalance,
        ]);

        return $affected > 0;
    }

    // ========================================
    // 약관 동의 관련 메서드
    // ========================================

    /**
     * 회원 약관 동의 저장
     *
     * @param int $memberId 회원 ID
     * @param int $policyId 약관 ID
     * @param string $policyVersion 동의한 약관 버전
     * @param string|null $ip 동의 IP
     * @return int 생성된 agreement_id
     */
    public function savePolicyAgreement(int $memberId, int $policyId, string $policyVersion, ?string $ip = null): int
    {
        return $this->getDb()->table('member_policy_agreements')->insert([
            'member_id' => $memberId,
            'policy_id' => $policyId,
            'policy_version' => $policyVersion,
            'ip_address' => $ip,
        ]);
    }
}
