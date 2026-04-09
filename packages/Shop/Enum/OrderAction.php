<?php

namespace Mublo\Packages\Shop\Enum;

/**
 * OrderAction
 *
 * 주문 상태의 시스템 액션 정의
 *
 * 설계:
 * - 시스템 액션: 고정 코드값, 자동 처리(재고/환불/적립금) 수행 훅
 * - 커스텀 액션: 시스템 동작 없음, 관리 목적 사용자 추가 단계
 * - 라벨은 shop_config.order_states JSON에서 관리 (사용자 변경 가능)
 * - 전이 규칙은 Config가 관리 (from/to), Enum은 액션 코드값의 단일 소스
 */
enum OrderAction: string
{
    case RECEIPT = 'receipt';
    case PAID = 'paid';
    case PREPARING = 'preparing';
    case SHIPPING = 'shipping';
    case DELIVERED = 'delivered';
    case CONFIRMED = 'confirmed';
    case CANCEL_REQUESTED = 'cancel_requested';
    case CANCELLED = 'cancelled';
    case RETURN_REQUESTED = 'return_requested';
    case RETURNED = 'returned';
    case CUSTOM = 'custom';

    /**
     * 기본 라벨 (shop_config에 설정이 없을 때 사용)
     */
    public function defaultLabel(): string
    {
        return match ($this) {
            self::RECEIPT => '주문접수',
            self::PAID => '결제완료',
            self::PREPARING => '배송준비',
            self::SHIPPING => '배송중',
            self::DELIVERED => '배송완료',
            self::CONFIRMED => '구매확정',
            self::CANCEL_REQUESTED => '취소요청',
            self::CANCELLED => '주문취소',
            self::RETURN_REQUESTED => '반품요청',
            self::RETURNED => '반품완료',
            self::CUSTOM => '사용자 정의',
        };
    }

    /**
     * 기본 설명 (관리자 설정 UI에서 사용)
     */
    public function defaultDescription(): string
    {
        return match ($this) {
            self::RECEIPT => '고객이 주문을 완료한 초기 상태',
            self::PAID => '결제가 확인된 상태',
            self::PREPARING => '상품 포장 및 출고 준비 중',
            self::SHIPPING => '택배사에 인계되어 배송 중',
            self::DELIVERED => '고객에게 배송이 완료된 상태',
            self::CONFIRMED => '고객이 구매를 확정한 최종 상태',
            self::CANCEL_REQUESTED => '고객이 주문 취소를 요청한 상태',
            self::CANCELLED => '주문이 취소 처리 완료된 상태',
            self::RETURN_REQUESTED => '고객이 반품을 요청한 상태',
            self::RETURNED => '반품이 처리 완료된 상태',
            self::CUSTOM => '',
        };
    }

    /**
     * 시스템 액션 여부 (삭제 불가)
     */
    public function isSystem(): bool
    {
        return $this !== self::CUSTOM;
    }

    /**
     * 활성 주문 여부 (취소/반품이 아닌 상태)
     */
    public function isActive(): bool
    {
        return !in_array($this, [self::CANCELLED, self::RETURNED]);
    }

    /**
     * 취소 가능 여부
     */
    public function isCancellable(): bool
    {
        return in_array($this, [self::RECEIPT, self::PAID]);
    }

    /**
     * 배송 중/후 상태
     */
    public function isShipped(): bool
    {
        return in_array($this, [self::SHIPPING, self::DELIVERED, self::CONFIRMED]);
    }

    /**
     * 시스템 액션만 반환 (CUSTOM 제외)
     */
    public static function systemCases(): array
    {
        return array_filter(self::cases(), fn(self $case) => $case->isSystem());
    }

    /**
     * 기본 주문 상태 JSON 배열 생성 (FSM 새 스키마)
     *
     * shop_config 초기값 또는 설정이 없을 때 사용.
     * to/terminal/system/id/description 포함.
     */
    public static function defaultStates(): array
    {
        return [
            [
                'id' => 'receipt',
                'label' => '주문접수',
                'description' => '고객이 주문을 완료한 초기 상태',
                'action' => 'receipt',
                'to' => ['paid', 'cancel_requested', 'cancelled'],
                'terminal' => false,
                'delivery_editable' => false,
                'system' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 'paid',
                'label' => '결제완료',
                'description' => '결제가 확인된 상태',
                'action' => 'paid',
                'to' => ['preparing', 'cancel_requested', 'cancelled'],
                'terminal' => false,
                'delivery_editable' => false,
                'system' => true,
                'sort_order' => 2,
            ],
            [
                'id' => 'preparing',
                'label' => '배송준비',
                'description' => '상품 포장 및 출고 준비 중',
                'action' => 'preparing',
                'to' => ['shipping'],
                'terminal' => false,
                'delivery_editable' => true,
                'system' => true,
                'sort_order' => 3,
            ],
            [
                'id' => 'shipping',
                'label' => '배송중',
                'description' => '택배사에 인계되어 배송 중',
                'action' => 'shipping',
                'to' => ['delivered'],
                'terminal' => false,
                'delivery_editable' => true,
                'system' => true,
                'sort_order' => 4,
            ],
            [
                'id' => 'delivered',
                'label' => '배송완료',
                'description' => '고객에게 배송이 완료된 상태',
                'action' => 'delivered',
                'to' => ['confirmed', 'return_requested'],
                'terminal' => false,
                'delivery_editable' => false,
                'system' => true,
                'sort_order' => 5,
            ],
            [
                'id' => 'confirmed',
                'label' => '구매확정',
                'description' => '고객이 구매를 확정한 최종 상태',
                'action' => 'confirmed',
                'to' => [],
                'terminal' => true,
                'delivery_editable' => false,
                'system' => true,
                'sort_order' => 6,
            ],
            [
                'id' => 'cancel_requested',
                'label' => '취소요청',
                'description' => '고객이 주문 취소를 요청한 상태',
                'action' => 'cancel_requested',
                'to' => ['cancelled'],
                'terminal' => false,
                'delivery_editable' => false,
                'system' => true,
                'sort_order' => 7,
            ],
            [
                'id' => 'cancelled',
                'label' => '주문취소',
                'description' => '주문이 취소 처리 완료된 상태',
                'action' => 'cancelled',
                'to' => [],
                'terminal' => true,
                'delivery_editable' => false,
                'system' => true,
                'sort_order' => 8,
            ],
            [
                'id' => 'return_requested',
                'label' => '반품요청',
                'description' => '고객이 반품을 요청한 상태',
                'action' => 'return_requested',
                'to' => ['returned'],
                'terminal' => false,
                'delivery_editable' => false,
                'system' => true,
                'sort_order' => 9,
            ],
            [
                'id' => 'returned',
                'label' => '반품완료',
                'description' => '반품이 처리 완료된 상태',
                'action' => 'returned',
                'to' => [],
                'terminal' => true,
                'delivery_editable' => false,
                'system' => true,
                'sort_order' => 10,
            ],
        ];
    }

    /**
     * 종료 상태 여부 (더 이상 전이 불가)
     *
     * @deprecated Config의 terminal 필드를 사용하세요. OrderStateResolver::getState()['terminal']
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::CONFIRMED, self::CANCELLED, self::RETURNED]);
    }

    /**
     * 전이 가능 액션 목록
     *
     * @deprecated Config의 from/to 필드를 사용하세요. OrderStateResolver::canTransition()
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::RECEIPT => [self::PAID, self::CANCEL_REQUESTED, self::CANCELLED],
            self::PAID => [self::PREPARING, self::CANCEL_REQUESTED, self::CANCELLED],
            self::PREPARING => [self::SHIPPING],
            self::SHIPPING => [self::DELIVERED],
            self::DELIVERED => [self::CONFIRMED, self::RETURN_REQUESTED],
            self::CANCEL_REQUESTED => [self::CANCELLED],
            self::RETURN_REQUESTED => [self::RETURNED],
            self::CONFIRMED, self::CANCELLED, self::RETURNED => [],
            self::CUSTOM => [],
        };
    }

    /**
     * options 배열 (드롭다운용)
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            if ($case === self::CUSTOM) {
                continue;
            }
            $options[$case->value] = $case->defaultLabel();
        }
        return $options;
    }
}
