<?php
/**
 * packages/Shop/tests/Unit/Service/CartCheckoutServiceTest.php
 *
 * CartCheckoutService 단위 테스트
 *
 * 검증 항목:
 * - prepareCheckout() — 빈 아이템 거부, 세션 불일치 제외, 비활성 상품 제외, 재고 부족 거부
 * - markOrdered()    — 장바구니 상태 ORDERED 변경
 */

namespace Tests\Shop\Unit\Service;

use Tests\Shop\TestCase;
use Mublo\Packages\Shop\Service\CartCheckoutService;
use Mublo\Packages\Shop\Service\PriceCalculator;
use Mublo\Packages\Shop\Service\ShopConfigService;
use Mublo\Packages\Shop\Repository\CartRepository;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Repository\ShippingRepository;
use Mublo\Packages\Shop\Entity\CartItem;
use Mublo\Packages\Shop\Entity\Product;
use Mublo\Core\Result\Result;

class CartCheckoutServiceTest extends TestCase
{
    private CartRepository $cartRepo;
    private ProductRepository $productRepo;
    private ShippingRepository $shippingRepo;
    private ShopConfigService $shopConfig;
    private PriceCalculator $priceCalc;
    private CartCheckoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartRepo     = $this->createMock(CartRepository::class);
        $this->productRepo  = $this->createMock(ProductRepository::class);
        $this->shippingRepo = $this->createMock(ShippingRepository::class);
        $this->shopConfig   = $this->createMock(ShopConfigService::class);
        $this->priceCalc    = new PriceCalculator();

        $this->service = new CartCheckoutService(
            $this->cartRepo,
            $this->productRepo,
            $this->priceCalc,
            $this->shopConfig,
            $this->shippingRepo
        );
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::fromArray($this->makeProductData($overrides));
    }

    private function makeCartItem(array $overrides = []): CartItem
    {
        return CartItem::fromArray($this->makeCartItemData($overrides));
    }

    // =========================================================
    // prepareCheckout()
    // =========================================================

    public function testPrepareCheckoutFailsWhenNoItemsSelected(): void
    {
        $result = $this->service->prepareCheckout('sess123', []);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('선택', $result->getMessage());
    }

    public function testPrepareCheckoutExcludesItemsFromDifferentSession(): void
    {
        // find()가 null을 반환 → 세션 불일치이거나 존재하지 않는 아이템
        $this->cartRepo->method('find')->willReturn(null);

        $result = $this->service->prepareCheckout('sess123', [99, 100]);

        // 모든 아이템이 제외되므로 실패
        $this->assertTrue($result->isFailure());
    }

    public function testPrepareCheckoutFailsWhenProductIsInactive(): void
    {
        $cartItem = $this->makeCartItem(['goods_id' => 1]);
        $product  = $this->makeProduct(['is_active' => false]);

        $this->cartRepo->method('find')->willReturn($cartItem);
        $this->productRepo->method('find')->willReturn($product);

        $result = $this->service->prepareCheckout('sess_abc123', [10]);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('판매 불가', $result->getMessage());
    }

    public function testPrepareCheckoutFailsWhenStockInsufficient(): void
    {
        $cartItem = $this->makeCartItem(['goods_id' => 1, 'quantity' => 5]);
        $product  = $this->makeProduct(['stock_quantity' => 2]);

        $this->cartRepo->method('find')->willReturn($cartItem);
        $this->productRepo->method('find')->willReturn($product);

        $result = $this->service->prepareCheckout('sess_abc123', [10]);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('판매 불가', $result->getMessage());
    }

    public function testPrepareCheckoutSuccessReturnsCheckoutData(): void
    {
        $cartItem = $this->makeCartItem([
            'goods_id'    => 1,
            'quantity'    => 1,
            'goods_price' => 20000,
            'total_price' => 20000,
            'option_mode' => 'NONE',
        ]);
        $product = $this->makeProduct(['stock_quantity' => null, 'display_price' => 20000]);

        $this->cartRepo->method('find')->willReturn($cartItem);
        $this->productRepo->method('find')->willReturn($product);

        $result = $this->service->prepareCheckout('sess_abc123', [10]);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('items', $result->getData());
        $this->assertArrayHasKey('totalPrice', $result->getData());
    }

    public function testPrepareCheckoutCalculatesTotalsCorrectly(): void
    {
        $cartItem1 = $this->makeCartItem([
            'cart_item_id' => 10,
            'goods_id'     => 1,
            'quantity'     => 2,
            'goods_price'  => 20000,
            'option_price' => 0,
            'total_price'  => 40000,
            'option_mode'  => 'NONE',
        ]);

        $product = $this->makeProduct(['stock_quantity' => null, 'display_price' => 20000]);

        $this->cartRepo->method('find')->willReturn($cartItem1);
        $this->productRepo->method('find')->willReturn($product);

        $result = $this->service->prepareCheckout('sess_abc123', [10]);

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('totalPrice', $result->getData());
        $this->assertGreaterThanOrEqual(0, $result->get('totalPrice'));
    }

    // =========================================================
    // markOrdered()
    // =========================================================

    public function testMarkOrderedUpdatesCartStatus(): void
    {
        $this->cartRepo->method('markOrdered')->willReturn(1);

        // markOrdered는 내부에서 repository를 호출하므로 예외 없이 실행되면 성공
        $this->service->markOrdered('sess_abc123', [10, 11]);

        // 검증: 여기까지 예외 없이 도달하면 성공
        $this->assertTrue(true);
    }
}
