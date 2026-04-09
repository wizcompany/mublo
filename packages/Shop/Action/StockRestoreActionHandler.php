<?php

namespace Mublo\Packages\Shop\Action;

use Mublo\Packages\Shop\Event\OrderStatusChangedEvent;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Repository\ProductOptionRepository;
use Mublo\Infrastructure\Logging\Logger;

/**
 * StockRestoreActionHandler
 *
 * 주문 취소/반품 등 상태 진입 시 차감된 재고를 자동 복구한다.
 * 관리자가 주문취소, 반품완료 등 원하는 시점에 배치할 수 있다.
 *
 * stock_deducted 플래그가 1인 아이템만 복구 대상.
 * 복구 후 플래그를 0으로 리셋하여 중복 복구 방지.
 */
class StockRestoreActionHandler implements ActionHandlerInterface
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

        $restored = 0;
        foreach ($items as $item) {
            $detailId = (int) ($item['order_detail_id'] ?? 0);
            if ($detailId <= 0) {
                continue;
            }

            // 차감되지 않은 아이템은 복구 대상 아님
            if (empty($item['stock_deducted'])) {
                continue;
            }

            $quantity = (int) ($item['quantity'] ?? 1);
            $optionMode = $item['option_mode'] ?? 'NONE';
            $optionId = (int) ($item['option_id'] ?? 0);
            $goodsId = (int) ($item['goods_id'] ?? 0);

            $this->restoreByMode($optionMode, $goodsId, $optionId, $quantity);

            // 차감 플래그 리셋
            $this->orderRepository->updateItemFlags($detailId, [
                'stock_deducted' => 0,
            ]);
            $restored++;
        }

        if ($restored > 0) {
            Logger::info('OrderStateAction:stock_restore 재고 복구 완료', [
                'order_no' => $orderNo,
                'items_restored' => $restored,
            ]);
        }
    }

    private function restoreByMode(string $mode, int $goodsId, int $optionId, int $quantity): void
    {
        $delta = $quantity; // 양수 = 증가

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
        return 'stock_restore';
    }

    public function getLabel(): string
    {
        return '재고 복구';
    }

    public function getDescription(): string
    {
        return '차감된 재고를 자동으로 복구합니다. 주문취소, 반품완료 등 원하는 시점에 설정하세요.';
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
