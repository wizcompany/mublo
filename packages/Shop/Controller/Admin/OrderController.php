<?php
namespace Mublo\Packages\Shop\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Packages\Shop\Service\OrderService;
use Mublo\Packages\Shop\Service\OrderFieldService;
use Mublo\Packages\Shop\Service\OrderStateResolver;
use Mublo\Packages\Shop\Service\RefundService;
use Mublo\Packages\Shop\Service\OrderMemoService;
use Mublo\Service\Auth\AuthService;

/**
 * Admin OrderController
 *
 * 주문 관리 컨트롤러 (FSM 기반 + 환불 + 메모)
 */
class OrderController
{
    private OrderService $orderService;
    private OrderFieldService $orderFieldService;
    private OrderStateResolver $stateResolver;
    private RefundService $refundService;
    private OrderMemoService $memoService;
    private AuthService $authService;

    public function __construct(
        OrderService $orderService,
        OrderFieldService $orderFieldService,
        OrderStateResolver $stateResolver,
        RefundService $refundService,
        OrderMemoService $memoService,
        AuthService $authService
    ) {
        $this->orderService = $orderService;
        $this->orderFieldService = $orderFieldService;
        $this->stateResolver = $stateResolver;
        $this->refundService = $refundService;
        $this->memoService = $memoService;
        $this->authService = $authService;
    }

    /**
     * 주문 목록
     *
     * GET /admin/shop/orders
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $page = (int) ($request->get('page') ?? 1);
        $perPage = (int) ($request->get('per_page') ?? 20);

        $filters = [
            'keyword' => $request->get('keyword') ?? '',
            'search_field' => $request->get('search_field') ?? '',
            'order_status' => $request->get('order_status') ?? '',
            'date_from' => $request->get('date_from') ?? '',
            'date_to' => $request->get('date_to') ?? '',
            'payment_method' => $request->get('payment_method') ?? '',
        ];

        $result = $this->orderService->getOrderList($domainId, $filters, $page, $perPage);

        // FSM 기반 상태 옵션 빌드
        $allStates = $this->stateResolver->getAllStates($domainId);
        $statusOptions = [];
        foreach ($allStates as $state) {
            $statusOptions[$state['id']] = $state['label'];
        }

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Order/List')
            ->withData([
                'pageTitle' => '주문 관리',
                'orders' => $result->get('items', []),
                'pagination' => $result->get('pagination', []),
                'filters' => $filters,
                'orderStatusOptions' => $statusOptions,
            ]);
    }

    /**
     * 주문 상세
     *
     * GET /admin/shop/orders/{orderNo}
     */
    public function view(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $orderNo = $params['orderNo'] ?? $params[0] ?? $request->query('order_no', '');

        if (empty($orderNo)) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Error/404')
                ->withData(['message' => '주문을 찾을 수 없습니다.']);
        }

        $result = $this->orderService->getOrder($orderNo);

        if ($result->isFailure()) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Error/404')
                ->withData(['message' => $result->getMessage()]);
        }

        $order = $result->get('order');

        // 도메인 경계 검증
        if ((int) ($order['domain_id'] ?? 0) !== $domainId) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Error/404')
                ->withData(['message' => '주문을 찾을 수 없습니다.']);
        }

        $currentStatusId = $order['order_status'] ?? '';

        // FSM 기반 데이터
        $allStates = $this->stateResolver->getAllStates($domainId);
        $statusOptions = [];
        foreach ($allStates as $state) {
            $statusOptions[$state['id']] = $state['label'];
        }

        $currentStatusLabel = $this->stateResolver->getLabel($domainId, $currentStatusId);
        $availableTransitions = $this->stateResolver->getAvailableTransitions($domainId, $currentStatusId);

        // 주문 로그
        $orderLogs = $this->orderService->getOrderLogs($orderNo);

        // 주문 추가 필드
        $orderFieldValues = $this->orderFieldService->getOrderFieldValues($orderNo);

        // 반품 정보
        $orderReturns = $this->orderService->getOrderReturns($orderNo);

        // 환불 정보
        $refundInfo = $this->refundService->getRefundableAmount($orderNo);
        $paymentTransactions = $this->refundService->getTransactionHistory($orderNo);

        // 관리자 메모
        $orderMemos = $this->memoService->getMemos($orderNo);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Order/View')
            ->withData([
                'pageTitle' => '주문 상세 - ' . $orderNo,
                'order' => $order,
                'orderItems' => $result->get('items', []),
                'orderStatusOptions' => $statusOptions,
                'availableTransitions' => $availableTransitions,
                'currentStatusLabel' => $currentStatusLabel,
                'orderLogs' => $orderLogs,
                'orderFieldValues' => $orderFieldValues,
                'orderReturns' => $orderReturns,
                'refundInfo' => $refundInfo->isSuccess() ? $refundInfo->getData() : [],
                'paymentTransactions' => $paymentTransactions,
                'orderMemos' => $orderMemos,
                'memoTypeLabels' => OrderMemoService::TYPE_LABELS,
                'domainId' => $domainId,
            ]);
    }

    /**
     * 주문 상태 변경 (FSM 검증)
     *
     * POST /admin/shop/orders/{orderNo}/status
     */
    public function updateStatus(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $orderNo = $params['orderNo'] ?? $params[0] ?? $request->json('order_no', '');
        $status = $request->json('order_status', '');
        $reason = $request->json('reason', '');

        if (empty($orderNo)) {
            return JsonResponse::error('주문번호가 필요합니다.');
        }

        if (empty($status)) {
            return JsonResponse::error('변경할 주문 상태를 선택해주세요.');
        }

        $result = $this->orderService->updateStatus($orderNo, $status, $domainId, $reason, 'STAFF');

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    // ===== 아이템 관리 =====

    /**
     * 아이템 상태 변경
     *
     * POST /admin/shop/orders/{orderNo}/items/{detailId}/status
     */
    public function updateItemStatus(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $orderNo = $params['orderNo'] ?? '';
        $detailId = (int) ($params['detailId'] ?? 0);
        $status = $request->json('order_status', '');
        $reason = $request->json('reason', '');

        if (empty($orderNo) || $detailId <= 0) {
            return JsonResponse::error('주문번호와 상품 ID가 필요합니다.');
        }

        if (empty($status)) {
            return JsonResponse::error('변경할 상태를 선택해주세요.');
        }

        $result = $this->orderService->updateItemStatus($orderNo, $detailId, $status, $domainId, $reason);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 아이템 취소
     *
     * POST /admin/shop/orders/{orderNo}/items/{detailId}/cancel
     */
    public function cancelItem(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $orderNo = $params['orderNo'] ?? '';
        $detailId = (int) ($params['detailId'] ?? 0);
        $reason = $request->json('reason', '');

        if (empty($orderNo) || $detailId <= 0) {
            return JsonResponse::error('주문번호와 상품 ID가 필요합니다.');
        }

        if (empty($reason)) {
            return JsonResponse::error('취소 사유를 입력해주세요.');
        }

        $result = $this->orderService->cancelOrderItem($orderNo, $detailId, $reason, $domainId);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 반품/교환 요청 접수
     *
     * POST /admin/shop/orders/{orderNo}/items/{detailId}/return
     */
    public function returnItem(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $orderNo = $params['orderNo'] ?? '';
        $detailId = (int) ($params['detailId'] ?? 0);
        $returnType = $request->json('return_type', '');
        $reasonType = $request->json('reason_type', '');
        $reasonDetail = $request->json('reason_detail', '');

        if (empty($orderNo) || $detailId <= 0) {
            return JsonResponse::error('주문번호와 상품 ID가 필요합니다.');
        }

        if (empty($returnType) || !in_array($returnType, ['RETURN', 'EXCHANGE'], true)) {
            return JsonResponse::error('반품/교환 유형을 선택해주세요.');
        }

        $result = $this->orderService->requestItemReturn(
            $orderNo, $detailId, $returnType, $reasonType, $reasonDetail, $domainId
        );

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 반품 승인/거절
     *
     * POST /admin/shop/orders/{orderNo}/items/{detailId}/return-process
     */
    public function processReturn(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $orderNo = $params['orderNo'] ?? '';
        $detailId = (int) ($params['detailId'] ?? 0);
        $accept = (bool) $request->json('accept', false);
        $reason = $request->json('reason', '');

        if (empty($orderNo) || $detailId <= 0) {
            return JsonResponse::error('주문번호와 상품 ID가 필요합니다.');
        }

        $result = $this->orderService->processItemReturn($orderNo, $detailId, $accept, $reason, $domainId);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    // ===== 환불 =====

    /**
     * 환불 처리
     *
     * POST /admin/shop/orders/{orderNo}/refund
     */
    public function refund(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $orderNo = $params['orderNo'] ?? '';
        $amount = (int) $request->json('amount', 0);
        $refundMethod = $request->json('refund_method', '');
        $reason = $request->json('reason', '');
        $bankInfo = [
            'bank' => $request->json('refund_bank', ''),
            'account' => $request->json('refund_account', ''),
            'holder' => $request->json('refund_holder', ''),
        ];

        if (empty($orderNo)) {
            return JsonResponse::error('주문번호가 필요합니다.');
        }
        if ($amount <= 0) {
            return JsonResponse::error('환불 금액을 입력해주세요.');
        }
        if (empty($refundMethod) || !in_array($refundMethod, ['PG_CANCEL', 'BANK', 'POINT'], true)) {
            return JsonResponse::error('환불 방법을 선택해주세요.');
        }
        if (empty($reason)) {
            return JsonResponse::error('환불 사유를 입력해주세요.');
        }

        $staffId = $this->authService->user()?->getMemberId() ?? 0;

        $result = $this->refundService->processRefund(
            $orderNo, $amount, $refundMethod, $reason, $domainId, $staffId, $bankInfo
        );

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    // ===== 메모 =====

    /**
     * 메모 추가
     *
     * POST /admin/shop/orders/{orderNo}/memos
     */
    public function addMemo(array $params, Context $context): JsonResponse
    {
        $orderNo = $params['orderNo'] ?? '';
        $request = $context->getRequest();
        $content = trim($request->json('content', ''));
        $memoType = $request->json('memo_type', 'MEMO');
        $staffId = $this->authService->user()?->getMemberId() ?? 0;

        if (empty($orderNo)) {
            return JsonResponse::error('주문번호가 필요합니다.');
        }
        if (empty($content)) {
            return JsonResponse::error('메모 내용을 입력해주세요.');
        }

        $result = $this->memoService->addMemo($orderNo, $content, $memoType, $staffId);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 메모 삭제
     *
     * POST /admin/shop/orders/{orderNo}/memos/{memoId}/delete
     */
    public function deleteMemo(array $params, Context $context): JsonResponse
    {
        $memoId = (int) ($params['memoId'] ?? 0);
        $staffId = $this->authService->user()?->getMemberId() ?? 0;

        if ($memoId <= 0) {
            return JsonResponse::error('메모 ID가 필요합니다.');
        }

        $result = $this->memoService->deleteMemo($memoId, $staffId);

        return $result->isSuccess()
            ? JsonResponse::success([], $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }
}
