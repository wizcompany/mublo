<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Repository\CartRepository;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Repository\ProductOptionRepository;
use Mublo\Packages\Shop\Service\PriceCalculator;
use Mublo\Packages\Shop\Enum\OrderAction;
use Mublo\Packages\Shop\Event\OrderStatusChangedEvent;
use Mublo\Packages\Shop\Service\OrderStateResolver;
use Mublo\Service\Member\FieldEncryptionService;

/**
 * OrderService
 *
 * 주문 비즈니스 로직 + 이벤트 발행
 *
 * 책임:
 * - 주문 생성 (주문번호 생성, 주문+주문상세 저장, 장바구니 상태 변경)
 * - 주문 조회 (단일, 목록, 회원별)
 * - 주문 상태 전이 관리
 *
 * 금지:
 * - Request/Response 직접 처리 (Controller 담당)
 * - DB 직접 접근 (Repository 담당)
 */
class OrderService
{
    private OrderRepository $orderRepository;
    private CartRepository $cartRepository;
    private ProductRepository $productRepository;
    private ProductOptionRepository $productOptionRepository;
    private PriceCalculator $priceCalculator;
    private OrderStateResolver $stateResolver;
    private ?EventDispatcher $eventDispatcher;
    private ?FieldEncryptionService $encryptionService;

    /** 암호화 대상 필드 (주문자 + 수령인 + 배송지) */
    private const ENCRYPTED_FIELDS = [
        'orderer_name',
        'orderer_phone',
        'orderer_email',
        'recipient_name',
        'recipient_phone',
        'shipping_zip',
        'shipping_address1',
        'shipping_address2',
    ];

    /** Blind Index 매핑 (검색 가능 필드 → 인덱스 컬럼명) */
    private const INDEXED_FIELDS = [
        'orderer_name'   => 'orderer_name_index',
        'orderer_phone'  => 'orderer_phone_index',
        'recipient_name' => 'recipient_name_index',
        'recipient_phone' => 'recipient_phone_index',
    ];

    public function __construct(
        OrderRepository $orderRepository,
        CartRepository $cartRepository,
        ProductRepository $productRepository,
        ProductOptionRepository $productOptionRepository,
        PriceCalculator $priceCalculator,
        OrderStateResolver $stateResolver,
        ?EventDispatcher $eventDispatcher = null,
        ?FieldEncryptionService $encryptionService = null
    ) {
        $this->orderRepository = $orderRepository;
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->productOptionRepository = $productOptionRepository;
        $this->priceCalculator = $priceCalculator;
        $this->stateResolver = $stateResolver;
        $this->eventDispatcher = $eventDispatcher;
        $this->encryptionService = $encryptionService;
    }

    /**
     * 이벤트 발행 헬퍼
     */
    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }

    /**
     * 주문 생성
     *
     * 주문번호 생성 -> 주문 레코드 생성 -> 주문 상세 아이템 생성 -> 장바구니 상태 변경
     *
     * @param int $domainId 도메인 ID
     * @param int $memberId 회원 ID
     * @param array $orderData 주문 데이터 (배송지, 결제수단, 메모 등)
     * @param array $items 주문 상품 목록 (cart_item_id 기반 또는 상품 데이터)
     * @return Result 성공 시 order_no 포함
     */
    public function createOrder(int $domainId, int $memberId, array $orderData, array $items): Result
    {
        if (empty($items)) {
            return Result::failure('주문할 상품이 없습니다.');
        }

        // 주문번호 생성
        $orderNo = $this->orderRepository->generateOrderNo();

        // 주문 아이템 검증 및 금액 계산
        $orderItems = [];
        $totalPrice = 0;

        foreach ($items as $item) {
            $itemResult = $this->validateAndBuildOrderItem($item, $orderNo);
            if ($itemResult->isFailure()) {
                return $itemResult;
            }
            $orderItems[] = $itemResult->get('order_item');
            $totalPrice += $itemResult->get('item_total', 0);
        }

        // 배송비 (주문 데이터에 포함된 경우 사용, 아니면 기본값)
        $shippingFee = (int) ($orderData['shipping_fee'] ?? 0);

        // 주문 데이터 구성
        $orderRecord = [
            'order_no' => $orderNo,
            'domain_id' => $domainId,
            'cart_session_id' => $orderData['cart_session_id'] ?? null,
            'member_id' => $memberId,
            'orderer_name' => $orderData['orderer_name'] ?? $orderData['recipient_name'] ?? '',
            'orderer_phone' => $orderData['orderer_phone'] ?? $orderData['recipient_phone'] ?? '',
            'orderer_email' => $orderData['orderer_email'] ?? null,
            'total_price' => $totalPrice,
            'extra_price' => (int) ($orderData['extra_price'] ?? 0),
            'point_used' => (int) ($orderData['point_used'] ?? 0),
            'coupon_discount' => (int) ($orderData['coupon_discount'] ?? 0),
            'coupon_id' => $orderData['coupon_id'] ?? null,
            'shipping_fee' => $shippingFee,
            'tax_amount' => (int) ($orderData['tax_amount'] ?? 0),
            'shipping_zip' => $orderData['shipping_zip'] ?? null,
            'shipping_address1' => $orderData['shipping_address1'] ?? null,
            'shipping_address2' => $orderData['shipping_address2'] ?? null,
            'recipient_name' => $orderData['recipient_name'] ?? null,
            'recipient_phone' => $orderData['recipient_phone'] ?? null,
            'payment_gateway' => $orderData['payment_gateway'] ?? null,
            'payment_method' => $orderData['payment_method'] ?? 'BANK',
            'order_status' => OrderAction::RECEIPT->value,
            'order_memo' => $orderData['order_memo'] ?? null,
            'is_direct_order' => (int) (bool) ($orderData['is_direct_order'] ?? false),
            'campaign_key' => isset($orderData['campaign_key']) ? mb_substr($orderData['campaign_key'], 0, 100) : null,
        ];

        // 개인정보 암호화 (주문자/수령인/배송지)
        $this->encryptOrderFields($orderRecord);

        // DB 트랜잭션: 주문 + 주문상세 원자적 생성
        $db = $this->orderRepository->getDb();
        $db->beginTransaction();
        try {
            $createdOrderNo = $this->orderRepository->createOrder($orderRecord);
            if (!$createdOrderNo) {
                $db->rollBack();
                return Result::failure('주문 생성에 실패했습니다.');
            }

            // 주문 상세 아이템 생성
            foreach ($orderItems as $orderItem) {
                $this->orderRepository->createOrderItem($orderItem);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            return Result::failure('주문 생성 중 오류가 발생했습니다.');
        }

        // 장바구니 상태 변경은 결제 확인(verify) 이후에 수행
        // → CartController::verify() 에서 markOrdered() 호출

        // 결제 금액 계산 (PG에 전달할 실제 청구 금액)
        $paymentAmount = $this->priceCalculator->calculatePaymentAmount($orderRecord);

        return Result::success('주문이 접수되었습니다.', [
            'order_no' => $orderNo,
            'cart_session_id' => $orderData['cart_session_id'] ?? null,
            'payment_amount' => $paymentAmount,
        ]);
    }

    /**
     * 주문 단건 조회
     *
     * 주문 기본 정보 + 주문 상세 아이템 함께 반환
     *
     * @param string $orderNo 주문번호
     * @return Result 성공 시 order, items 포함
     */
    public function getOrder(string $orderNo): Result
    {
        $order = $this->orderRepository->find($orderNo);
        if (!$order) {
            return Result::failure('주문을 찾을 수 없습니다.');
        }

        $items = $this->orderRepository->getItems($orderNo);

        // 개인정보 복호화
        $orderArray = $order->toArray();
        $this->decryptOrderFields($orderArray);

        return Result::success('', [
            'order' => $orderArray,
            'items' => $items,
        ]);
    }

    /**
     * 주문 목록 조회 (관리자용)
     *
     * 도메인별 주문 목록을 필터 조건과 페이지네이션으로 조회
     *
     * @param int $domainId 도메인 ID
     * @param array $filters 검색 조건 (member_id, order_status, date_from, date_to, keyword)
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @return Result 성공 시 items, pagination 포함
     */
    public function getOrderList(int $domainId, array $filters, int $page, int $perPage = 20): Result
    {
        // 검색 키워드가 있으면 Blind Index 생성
        $filters = $this->prepareSearchFilters($filters);

        $result = $this->orderRepository->getList($domainId, $filters, $page, $perPage);

        return Result::success('', [
            'items' => array_map(fn($order) => $this->decryptOrderArray($order->toArray()), $result['items']),
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * 회원별 주문 목록 조회
     *
     * @param int $memberId 회원 ID
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @return Result 성공 시 items, pagination 포함
     */
    public function getMemberOrders(int $memberId, int $page, int $perPage = 10): Result
    {
        $result = $this->orderRepository->getByMember($memberId, $page, $perPage);

        // 주문별 아이템 조회 (목록에서 대표 상품 표시용)
        $orderNos = array_map(fn($order) => $order->getOrderNo(), $result['items']);
        $itemsMap = $this->orderRepository->getItemsByOrderNos($orderNos);

        $orders = array_map(function ($order) use ($itemsMap) {
            $arr = $this->decryptOrderArray($order->toArray());
            $arr['items'] = $itemsMap[$order->getOrderNo()] ?? [];
            return $arr;
        }, $result['items']);

        return Result::success('', [
            'items' => $orders,
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * 주문 상태 변경 (FSM 기반)
     *
     * OrderStateResolver를 통해 전이 규칙을 검증하고,
     * 로그 기록 + 이벤트 발행을 수행한다.
     *
     * @param string $orderNo 주문번호
     * @param string $newStateId 변경할 상태 id (FSM state id)
     * @param int $domainId 도메인 ID
     * @param string $reason 변경 사유
     * @param string $changedBy 변경 주체 (SYSTEM|STAFF|CUSTOMER)
     * @return Result
     */
    public function updateStatus(
        string $orderNo,
        string $newStateId,
        int $domainId,
        string $reason = '',
        string $changedBy = 'STAFF'
    ): Result {
        $order = $this->orderRepository->find($orderNo);
        if (!$order) {
            return Result::failure('주문을 찾을 수 없습니다.');
        }

        $currentId = $order->getOrderStatusRaw() ?? '';

        // FSM 전이 검증
        if (!$this->stateResolver->canTransition($domainId, $currentId, $newStateId)) {
            $currentLabel = $this->stateResolver->getLabel($domainId, $currentId);
            $newLabel = $this->stateResolver->getLabel($domainId, $newStateId);
            return Result::failure("'{$currentLabel}'에서 '{$newLabel}'(으)로 변경할 수 없습니다.");
        }

        // DB 업데이트 (조건부: 현재 상태가 기대값과 일치해야 변경)
        $updated = $this->orderRepository->updateStatus($orderNo, $newStateId, $currentId);
        if (!$updated) {
            return Result::failure('주문 상태 변경에 실패했습니다.');
        }

        // 라벨 스냅샷 + 로그 기록
        $prevLabel = $this->stateResolver->getLabel($domainId, $currentId);
        $newLabel = $this->stateResolver->getLabel($domainId, $newStateId);

        $this->orderRepository->insertOrderLog([
            'order_no' => $orderNo,
            'prev_status' => $currentId,
            'prev_status_label' => $prevLabel,
            'new_status' => $newStateId,
            'new_status_label' => $newLabel,
            'change_type' => 'STATUS',
            'changed_by' => $changedBy,
            'reason' => $reason ?: null,
        ]);

        // 이벤트 발행
        $this->dispatch(new OrderStatusChangedEvent(
            $orderNo,
            $currentId,
            $newStateId,
            $prevLabel,
            $newLabel,
            OrderAction::tryFrom($currentId),
            OrderAction::tryFrom($newStateId),
            $order->toArray()
        ));

        return Result::success('주문 상태가 변경되었습니다.', [
            'order_no' => $orderNo,
            'status' => $newStateId,
            'status_label' => $newLabel,
        ]);
    }

    /**
     * 주문 로그 조회
     */
    public function getOrderLogs(string $orderNo): array
    {
        return $this->orderRepository->getOrderLogs($orderNo);
    }

    /**
     * 주문 반품 목록 조회
     */
    public function getOrderReturns(string $orderNo): array
    {
        return $this->orderRepository->getReturnsByOrderNo($orderNo);
    }

    // ===== 아이템 관리 =====

    /**
     * 아이템 상태 변경 (관리자)
     *
     * FSM 전이 검증 → 플래그 동기화 → 로그 기록 → 주문 자동 전이
     */
    public function updateItemStatus(
        string $orderNo,
        int $detailId,
        string $newStateId,
        int $domainId,
        string $reason = ''
    ): Result {
        $item = $this->orderRepository->getItem($detailId);
        if (!$item || ($item['order_no'] ?? '') !== $orderNo) {
            return Result::failure('주문 상품을 찾을 수 없습니다.');
        }

        $currentId = $item['status'] ?? '';

        // FSM 전이 검증
        if ($currentId !== '' && !$this->stateResolver->canTransition($domainId, $currentId, $newStateId)) {
            $currentLabel = $this->stateResolver->getLabel($domainId, $currentId);
            $newLabel = $this->stateResolver->getLabel($domainId, $newStateId);
            return Result::failure("'{$currentLabel}'에서 '{$newLabel}'(으)로 변경할 수 없습니다.");
        }

        $this->orderRepository->updateItemStatus($detailId, $newStateId);
        $this->syncItemFlags($detailId, $newStateId, $domainId);

        // 로그 기록
        $prevLabel = $this->stateResolver->getLabel($domainId, $currentId);
        $newLabel = $this->stateResolver->getLabel($domainId, $newStateId);

        $this->orderRepository->insertOrderLog([
            'order_no' => $orderNo,
            'order_detail_id' => $detailId,
            'prev_status' => $currentId,
            'prev_status_label' => $prevLabel,
            'new_status' => $newStateId,
            'new_status_label' => $newLabel,
            'change_type' => 'STATUS',
            'changed_by' => 'STAFF',
            'reason' => $reason ?: null,
        ]);

        // 모든 아이템 동일 상태 시 주문 자동 전이
        $this->autoSyncOrderStatus($orderNo, $domainId);

        return Result::success('상품 상태가 변경되었습니다.', [
            'detail_id' => $detailId,
            'status' => $newStateId,
            'status_label' => $newLabel,
        ]);
    }

    /**
     * 아이템 취소
     *
     * receipt/paid 상태에서만 가능. shop_returns + status/return 컬럼 업데이트.
     */
    public function cancelOrderItem(string $orderNo, int $detailId, string $reason, int $domainId): Result
    {
        $item = $this->orderRepository->getItem($detailId);
        if (!$item || ($item['order_no'] ?? '') !== $orderNo) {
            return Result::failure('주문 상품을 찾을 수 없습니다.');
        }

        $currentStatus = $item['status'] ?? '';
        $action = OrderAction::tryFrom($currentStatus);
        if ($action && !$action->isCancellable()) {
            return Result::failure('현재 상태에서는 취소할 수 없습니다.');
        }

        // shop_returns 레코드 생성
        $this->orderRepository->createReturn([
            'order_no' => $orderNo,
            'order_detail_id' => $detailId,
            'member_id' => 0,
            'return_type' => 'CANCEL',
            'return_status' => 'COMPLETED',
            'reason_type' => 'OTHER',
            'reason_detail' => $reason,
            'quantity' => (int) ($item['quantity'] ?? 1),
            'refund_amount' => (int) ($item['total_price'] ?? 0),
        ]);

        // 아이템 상태 업데이트
        $this->orderRepository->updateItemStatus($detailId, OrderAction::CANCELLED->value);
        $this->orderRepository->updateItemReturn($detailId, 'CANCEL', 'COMPLETED');

        // 로그 기록
        $prevLabel = $this->stateResolver->getLabel($domainId, $currentStatus);
        $this->orderRepository->insertOrderLog([
            'order_no' => $orderNo,
            'order_detail_id' => $detailId,
            'prev_status' => $currentStatus,
            'prev_status_label' => $prevLabel,
            'new_status' => OrderAction::CANCELLED->value,
            'new_status_label' => '취소완료',
            'change_type' => 'STATUS',
            'changed_by' => 'STAFF',
            'reason' => $reason ?: null,
        ]);

        // 모든 아이템 취소 시 주문도 취소
        $this->autoSyncOrderStatus($orderNo, $domainId);

        return Result::success('상품이 취소되었습니다.', [
            'detail_id' => $detailId,
            'refund_amount' => (int) ($item['total_price'] ?? 0),
        ]);
    }

    /**
     * 반품/교환 요청 접수
     */
    public function requestItemReturn(
        string $orderNo,
        int $detailId,
        string $returnType,
        string $reasonType,
        string $reasonDetail,
        int $domainId
    ): Result {
        $item = $this->orderRepository->getItem($detailId);
        if (!$item || ($item['order_no'] ?? '') !== $orderNo) {
            return Result::failure('주문 상품을 찾을 수 없습니다.');
        }

        // 반품 가능 상태 확인
        $action = OrderAction::tryFrom($item['status'] ?? '');
        if (!$action || !$action->isShipped()) {
            return Result::failure('배송 완료 후에만 반품/교환을 요청할 수 있습니다.');
        }

        if (($item['return_type'] ?? 'NONE') !== 'NONE') {
            return Result::failure('이미 반품/교환이 진행 중입니다.');
        }

        if (!in_array($returnType, ['RETURN', 'EXCHANGE'], true)) {
            return Result::failure('유효하지 않은 반품 유형입니다.');
        }

        // shop_returns 레코드 생성
        $this->orderRepository->createReturn([
            'order_no' => $orderNo,
            'order_detail_id' => $detailId,
            'member_id' => 0,
            'return_type' => $returnType,
            'return_status' => 'REQUESTED',
            'reason_type' => $reasonType,
            'reason_detail' => $reasonDetail,
            'quantity' => (int) ($item['quantity'] ?? 1),
        ]);

        // 아이템 상태 업데이트
        $this->orderRepository->updateItemStatus($detailId, OrderAction::RETURN_REQUESTED->value);
        $this->orderRepository->updateItemReturn($detailId, $returnType, 'REQUESTED');

        // 로그 기록
        $prevLabel = $this->stateResolver->getLabel($domainId, $item['status'] ?? '');
        $this->orderRepository->insertOrderLog([
            'order_no' => $orderNo,
            'order_detail_id' => $detailId,
            'prev_status' => $item['status'] ?? '',
            'prev_status_label' => $prevLabel,
            'new_status' => OrderAction::RETURN_REQUESTED->value,
            'new_status_label' => '반품요청',
            'change_type' => 'RETURN',
            'changed_by' => 'STAFF',
            'reason' => $reasonDetail ?: null,
        ]);

        $typeLabel = $returnType === 'RETURN' ? '반품' : '교환';
        return Result::success("{$typeLabel} 요청이 접수되었습니다.", ['detail_id' => $detailId]);
    }

    /**
     * 반품 승인/거절
     */
    public function processItemReturn(
        string $orderNo,
        int $detailId,
        bool $accept,
        string $reason,
        int $domainId
    ): Result {
        $item = $this->orderRepository->getItem($detailId);
        if (!$item || ($item['order_no'] ?? '') !== $orderNo) {
            return Result::failure('주문 상품을 찾을 수 없습니다.');
        }

        if (($item['return_status'] ?? '') !== 'REQUESTED') {
            return Result::failure('반품 요청 상태가 아닙니다.');
        }

        $returnRecord = $this->orderRepository->getReturnByDetailId($detailId);

        if ($accept) {
            // 승인: return_status=COMPLETED, status=returned
            $refundAmount = (int) ($item['total_price'] ?? 0);

            if ($returnRecord) {
                $this->orderRepository->updateReturn($returnRecord['return_id'], [
                    'return_status' => 'COMPLETED',
                    'refund_amount' => $refundAmount,
                    'staff_memo' => $reason ?: null,
                    'completed_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $this->orderRepository->updateItemStatus($detailId, OrderAction::RETURNED->value);
            $this->orderRepository->updateItemReturn($detailId, $item['return_type'] ?? 'RETURN', 'COMPLETED');

            $this->orderRepository->insertOrderLog([
                'order_no' => $orderNo,
                'order_detail_id' => $detailId,
                'prev_status' => $item['status'] ?? '',
                'prev_status_label' => '반품요청',
                'new_status' => OrderAction::RETURNED->value,
                'new_status_label' => '반품완료',
                'change_type' => 'RETURN',
                'changed_by' => 'STAFF',
                'reason' => $reason ?: null,
            ]);

            $this->autoSyncOrderStatus($orderNo, $domainId);

            return Result::success('반품이 승인되었습니다.', [
                'detail_id' => $detailId,
                'refund_amount' => $refundAmount,
            ]);
        } else {
            // 거절: return_status=REFUSED, 상태 원복
            if ($returnRecord) {
                $this->orderRepository->updateReturn($returnRecord['return_id'], [
                    'return_status' => 'REFUSED',
                    'refused_reason' => $reason,
                ]);
            }

            // 이전 상태 복원 (로그에서 반품 요청 직전 상태 조회)
            $logs = $this->orderRepository->getOrderLogs($orderNo);
            $prevStatus = '';
            foreach ($logs as $log) {
                if ((int) ($log['order_detail_id'] ?? 0) === $detailId
                    && ($log['new_status'] ?? '') === OrderAction::RETURN_REQUESTED->value
                ) {
                    $prevStatus = $log['prev_status'] ?? '';
                    break;
                }
            }
            $restoreStatus = $prevStatus ?: OrderAction::DELIVERED->value;

            $this->orderRepository->updateItemStatus($detailId, $restoreStatus);
            $this->orderRepository->updateItemReturn($detailId, 'NONE', 'NONE');

            $this->orderRepository->insertOrderLog([
                'order_no' => $orderNo,
                'order_detail_id' => $detailId,
                'prev_status' => OrderAction::RETURN_REQUESTED->value,
                'prev_status_label' => '반품요청',
                'new_status' => $restoreStatus,
                'new_status_label' => $this->stateResolver->getLabel($domainId, $restoreStatus),
                'change_type' => 'RETURN',
                'changed_by' => 'STAFF',
                'reason' => '반품 거절: ' . $reason,
            ]);

            return Result::success('반품이 거절되었습니다.', ['detail_id' => $detailId]);
        }
    }

    // ===== 아이템 관리 헬퍼 =====

    /**
     * FSM action 기반 플래그 동기화
     */
    private function syncItemFlags(int $detailId, string $stateId, int $domainId): void
    {
        $stateDef = $this->stateResolver->getState($domainId, $stateId);
        $action = $stateDef ? $this->stateResolver->getAction($stateId, $stateDef) : null;

        $flags = [
            'is_paid' => (int) ($action && in_array($action, [
                OrderAction::PAID, OrderAction::PREPARING,
                OrderAction::SHIPPING, OrderAction::DELIVERED, OrderAction::CONFIRMED,
            ], true)),
            'is_preparing' => (int) ($action === OrderAction::PREPARING),
            'is_shipped' => (int) ($action && in_array($action, [
                OrderAction::SHIPPING, OrderAction::DELIVERED,
            ], true)),
            'is_completed' => (int) ($action && in_array($action, [
                OrderAction::DELIVERED, OrderAction::CONFIRMED,
            ], true)),
        ];

        $this->orderRepository->updateItemFlags($detailId, $flags);
    }

    /**
     * 모든 아이템 동일 상태 시 주문 자동 전이
     */
    private function autoSyncOrderStatus(string $orderNo, int $domainId): void
    {
        $items = $this->orderRepository->getItems($orderNo);
        if (empty($items)) {
            return;
        }

        $statuses = array_unique(array_column($items, 'status'));
        if (count($statuses) !== 1) {
            return;
        }

        $unanimousState = $statuses[0];
        $order = $this->orderRepository->find($orderNo);
        if (!$order || $order->getOrderStatusRaw() === $unanimousState) {
            return;
        }

        // 자동 전이 (아이템 상태 기반이므로 FSM 검증 스킵)
        $this->orderRepository->updateStatus($orderNo, $unanimousState);

        $prevLabel = $this->stateResolver->getLabel($domainId, $order->getOrderStatusRaw() ?? '');
        $newLabel = $this->stateResolver->getLabel($domainId, $unanimousState);

        $this->orderRepository->insertOrderLog([
            'order_no' => $orderNo,
            'prev_status' => $order->getOrderStatusRaw() ?? '',
            'prev_status_label' => $prevLabel,
            'new_status' => $unanimousState,
            'new_status_label' => $newLabel,
            'change_type' => 'STATUS',
            'changed_by' => 'SYSTEM',
            'reason' => '전 상품 동일 상태 자동 전이',
        ]);
    }

    // ===== 주문 아이템 검증/빌드 =====

    /**
     * 단일 주문 아이템 검증 및 DB 행 빌드
     *
     * 상품 존재/활성/재고 검증 → 옵션 검증 → 금액 계산 → 주문 아이템 배열 반환
     *
     * @param array $item 프론트에서 전달된 아이템 데이터
     * @param string $orderNo 주문번호
     * @return Result 성공 시 order_item, item_total 포함
     */
    private function validateAndBuildOrderItem(array $item, string $orderNo): Result
    {
        $goodsId = (int) ($item['goods_id'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 1);

        // 상품 유효성 검증
        $product = $this->productRepository->find($goodsId);
        if (!$product) {
            return Result::failure('존재하지 않는 상품이 포함되어 있습니다. (goods_id: ' . $goodsId . ')');
        }

        if (!$product->isActive()) {
            return Result::failure('판매 중지된 상품이 포함되어 있습니다: ' . $product->getGoodsName());
        }

        if ($product->getStockQuantity() !== null && $product->getStockQuantity() < $quantity) {
            return Result::failure(
                '재고가 부족한 상품이 있습니다: ' . $product->getGoodsName()
                . ' (재고: ' . $product->getStockQuantity() . '개)'
            );
        }

        // 옵션 가격: DB에서 실제 가격을 조회하여 검증
        $clientOptionPrice = (int) ($item['option_price'] ?? 0);
        $optionId = (int) ($item['option_id'] ?? 0);
        $optionMode = $item['option_mode'] ?? 'NONE';
        $optionCode = $item['option_code'] ?? null;
        $optionPrice = 0;

        if ($optionMode === 'COMBINATION' && $optionId > 0) {
            // 조합형: combo_id로 조합 레코드 조회
            $combo = $this->productOptionRepository->findCombo($optionId);
            if (!$combo || (int) ($combo['goods_id'] ?? 0) !== $goodsId) {
                return Result::failure('존재하지 않는 옵션 조합이 포함되어 있습니다.');
            }
            $optionPrice = (int) ($combo['extra_price'] ?? 0);
        } elseif ($optionMode === 'SINGLE' && $optionId > 0 && $optionCode) {
            // 단독형: option_code(opt-{optionId}-{valueId})에서 value_id 추출
            if (preg_match('/^opt-\d+-(\d+)$/', $optionCode, $m)) {
                $valueId = (int) $m[1];
                $value = $this->productOptionRepository->findValue($valueId);
                if (!$value || (int) ($value['option_id'] ?? 0) !== $optionId) {
                    return Result::failure('존재하지 않는 옵션 값이 포함되어 있습니다.');
                }
                $optionPrice = (int) ($value['extra_price'] ?? 0);
            } else {
                return Result::failure('잘못된 옵션 코드 형식입니다.');
            }
        } elseif ($optionId > 0) {
            // 기타 옵션 모드에서 option_id가 있는 경우 존재 확인
            $option = $this->productOptionRepository->find($optionId);
            if (!$option) {
                return Result::failure('존재하지 않는 옵션이 포함되어 있습니다.');
            }
        }

        // 클라이언트 전달 가격과 서버 조회 가격 불일치 검증
        if ($clientOptionPrice !== $optionPrice) {
            return Result::failure(
                '옵션 가격 정보가 변경되었습니다. 새로고침 후 다시 주문해 주세요. (상품: ' . $product->getGoodsName() . ')'
            );
        }

        // 할인 적용 판매가 계산
        $priceResult = $this->priceCalculator->calculateSalesPrice(
            $product->getDisplayPrice(),
            $product->getDiscountType(),
            $product->getDiscountValue()
        );
        $goodsPrice = $priceResult['sales_price'];
        $itemTotal = ($goodsPrice + $optionPrice) * $quantity;

        // 적립 포인트 계산
        $rewardResult = $this->priceCalculator->calculateRewardPoints(
            $goodsPrice,
            $product->getRewardType(),
            $product->getRewardValue()
        );

        return Result::success('', [
            'order_item' => [
                'order_no' => $orderNo,
                'goods_id' => $goodsId,
                'goods_name' => $product->getGoodsName(),
                'goods_image' => $item['product_image']['image_url'] ?? null,
                'option_mode' => $item['option_mode'] ?? 'NONE',
                'option_id' => $optionId,
                'option_code' => $item['option_code'] ?? null,
                'option_name' => $item['option_label'] ?? null,
                'option_type' => $item['option_type'] ?? 'BASIC',
                'goods_price' => $goodsPrice,
                'option_price' => $optionPrice,
                'support_price' => (int) ($item['support_price'] ?? 0),
                'total_price' => $itemTotal,
                'quantity' => $quantity,
                'point_amount' => $rewardResult['point_amount'] * $quantity,
                'coupon_discount' => 0,
                'coupon_id' => null,
                'status' => OrderAction::RECEIPT->value,
            ],
            'item_total' => $itemTotal,
        ]);
    }

    // ===== 개인정보 암호화 (AES-256-GCM) =====

    /**
     * 주문 레코드 암호화 (저장 전)
     *
     * 주문자/수령인/배송지 필드를 AES-256-GCM으로 암호화하고
     * 검색 가능 필드에 Blind Index를 생성한다.
     */
    private function encryptOrderFields(array &$data): void
    {
        if (!$this->encryptionService) {
            return;
        }

        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (!empty($data[$field])) {
                $plainValue = $data[$field];
                $data[$field] = $this->encryptionService->encrypt($plainValue);

                // Blind Index 생성 (검색용)
                if (isset(self::INDEXED_FIELDS[$field])) {
                    $data[self::INDEXED_FIELDS[$field]] = $this->encryptionService->createSearchIndex($plainValue);
                }
            }
        }
    }

    /**
     * 주문 데이터 복호화 (조회 후)
     *
     * 복호화 실패 시 원본 값 유지 (기존 평문 데이터 하위 호환)
     */
    private function decryptOrderFields(array &$data): void
    {
        if (!$this->encryptionService) {
            return;
        }

        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (!empty($data[$field])) {
                $decrypted = $this->encryptionService->decrypt($data[$field]);
                if ($decrypted !== null) {
                    $data[$field] = $decrypted;
                }
                // null → 복호화 실패 = 기존 평문 데이터 → 그대로 유지
            }
        }
    }

    /**
     * 검색 필터에 Blind Index 추가
     *
     * keyword가 있으면 해당 값의 Blind Index를 생성하여 필터에 추가
     */
    private function prepareSearchFilters(array $filters): array
    {
        if (!empty($filters['keyword']) && $this->encryptionService) {
            $filters['keyword_index'] = $this->encryptionService->createSearchIndex($filters['keyword']);
        }

        return $filters;
    }

    /**
     * 주문 배열 복호화 헬퍼 (목록용)
     */
    private function decryptOrderArray(array $orderArray): array
    {
        $this->decryptOrderFields($orderArray);
        return $orderArray;
    }
}
