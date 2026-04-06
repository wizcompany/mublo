<?php

namespace Mublo\Packages\Shop\Controller\Front;

use Mublo\Core\Context\Context;
use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Service\Auth\AuthService;
use Mublo\Packages\Shop\Service\CartService;
use Mublo\Packages\Shop\Service\CartCheckoutService;
use Mublo\Packages\Shop\Service\DirectBuyService;
use Mublo\Packages\Shop\Service\OrderService;
use Mublo\Packages\Shop\Service\PaymentService;
use Mublo\Packages\Shop\Service\ShopConfigService;
use Mublo\Packages\Shop\Service\MemberAddressService;
use Mublo\Packages\Shop\Service\OrderFieldService;
use Mublo\Packages\Shop\Enum\OrderAction;
use Mublo\Contract\Tracking\TrackingKeys;
use Mublo\Core\Session\SessionInterface;
use Mublo\Infrastructure\Storage\UploadedFile;

/**
 * Front 장바구니 컨트롤러
 *
 * /shop/cart, /shop/checkout 라우트 처리
 */
class CartController
{
    private CartService $cartService;
    private CartCheckoutService $cartCheckoutService;
    private DirectBuyService $directBuyService;
    private OrderService $orderService;
    private PaymentService $paymentService;
    private AuthService $authService;
    private MemberAddressService $addressService;
    private ShopConfigService $shopConfigService;
    private OrderFieldService $orderFieldService;
    private SessionInterface $session;

    public function __construct(
        CartService $cartService,
        CartCheckoutService $cartCheckoutService,
        DirectBuyService $directBuyService,
        OrderService $orderService,
        PaymentService $paymentService,
        AuthService $authService,
        MemberAddressService $addressService,
        ShopConfigService $shopConfigService,
        OrderFieldService $orderFieldService,
        SessionInterface $session
    ) {
        $this->cartService = $cartService;
        $this->cartCheckoutService = $cartCheckoutService;
        $this->directBuyService = $directBuyService;
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
        $this->authService = $authService;
        $this->addressService = $addressService;
        $this->shopConfigService = $shopConfigService;
        $this->orderFieldService = $orderFieldService;
        $this->session = $session;
    }

    /**
     * 장바구니 세션 ID 가져오기 또는 생성
     *
     * 쿠키/세션에서 cart_session_id를 조회하고,
     * 없으면 새로 생성하여 쿠키에 저장한다.
     * 쿠키 유효기간: 회원 cart_keep_days(기본 15일), 비회원 guest_cart_keep_days(기본 7일)
     */
    private function getCartSession(Context $context): string
    {
        $request = $context->getRequest();
        $sessionId = $request->cookie('cart_session_id') ?? '';

        $domainId = $context->getDomainId() ?? 1;
        $configResult = $this->shopConfigService->getConfig($domainId);
        $shopConfig = $configResult->get('config', []);

        $isLoggedIn = $this->authService->check();
        $keepDays = $isLoggedIn
            ? (int) ($shopConfig['cart_keep_days'] ?? 15)
            : (int) ($shopConfig['guest_cart_keep_days'] ?? 7);

        if ($sessionId === '') {
            $sessionId = bin2hex(random_bytes(16));
        }

        // 매 요청마다 쿠키 갱신 (유효기간 연장)
        setcookie('cart_session_id', $sessionId, time() + (86400 * $keepDays), '/', '', false, true);

        return $sessionId;
    }

    /**
     * 장바구니 목록 페이지 (배송 그룹핑)
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $cartSessionId = $this->getCartSession($context);
        $memberId = $this->authService->id() ?? 0;

        $result = $this->cartService->getGroupedCartList($cartSessionId, $memberId);

        $groups = $result->isSuccess() ? ($result->getData()['groups'] ?? []) : [];
        $totals = $result->isSuccess() ? ($result->getData()['totals'] ?? []) : [
            'itemTotal' => 0, 'shippingTotal' => 0, 'pointTotal' => 0, 'grandTotal' => 0,
        ];
        $productData = $result->isSuccess() ? ($result->getData()['productData'] ?? []) : [];

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Cart/List')
            ->withData([
                'groups'      => $groups,
                'totals'      => $totals,
                'productData' => $productData,
            ]);
    }

    /**
     * 장바구니 내 옵션 수정 (POST, JSON)
     */
    public function updateOption(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $domainId = $context->getDomainId() ?? 1;
        $cartSessionId = $this->getCartSession($context);
        $memberId = $this->authService->id() ?? 0;

        $goodsId = (int) ($request->json('goods_id') ?? 0);
        if ($goodsId <= 0) {
            return JsonResponse::error('상품 정보가 올바르지 않습니다.');
        }

        $result = $this->cartService->updateOption($cartSessionId, $memberId, $domainId, [
            'goods_id'        => $goodsId,
            'option_mode'     => $request->json('optionMode') ?? 'NONE',
            'quantity'        => max(1, (int) ($request->json('quantity') ?? 1)),
            'selectedOptions' => $request->json('selectedOptions') ?? [],
            'selectedExtras'  => $request->json('selectedExtras') ?? [],
        ]);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success(null, $result->getMessage());
    }

    /**
     * 장바구니 담기 / 바로구매 (POST, JSON)
     *
     * JS ShopProductOption.getSubmitData() 구조를 그대로 전달:
     * - optionMode (camelCase)
     * - selectedOptions[], selectedExtras[]
     * - action: 'cart' | 'direct'
     */
    public function add(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $domainId = $context->getDomainId() ?? 1;
        $cartSessionId = $this->getCartSession($context);
        $memberId = $this->authService->id() ?? 0;

        $goodsId = (int) ($request->json('goods_id') ?? 0);
        if ($goodsId <= 0) {
            return JsonResponse::error('상품 정보가 올바르지 않습니다.');
        }

        // JS camelCase → Service 키 매핑
        $result = $this->cartService->addToCart([
            'cart_session_id' => $cartSessionId,
            'member_id'       => $memberId,
            'domain_id'       => $domainId,
            'goods_id'        => $goodsId,
            'action'          => $request->json('action') ?? 'cart',
            'option_mode'     => $request->json('optionMode') ?? 'NONE',
            'quantity'        => max(1, (int) ($request->json('quantity') ?? 1)),
            'selectedOptions' => $request->json('selectedOptions') ?? [],
            'selectedExtras'  => $request->json('selectedExtras') ?? [],
        ]);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success($result->getData(), $result->getMessage());
    }

    /**
     * 장바구니 수량 변경 (POST, JSON)
     */
    public function update(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $cartSessionId = $this->getCartSession($context);
        $memberId = $this->authService->id() ?? 0;
        $cartItemId = (int) ($request->json('cart_item_id') ?? 0);
        $quantity = max(1, (int) ($request->json('quantity') ?? 1));

        if ($cartItemId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $result = $this->cartService->updateQuantity($cartItemId, $quantity, $cartSessionId, $memberId);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success($result->getData(), $result->getMessage());
    }

    /**
     * 장바구니 아이템 삭제 (POST, JSON)
     */
    public function remove(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $cartSessionId = $this->getCartSession($context);
        $memberId = $this->authService->id() ?? 0;
        $cartItemId = (int) ($request->json('cart_item_id') ?? 0);

        if ($cartItemId <= 0) {
            return JsonResponse::error('잘못된 요청입니다.');
        }

        $result = $this->cartService->removeItem($cartItemId, $cartSessionId, $memberId);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success(null, $result->getMessage());
    }

    /**
     * 체크아웃 준비 (POST, JSON)
     *
     * 선택된 cart_item_ids를 검증하여 세션에 저장
     */
    public function prepareCheckout(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $cartSessionId = $this->getCartSession($context);
        $cartItemIds = $request->json('cart_item_ids') ?? [];

        if (empty($cartItemIds)) {
            return JsonResponse::error('주문할 상품을 선택해주세요.');
        }

        $result = $this->cartCheckoutService->prepareCheckout($cartSessionId, $cartItemIds);

        if ($result->isFailure()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success(['redirect' => '/shop/checkout'], '체크아웃 준비가 완료되었습니다.');
    }

    /**
     * 체크아웃 페이지
     *
     * ?mode=direct → 바로구매 세션 사용
     * ?guest=1 → 비회원 주문 모드
     * 기본 → 장바구니 아이템 사용
     */
    public function checkout(array $params, Context $context): ViewResponse|RedirectResponse
    {
        $request = $context->getRequest();
        $user = $this->authService->user();
        $isGuest = $request->query('guest') === '1';

        if ($user === null && !$isGuest) {
            return RedirectResponse::to('/login?redirect=' . urlencode('/shop/checkout') . '&intent=checkout');
        }

        $domainId = $context->getDomainId() ?? 1;
        $cartSessionId = $this->getCartSession($context);
        $memberId = $user ? ($this->authService->id() ?? 0) : 0;
        $mode = $request->query('mode') ?? '';

        // 바로구매 모드: 세션에서 데이터 로드
        if ($mode === 'direct') {
            $directData = $this->directBuyService->getDirectBuyData();
            if (!$directData) {
                return RedirectResponse::to('/shop/products');
            }
            $cartItems = $directData['items'] ?? [];
            $totals = [
                'totalPrice' => $directData['totalPrice'] ?? 0,
                'shippingFee' => $directData['shippingFee'] ?? 0,
                'totalPoint' => 0,
                'totalQuantity' => array_sum(array_column($cartItems, 'quantity')),
                'grandTotal' => ($directData['totalPrice'] ?? 0) + ($directData['shippingFee'] ?? 0),
            ];
        } else {
            // 일반 장바구니 모드
            $cartResult = $this->cartService->getItems($cartSessionId, $memberId);
            $cartItems = $cartResult->isSuccess() ? ($cartResult->getData()['items'] ?? []) : [];

            if (empty($cartItems)) {
                return RedirectResponse::to('/shop/cart');
            }

            $totals = $this->cartCheckoutService->calculateTotals($cartItems);
        }

        // 관리자가 선택한 PG만 필터링하여 결제 수단 조회
        $configResult = $this->shopConfigService->getConfig($domainId);
        $shopConfig = $configResult->get('config', []);
        $enabledPgKeys = $this->parseEnabledPgKeys($shopConfig);

        $gwResult = $this->paymentService->getAvailableGateways($enabledPgKeys);
        $gateways = $gwResult->isSuccess() ? ($gwResult->get('gateways', [])) : [];
        $selectedGateway = $this->paymentService->selectGatewayKey(
            $enabledPgKeys,
            null,
            (string) ($shopConfig['payment_pg_key'] ?? '')
        );

        // 회원 저장 배송지 목록 조회
        $addresses = [];
        $defaultAddress = null;
        if ($memberId > 0) {
            $addrResult = $this->addressService->getList($memberId, $domainId);
            if ($addrResult->isSuccess()) {
                $addresses = $addrResult->get('addresses', []);
                foreach ($addresses as $addr) {
                    if (!empty($addr['is_default'])) {
                        $defaultAddress = $addr;
                        break;
                    }
                }
            }
        }

        // 주문 추가 필드 (활성 필드만)
        $orderFields = $this->orderFieldService->getActiveFields($domainId);

        // 활성화된 PG들의 체크아웃 JS 핸들러 수집
        $checkoutScripts = $this->paymentService->collectCheckoutScripts($enabledPgKeys);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Cart/Checkout')
            ->withData([
                'cartItems'       => $cartItems,
                'totals'          => $totals,
                'gateways'        => $gateways,
                'selectedGateway' => $selectedGateway,
                'member'          => $user,
                'isGuest'         => $isGuest,
                'checkoutMode'    => $mode ?: 'cart',
                'addresses'       => $addresses,
                'defaultAddress'  => $defaultAddress,
                'orderFields'     => $orderFields,
                'checkoutScripts' => $checkoutScripts,
            ]);
    }

    /**
     * 결제 준비 (POST, JSON)
     *
     * 주문 생성 → PG prepare → 프론트에 결제 정보 반환
     * 프론트에서 PG 결제창을 열고, 완료 후 verify() 호출
     */
    public function payment(array $params, Context $context): JsonResponse
    {
        $user = $this->authService->user();

        if ($user === null) {
            return JsonResponse::error('로그인이 필요합니다.');
        }

        $request = $context->getRequest();
        $domainId = $context->getDomainId() ?? 1;
        $cartSessionId = $this->getCartSession($context);
        $memberId = $this->authService->id() ?? 0;

        $requestedGateway = trim($request->json('payment_gateway') ?? '');
        $paymentMethod = trim($request->json('payment_method') ?? '');
        $checkoutMode = trim($request->json('checkout_mode') ?? '');
        $cartItemIds = $request->json('cart_item_ids') ?? [];

        // 배송 정보
        $shippingData = [
            'recipient_name'   => trim($request->json('recipient_name') ?? ''),
            'recipient_phone'  => trim($request->json('recipient_phone') ?? ''),
            'shipping_zip'     => trim($request->json('shipping_zip') ?? ''),
            'shipping_address1' => trim($request->json('shipping_address1') ?? ''),
            'shipping_address2' => trim($request->json('shipping_address2') ?? ''),
            'order_memo'       => trim($request->json('order_memo') ?? ''),
        ];

        if ($paymentMethod === '') {
            return JsonResponse::error('결제 수단을 선택해주세요.');
        }

        $configResult = $this->shopConfigService->getConfig($domainId);
        $shopConfig = $configResult->get('config', []);
        $enabledPgKeys = $this->parseEnabledPgKeys($shopConfig);
        $paymentGateway = $this->paymentService->selectGatewayKey(
            $enabledPgKeys,
            $requestedGateway,
            (string) ($shopConfig['payment_pg_key'] ?? '')
        );

        if ($paymentGateway === null || $paymentGateway === '') {
            return JsonResponse::error('사용 가능한 결제 게이트웨이가 없습니다.');
        }

        if (empty($shippingData['recipient_name'])) {
            return JsonResponse::error('수령인 이름을 입력해주세요.');
        }

        if (empty($shippingData['shipping_address1'])) {
            return JsonResponse::error('배송 주소를 입력해주세요.');
        }

        // 바로구매 모드: CartService 대신 DirectBuyService 세션 데이터 사용
        $shippingFee = 0;
        if ($checkoutMode === 'direct') {
            $directData = $this->directBuyService->getDirectBuyData();
            if (empty($directData['items'])) {
                return JsonResponse::error('바로구매 정보가 만료되었습니다. 다시 시도해주세요.');
            }
            $allItems = $directData['items'];
            $shippingFee = (int) ($directData['shippingFee'] ?? 0);
        } else {
            // 장바구니 아이템 조회
            $cartResult = $this->cartService->getItems($cartSessionId, $memberId);
            $allItems = $cartResult->isSuccess() ? ($cartResult->getData()['items'] ?? []) : [];

            if (empty($allItems)) {
                return JsonResponse::error('장바구니에 상품이 없습니다.');
            }

            // cart_item_ids로 필터링 (지정된 경우)
            if (!empty($cartItemIds)) {
                $selectedIds = array_map('intval', $cartItemIds);
                $allItems = array_filter($allItems, fn($item) =>
                    in_array((int) ($item['cart_item_id'] ?? 0), $selectedIds)
                );
                $allItems = array_values($allItems);
            }

            if (empty($allItems)) {
                return JsonResponse::error('주문할 상품을 선택해주세요.');
            }

            $shippingFee = $this->cartCheckoutService->calculateTotals($allItems)['shippingFee'] ?? 0;
        }

        // 주문 추가 필드 검증
        $orderFieldValues = $request->json('order_fields') ?? [];
        if (!empty($orderFieldValues)) {
            $validateResult = $this->orderFieldService->validateValues($domainId, $orderFieldValues);
            if ($validateResult->isFailure()) {
                return JsonResponse::error($validateResult->getMessage());
            }
        }

        // 주문 생성
        $orderResult = $this->orderService->createOrder($domainId, $memberId, [
            'cart_session_id'   => $cartSessionId,
            'payment_gateway'   => $paymentGateway,
            'payment_method'    => $paymentMethod,
            'shipping_fee'      => $shippingFee,
            'recipient_name'    => $shippingData['recipient_name'],
            'recipient_phone'   => $shippingData['recipient_phone'],
            'shipping_zip'      => $shippingData['shipping_zip'],
            'shipping_address1' => $shippingData['shipping_address1'],
            'shipping_address2' => $shippingData['shipping_address2'],
            'order_memo'        => $shippingData['order_memo'],
            'campaign_key'      => $this->session->get(TrackingKeys::CAMPAIGN_KEY),
        ], $allItems);

        if ($orderResult->isFailure()) {
            return JsonResponse::error($orderResult->getMessage());
        }

        $orderNo = $orderResult->get('order_no', '');

        // 주문에 사용된 cart_item_ids 세션 저장 (verify에서 해당 아이템만 ORDERED 처리)
        $usedCartItemIds = array_filter(array_map(
            fn($item) => (int) ($item['cart_item_id'] ?? 0),
            $allItems
        ));
        if (!empty($usedCartItemIds)) {
            $this->cartCheckoutService->saveOrderCartItems($orderNo, array_values($usedCartItemIds));
        }

        // 주문 추가 필드 값 저장
        if (!empty($orderFieldValues)) {
            $this->orderFieldService->saveValues($orderNo, $domainId, $orderFieldValues);
        }

        // PG 결제 준비 (prepare)
        $prepareResult = $this->paymentService->processPayment($paymentGateway, [
            'order_no' => $orderNo,
            'amount' => $orderResult->get('payment_amount', 0),
            'payment_method' => $paymentMethod,
        ]);

        if ($prepareResult->isFailure()) {
            // PG 준비 실패 → 주문 취소 처리 (고아 주문 방지)
            $this->orderService->updateStatus(
                $orderNo, OrderAction::CANCELLED->value, $domainId,
                'PG 준비 실패: ' . $prepareResult->getMessage(), 'SYSTEM'
            );
            return JsonResponse::error($prepareResult->getMessage());
        }

        $prepareData = $prepareResult->getData();

        // 주문명 생성 (PG 결제창 표시용)
        // CartService: $item['product']['goods_name'], DirectBuyService: $item['goods_name']
        $firstName = $allItems[0]['product']['goods_name'] ?? $allItems[0]['goods_name'] ?? '상품';
        $itemCount = count($allItems);
        $orderName = $itemCount > 1
            ? $firstName . ' 외 ' . ($itemCount - 1) . '건'
            : $firstName;

        // 프론트에 결제 정보 반환 (프론트가 PG 결제창 오픈)
        return JsonResponse::success([
            'order_no'       => $orderNo,
            'order_name'     => $orderName,
            'gateway'        => $paymentGateway,
            'transaction_id' => $prepareData['transaction_id'] ?? '',
            'amount'         => $prepareData['amount'] ?? 0,
            'client_config'  => $this->paymentService->getClientConfig($paymentGateway),
        ], '결제를 진행해주세요.');
    }

    /**
     * 결제 설정의 활성 PG 키 목록 파싱
     *
     * @return string[]
     */
    private function parseEnabledPgKeys(array $shopConfig): array
    {
        if (empty($shopConfig['payment_pg_keys'])) {
            return [];
        }

        $decoded = is_string($shopConfig['payment_pg_keys'])
            ? json_decode($shopConfig['payment_pg_keys'], true)
            : $shopConfig['payment_pg_keys'];

        if (!is_array($decoded)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $decoded)));
    }

    /**
     * 결제 검증 (POST, JSON)
     *
     * PG 결제창 완료 후 프론트에서 호출
     * PaymentService.verifyPayment()가 소유권/이중결제/금액 검증 + 상태 전이를 일괄 처리
     */
    public function verify(array $params, Context $context): JsonResponse
    {
        $user = $this->authService->user();

        if ($user === null) {
            return JsonResponse::error('로그인이 필요합니다.');
        }

        $request = $context->getRequest();
        $domainId = $context->getDomainId() ?? 1;
        $memberId = $this->authService->id() ?? 0;
        $orderNo = trim($request->json('order_no') ?? '');
        $paymentGateway = trim($request->json('payment_gateway') ?? '');
        $transactionId = trim($request->json('transaction_id') ?? '');

        if ($orderNo === '' || $transactionId === '') {
            return JsonResponse::error('결제 정보가 올바르지 않습니다.');
        }

        // PG 결제 검증 + 상태 전이 (소유권/이중결제/금액 검증 + RECEIPT→PAID 전이 포함)
        $verifyResult = $this->paymentService->verifyPayment(
            $paymentGateway, $transactionId, $orderNo, $memberId, $domainId
        );

        if ($verifyResult->isFailure()) {
            return JsonResponse::error($verifyResult->getMessage());
        }

        $verifyData = $verifyResult->getData();

        if (empty($verifyData['success'])) {
            return JsonResponse::error('결제 검증에 실패했습니다.');
        }

        // 장바구니 상태 변경 (PENDING → ORDERED) — 해당 주문 아이템만
        $cartSessionId = $this->getCartSession($context);
        $orderCartItemIds = $this->cartCheckoutService->getOrderCartItems($orderNo);
        $this->cartCheckoutService->markOrdered($cartSessionId, $orderCartItemIds);

        return JsonResponse::success([
            'order_no' => $orderNo,
            'redirect' => '/shop/order/' . $orderNo . '/complete',
        ], '결제가 완료되었습니다.');
    }

    /**
     * 주문 추가 필드 파일 업로드 (AJAX)
     *
     * POST /shop/checkout/upload-file
     */
    public function uploadFieldFile(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $domainId = $context->getDomainId() ?? 1;
        $fieldId = (int) ($request->post('field_id') ?? 0);

        $field = $this->orderFieldService->getField($fieldId);
        if (!$field || $field['field_type'] !== 'file') {
            return JsonResponse::error('유효하지 않은 필드입니다.');
        }

        $file = UploadedFile::fromGlobal('file');
        if (!$file || !$file->isValid()) {
            return JsonResponse::error($file ? $file->getErrorMessage() : '파일이 업로드되지 않았습니다.');
        }

        $config = json_decode($field['field_config'] ?? '{}', true) ?: [];

        // OrderFieldService 내부의 fileHandler 사용
        $fileHandler = $this->getFileHandler();
        if (!$fileHandler) {
            return JsonResponse::error('파일 업로드 기능을 사용할 수 없습니다.');
        }

        $result = $fileHandler->uploadTemp($file, $domainId, $config);

        if (!$result->isSuccess()) {
            return JsonResponse::error($result->getMessage());
        }

        return JsonResponse::success(
            $fileHandler->buildTempResponse($result),
            '파일이 업로드되었습니다.'
        );
    }

    /**
     * CustomFieldFileHandler 접근 (OrderFieldService 내부에서 가져옴)
     */
    private function getFileHandler(): ?\Mublo\Service\CustomField\CustomFieldFileHandler
    {
        // Service의 fileHandler에 직접 접근하는 대신 reflection 없이
        // ShopProvider에서 같은 인스턴스를 만들어 제공
        static $handler = null;
        if ($handler === null) {
            try {
                $container = \Mublo\Core\App\Application::getInstance()->getContainer();
                $fileUploader = $container->get(\Mublo\Infrastructure\Storage\FileUploader::class);
                $handler = new \Mublo\Service\CustomField\CustomFieldFileHandler($fileUploader);
            } catch (\Throwable $e) {
                return null;
            }
        }
        return $handler;
    }
}
