<?php

namespace Mublo\Packages\Shop\Gateway;

use Mublo\Contract\Payment\PaymentGatewayInterface;

/**
 * Mock 결제 게이트웨이 (개발/테스트 전용)
 *
 * ContractRegistry에 'mock' 키로 등록됩니다.
 * 실제 PG 연동 없이 결제 흐름 전체를 테스트할 수 있습니다.
 *
 * 사용법: ShopConfig PG 설정에서 gateway = 'mock' 선택
 */
class MockPaymentGateway implements PaymentGatewayInterface
{
    /**
     * {@inheritdoc}
     *
     * 항상 성공 — transaction_id는 "mock_{order_no}" 형식
     */
    public function prepare(array $orderData): array
    {
        $orderNo = $orderData['order_no'] ?? uniqid('mock_');

        return [
            'gateway'        => 'mock',
            'order_no'       => $orderNo,
            'amount'         => (int) ($orderData['amount'] ?? 0),
            'transaction_id' => 'mock_' . $orderNo,
            'client_config'  => $this->getClientConfig(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * mock_ 접두사가 있으면 항상 성공 처리
     */
    public function verify(string $transactionId): array
    {
        if (!str_starts_with($transactionId, 'mock_')) {
            return [
                'success' => false,
                'message' => '유효하지 않은 Mock 거래 ID',
            ];
        }

        return [
            'success'     => true,
            'payment_key' => $transactionId,
            'order_id'    => str_replace('mock_', '', $transactionId),
            'amount'      => 0, // 검증 불필요 (mock)
            'method'      => 'Mock',
            'approved_at' => date('c'),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * 항상 취소 성공
     */
    public function cancel(string $transactionId, int $amount, string $reason = ''): array
    {
        return [
            'success' => true,
            'amount'  => $amount,
            'reason'  => $reason,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getClientConfig(): array
    {
        return [
            'gateway'  => 'mock',
            'testMode' => true,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Mock 결제는 버튼 클릭 시 즉시 verify 엔드포인트 호출
     */
    public function getCheckoutScript(): ?string
    {
        return <<<'JS'
(function() {
    window.MubloPayHandlers = window.MubloPayHandlers || {};
    window.MubloPayHandlers['mock'] = function(data) {
        // Mock 결제: 즉시 verify 요청 (실제 PG 창 없음)
        MubloRequest.requestJson('/shop/checkout/verify', {
            gateway: 'mock',
            transaction_id: data.transaction_id,
            order_no: data.order_no,
            amount: data.amount,
        }).then(function(res) {
            if (res && res.redirect) {
                window.location.href = res.redirect;
            }
        });
    };
})();
JS;
    }
}
