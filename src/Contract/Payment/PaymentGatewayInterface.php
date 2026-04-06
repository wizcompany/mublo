<?php

namespace Mublo\Contract\Payment;

/**
 * PG(Payment Gateway) 계약 인터페이스
 *
 * ContractRegistry에 등록되어 1:N PG 연동을 지원합니다.
 * 각 PG사(토스, 이니시스 등)는 이 인터페이스를 구현합니다.
 */
interface PaymentGatewayInterface
{
    /**
     * 결제 준비 (PG에 주문 정보 전달, 클라이언트 토큰 반환)
     */
    public function prepare(array $orderData): array;

    /**
     * 결제 검증 (PG 서버에서 실제 결제 확인)
     */
    public function verify(string $transactionId): array;

    /**
     * 결제 취소
     */
    public function cancel(string $transactionId, int $amount, string $reason = ''): array;

    /**
     * 클라이언트 SDK 설정 (프론트에서 PG 결제창 호출에 필요한 정보)
     */
    public function getClientConfig(): array;

    /**
     * 체크아웃 페이지에 삽입할 결제 핸들러 JS
     *
     * 반환한 JS는 체크아웃 페이지에 <script>로 삽입됩니다.
     * JS는 window.MubloPayHandlers['pg_key'] = function(data) {...} 형태로
     * 핸들러를 등록해야 합니다.
     *
     * data 구조: { order_no, amount, gateway, transaction_id, client_config }
     * window.MubloPayReset() → 결제 취소 시 버튼 복원용
     *
     * 특별한 클라이언트 처리가 불필요한 PG는 null을 반환합니다.
     */
    public function getCheckoutScript(): ?string;
}
