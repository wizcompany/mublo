<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Packages\Shop\Repository\PaymentTransactionRepository;
use Mublo\Packages\Shop\Repository\OrderRepository;

/**
 * RefundService
 *
 * 환불 비즈니스 로직
 *
 * 책임:
 * - 환불 금액 검증 (부분/전액)
 * - PG 결제 취소 연동 (PaymentService)
 * - shop_payment_transactions 기록
 * - shop_order_logs 환불 로그
 *
 * 금지:
 * - Request/Response 처리
 * - 주문 상태 전이 (OrderService 담당)
 */
class RefundService
{
    private PaymentService $paymentService;
    private PaymentTransactionRepository $txnRepository;
    private OrderRepository $orderRepository;
    private OrderStateResolver $stateResolver;
    private ?EventDispatcher $eventDispatcher;

    public function __construct(
        PaymentService $paymentService,
        PaymentTransactionRepository $txnRepository,
        OrderRepository $orderRepository,
        OrderStateResolver $stateResolver,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->paymentService = $paymentService;
        $this->txnRepository = $txnRepository;
        $this->orderRepository = $orderRepository;
        $this->stateResolver = $stateResolver;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * 주문 환불 처리 (관리자)
     *
     * @param string $orderNo 주문번호
     * @param int $amount 환불 금액
     * @param string $refundMethod 환불 방법: PG_CANCEL | BANK | POINT
     * @param string $reason 환불 사유
     * @param int $domainId 도메인 ID
     * @param int $staffId 처리 담당자 ID
     * @param array $bankInfo 무통장 환불 시: [bank, account, holder]
     * @return Result
     */
    public function processRefund(
        string $orderNo,
        int $amount,
        string $refundMethod,
        string $reason,
        int $domainId,
        int $staffId = 0,
        array $bankInfo = []
    ): Result {
        // 주문 확인
        $order = $this->orderRepository->find($orderNo);
        if (!$order) {
            return Result::failure('주문을 찾을 수 없습니다.');
        }

        // 도메인 경계 검증
        if ((int) ($order->getDomainId() ?? 0) !== $domainId) {
            return Result::failure('주문을 찾을 수 없습니다.');
        }

        // 금액 검증
        if ($amount <= 0) {
            return Result::failure('환불 금액은 0보다 커야 합니다.');
        }

        $totalPaid = $order->getFinalAmount();
        $totalRefunded = $this->txnRepository->getTotalRefunded($orderNo);
        $refundable = $totalPaid - $totalRefunded;

        if ($amount > $refundable) {
            return Result::failure(
                "환불 가능 금액을 초과했습니다. (환불 가능: "
                . number_format($refundable) . "원)"
            );
        }

        // 트랜잭션 유형 결정
        $txnType = ($amount >= $refundable) ? 'CANCEL' : 'PARTIAL_CANCEL';

        // PG 취소 (원결제 취소)
        $pgResult = [];
        if ($refundMethod === 'PG_CANCEL') {
            $pgKey = $order->getPaymentGateway() ?? '';
            if (empty($pgKey)) {
                return Result::failure('결제 수단 정보가 없어 PG 취소를 진행할 수 없습니다.');
            }

            // 결제 트랜잭션에서 pg_tid 조회
            $pgTid = $this->findPgTid($orderNo);
            if (empty($pgTid)) {
                // pg_tid가 없으면 order_no를 대체 사용 (TestPay 등)
                $pgTid = $orderNo;
            }

            $cancelResult = $this->paymentService->cancelPayment($pgKey, $pgTid, $amount, $reason);
            if ($cancelResult->isFailure()) {
                return Result::failure('PG 결제 취소 실패: ' . $cancelResult->getMessage());
            }

            $pgResult = $cancelResult->getData();
        }

        // 환불 방법 라벨
        $methodLabel = match ($refundMethod) {
            'PG_CANCEL' => '원결제 취소',
            'BANK' => '무통장 환불',
            'POINT' => '포인트 환불',
            default => $refundMethod,
        };

        // shop_payment_transactions 기록
        $txnData = [
            'order_no' => $orderNo,
            'domain_id' => $domainId,
            'pg_key' => ($refundMethod === 'PG_CANCEL') ? ($order->getPaymentGateway() ?? '') : 'manual',
            'pg_tid' => $pgResult['transaction_id'] ?? null,
            'pg_approval_no' => $pgResult['approval_no'] ?? null,
            'pg_response' => !empty($pgResult) ? json_encode($pgResult) : null,
            'payment_method' => $order->getPaymentMethod()->value ?? '',
            'amount' => 0,
            'transaction_type' => $txnType,
            'transaction_status' => 'SUCCESS',
            'cancel_amount' => $amount,
            'cancel_reason' => $reason,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'admin_memo' => "{$methodLabel} ({$amount}원)",
        ];

        // 무통장 환불 시 은행 정보 메모에 추가
        if ($refundMethod === 'BANK' && !empty($bankInfo['bank'])) {
            $txnData['admin_memo'] .= " / {$bankInfo['bank']} {$bankInfo['account']} {$bankInfo['holder']}";
        }

        $txnId = $this->txnRepository->createTransaction($txnData);

        // shop_order_logs 기록
        $this->orderRepository->insertOrderLog([
            'order_no' => $orderNo,
            'prev_status' => '',
            'prev_status_label' => '',
            'new_status' => '',
            'new_status_label' => '',
            'change_type' => 'PAYMENT',
            'changed_by' => 'STAFF',
            'staff_id' => $staffId,
            'reason' => "환불 " . number_format($amount) . "원 ({$methodLabel}): {$reason}",
        ]);

        $newTotalRefunded = $totalRefunded + $amount;

        return Result::success('환불이 처리되었습니다.', [
            'transaction_id' => $txnId,
            'refund_amount' => $amount,
            'refund_method' => $refundMethod,
            'total_refunded' => $newTotalRefunded,
            'remaining' => $totalPaid - $newTotalRefunded,
        ]);
    }

    /**
     * 주문의 환불 가능 금액 조회
     */
    public function getRefundableAmount(string $orderNo): Result
    {
        $order = $this->orderRepository->find($orderNo);
        if (!$order) {
            return Result::failure('주문을 찾을 수 없습니다.');
        }

        $totalPaid = $order->getFinalAmount();
        $totalRefunded = $this->txnRepository->getTotalRefunded($orderNo);

        return Result::success('', [
            'total_paid' => $totalPaid,
            'total_refunded' => $totalRefunded,
            'refundable' => $totalPaid - $totalRefunded,
        ]);
    }

    /**
     * 주문의 결제 트랜잭션 이력
     */
    public function getTransactionHistory(string $orderNo): array
    {
        return $this->txnRepository->getByOrderNo($orderNo);
    }

    /**
     * 결제 트랜잭션에서 PG TID 조회
     */
    private function findPgTid(string $orderNo): string
    {
        $transactions = $this->txnRepository->getByOrderNo($orderNo);

        foreach ($transactions as $txn) {
            if (($txn['transaction_type'] ?? '') === 'PAYMENT'
                && ($txn['transaction_status'] ?? '') === 'SUCCESS'
                && !empty($txn['pg_tid'])
            ) {
                return $txn['pg_tid'];
            }
        }

        return '';
    }
}
