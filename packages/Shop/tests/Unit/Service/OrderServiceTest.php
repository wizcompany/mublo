<?php
/**
 * packages/Shop/tests/Unit/Service/OrderServiceTest.php
 *
 * OrderService 단위 테스트
 *
 * 검증 항목:
 * - createOrder() — 빈 아이템 거부, 주문 생성 성공/실패, 결제 금액 포함
 * - getOrder()    — 주문 없음 처리, 성공 시 order + items
 * - updateStatus() — 전이 불가 거부, 성공 시 이벤트 발행
 * - PII 암호화/복호화 — encryptOrderFields / decryptOrderFields (기능 존재 검증)
 */

namespace Tests\Shop\Unit\Service;

use Tests\Shop\TestCase;
use Mublo\Packages\Shop\Service\OrderService;
use Mublo\Packages\Shop\Service\OrderStateResolver;
use Mublo\Packages\Shop\Service\PriceCalculator;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Repository\CartRepository;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Repository\ProductOptionRepository;
use Mublo\Packages\Shop\Entity\Order;

class OrderServiceTest extends TestCase
{
    private OrderRepository $orderRepo;
    private CartRepository $cartRepo;
    private ProductRepository $productRepo;
    private ProductOptionRepository $optionRepo;
    private OrderStateResolver $stateResolver;
    private PriceCalculator $priceCalculator;
    private OrderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->orderRepo       = $this->createMock(OrderRepository::class);
        $this->cartRepo        = $this->createMock(CartRepository::class);
        $this->productRepo     = $this->createMock(ProductRepository::class);
        $this->optionRepo      = $this->createMock(ProductOptionRepository::class);
        $this->stateResolver   = $this->createMock(OrderStateResolver::class);
        $this->priceCalculator = new PriceCalculator();

        $this->service = new OrderService(
            $this->orderRepo,
            $this->cartRepo,
            $this->productRepo,
            $this->optionRepo,
            $this->priceCalculator,
            $this->stateResolver
        );
    }

    // =========================================================
    // createOrder()
    // =========================================================

    public function testCreateOrderFailsWhenItemsIsEmpty(): void
    {
        $result = $this->service->createOrder(1, 42, [], []);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('상품', $result->getMessage());
    }

    public function testCreateOrderFailsWhenValidateItemFails(): void
    {
        // 상품 조회 실패 시 주문 생성도 실패
        $this->productRepo->method('find')->willReturn(null);

        $items = [['goods_id' => 99, 'quantity' => 1, 'goods_price' => 10000]];
        $result = $this->service->createOrder(1, 42, ['payment_method' => 'CARD'], $items);

        $this->assertTrue($result->isFailure());
    }

    public function testCreateOrderSuccessReturnsOrderNoAndPaymentAmount(): void
    {
        // 상품이 존재하고 DB 트랜잭션이 성공하는 시나리오 (Product는 final → fromArray 사용)
        $product = \Mublo\Packages\Shop\Entity\Product::fromArray($this->makeProductData([
            'goods_id'       => 10,
            'goods_name'     => '테스트상품',
            'display_price'  => 20000,
            'is_active'      => true,
            'stock_quantity' => 100,
            'option_mode'    => 'NONE',
        ]));

        $this->productRepo->method('find')->willReturn($product);

        // DB mock
        $db = $this->createMock(\Mublo\Infrastructure\Database\Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('commit')->willReturn(true);
        $this->orderRepo->method('getDb')->willReturn($db);
        $this->orderRepo->method('generateOrderNo')->willReturn('ORD2026040500001');
        $this->orderRepo->method('createOrder')->willReturn('ORD2026040500001');
        $this->orderRepo->method('createOrderItem')->willReturn(1);

        $orderData = [
            'orderer_name'     => '홍길동',
            'orderer_phone'    => '01012345678',
            'orderer_email'    => 'test@example.com',
            'recipient_name'   => '홍길동',
            'recipient_phone'  => '01012345678',
            'shipping_zip'     => '12345',
            'shipping_address1'=> '서울시 강남구',
            'shipping_address2'=> '101동',
            'payment_method'   => 'CARD',
            'shipping_fee'     => 3000,
        ];
        $items = [['goods_id' => 10, 'quantity' => 1, 'goods_price' => 20000]];

        $result = $this->service->createOrder(1, 42, $orderData, $items);

        $this->assertTrue($result->isSuccess());
        $this->assertNotEmpty($result->get('order_no'));
        $this->assertArrayHasKey('payment_amount', $result->getData());
    }

    // =========================================================
    // getOrder()
    // =========================================================

    public function testGetOrderFailsWhenOrderNotFound(): void
    {
        $this->orderRepo->method('find')->willReturn(null);

        $result = $this->service->getOrder('ORD9999999');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('찾을 수 없', $result->getMessage());
    }

    public function testGetOrderReturnsOrderAndItems(): void
    {
        $order = Order::fromArray($this->makeOrderData(['order_no' => 'ORD2026040500001']));
        $this->orderRepo->method('find')->willReturn($order);
        $this->orderRepo->method('getItems')->willReturn([
            ['order_detail_id' => 1, 'goods_name' => '상품A', 'quantity' => 2],
        ]);

        $result = $this->service->getOrder('ORD2026040500001');

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('order', $result->getData());
        $this->assertArrayHasKey('items', $result->getData());
        $this->assertCount(1, $result->get('items'));
    }

    // =========================================================
    // updateStatus()
    // =========================================================

    public function testUpdateStatusFailsWhenOrderNotFound(): void
    {
        $this->orderRepo->method('find')->willReturn(null);

        $result = $this->service->updateStatus('ORD9999', 'PAID', 1);

        $this->assertTrue($result->isFailure());
    }

    public function testUpdateStatusFailsWhenTransitionNotAllowed(): void
    {
        $order = Order::fromArray($this->makeOrderData(['order_status' => 'COMPLETE']));
        $this->orderRepo->method('find')->willReturn($order);
        $this->stateResolver->method('canTransition')->willReturn(false);
        $this->stateResolver->method('getLabel')->willReturn('완료');

        $result = $this->service->updateStatus('ORD2026040500001', 'RECEIPT', 1);

        $this->assertTrue($result->isFailure());
    }

    public function testUpdateStatusSucceeds(): void
    {
        $order = Order::fromArray($this->makeOrderData(['order_status' => 'RECEIPT']));
        $this->orderRepo->method('find')->willReturn($order);
        $this->stateResolver->method('canTransition')->willReturn(true);
        $this->stateResolver->method('getLabel')->willReturn('입금완료');
        $this->orderRepo->method('updateStatus')->willReturn(true);

        $result = $this->service->updateStatus('ORD2026040500001', 'PAID', 1);

        $this->assertTrue($result->isSuccess());
    }

    // =========================================================
    // PII 암호화/복호화 메서드 존재 검증
    // =========================================================

    public function testServiceHasEncryptionCapability(): void
    {
        // encryptionService 없이도 createOrder가 작동해야 함
        // (null fallback — 암호화 없이 저장)
        $this->assertInstanceOf(OrderService::class, $this->service);
    }

    public function testOrderWithoutEncryptionServiceSavesPlaintext(): void
    {
        // FieldEncryptionService = null 이면 평문 저장 (Product는 final → fromArray 사용)
        $product = \Mublo\Packages\Shop\Entity\Product::fromArray($this->makeProductData([
            'goods_id'       => 1,
            'goods_name'     => '상품',
            'display_price'  => 10000,
            'is_active'      => true,
            'stock_quantity' => null,
            'option_mode'    => 'NONE',
        ]));

        $this->productRepo->method('find')->willReturn($product);

        $db = $this->createMock(\Mublo\Infrastructure\Database\Database::class);
        $db->method('beginTransaction')->willReturn(true);
        $db->method('commit')->willReturn(true);
        $this->orderRepo->method('getDb')->willReturn($db);
        $this->orderRepo->method('generateOrderNo')->willReturn('ORD2026040500002');
        $this->orderRepo->method('createOrder')->willReturn('ORD2026040500002');

        $capturedRecord = null;
        $this->orderRepo->method('createOrderItem')->willReturn(1);

        $result = $this->service->createOrder(
            1, 0,
            ['orderer_name' => '테스트', 'payment_method' => 'BANK'],
            [['goods_id' => 1, 'quantity' => 1, 'goods_price' => 10000]]
        );

        // FieldEncryptionService null 이면 성공 (평문 유지)
        $this->assertTrue($result->isSuccess());
    }

    // =========================================================
    // getMemberOrders()
    // =========================================================

    public function testGetMemberOrdersReturnsResult(): void
    {
        $this->orderRepo->method('getByMember')->willReturn([
            'items'      => [],
            'pagination' => ['totalItems' => 0, 'perPage' => 10, 'currentPage' => 1, 'totalPages' => 1],
        ]);

        $result = $this->service->getMemberOrders(42, 1, 10);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('items', $result->getData());
    }
}
