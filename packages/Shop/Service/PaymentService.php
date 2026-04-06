<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Core\Registry\ContractRegistry;
use Mublo\Core\Registry\RegistryNotFoundException;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Repository\PaymentTransactionRepository;
use Mublo\Packages\Shop\Enum\OrderAction;
use Mublo\Packages\Shop\Event\PaymentCompletedEvent;
use Mublo\Packages\Shop\Event\PaymentMismatchEvent;
use Mublo\Contract\Payment\PaymentGatewayInterface;

/**
 * PaymentService
 *
 * 결제 비즈니스 로직 + 이벤트 발행
 *
 * ContractRegistry를 통해 1:N PG 연동을 지원합니다.
 * 각 PG사(토스, 이니시스 등)는 PaymentGatewayInterface를 구현하여
 * ContractRegistry에 등록합니다.
 *
 * 책임:
 * - PG 목록 조회 (메타데이터 기반)
 * - 결제 준비/검증/취소 (PG 인터페이스 호출)
 *
 * 금지:
 * - Request/Response 직접 처리 (Controller 담당)
 * - DB 직접 접근 (Repository 담당)
 * - PG사별 구현 로직 (각 PG 구현체 담당)
 */
class PaymentService
{
    private ContractRegistry $registry;
    private OrderRepository $orderRepository;
    private OrderService $orderService;
    private PriceCalculator $priceCalculator;
    private ?PaymentTransactionRepository $txnRepository;
    private ?EventDispatcher $eventDispatcher;

    public function __construct(
        ContractRegistry $registry,
        OrderRepository $orderRepository,
        OrderService $orderService,
        PriceCalculator $priceCalculator,
        ?PaymentTransactionRepository $txnRepository = null,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->registry = $registry;
        $this->orderRepository = $orderRepository;
        $this->orderService = $orderService;
        $this->priceCalculator = $priceCalculator;
        $this->txnRepository = $txnRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * 이벤트 발행 헬퍼
     */
    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }

    /**
     * 사용 가능한 PG 목록 조회
     *
     * ContractRegistry에 등록된 PaymentGatewayInterface 메타데이터 반환
     * 인스턴스를 resolve하지 않으므로 lazy 등록의 이점을 유지
     *
     * @param array $enabledKeys 활성화된 PG 키 목록 (빈 배열이면 전체 반환)
     * @return Result 성공 시 gateways 배열 포함 (key => meta)
     */
    public function getAvailableGateways(array $enabledKeys = []): Result
    {
        $allMeta = $this->registry->allMeta(PaymentGatewayInterface::class);

        if (!empty($enabledKeys)) {
            $allMeta = array_intersect_key($allMeta, array_flip($enabledKeys));
        }

        return Result::success('', ['gateways' => $allMeta]);
    }

    /**
     * 결제 게이트웨이 키 선택
     *
     * 우선순위:
     * 1) 요청 키
     * 2) 도메인 기본 키
     * 3) 활성 키 중 첫 번째
     * 4) Registry 등록 키 중 첫 번째
     */
    public function selectGatewayKey(
        array $enabledKeys,
        ?string $requestedKey = null,
        ?string $defaultKey = null
    ): ?string {
        $requestedKey = trim((string) $requestedKey);
        $defaultKey = trim((string) $defaultKey);
        $normalizedEnabled = array_values(array_filter(array_map('strval', $enabledKeys)));

        if ($requestedKey !== '' && $this->isSelectableGateway($requestedKey, $normalizedEnabled)) {
            return $requestedKey;
        }

        if ($defaultKey !== '' && $this->isSelectableGateway($defaultKey, $normalizedEnabled)) {
            return $defaultKey;
        }

        foreach ($normalizedEnabled as $enabledKey) {
            if ($this->registry->hasKey(PaymentGatewayInterface::class, $enabledKey)) {
                return $enabledKey;
            }
        }

        foreach (array_keys($this->registry->allMeta(PaymentGatewayInterface::class)) as $registeredKey) {
            if ($this->registry->hasKey(PaymentGatewayInterface::class, $registeredKey)) {
                return (string) $registeredKey;
            }
        }

        return null;
    }

    /**
     * 결제 준비
     *
     * PG에 주문 정보를 전달하고 클라이언트 토큰/결제 정보를 반환
     *
     * @param string $pgKey PG 키 (예: 'tosspay', 'nicepay')
     * @param array $paymentData 결제 데이터 (order_no, amount, buyer_name 등)
     * @return Result 성공 시 PG prepare 응답 데이터 포함
     */
    public function processPayment(string $pgKey, array $paymentData): Result
    {
        // PG 구현체 조회
        $gateway = $this->resolveGateway($pgKey);
        if ($gateway === null) {
            return Result::failure("등록되지 않은 결제 수단입니다: {$pgKey}");
        }

        try {
            $result = $gateway->prepare($paymentData);
        } catch (\Throwable $e) {
            return Result::failure('결제 준비 중 오류가 발생했습니다: ' . $e->getMessage());
        }

        return Result::success('결제가 준비되었습니다.', $result);
    }

    /**
     * 결제 검증 + 상태 전이
     *
     * PG 서버에서 실제 결제가 완료되었는지 검증하고,
     * 성공 시 주문 상태를 PAID로 전이합니다.
     *
     * 소유권, 이중결제 방지, 금액 일치 여부를 함께 검증합니다.
     * 상태 전이 실패 시 PaymentMismatchEvent를 발행하여 관리자 개입을 유도합니다.
     *
     * @param string $pgKey PG 키
     * @param string $transactionId 트랜잭션 ID
     * @param string $orderNo 주문번호
     * @param int $memberId 현재 로그인 회원 ID
     * @param int $domainId 도메인 ID (FSM 상태 전이에 필요)
     * @return Result 성공 시 검증 결과 데이터 포함
     */
    public function verifyPayment(string $pgKey, string $transactionId, string $orderNo, int $memberId, int $domainId): Result
    {
        // 주문 조회
        $order = $this->orderRepository->find($orderNo);
        if (!$order) {
            return Result::failure('주문을 찾을 수 없습니다.');
        }

        $orderArray = $order->toArray();

        // 소유권 검증: 현재 사용자의 주문인지 확인
        if ((int) ($orderArray['member_id'] ?? 0) !== $memberId) {
            return Result::failure('주문 정보에 접근할 수 없습니다.');
        }

        // 이중결제 방지: receipt 상태에서만 결제 검증 허용
        $currentStatus = $orderArray['order_status'] ?? '';
        if ($currentStatus !== OrderAction::RECEIPT->value) {
            return Result::failure('이미 처리된 주문입니다.');
        }

        // PG 키 일치 검증: 주문 생성 시 선택한 PG와 검증 요청 PG가 동일해야 함
        $orderPgKey = $orderArray['payment_gateway'] ?? '';
        if ($orderPgKey !== '' && $pgKey !== $orderPgKey) {
            return Result::failure('결제 수단이 주문 정보와 일치하지 않습니다.');
        }

        // PG 검증
        $gateway = $this->resolveGateway($pgKey);
        if ($gateway === null) {
            return Result::failure("등록되지 않은 결제 수단입니다: {$pgKey}");
        }

        try {
            $result = $gateway->verify($transactionId);
        } catch (\Throwable $e) {
            return Result::failure('결제 검증 중 오류가 발생했습니다: ' . $e->getMessage());
        }

        // PG 응답의 주문번호가 요청 주문번호와 일치하는지 검증
        if (isset($result['order_no']) && $result['order_no'] !== $orderNo) {
            return Result::failure('결제 정보의 주문번호가 일치하지 않습니다.');
        }

        // 금액 검증: PG가 결제 금액을 반환하는 경우 주문 금액과 비교
        if (isset($result['amount'])) {
            $expectedAmount = $this->priceCalculator->calculatePaymentAmount($orderArray);
            if ((int) $result['amount'] !== $expectedAmount) {
                return Result::failure('결제 금액이 일치하지 않습니다.');
            }
        }

        // PAYMENT 트랜잭션 기록 (환불 시 pg_tid 조회에 필요)
        $paymentAmount = $this->priceCalculator->calculatePaymentAmount($orderArray);
        if ($this->txnRepository) {
            $this->txnRepository->createTransaction([
                'order_no' => $orderNo,
                'domain_id' => $domainId,
                'pg_key' => $pgKey,
                'pg_tid' => $result['transaction_id'] ?? $transactionId,
                'pg_approval_no' => $result['approval_no'] ?? null,
                'pg_response' => !empty($result) ? json_encode($result) : null,
                'payment_method' => $orderArray['payment_method'] ?? '',
                'amount' => $paymentAmount,
                'transaction_type' => 'PAYMENT',
                'transaction_status' => 'SUCCESS',
            ]);
        }

        // PG가 실제 결제 수단을 반환한 경우 주문에 반영 (예: TossPay '카드', '가상계좌')
        if (!empty($result['payment_method'])) {
            $this->orderRepository->updatePaymentMethod($orderNo, (string) $result['payment_method']);
        }

        // FSM 상태 전이 (접수 → 결제완료)
        $statusResult = $this->orderService->updateStatus(
            $orderNo, OrderAction::PAID->value, $domainId, '', 'SYSTEM'
        );

        if ($statusResult->isFailure()) {
            // 결제는 완료, 상태 전이 실패 → 관리자 개입 필요
            error_log("[CRITICAL] 결제-상태 불일치: {$orderNo}, PG={$pgKey}, TXN={$transactionId}");
            $this->dispatch(new PaymentMismatchEvent($orderNo, $pgKey, $transactionId, $statusResult->getMessage()));
        } else {
            // 상태 전이 성공 시에만 결제 완료 이벤트 발행 (재고 차감, 적립금 등)
            $this->dispatch(new PaymentCompletedEvent($orderNo, $pgKey, $transactionId, $result));
        }

        return Result::success('결제가 검증되었습니다.', $result);
    }

    /**
     * 결제 취소 (PG 취소 전용)
     *
     * PG를 통해 결제를 취소하고 환불 처리합니다.
     * 주문 상태 전이는 호출자(RefundService, Admin Controller)가 관리합니다.
     *
     * @param string $pgKey PG 키
     * @param string $transactionId 트랜잭션 ID
     * @param int $amount 취소 금액
     * @param string $reason 취소 사유
     * @return Result 성공 시 취소 결과 데이터 포함
     */
    public function cancelPayment(string $pgKey, string $transactionId, int $amount, string $reason = ''): Result
    {
        if ($amount <= 0) {
            return Result::failure('취소 금액은 0보다 커야 합니다.');
        }

        $gateway = $this->resolveGateway($pgKey);
        if ($gateway === null) {
            return Result::failure("등록되지 않은 결제 수단입니다: {$pgKey}");
        }

        try {
            $result = $gateway->cancel($transactionId, $amount, $reason);
        } catch (\Throwable $e) {
            return Result::failure('결제 취소 중 오류가 발생했습니다: ' . $e->getMessage());
        }

        return Result::success('결제가 취소되었습니다.', $result);
    }

    /**
     * PG 클라이언트 설정 조회
     *
     * 프론트에서 PG 결제창을 열기 위한 설정 반환
     *
     * @param string $pgKey PG 키
     * @return array 클라이언트 설정 (mode, requires_redirect 등)
     */
    public function getClientConfig(string $pgKey): array
    {
        $gateway = $this->resolveGateway($pgKey);
        if ($gateway === null) {
            return [];
        }

        return $gateway->getClientConfig();
    }

    /**
     * 활성화된 PG들의 체크아웃 핸들러 JS 수집
     *
     * 각 PG가 getCheckoutScript()로 반환한 JS 문자열 목록.
     * null을 반환한 PG (특별 JS 불필요)는 제외됩니다.
     *
     * @param string[] $pgKeys 활성화된 PG 키 목록
     * @return string[] JS 문자열 배열
     */
    public function collectCheckoutScripts(array $pgKeys): array
    {
        $scripts = [];
        foreach ($pgKeys as $key) {
            $gateway = $this->resolveGateway($key);
            if ($gateway === null) {
                continue;
            }
            $script = $gateway->getCheckoutScript();
            if ($script !== null) {
                $scripts[] = $script;
            }
        }
        return $scripts;
    }

    /**
     * ContractRegistry에서 PG 구현체 조회
     *
     * @param string $pgKey PG 키
     * @return PaymentGatewayInterface|null
     */
    private function resolveGateway(string $pgKey): ?PaymentGatewayInterface
    {
        try {
            $gateway = $this->registry->get(PaymentGatewayInterface::class, $pgKey);
            return $gateway instanceof PaymentGatewayInterface ? $gateway : null;
        } catch (RegistryNotFoundException $e) {
            return null;
        }
    }

    /**
     * 선택 가능한 결제 게이트웨이인지 확인
     *
     * @param string[] $enabledKeys
     */
    private function isSelectableGateway(string $key, array $enabledKeys): bool
    {
        if (!$this->registry->hasKey(PaymentGatewayInterface::class, $key)) {
            return false;
        }

        return empty($enabledKeys) || in_array($key, $enabledKeys, true);
    }
}
