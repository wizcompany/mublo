<?php
namespace Mublo\Repository\Balance;

use Mublo\Infrastructure\Database\Database;
use Mublo\Entity\Balance\BalanceLog;

/**
 * Class BalanceLogRepository
 *
 * 포인트 변경 원장(balance_logs) 관리
 *
 * 책임:
 * - 로그 생성 (INSERT ONLY)
 * - 로그 조회 (단일, 목록, 합계)
 * - 멱등성 키로 조회
 *
 * 금지:
 * - UPDATE/DELETE (불변 원장)
 * - 비즈니스 로직 (Service 담당)
 *
 * Note: 이 테이블은 INSERT ONLY - 감사 추적용 불변 원장
 */
class BalanceLogRepository
{
    protected string $table = 'balance_logs';
    protected Database $db;
    protected ?int $domainId = null;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }

    /**
     * 도메인 ID 설정 (멀티테넌시)
     */
    public function setDomainId(int $domainId): void
    {
        $this->domainId = $domainId;
    }

    /**
     * 도메인 ID 조회
     */
    public function getDomainId(): ?int
    {
        return $this->domainId;
    }

    // ========================================
    // CREATE (INSERT ONLY)
    // ========================================

    /**
     * 로그 생성
     *
     * @param array $data 로그 데이터
     * @return int 생성된 로그 ID
     */
    public function create(array $data): int
    {
        // domain_id 자동 주입
        if ($this->domainId !== null && !isset($data['domain_id'])) {
            $data['domain_id'] = $this->domainId;
        }

        // created_at 자동 설정
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        $this->db->table($this->table)->insert($data);

        return (int) $this->db->lastInsertId();
    }

    // ========================================
    // READ (단일 조회)
    // ========================================

    /**
     * 로그 ID로 조회
     */
    public function find(int $logId): ?BalanceLog
    {
        $qb = $this->db->table($this->table)
            ->where('log_id', $logId);

        $this->applyDomainFilter($qb);

        $row = $qb->first();

        return $row ? BalanceLog::fromArray($row) : null;
    }

    /**
     * 멱등성 키로 조회
     *
     * @param string $idempotencyKey 멱등성 키
     * @param int|null $domainId 도메인 ID (멀티테넌트 격리)
     */
    public function findByIdempotencyKey(string $idempotencyKey, ?int $domainId = null): ?BalanceLog
    {
        $qb = $this->db->table($this->table)
            ->where('idempotency_key', $idempotencyKey);

        if ($domainId !== null) {
            $qb->where('domain_id', $domainId);
        } else {
            $this->applyDomainFilter($qb);
        }

        $row = $qb->first();

        return $row ? BalanceLog::fromArray($row) : null;
    }

    // ========================================
    // READ (목록 조회)
    // ========================================

    /**
     * 전체 로그 페이지네이션 목록 조회 (관리자용)
     *
     * @param int $domainId 도메인 ID
     * @param int $page 페이지 번호 (1-based)
     * @param int $perPage 페이지당 개수
     * @param array $filters 필터 조건 [member_id, source_type, start_date, end_date]
     * @return array ['items' => BalanceLog[], 'pagination' => array]
     */
    public function getPaginatedList(int $domainId, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;

        $qb = $this->db->table($this->table)
            ->where('domain_id', $domainId);

        // 필터 적용
        if (!empty($filters['member_id'])) {
            $qb->where('member_id', (int) $filters['member_id']);
        }
        if (!empty($filters['source_type'])) {
            $qb->where('source_type', $filters['source_type']);
        }
        if (!empty($filters['start_date'])) {
            $qb->where('created_at', '>=', $filters['start_date'] . ' 00:00:00');
        }
        if (!empty($filters['end_date'])) {
            $qb->where('created_at', '<=', $filters['end_date'] . ' 23:59:59');
        }

        // 전체 개수 조회 (Clone 필요)
        $countQb = clone $qb;
        $total = $countQb->count();

        // 목록 조회
        $rows = $qb->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $items = array_map(fn($row) => BalanceLog::fromArray($row), $rows);

        return [
            'items' => $items,
            'pagination' => [
                'currentPage' => $page,
                'perPage' => $perPage,
                'totalItems' => $total,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * 회원별 로그 목록 조회
     *
     * @param int $memberId 회원 ID
     * @param int $page 페이지 번호 (1-based)
     * @param int $perPage 페이지당 개수
     * @return BalanceLog[]
     */
    public function getByMember(int $memberId, int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;

        $qb = $this->db->table($this->table)
            ->where('member_id', $memberId);

        $this->applyDomainFilter($qb);

        $rows = $qb->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return array_map(fn($row) => BalanceLog::fromArray($row), $rows);
    }

    /**
     * 회원별 로그 수 조회
     */
    public function countByMember(int $memberId): int
    {
        $qb = $this->db->table($this->table)
            ->where('member_id', $memberId);

        $this->applyDomainFilter($qb);

        return $qb->count();
    }

    /**
     * 참조 타입/ID로 로그 조회
     *
     * @return BalanceLog[]
     */
    public function getByReference(string $referenceType, string $referenceId): array
    {
        $qb = $this->db->table($this->table)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);

        $this->applyDomainFilter($qb);

        $rows = $qb->get();

        return array_map(fn($row) => BalanceLog::fromArray($row), $rows);
    }

    /**
     * 회원의 최신 로그 조회
     */
    public function getLatestByMember(int $memberId): ?BalanceLog
    {
        $qb = $this->db->table($this->table)
            ->where('member_id', $memberId);

        $this->applyDomainFilter($qb);

        $row = $qb->orderBy('created_at', 'DESC')
            ->first();

        return $row ? BalanceLog::fromArray($row) : null;
    }

    // ========================================
    // READ (집계)
    // ========================================

    /**
     * 회원 원장 합계 조회 (무결성 검증용)
     *
     * Source of Truth = SUM(balance_logs.amount)
     *
     * @param int $memberId 회원 ID
     * @param int|null $domainId 명시적 도메인 ID (미지정 시 setDomainId 설정값 사용)
     */
    public function getSumByMember(int $memberId, ?int $domainId = null): int
    {
        $qb = $this->db->table($this->table)
            ->where('member_id', $memberId);

        if ($domainId !== null) {
            $qb->where('domain_id', $domainId);
        } else {
            $this->applyDomainFilter($qb);
        }

        return (int) $qb->sum('amount');
    }

    // ========================================
    // 헬퍼 메서드
    // ========================================

    /**
     * 도메인 필터 적용
     */
    protected function applyDomainFilter($qb): void
    {
        if ($this->domainId !== null) {
            $qb->where('domain_id', $this->domainId);
        }
    }
}
