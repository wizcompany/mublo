<?php

namespace Mublo\Packages\Shop\Action;

use Mublo\Packages\Shop\Event\OrderStatusChangedEvent;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Repository\ProductOptionRepository;
use Mublo\Infrastructure\Logging\Logger;

/**
 * StockDeductActionHandler
 *
 * 주문 상태 진입 시 주문 아이템의 재고를 자동 차감한다.
 * 관리자가 결제완료, 배송준비 등 원하는 시점에 배치할 수 있다.
 *
 * 재고 차감 대상:
 * - NONE: shop_products.stock_quantity
 * - SINGLE: shop_product_option_values.stock_quantity
 * - COMBINATION: shop_product_option_combos.stock_quantity
 *
 * stock_quantity가 NULL(미관리)인 경우 차감하지 않음.
 * idempotency: shop_order_details.stock_deducted 플래그로 중복 차감 방지.
 */
class StockDeductActionHandler implements ActionHandlerInterface
{
    public function __construct(
        private OrderRepository $orderRepository,
        private ProductRepository $productRepository,
        private ProductOptionRepository $optionRepository,
    ) {}

    public function execute(array $config, OrderStatusChangedEvent $event): void
    {
        $orderNo = $event->getOrderNo();
        $items = $this->orderRepository->getItems($orderNo);

        if (empty($items)) {
            return;
        }

        $deducted = 0;
        foreach ($items as $item) {
            $detailId = (int) ($item['order_detail_id'] ?? 0);
            if ($detailId <= 0) {
                continue;
            }

            // 이미 차감된 아이템 스킵
            if (!empty($item['stock_deducted'])) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 1);
            $optionMode = $item['option_mode'] ?? 'NONE';
            $optionId = (int) ($item['option_id'] ?? 0);
            $goodsId = (int) ($item['goods_id'] ?? 0);

            $this->deductByMode($optionMode, $goodsId, $optionId, $quantity);

            // 차감 완료 플래그
            $this->orderRepository->updateItemFlags($detailId, [
                'stock_deducted' => 1,
            ]);
            $deducted++;
        }

        if ($deducted > 0) {
            Logger::info('OrderStateAction:stock_deduct 재고 차감 완료', [
                'order_no' => $orderNo,
                'items_deducted' => $deducted,
            ]);
        }
    }

    private function deductByMode(string $mode, int $goodsId, int $optionId, int $quantity): void
    {
        $delta = -$quantity;

        switch ($mode) {
            case 'COMBINATION':
                if ($optionId > 0) {
                    $this->optionRepository->adjustComboStock($optionId, $delta);
                }
                break;

            case 'SINGLE':
                if ($optionId > 0) {
                    $this->optionRepository->adjustValueStock($optionId, $delta);
                }
                break;

            default: // NONE
                if ($goodsId > 0) {
                    $this->productRepository->adjustStock($goodsId, $delta);
                }
                break;
        }
    }

    public function getType(): string
    {
        return 'stock_deduct';
    }

    public function getLabel(): string
    {
        return '재고 차감';
    }

    public function getDescription(): string
    {
        return '주문 아이템의 재고를 자동으로 차감합니다. 결제완료, 배송준비 등 원하는 시점에 설정하세요.';
    }

    public function allowDuplicate(): bool
    {
        return false;
    }

    public function getSchema(): array
    {
        return [
            'required' => [],
            'fields' => [],
        ];
    }
}
