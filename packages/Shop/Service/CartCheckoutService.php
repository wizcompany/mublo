<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Infrastructure\Session\SessionManager;
use Mublo\Packages\Shop\Repository\CartRepository;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Repository\ShippingRepository;
use Mublo\Packages\Shop\Service\PriceCalculator;
use Mublo\Packages\Shop\Service\ShopConfigService;

/**
 * CartCheckoutService
 *
 * 체크아웃/결제 관련 장바구니 로직
 *
 * 책임:
 * - 체크아웃 데이터 준비 (prepareCheckout)
 * - 장바구니 상태 변경 (markOrdered)
 * - 주문용 cart_item_ids 세션 관리
 * - checkout용 합계 계산
 */
class CartCheckoutService
{
    private CartRepository $cartRepository;
    private ProductRepository $productRepository;
    private PriceCalculator $priceCalculator;
    private ShopConfigService $shopConfigService;
    private ShippingRepository $shippingRepository;
    private ?SessionManager $sessionManager;

    public function __construct(
        CartRepository $cartRepository,
        ProductRepository $productRepository,
        PriceCalculator $priceCalculator,
        ShopConfigService $shopConfigService,
        ShippingRepository $shippingRepository,
        ?SessionManager $sessionManager = null
    ) {
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
        $this->priceCalculator = $priceCalculator;
        $this->shopConfigService = $shopConfigService;
        $this->shippingRepository = $shippingRepository;
        $this->sessionManager = $sessionManager;
    }

    /**
     * 체크아웃 준비
     *
     * 선택된 cart_item_ids의 상품 유효성, 재고, 가격 변동을 검증하고
     * 체크아웃 데이터를 반환한다.
     */
    public function prepareCheckout(string $sessionId, array $cartItemIds): Result
    {
        if (empty($cartItemIds)) {
            return Result::failure('선택된 상품이 없습니다.');
        }

        $checkoutItems = [];
        $totalPrice = 0;
        $unavailableItems = [];

        foreach ($cartItemIds as $cartItemId) {
            $cartItem = $this->cartRepository->find($cartItemId);
            if (!$cartItem || $cartItem->getCartSessionId() !== $sessionId) {
                continue;
            }

            $product = $this->productRepository->find($cartItem->getGoodsId());
            if (!$product || !$product->isActive()) {
                $unavailableItems[] = $cartItem->getGoodsId();
                continue;
            }

            if ($product->getStockQuantity() !== null && $product->getStockQuantity() < $cartItem->getQuantity()) {
                $unavailableItems[] = $product->getGoodsName();
                continue;
            }

            // 가격 재검증: 장바구니 저장 시점과 현재 가격 비교
            if (!$cartItem->isExtraOption()) {
                $priceResult = $this->priceCalculator->calculateSalesPrice(
                    $product->getDisplayPrice(),
                    $product->getDiscountType(),
                    $product->getDiscountValue()
                );
                if ($cartItem->getGoodsPrice() !== $priceResult['sales_price']) {
                    $unavailableItems[] = $product->getGoodsName() . ' (가격 변동)';
                    continue;
                }
            }

            $itemData = $cartItem->toArray();
            $itemData['product'] = $product->toArray();

            $checkoutItems[] = $itemData;
            $totalPrice += $cartItem->getTotalPrice();
        }

        if (!empty($unavailableItems)) {
            return Result::failure(
                '일부 상품이 판매 불가 상태입니다: ' . implode(', ', $unavailableItems)
            );
        }

        if (empty($checkoutItems)) {
            return Result::failure('유효한 상품이 없습니다.');
        }

        $totalQuantity = array_sum(array_column($checkoutItems, 'quantity'));
        $shippingFee = $this->estimateShippingFee($totalPrice, $totalQuantity);

        return Result::success('', [
            'items' => $checkoutItems,
            'totalPrice' => $totalPrice,
            'shippingFee' => $shippingFee,
        ]);
    }

    /**
     * 장바구니 상태를 ORDERED로 변경 (결제 확인 후 호출)
     *
     * @param string $sessionId 장바구니 세션 ID
     * @param array $cartItemIds 특정 아이템만 변경 (빈 배열이면 세션 전체)
     */
    public function markOrdered(string $sessionId, array $cartItemIds = []): void
    {
        if (!empty($cartItemIds)) {
            $this->cartRepository->markOrderedByIds($cartItemIds, $sessionId);
        } else {
            $this->cartRepository->markOrdered($sessionId);
        }
    }

    /**
     * 주문 생성 시 사용된 cart_item_ids를 세션에 저장
     *
     * verify()에서 해당 아이템만 ORDERED로 변경하기 위해 사용
     */
    public function saveOrderCartItems(string $orderNo, array $cartItemIds): void
    {
        $this->sessionManager?->set("order_cart_items_{$orderNo}", $cartItemIds);
    }

    /**
     * 주문에 포함된 cart_item_ids 조회 (세션)
     *
     * @return array cart_item_id 배열
     */
    public function getOrderCartItems(string $orderNo): array
    {
        $ids = $this->sessionManager?->get("order_cart_items_{$orderNo}");
        if (is_array($ids)) {
            $this->sessionManager?->remove("order_cart_items_{$orderNo}");
            return $ids;
        }
        return [];
    }

    /**
     * checkout()용 합계 계산 (이미 조회된 아이템 배열 기반)
     */
    public function calculateTotals(array $cartItems): array
    {
        $totalPrice = 0;
        $totalPoint = 0;
        $totalQuantity = 0;

        foreach ($cartItems as $item) {
            $totalPrice += (int) ($item['total_price'] ?? 0);
            $totalPoint += (int) ($item['point_amount'] ?? 0);
            $totalQuantity += (int) ($item['quantity'] ?? 0);
        }

        $shippingFee = $this->estimateShippingFee($totalPrice, $totalQuantity);

        return [
            'totalPrice' => $totalPrice,
            'shippingFee' => $shippingFee,
            'totalPoint' => $totalPoint,
            'totalQuantity' => $totalQuantity,
            'grandTotal' => $totalPrice + $shippingFee,
        ];
    }

    /**
     * 기본 배송비 추정
     */
    private function estimateShippingFee(int $totalPrice, int $totalQuantity): int
    {
        return $this->priceCalculator->estimateDefaultShippingFee($totalPrice, $totalQuantity);
    }
}
