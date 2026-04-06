<?php

namespace Mublo\Packages\Shop\Repository;

use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Packages\Shop\Entity\Order;
use Mublo\Repository\BaseRepository;

/**
 * Order Repository
 *
 * 주문 데이터베이스 접근 담당
 *
 * 책임:
 * - shop_orders 테이블 CRUD
 * - shop_order_details 테이블 관리
 * - Order Entity 반환
 *
 * 금지:
 * - 비즈니스 로직 (Service 담당)
 */
class OrderRepository extends BaseRepository
{
    protected string $table = 'shop_orders';
    protected string $entityClass = Order::class;
    protected string $primaryKey = 'order_no';

    private string $detailsTable = 'shop_order_details';
    private string $logsTable = 'shop_order_logs';
    private string $returnsTable = 'shop_returns';

    public function __construct(?Database $db = null)
    {
        $db = $db ?? DatabaseManager::getInstance()->connect();
        parent::__construct($db);
    }

    /**
     * 주문 목록 조회 (관리자용, 페이지네이션)
     *
     * @param int $domainId 도메인 ID
     * @param array $filters 검색 조건 (member_id, order_status, date_from, date_to, keyword)
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @return array ['items' => Order[], 'pagination' => [...]]
     */
    public function getList(int $domainId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId);

        // 회원 필터
        if (!empty($filters['member_id'])) {
            $query->where('member_id', '=', (int) $filters['member_id']);
        }

        // 주문 상태 필터
        if (!empty($filters['order_status'])) {
            $query->where('order_status', '=', $filters['order_status']);
        }

        // 기간 필터
        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to'] . ' 23:59:59');
        }

        // 키워드 검색 (Blind Index + 주문번호)
        if (!empty($filters['keyword'])) {
            $orderNoPattern = '%' . $filters['keyword'] . '%';

            if (!empty($filters['keyword_index'])) {
                // 암호화 환경: Blind Index로 이름/연락처 정확 검색
                $idx = $filters['keyword_index'];
                $query->whereRaw(
                    '(recipient_name_index = ? OR orderer_name_index = ? '
                    . 'OR recipient_phone_index = ? OR orderer_phone_index = ? '
                    . 'OR order_no LIKE ?)',
                    [$idx, $idx, $idx, $idx, $orderNoPattern]
                );
            } else {
                // Blind Index 미생성 시 주문번호만 검색
                $query->whereRaw('(order_no LIKE ?)', [$orderNoPattern]);
            }
        }

        // 전체 개수
        $total = $query->count();

        // 정렬 및 페이지네이션
        $offset = ($page - 1) * $perPage;
        $rows = $query
            ->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'items' => $this->toEntities($rows),
            'pagination' => [
                'totalItems' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    /**
     * 주문 상세 아이템 조회
     *
     * @param string $orderNo 주문번호
     * @return array 주문 상세 목록 (raw arrays)
     */
    public function getItems(string $orderNo): array
    {
        return $this->getDb()->table($this->detailsTable)
            ->where('order_no', '=', $orderNo)
            ->orderBy('order_detail_id', 'ASC')
            ->get();
    }

    /**
     * 주문 생성
     *
     * @param array $orderData 주문 데이터 (order_no 포함)
     * @return string 주문번호
     */
    public function createOrder(array $orderData): string
    {
        if (empty($orderData['order_no'])) {
            $orderData['order_no'] = $this->generateOrderNo();
        }

        if (!isset($orderData['created_at'])) {
            $orderData['created_at'] = date('Y-m-d H:i:s');
        }

        $this->getDb()->table($this->table)->insert($orderData);

        return $orderData['order_no'];
    }

    /**
     * 주문 상세 아이템 생성
     *
     * @param array $data 주문 상세 데이터
     * @return int 생성된 order_detail_id
     */
    public function createOrderItem(array $data): int
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $this->getDb()->table($this->detailsTable)->insert($data);
    }

    /**
     * 주문 상태 변경
     *
     * @param string $orderNo 주문번호
     * @param string $status 변경할 상태
     * @return bool 성공 여부
     */
    /**
     * 주문 상태 업데이트
     *
     * @param string $orderNo 주문번호
     * @param string $newStatus 변경할 상태
     * @param string|null $expectedStatus 현재 상태 검증 (null이면 무조건 업데이트)
     *   더블클릭/재요청 방어: DB 레벨에서 현재 상태가 기대값과 다르면 affected=0
     */
    public function updateStatus(string $orderNo, string $newStatus, ?string $expectedStatus = null): bool
    {
        $query = $this->getDb()->table($this->table)
            ->where('order_no', '=', $orderNo);

        if ($expectedStatus !== null) {
            $query->where('order_status', '=', $expectedStatus);
        }

        $affected = $query->update([
            'order_status' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $affected > 0;
    }

    /**
     * 결제 수단 업데이트 (결제 완료 후 PG 실제 수단으로 갱신)
     */
    public function updatePaymentMethod(string $orderNo, string $paymentMethod): void
    {
        $this->getDb()->table($this->table)
            ->where('order_no', '=', $orderNo)
            ->update([
                'payment_method' => $paymentMethod,
                'updated_at'     => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 회원별 주문 목록 조회 (페이지네이션)
     *
     * @param int $memberId 회원 ID
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @return array ['items' => Order[], 'pagination' => [...]]
     */
    public function getByMember(int $memberId, int $page = 1, int $perPage = 20): array
    {
        $query = $this->getDb()->table($this->table)
            ->where('member_id', '=', $memberId);

        // 전체 개수
        $total = $query->count();

        // 정렬 및 페이지네이션
        $offset = ($page - 1) * $perPage;
        $rows = $query
            ->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'items' => $this->toEntities($rows),
            'pagination' => [
                'totalItems' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    /**
     * 여러 주문의 상세 아이템 조회 (주문번호 기준 그룹핑)
     *
     * @param array $orderNos 주문번호 배열
     * @return array [order_no => items[]] 형태
     */
    public function getItemsByOrderNos(array $orderNos): array
    {
        if (empty($orderNos)) {
            return [];
        }

        $rows = $this->getDb()->table($this->detailsTable)
            ->whereIn('order_no', $orderNos)
            ->orderBy('order_detail_id', 'ASC')
            ->get();

        $grouped = [];
        foreach ($rows as $row) {
            $no = $row['order_no'] ?? '';
            $grouped[$no][] = $row;
        }

        return $grouped;
    }

    // ===== 주문 로그 =====

    /**
     * 주문 로그 기록
     */
    public function insertOrderLog(array $data): int
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $this->getDb()->table($this->logsTable)->insert($data);
    }

    /**
     * 주문 로그 조회 (최신순)
     */
    public function getOrderLogs(string $orderNo): array
    {
        return $this->getDb()->table($this->logsTable)
            ->where('order_no', '=', $orderNo)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    // ===== 아이템 관리 =====

    /**
     * 주문 상세 아이템 단건 조회
     */
    public function getItem(int $detailId): ?array
    {
        $rows = $this->getDb()->table($this->detailsTable)
            ->where('order_detail_id', '=', $detailId)
            ->limit(1)
            ->get();

        return $rows[0] ?? null;
    }

    /**
     * 아이템 상태 변경
     */
    public function updateItemStatus(int $detailId, string $status): bool
    {
        $affected = $this->getDb()->table($this->detailsTable)
            ->where('order_detail_id', '=', $detailId)
            ->update([
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * 아이템 반품 정보 변경
     */
    public function updateItemReturn(int $detailId, string $returnType, string $returnStatus): bool
    {
        $affected = $this->getDb()->table($this->detailsTable)
            ->where('order_detail_id', '=', $detailId)
            ->update([
                'return_type' => $returnType,
                'return_status' => $returnStatus,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $affected > 0;
    }

    /**
     * 아이템 플래그 업데이트 (is_paid, is_preparing, is_shipped, is_completed)
     */
    public function updateItemFlags(int $detailId, array $flags): bool
    {
        $allowed = ['is_paid', 'is_preparing', 'is_shipped', 'is_completed', 'stock_deducted'];
        $data = array_intersect_key($flags, array_flip($allowed));
        if (empty($data)) {
            return false;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        $affected = $this->getDb()->table($this->detailsTable)
            ->where('order_detail_id', '=', $detailId)
            ->update($data);

        return $affected > 0;
    }

    // ===== 반품 관리 (shop_returns) =====

    /**
     * 반품 레코드 생성
     */
    public function createReturn(array $data): int
    {
        if (!isset($data['created_at'])) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        return $this->getDb()->table($this->returnsTable)->insert($data);
    }

    /**
     * 반품 단건 조회 (order_detail_id 기준, 최신)
     */
    public function getReturnByDetailId(int $detailId): ?array
    {
        $rows = $this->getDb()->table($this->returnsTable)
            ->where('order_detail_id', '=', $detailId)
            ->orderBy('created_at', 'DESC')
            ->limit(1)
            ->get();

        return $rows[0] ?? null;
    }

    /**
     * 주문의 반품 목록 조회
     */
    public function getReturnsByOrderNo(string $orderNo): array
    {
        return $this->getDb()->table($this->returnsTable)
            ->where('order_no', '=', $orderNo)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * 반품 상태/정보 업데이트
     */
    public function updateReturn(int $returnId, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        $affected = $this->getDb()->table($this->returnsTable)
            ->where('return_id', '=', $returnId)
            ->update($data);

        return $affected > 0;
    }

    /**
     * 주문번호 생성
     *
     * YYYYMMDD + 6자리 랜덤 숫자
     *
     * @return string 주문번호 (예: 20260207123456)
     */
    public function generateOrderNo(): string
    {
        return date('Ymd') . str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 생성 타임스탬프 필드명
     *
     * BaseRepository의 create()에서 자동 추가하지 않도록 null 반환
     * (createOrder에서 직접 관리)
     */
    protected function getCreatedAtField(): ?string
    {
        return 'created_at';
    }

    /**
     * 회원의 완료된 주문 존재 여부 확인 (쿠폰 첫 주문 검증용)
     */
    public function hasCompletedOrder(int $memberId): bool
    {
        return $this->getDb()->table($this->table)
            ->where('member_id', '=', $memberId)
            ->where('is_paid', '=', 1)
            ->count() > 0;
    }

    // ── 대시보드 통계 ────────────────────────────────────────────────────────

    /**
     * 기간 내 매출 합계 (is_paid = 1 기준)
     */
    public function sumRevenue(int $domainId, string $startDate, string $endDate): int
    {
        $row = $this->getDb()->selectOne(
            "SELECT COALESCE(SUM(final_price), 0) AS total
             FROM {$this->table}
             WHERE domain_id = ? AND is_paid = 1
               AND DATE(created_at) BETWEEN ? AND ?",
            [$domainId, $startDate, $endDate]
        );
        return (int) ($row['total'] ?? 0);
    }

    /**
     * 기간 내 주문 수 (전체 주문 기준)
     */
    public function countByDateRange(int $domainId, string $startDate, string $endDate): int
    {
        $row = $this->getDb()->selectOne(
            "SELECT COUNT(*) AS cnt
             FROM {$this->table}
             WHERE domain_id = ? AND DATE(created_at) BETWEEN ? AND ?",
            [$domainId, $startDate, $endDate]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * 지정 상태들의 주문 수 합계
     */
    public function countByStatuses(int $domainId, array $statuses): int
    {
        if (empty($statuses)) {
            return 0;
        }
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $row = $this->getDb()->selectOne(
            "SELECT COUNT(*) AS cnt
             FROM {$this->table}
             WHERE domain_id = ? AND order_status IN ({$placeholders})",
            [$domainId, ...$statuses]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * 상태별 주문 수 (대시보드 현황판용)
     *
     * @return array [['order_status' => ..., 'cnt' => ...], ...]
     */
    public function countGroupByStatus(int $domainId): array
    {
        return $this->getDb()->select(
            "SELECT order_status, COUNT(*) AS cnt
             FROM {$this->table}
             WHERE domain_id = ?
             GROUP BY order_status",
            [$domainId]
        );
    }

    /**
     * 최근 주문 목록 (복호화되지 않은 원본 rows)
     */
    public function getRecentOrders(int $domainId, int $limit = 10): array
    {
        return $this->getDb()->select(
            "SELECT order_no, member_id, orderer_name, final_price, order_status, created_at
             FROM {$this->table}
             WHERE domain_id = ?
             ORDER BY created_at DESC
             LIMIT ?",
            [$domainId, $limit]
        );
    }

    /**
     * 기간 내 판매 상위 상품
     *
     * @return array [['goods_id' => ..., 'goods_name' => ..., 'total_qty' => ..., 'total_revenue' => ...], ...]
     */
    public function getTopProducts(int $domainId, string $startDate, string $endDate, int $limit = 5): array
    {
        return $this->getDb()->select(
            "SELECT d.goods_id, d.goods_name,
                    SUM(d.quantity) AS total_qty,
                    SUM(d.subtotal_price) AS total_revenue
             FROM shop_order_details d
             INNER JOIN {$this->table} o ON o.order_no = d.order_no
             WHERE o.domain_id = ? AND o.is_paid = 1
               AND DATE(o.created_at) BETWEEN ? AND ?
             GROUP BY d.goods_id, d.goods_name
             ORDER BY total_revenue DESC
             LIMIT ?",
            [$domainId, $startDate, $endDate, $limit]
        );
    }
}
