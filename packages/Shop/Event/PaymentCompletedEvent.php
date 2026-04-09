<?php

namespace Mublo\Packages\Shop\Event;

use Mublo\Core\Event\AbstractEvent;

/**
 * PaymentCompletedEvent
 *
 * 결제 검증 완료 시 발행되는 이벤트
 *
 * 구독자 활용 예:
 * - 재고 차감 (InventorySubscriber)
 * - 적립금 적립 (PointSubscriber)
 * - 주문 확인 이메일/알림 발송
 */
class PaymentCompletedEvent extends AbstractEvent
{
    public function __construct(
        private readonly string $orderNo,
        private readonly string $pgKey,
        private readonly string $transactionId,
        private readonly array $verifyData,
    ) {}

    public function getOrderNo(): string
    {
        return $this->orderNo;
    }

    public function getPgKey(): string
    {
        return $this->pgKey;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getVerifyData(): array
    {
        return $this->verifyData;
    }
}
