<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Infrastructure\Session\SessionManager;
use Mublo\Packages\Shop\Entity\Product;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Service\PriceCalculator;

/**
 * DirectBuyService
 *
 * 바로구매 세션 관리
 *
 * 책임:
 * - 바로구매 세션 저장/조회/삭제
 * - 세션 조회 시 가격 재검증
 */
class DirectBuyService
{
    private ProductRepository $productRepository;
    private PriceCalculator $priceCalculator;
    private ?SessionManager $sessionManager;

    public function __construct(
        ProductRepository $productRepository,
        PriceCalculator $priceCalculator,
        ?SessionManager $sessionManager = null
    ) {
        $this->productRepository = $productRepository;
        $this->priceCalculator = $priceCalculator;
        $this->sessionManager = $sessionManager;
    }

    /**
     * 바로구매 세션 저장
     */
    public function processDirectBuy(string $sessionId, int $memberId, array $cartItems, Product $product): Result
    {
        if (!$this->sessionManager) {
            return Result::failure('세션 관리자를 사용할 수 없습니다.');
        }

        $totalPrice = 0;
        $totalQuantity = 0;
        $enrichedItems = [];

        $mainImages = $this->productRepository->getMainImages([$product->getGoodsId()]);

        foreach ($cartItems as $item) {
            $totalPrice += $item['total_price'];
            $totalQuantity += $item['quantity'];

            $item['product'] = [
                'goods_id' => $product->getGoodsId(),
                'goods_name' => $product->getGoodsName(),
            ];
            $item['product_image'] = $mainImages[$product->getGoodsId()] ?? null;

            $enrichedItems[] = $item;
        }

        $shippingFee = $this->priceCalculator->estimateDefaultShippingFee($totalPrice, $totalQuantity);

        $this->sessionManager->set('shop_direct_buy', [
            'cart_session_id' => $sessionId,
            'member_id' => $memberId,
            'items' => $enrichedItems,
            'totalPrice' => $totalPrice,
            'shippingFee' => $shippingFee,
            'created_at' => time(),
        ]);

        return Result::success('바로구매 준비가 완료되었습니다.', [
            'redirect' => '/shop/checkout?mode=direct',
        ]);
    }

    /**
     * 바로구매 세션 데이터 조회
     *
     * 조회 시 상품 가격을 재검증하여 가격 변동 시 null 반환
     */
    public function getDirectBuyData(): ?array
    {
        if (!$this->sessionManager) {
            return null;
        }

        $data = $this->sessionManager->get('shop_direct_buy');
        if (!$data || !is_array($data)) {
            return null;
        }

        // 30분 만료
        if (time() - ($data['created_at'] ?? 0) > 1800) {
            $this->sessionManager->remove('shop_direct_buy');
            return null;
        }

        // 가격 재검증: 세션 저장 시점과 현재 가격 비교
        $items = $data['items'] ?? [];
        if (!empty($items)) {
            $goodsId = (int) ($items[0]['goods_id'] ?? 0);
            if ($goodsId > 0) {
                $product = $this->productRepository->find($goodsId);
                if (!$product || !$product->isActive()) {
                    $this->sessionManager->remove('shop_direct_buy');
                    return null;
                }

                // EXTRA 옵션이 아닌 첫 번째 아이템의 가격 비교
                foreach ($items as $item) {
                    if (($item['option_type'] ?? '') === 'EXTRA') {
                        continue;
                    }
                    $priceResult = $this->priceCalculator->calculateSalesPrice(
                        $product->getDisplayPrice(),
                        $product->getDiscountType(),
                        $product->getDiscountValue()
                    );
                    if ((int) ($item['goods_price'] ?? 0) !== $priceResult['sales_price']) {
                        $this->sessionManager->remove('shop_direct_buy');
                        return null;
                    }
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * 바로구매 세션 데이터 삭제
     */
    public function clearDirectBuyData(): void
    {
        $this->sessionManager?->remove('shop_direct_buy');
    }
}
