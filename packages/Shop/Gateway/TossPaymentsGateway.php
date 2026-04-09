<?php

namespace Mublo\Packages\Shop\Gateway;

use Mublo\Contract\Payment\PaymentGatewayInterface;

/**
 * 토스페이먼츠 PG 게이트웨이
 *
 * ContractRegistry에 'toss' 키로 등록됩니다.
 * 실제 API 연동 시 clientKey/secretKey를 config에서 주입받습니다.
 */
class TossPaymentsGateway implements PaymentGatewayInterface
{
    private string $clientKey;
    private string $secretKey;
    private bool $testMode;

    public function __construct(string $clientKey, string $secretKey, bool $testMode = true)
    {
        $this->clientKey = $clientKey;
        $this->secretKey = $secretKey;
        $this->testMode = $testMode;
    }

    /**
     * {@inheritdoc}
     *
     * 토스 결제 준비 — 클라이언트에서 SDK 호출에 필요한 정보 반환
     */
    public function prepare(array $orderData): array
    {
        return [
            'gateway'        => 'toss',
            'order_no'       => $orderData['order_no'] ?? '',
            'amount'         => (int) ($orderData['amount'] ?? 0),
            'order_name'     => $orderData['order_name'] ?? '주문',
            'customer_name'  => $orderData['customer_name'] ?? '',
            'customer_email' => $orderData['customer_email'] ?? '',
            'client_config'  => $this->getClientConfig(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * 결제 금액 검증 후 승인 API 호출
     */
    public function verify(string $transactionId): array
    {
        // paymentKey, orderId, amount 필요 (토스 콜백에서 전달됨)
        // 실제 구현: POST https://api.tosspayments.com/v1/payments/confirm
        $baseUrl = 'https://api.tosspayments.com/v1/payments/confirm';
        $authorization = 'Basic ' . base64_encode($this->secretKey . ':');

        // transactionId 형식: "{paymentKey}|{orderId}|{amount}"
        [$paymentKey, $orderId, $amount] = explode('|', $transactionId, 3) + ['', '', '0'];

        $payload = json_encode([
            'paymentKey' => $paymentKey,
            'orderId'    => $orderId,
            'amount'     => (int) $amount,
        ]);

        $ch = curl_init($baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $authorization,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true) ?? [];

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => $data['message'] ?? '결제 검증 실패',
                'code'    => $data['code'] ?? 'UNKNOWN',
            ];
        }

        return [
            'success'      => true,
            'payment_key'  => $data['paymentKey'] ?? '',
            'order_id'     => $data['orderId'] ?? '',
            'amount'       => (int) ($data['totalAmount'] ?? 0),
            'method'       => $data['method'] ?? '',
            'approved_at'  => $data['approvedAt'] ?? '',
            'raw'          => $data,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * 결제 취소 API 호출
     */
    public function cancel(string $transactionId, int $amount, string $reason = ''): array
    {
        // paymentKey를 transactionId에서 추출
        $paymentKey = explode('|', $transactionId)[0];
        $url = "https://api.tosspayments.com/v1/payments/{$paymentKey}/cancel";
        $authorization = 'Basic ' . base64_encode($this->secretKey . ':');

        $payload = json_encode([
            'cancelReason' => $reason ?: '고객 요청 취소',
            'cancelAmount' => $amount,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: ' . $authorization,
                'Content-Type: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($response, true) ?? [];

        if ($httpCode !== 200) {
            return [
                'success' => false,
                'message' => $data['message'] ?? '결제 취소 실패',
            ];
        }

        return [
            'success' => true,
            'amount'  => $amount,
            'raw'     => $data,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getClientConfig(): array
    {
        return [
            'clientKey' => $this->clientKey,
            'testMode'  => $this->testMode,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * 토스 결제 SDK 초기화 + 결제창 호출 JS
     */
    public function getCheckoutScript(): ?string
    {
        return <<<'JS'
(function() {
    var existing = document.querySelector('script[src*="tosspayments"]');
    if (!existing) {
        var s = document.createElement('script');
        s.src = 'https://js.tosspayments.com/v1/payment';
        document.head.appendChild(s);
    }

    window.MubloPayHandlers = window.MubloPayHandlers || {};
    window.MubloPayHandlers['toss'] = function(data) {
        var config = data.client_config || {};
        var tossPayments = TossPayments(config.clientKey);

        tossPayments.requestPayment('카드', {
            amount: data.amount,
            orderId: data.order_no,
            orderName: data.order_name || '주문',
            customerName: data.customer_name || '',
            successUrl: window.location.origin + '/shop/checkout/verify?gateway=toss',
            failUrl: window.location.origin + '/shop/cart',
        }).catch(function(error) {
            if (error.code !== 'USER_CANCEL') {
                alert(error.message || '결제 오류가 발생했습니다.');
            }
            if (typeof window.MubloPayReset === 'function') {
                window.MubloPayReset();
            }
        });
    };
})();
JS;
    }
}
