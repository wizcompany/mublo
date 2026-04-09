<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Packages\Shop\Enum\DiscountType;
use Mublo\Packages\Shop\Enum\RewardType;
use Mublo\Packages\Shop\Enum\ShippingMethod;

class PriceCalculator
{
    /**
     * Calculate sale price after discount
     * Rule: value >= 100 → fixed amount, value < 100 → percentage
     * @return array ['sales_price', 'discount_amount', 'discount_percent']
     */
    public function calculateSalesPrice(int $displayPrice, DiscountType $type, float $value, array $shopConfig = []): array
    {
        if (!$type->isApplicable() || $value <= 0) {
            return ['sales_price' => $displayPrice, 'discount_amount' => 0, 'discount_percent' => 0];
        }

        // BASIC uses config default value
        if ($type === DiscountType::BASIC) {
            $value = (float) ($shopConfig['discount_value'] ?? 0);
            if ($value <= 0) {
                return ['sales_price' => $displayPrice, 'discount_amount' => 0, 'discount_percent' => 0];
            }
        }

        // Calculate discount
        if ($value >= 100) {
            // Fixed amount
            $discountAmount = (int) $value;
            $salesPrice = max(0, $displayPrice - $discountAmount);
            $discountPercent = $displayPrice > 0 ? round(($discountAmount / $displayPrice) * 100) : 0;
        } else {
            // Percentage
            $discountPercent = $value;
            $discountAmount = (int) round($displayPrice * ($value / 100));
            $salesPrice = max(0, $displayPrice - $discountAmount);
        }

        return [
            'sales_price' => $salesPrice,
            'discount_amount' => $discountAmount,
            'discount_percent' => (int) $discountPercent,
        ];
    }

    /**
     * Calculate reward points
     * Same rule: value >= 100 → fixed, value < 100 → percentage
     * @return array ['point_amount', 'reward_percent']
     */
    public function calculateRewardPoints(int $salesPrice, RewardType $type, float $value, array $shopConfig = []): array
    {
        if (!$type->isApplicable() || $value <= 0) {
            return ['point_amount' => 0, 'reward_percent' => 0];
        }

        if ($type === RewardType::BASIC) {
            $value = (float) ($shopConfig['reward_value'] ?? 0);
            if ($value <= 0) {
                return ['point_amount' => 0, 'reward_percent' => 0];
            }
        }

        if ($value >= 100) {
            $pointAmount = (int) $value;
            $rewardPercent = $salesPrice > 0 ? round(($pointAmount / $salesPrice) * 100) : 0;
        } else {
            $rewardPercent = $value;
            $pointAmount = (int) round($salesPrice * ($value / 100));
        }

        return [
            'point_amount' => $pointAmount,
            'reward_percent' => (int) $rewardPercent,
        ];
    }

    /**
     * Calculate shipping fee based on template
     * @return int shipping fee
     */
    public function calculateShippingFee(array $template, int $totalPrice, int $quantity): int
    {
        $method = ShippingMethod::tryFrom($template['shipping_method'] ?? '') ?? ShippingMethod::FREE;
        $basicCost = (int) ($template['basic_cost'] ?? 0);
        $freeThreshold = (int) ($template['free_threshold'] ?? 0);

        return match ($method) {
            ShippingMethod::FREE => 0,
            ShippingMethod::PAID => $basicCost,
            ShippingMethod::COND => ($freeThreshold > 0 && $totalPrice >= $freeThreshold) ? 0 : $basicCost,
            ShippingMethod::QUANTITY => $this->calculateByQuantity($template, $quantity),
            ShippingMethod::AMOUNT => $this->calculateByAmount($template, $totalPrice),
        };
    }

    private function calculateByQuantity(array $template, int $quantity): int
    {
        $basicCost = (int) ($template['basic_cost'] ?? 0);
        $perUnit = (int) ($template['goods_per_unit'] ?? 1);
        if ($perUnit <= 0) $perUnit = 1;
        return $basicCost * (int) ceil($quantity / $perUnit);
    }

    private function calculateByAmount(array $template, int $totalPrice): int
    {
        $ranges = $template['price_ranges'] ?? [];
        if (is_string($ranges)) {
            $ranges = json_decode($ranges, true) ?: [];
        }
        foreach ($ranges as $range) {
            $min = (int) ($range['min'] ?? 0);
            $max = (int) ($range['max'] ?? PHP_INT_MAX);
            if ($totalPrice >= $min && $totalPrice <= $max) {
                return (int) ($range['cost'] ?? 0);
            }
        }
        return (int) ($template['basic_cost'] ?? 0);
    }

    /**
     * 배송 템플릿 없을 때 기본 배송비 추정
     *
     * shopConfig에서 기본값을 읽고, 없으면 하드코딩 기본값 사용.
     */
    public function estimateDefaultShippingFee(int $totalPrice, int $totalQuantity, array $shopConfig = []): int
    {
        $defaultTemplate = [
            'shipping_method' => 'COND',
            'basic_cost' => (int) ($shopConfig['default_shipping_cost'] ?? 3000),
            'free_threshold' => (int) ($shopConfig['free_shipping_threshold'] ?? 50000),
        ];

        return $this->calculateShippingFee($defaultTemplate, $totalPrice, $totalQuantity);
    }

    /**
     * 주문의 최종 결제 금액 계산
     *
     * 상품 합계 + 배송비 + 추가금액 + 세금 - 포인트 - 쿠폰
     *
     * @param array $orderData 주문 데이터 (total_price, shipping_fee, extra_price, tax_amount, point_used, coupon_discount)
     */
    public function calculatePaymentAmount(array $orderData): int
    {
        return (int) ($orderData['total_price'] ?? 0)
             + (int) ($orderData['shipping_fee'] ?? 0)
             + (int) ($orderData['extra_price'] ?? 0)
             + (int) ($orderData['tax_amount'] ?? 0)
             - (int) ($orderData['point_used'] ?? 0)
             - (int) ($orderData['coupon_discount'] ?? 0);
    }
}
