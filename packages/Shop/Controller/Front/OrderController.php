<?php

namespace Mublo\Packages\Shop\Controller\Front;

use Mublo\Core\Context\Context;
use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Service\Auth\AuthService;
use Mublo\Packages\Shop\Service\OrderService;
use Mublo\Packages\Shop\Service\OrderFieldService;
use Mublo\Packages\Shop\Service\OrderStateResolver;

/**
 * Front 주문 컨트롤러
 *
 * /shop/order, /shop/orders 라우트 처리
 */
class OrderController
{
    private OrderService $orderService;
    private AuthService $authService;
    private OrderFieldService $orderFieldService;
    private OrderStateResolver $orderStateResolver;

    public function __construct(
        OrderService $orderService,
        AuthService $authService,
        OrderFieldService $orderFieldService,
        OrderStateResolver $orderStateResolver
    ) {
        $this->orderService = $orderService;
        $this->authService = $authService;
        $this->orderFieldService = $orderFieldService;
        $this->orderStateResolver = $orderStateResolver;
    }

    /**
     * 주문 완료 페이지
     */
    public function complete(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $user = $this->authService->user();

        if ($user === null) {
            return RedirectResponse::to('/login');
        }

        $domainId = $context->getDomainId() ?? 1;
        $memberId = $this->authService->id() ?? 0;
        $orderNo = $params['orderNo'] ?? $params[0] ?? '';

        if ($orderNo === '') {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Order/Complete')
                ->withStatusCode(404)
                ->withData(['message' => '주문 정보를 찾을 수 없습니다.']);
        }

        // 주문 정보 조회
        $result = $this->orderService->getOrder($orderNo);

        if ($result->isFailure()) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Order/Complete')
                ->withStatusCode(404)
                ->withData(['message' => $result->getMessage()]);
        }

        $data = $result->getData();
        $order = $data['order'] ?? [];

        // 소유권 검증: 자신의 주문만 접근 가능
        $orderMemberId = (int) ($order['member_id'] ?? 0);
        if ($orderMemberId !== $memberId) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Order/Complete')
                ->withStatusCode(403)
                ->withData(['message' => '주문 정보에 접근할 수 없습니다.']);
        }

        // 완료 페이지 표시용 주문 정보 (복호화 완료된 상태)
        $safeOrder = [
            'order_no'         => $order['order_no'] ?? '',
            'order_status'     => $order['order_status'] ?? '',
            'total_price'      => $order['total_price'] ?? 0,
            'shipping_fee'     => $order['shipping_fee'] ?? 0,
            'grand_total'      => ($order['total_price'] ?? 0) + ($order['shipping_fee'] ?? 0),
            'payment_method'   => $order['payment_method'] ?? '',
            'payment_gateway'  => $order['payment_gateway'] ?? '',
            'recipient_name'   => $order['recipient_name'] ?? '',
            'shipping_zip'     => $order['shipping_zip'] ?? '',
            'shipping_address1' => $order['shipping_address1'] ?? '',
            'shipping_address2' => $order['shipping_address2'] ?? '',
            'created_at'       => $order['created_at'] ?? '',
        ];

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Order/Complete')
            ->withData([
                'order'      => $safeOrder,
                'orderItems' => $data['items'] ?? [],
            ]);
    }

    /**
     * 내 주문 목록 (로그인 필수)
     */
    public function index(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $user = $this->authService->user();

        if ($user === null) {
            return RedirectResponse::to('/login');
        }

        $domainId = $context->getDomainId() ?? 1;
        $memberId = $this->authService->id() ?? 0;
        $request = $context->getRequest();
        $page = max(1, (int) ($request->get('page') ?? 1));

        // 회원 주문 목록 조회
        $result = $this->orderService->getMemberOrders($memberId, $page);

        $data = $result->isSuccess() ? $result->getData() : ['items' => [], 'pagination' => []];
        $items = $data['items'] ?? [];
        $pagination = $data['pagination'] ?? [
            'totalItems' => 0,
            'perPage' => 20,
            'currentPage' => $page,
            'totalPages' => 1,
        ];
        $pagination['pageNums'] = 10;

        // 상태 라벨 매핑용
        $allStates = $this->orderStateResolver->getAllStates($domainId);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Order/Index')
            ->withData([
                'orders'     => $items,
                'pagination' => $pagination,
                'allStates'  => $allStates,
            ]);
    }

    /**
     * 주문 상세 (로그인 필수)
     */
    public function view(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $user = $this->authService->user();

        if ($user === null) {
            return RedirectResponse::to('/login');
        }

        $domainId = $context->getDomainId() ?? 1;
        $memberId = $this->authService->id() ?? 0;
        $orderNo = $params['orderNo'] ?? $params[0] ?? '';

        if ($orderNo === '') {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Order/View')
                ->withStatusCode(404)
                ->withData(['message' => '주문 정보를 찾을 수 없습니다.']);
        }

        // 주문 정보 조회
        $result = $this->orderService->getOrder($orderNo);

        if ($result->isFailure()) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Order/View')
                ->withStatusCode(404)
                ->withData(['message' => $result->getMessage()]);
        }

        $data = $result->getData();
        $order = $data['order'] ?? [];

        // 주문 소유자 확인
        $orderMemberId = (int) ($order['member_id'] ?? 0);
        if ($orderMemberId !== $memberId) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Order/View')
                ->withStatusCode(403)
                ->withData(['message' => '주문 정보에 접근할 수 없습니다.']);
        }

        // 커스텀 필드 값 조회
        $orderFieldValues = $this->orderFieldService->getOrderFieldValues($orderNo);

        // 상태 라벨 + 전체 상태 목록 (타임라인용)
        $currentStatus = $order['order_status'] ?? '';
        $statusLabel = $this->orderStateResolver->getLabel($domainId, $currentStatus);
        $allStates = $this->orderStateResolver->getAllStates($domainId);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Order/View')
            ->withData([
                'order'             => $order,
                'orderItems'        => $data['items'] ?? [],
                'orderFieldValues'  => $orderFieldValues,
                'statusLabel'       => $statusLabel,
                'allStates'         => $allStates,
            ]);
    }
}
