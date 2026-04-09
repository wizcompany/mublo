<?php
/**
 * packages/Shop/tests/Unit/Service/CartServiceTest.php
 *
 * CartService 단위 테스트
 *
 * 검증 항목:
 * - addToCart()    — 상품 없음 처리, 비활성/재고 없음 처리, 추가 성공, 중복 upsert
 * - updateQuantity() — 0 이하 수량 거부, 재고 초과 거부, 세션 불일치 거부, 성공
 * - removeItem()   — 성공/실패/세션 불일치
 * - getCartList()  — 빈 장바구니, 아이템 포함
 */

namespace Tests\Shop\Unit\Service;

use Tests\Shop\TestCase;
use Mublo\Packages\Shop\Service\CartService;
use Mublo\Packages\Shop\Service\DirectBuyService;
use Mublo\Packages\Shop\Service\PriceCalculator;
use Mublo\Packages\Shop\Service\ShopConfigService;
use Mublo\Packages\Shop\Repository\CartRepository;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Repository\ProductOptionRepository;
use Mublo\Packages\Shop\Repository\ShippingRepository;
use Mublo\Packages\Shop\Entity\CartItem;
use Mublo\Packages\Shop\Entity\Product;

class CartServiceTest extends TestCase
{
    private CartRepository $cartRepo;
    private ProductRepository $productRepo;
    private ProductOptionRepository $optionRepo;
    private ShippingRepository $shippingRepo;
    private ShopConfigService $shopConfig;
    private DirectBuyService $directBuy;
    private PriceCalculator $priceCalc;
    private CartService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cartRepo     = $this->createMock(CartRepository::class);
        $this->productRepo  = $this->createMock(ProductRepository::class);
        $this->optionRepo   = $this->createMock(ProductOptionRepository::class);
        $this->shippingRepo = $this->createMock(ShippingRepository::class);
        $this->shopConfig   = $this->createMock(ShopConfigService::class);
        $this->directBuy    = $this->createMock(DirectBuyService::class);
        $this->priceCalc    = new PriceCalculator();

        $this->service = new CartService(
            $this->cartRepo,
            $this->productRepo,
            $this->optionRepo,
            $this->priceCalc,
            $this->shippingRepo,
            $this->shopConfig,
            $this->directBuy
        );
    }

    private function makeProduct(array $overrides = []): Product
    {
        return Product::fromArray($this->makeProductData($overrides));
    }

    // =========================================================
    // addToCart()
    // =========================================================

    public function testAddToCartFailsWhenProductNotFound(): void
    {
        $this->productRepo->method('find')->willReturn(null);

        $result = $this->service->addToCart([
            'cart_session_id' => 'sess123',
            'member_id'       => 0,
            'domain_id'       => 1,
            'goods_id'        => 999,
            'quantity'        => 1,
        ]);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('상품', $result->getMessage());
    }

    public function testAddToCartFailsWhenProductIsInactive(): void
    {
        $product = $this->makeProduct(['is_active' => false]);
        $this->productRepo->method('find')->willReturn($product);

        $result = $this->service->addToCart([
            'cart_session_id' => 'sess123',
            'member_id'       => 0,
            'domain_id'       => 1,
            'goods_id'        => 1,
            'quantity'        => 1,
        ]);

        $this->assertTrue($result->isFailure());
    }

    public function testAddToCartFailsWhenOutOfStock(): void
    {
        // stock_quantity = 0 → isInStock() = false
        $product = $this->makeProduct(['stock_quantity' => 0]);
        $this->productRepo->method('find')->willReturn($product);

        $result = $this->service->addToCart([
            'cart_session_id' => 'sess123',
            'member_id'       => 0,
            'domain_id'       => 1,
            'goods_id'        => 1,
            'quantity'        => 1,
        ]);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('재고', $result->getMessage());
    }

    public function testAddToCartFailsWhenQuantityExceedsStock(): void
    {
        // stock_quantity = 2, 요청 quantity = 5 → buildCartItems에서 빈 배열 반환
        $product = $this->makeProduct(['stock_quantity' => 2, 'option_mode' => 'NONE']);
        $this->productRepo->method('find')->willReturn($product);
        $this->cartRepo->method('getItems')->willReturn([]);

        $result = $this->service->addToCart([
            'cart_session_id' => 'sess123',
            'member_id'       => 0,
            'domain_id'       => 1,
            'goods_id'        => 1,
            'quantity'        => 5,
        ]);

        $this->assertTrue($result->isFailure());
    }

    public function testAddToCartSuccessfullyAddsNewItem(): void
    {
        $product = $this->makeProduct(['stock_quantity' => 10, 'option_mode' => 'NONE']);
        $this->productRepo->method('find')->willReturn($product);
        $this->cartRepo->method('findDuplicate')->willReturn(null);
        $this->cartRepo->method('addItem')->willReturn(1);
        $this->cartRepo->method('getItems')->willReturn([]);

        $result = $this->service->addToCart([
            'cart_session_id' => 'sess123',
            'member_id'       => 0,
            'domain_id'       => 1,
            'goods_id'        => 1,
            'quantity'        => 2,
        ]);

        $this->assertTrue($result->isSuccess());
    }

    public function testAddToCartUpsertsDuplicateItem(): void
    {
        // 동일 상품이 이미 장바구니에 있으면 수량 증가 (upsert)
        $product   = $this->makeProduct(['stock_quantity' => 10, 'option_mode' => 'NONE']);
        $existItem = CartItem::fromArray($this->makeCartItemData(['cart_item_id' => 5, 'quantity' => 1]));
        $this->productRepo->method('find')->willReturn($product);
        $this->cartRepo->method('findDuplicate')->willReturn(5); // 기존 cart_item_id
        $this->cartRepo->method('find')->willReturn($existItem);
        $this->cartRepo->method('updateQuantity')->willReturn(true);
        $this->cartRepo->method('getItems')->willReturn([]);

        $result = $this->service->addToCart([
            'cart_session_id' => 'sess123',
            'member_id'       => 0,
            'domain_id'       => 1,
            'goods_id'        => 1,
            'quantity'        => 2,
        ]);

        $this->assertTrue($result->isSuccess());
    }

    // =========================================================
    // updateQuantity()
    // updateQuantity(int $cartItemId, int $quantity, string $cartSessionId, int $memberId)
    // =========================================================

    public function testUpdateQuantityFailsWhenZero(): void
    {
        $cartItem = CartItem::fromArray($this->makeCartItemData());
        $this->cartRepo->method('find')->willReturn($cartItem);

        $result = $this->service->updateQuantity(10, 0, 'sess_abc123');

        $this->assertTrue($result->isFailure());
    }

    public function testUpdateQuantityFailsWhenStockInsufficient(): void
    {
        $cartItem = CartItem::fromArray($this->makeCartItemData(['goods_id' => 1]));
        $product  = $this->makeProduct(['stock_quantity' => 3]);
        $this->cartRepo->method('find')->willReturn($cartItem);
        $this->productRepo->method('find')->willReturn($product);

        $result = $this->service->updateQuantity(10, 10, 'sess_abc123');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('재고', $result->getMessage());
    }

    public function testUpdateQuantitySucceeds(): void
    {
        $cartItem = CartItem::fromArray($this->makeCartItemData(['goods_id' => 1]));
        $product  = $this->makeProduct(['stock_quantity' => 100]);
        $this->cartRepo->method('find')->willReturn($cartItem);
        $this->productRepo->method('find')->willReturn($product);
        $this->cartRepo->method('updateQuantity')->willReturn(true);

        $result = $this->service->updateQuantity(10, 3, 'sess_abc123');

        $this->assertTrue($result->isSuccess());
    }

    public function testUpdateQuantityFailsForWrongSession(): void
    {
        // session 불일치 → 수정 거부
        $cartItem = CartItem::fromArray($this->makeCartItemData(['cart_session_id' => 'sess_other']));
        $this->cartRepo->method('find')->willReturn($cartItem);

        $result = $this->service->updateQuantity(10, 1, 'sess_mine');

        $this->assertTrue($result->isFailure());
    }

    // =========================================================
    // removeItem()
    // =========================================================

    public function testRemoveItemSucceeds(): void
    {
        $cartItem = CartItem::fromArray($this->makeCartItemData());
        $this->cartRepo->method('find')->willReturn($cartItem);
        $this->cartRepo->method('removeItem')->willReturn(true);

        $result = $this->service->removeItem(10, 'sess_abc123');

        $this->assertTrue($result->isSuccess());
    }

    public function testRemoveItemFailsWhenNotFound(): void
    {
        $this->cartRepo->method('find')->willReturn(null);

        $result = $this->service->removeItem(99, 'sess_abc123');

        $this->assertTrue($result->isFailure());
    }

    public function testRemoveItemFailsForWrongSession(): void
    {
        $cartItem = CartItem::fromArray($this->makeCartItemData(['cart_session_id' => 'sess_other']));
        $this->cartRepo->method('find')->willReturn($cartItem);

        $result = $this->service->removeItem(10, 'sess_mine');

        $this->assertTrue($result->isFailure());
    }

    // =========================================================
    // getCartList()
    // =========================================================

    public function testGetCartListReturnsEmptyWhenNoItems(): void
    {
        $this->cartRepo->method('getItems')->willReturn([]);

        $result = $this->service->getCartList('sess123', 0);

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->get('items'));
    }

    public function testGetCartListReturnsItemsWithProductInfo(): void
    {
        $cartItem = CartItem::fromArray($this->makeCartItemData(['goods_id' => 1]));
        $product  = $this->makeProduct(['goods_id' => 1]);

        $this->cartRepo->method('getItems')->willReturn([$cartItem]);
        $this->productRepo->method('findByIds')->willReturn([$product]);
        $this->productRepo->method('getMainImages')->willReturn([]);

        $result = $this->service->getCartList('sess123', 0);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(1, $result->get('items'));
    }
}
