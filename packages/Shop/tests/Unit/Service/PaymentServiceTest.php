<?php
/**
 * packages/Shop/tests/Unit/Service/PaymentServiceTest.php
 *
 * PaymentService 단위 테스트
 *
 * 검증 항목:
 * - getAvailableGateways() — 전체 반환, enabledKeys 필터링
 * - processPayment()       — 미등록 PG 거부, 예외 처리, 성공
 * - verifyPayment()        — 주문 없음, 소유자 불일치, 이미 처리됨, PG 불일치, 금액 불일치, 성공
 * - cancelPayment()        — 금액 0 거부, 미등록 PG 거부, 예외 처리, 성공
 */

namespace Tests\Shop\Unit\Service;

use Tests\Shop\TestCase;
use Mublo\Packages\Shop\Service\PaymentService;
use Mublo\Packages\Shop\Service\OrderService;
use Mublo\Packages\Shop\Service\PriceCalculator;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Repository\PaymentTransactionRepository;
use Mublo\Packages\Shop\Entity\Order;
use Mublo\Core\Registry\ContractRegistry;
use Mublo\Core\Result\Result;
use Mublo\Contract\Payment\PaymentGatewayInterface;

class PaymentServiceTest extends TestCase
{
    private ContractRegistry $registry;
    private OrderRepository $orderRepo;
    private OrderService $orderService;
    private PriceCalculator $priceCalc;
    private PaymentTransactionRepository $txnRepo;
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry     = new ContractRegistry();
        $this->orderRepo    = $this->createMock(OrderRepository::class);
        $this->orderService = $this->createMock(OrderService::class);
        $this->priceCalc    = new PriceCalculator();
        $this->txnRepo      = $this->createMock(PaymentTransactionRepository::class);

        $this->service = new PaymentService(
            $this->registry,
            $this->orderRepo,
            $this->orderService,
            $this->priceCalc,
            $this->txnRepo
        );
    }

    /**
     * 테스트용 PG 목(PaymentGatewayInterface 구현체) 생성
     */
    private function makeMockGateway(array $prepareResult = [], array $verifyResult = [], array $cancelResult = []): PaymentGatewayInterface
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('prepare')->willReturn($prepareResult ?: ['token' => 'tok_test']);
        $gateway->method('verify')->willReturn($verifyResult ?: ['transaction_id' => 'txn_test', 'amount' => 30000]);
        $gateway->method('cancel')->willReturn($cancelResult ?: ['cancelled' => true]);
        $gateway->method('getClientConfig')->willReturn(['mode' => 'test']);
        $gateway->method('getCheckoutScript')->willReturn(null);
        return $gateway;
    }

    /**
     * ContractRegistry에 PG 등록 (메타 포함)
     */
    private function registerGateway(string $key, PaymentGatewayInterface $gateway): void
    {
        $this->registry->register(
            PaymentGatewayInterface::class,
            $key,
            $gateway,
            ['label' => $key . ' 결제']
        );
    }

    // =========================================================
    // getAvailableGateways()
    // =========================================================

    public function testGetAvailableGatewaysReturnsAll(): void
    {
        $this->registerGateway('tosspay', $this->makeMockGateway());
        $this->registerGateway('nicepay', $this->makeMockGateway());

        $result = $this->service->getAvailableGateways();

        $this->assertTrue($result->isSuccess());
        $gateways = $result->get('gateways');
        $this->assertArrayHasKey('tosspay', $gateways);
        $this->assertArrayHasKey('nicepay', $gateways);
    }

    public function testGetAvailableGatewaysFiltersEnabledKeys(): void
    {
        $this->registerGateway('tosspay', $this->makeMockGateway());
        $this->registerGateway('nicepay', $this->makeMockGateway());
        $this->registerGateway('iamport', $this->makeMockGateway());

        $result = $this->service->getAvailableGateways(['tosspay', 'iamport']);

        $this->assertTrue($result->isSuccess());
        $gateways = $result->get('gateways');
        $this->assertArrayHasKey('tosspay', $gateways);
        $this->assertArrayHasKey('iamport', $gateways);
        $this->assertArrayNotHasKey('nicepay', $gateways);
    }

    public function testGetAvailableGatewaysReturnsEmptyWhenNoneRegistered(): void
    {
        $result = $this->service->getAvailableGateways();

        $this->assertTrue($result->isSuccess());
        $this->assertEmpty($result->get('gateways'));
    }

    // =========================================================
    // processPayment()
    // =========================================================

    public function testProcessPaymentFailsWhenPgNotRegistered(): void
    {
        $result = $this->service->processPayment('unknown_pg', ['order_no' => 'ORD001']);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('등록되지 않은', $result->getMessage());
    }

    public function testProcessPaymentFailsWhenPrepareThrows(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('prepare')->willThrowException(new \RuntimeException('PG 연결 실패'));
        $this->registerGateway('tosspay', $gateway);

        $result = $this->service->processPayment('tosspay', ['order_no' => 'ORD001']);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('오류', $result->getMessage());
    }

    public function testProcessPaymentSuccessReturnsPrepareData(): void
    {
        $gateway = $this->makeMockGateway(['token' => 'tok_abc', 'payment_url' => 'https://pg.example.com/pay']);
        $this->registerGateway('tosspay', $gateway);

        $result = $this->service->processPayment('tosspay', [
            'order_no' => 'ORD001',
            'amount'   => 30000,
        ]);

        $this->assertTrue($result->isSuccess());
        $data = $result->getData();
        $this->assertArrayHasKey('token', $data);
    }

    // =========================================================
    // verifyPayment()
    // =========================================================

    public function testVerifyPaymentFailsWhenOrderNotFound(): void
    {
        $this->orderRepo->method('find')->willReturn(null);

        $result = $this->service->verifyPayment('tosspay', 'txn_001', 'ORD001', 42, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('찾을 수 없', $result->getMessage());
    }

    public function testVerifyPaymentFailsWhenMemberMismatch(): void
    {
        $order = Order::fromArray($this->makeOrderData([
            'order_no'     => 'ORD001',
            'member_id'    => 99,          // 다른 회원
            'order_status' => 'RECEIPT',
        ]));
        $this->orderRepo->method('find')->willReturn($order);

        $result = $this->service->verifyPayment('tosspay', 'txn_001', 'ORD001', 42, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('접근', $result->getMessage());
    }

    public function testVerifyPaymentFailsWhenAlreadyProcessed(): void
    {
        $order = Order::fromArray($this->makeOrderData([
            'order_no'     => 'ORD001',
            'member_id'    => 42,
            'order_status' => 'PAID',      // 이미 결제 완료
        ]));
        $this->orderRepo->method('find')->willReturn($order);

        $result = $this->service->verifyPayment('tosspay', 'txn_001', 'ORD001', 42, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('이미 처리', $result->getMessage());
    }

    public function testVerifyPaymentFailsWhenPgKeyMismatch(): void
    {
        $order = Order::fromArray($this->makeOrderData([
            'order_no'       => 'ORD001',
            'member_id'      => 42,
            'order_status'   => 'receipt',
            'payment_gateway'=> 'nicepay',  // 다른 PG로 생성
        ]));
        $this->orderRepo->method('find')->willReturn($order);

        $result = $this->service->verifyPayment('tosspay', 'txn_001', 'ORD001', 42, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('결제 수단', $result->getMessage());
    }

    public function testVerifyPaymentFailsWhenPgNotRegistered(): void
    {
        $order = Order::fromArray($this->makeOrderData([
            'order_no'       => 'ORD001',
            'member_id'      => 42,
            'order_status'   => 'receipt',
            'payment_gateway'=> '',
        ]));
        $this->orderRepo->method('find')->willReturn($order);

        $result = $this->service->verifyPayment('unknown_pg', 'txn_001', 'ORD001', 42, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('등록되지 않은', $result->getMessage());
    }

    public function testVerifyPaymentFailsWhenAmountMismatch(): void
    {
        $order = Order::fromArray($this->makeOrderData([
            'order_no'       => 'ORD001',
            'member_id'      => 42,
            'order_status'   => 'receipt',
            'payment_gateway'=> 'tosspay',
            'total_price'    => 30000,
            'shipping_fee'   => 3000,
        ]));
        $this->orderRepo->method('find')->willReturn($order);

        // PG가 다른 금액 반환
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('verify')->willReturn([
            'transaction_id' => 'txn_001',
            'amount'         => 99999,     // 실제 주문 금액과 다름
        ]);
        $this->registerGateway('tosspay', $gateway);

        $result = $this->service->verifyPayment('tosspay', 'txn_001', 'ORD001', 42, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('금액', $result->getMessage());
    }

    public function testVerifyPaymentSuccessCallsUpdateStatus(): void
    {
        $order = Order::fromArray($this->makeOrderData([
            'order_no'       => 'ORD001',
            'member_id'      => 42,
            'order_status'   => 'receipt',
            'payment_gateway'=> 'tosspay',
        ]));
        $this->orderRepo->method('find')->willReturn($order);

        // PG: 금액 미반환 (검증 스킵 경로)
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('verify')->willReturn(['transaction_id' => 'txn_001']);
        $this->registerGateway('tosspay', $gateway);

        $this->orderService->method('updateStatus')
            ->willReturn(Result::success('상태 변경됨'));
        $this->txnRepo->method('createTransaction')->willReturn(1);
        $this->orderRepo->method('updatePaymentMethod'); // void 반환

        $result = $this->service->verifyPayment('tosspay', 'txn_001', 'ORD001', 42, 1);

        $this->assertTrue($result->isSuccess());
    }

    // =========================================================
    // cancelPayment()
    // =========================================================

    public function testCancelPaymentFailsWhenAmountIsZero(): void
    {
        $result = $this->service->cancelPayment('tosspay', 'txn_001', 0, '고객 요청');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('취소 금액', $result->getMessage());
    }

    public function testCancelPaymentFailsWhenPgNotRegistered(): void
    {
        $result = $this->service->cancelPayment('unknown_pg', 'txn_001', 10000, '고객 요청');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('등록되지 않은', $result->getMessage());
    }

    public function testCancelPaymentFailsWhenCancelThrows(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('cancel')->willThrowException(new \RuntimeException('PG 취소 실패'));
        $this->registerGateway('tosspay', $gateway);

        $result = $this->service->cancelPayment('tosspay', 'txn_001', 10000, '고객 요청');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('오류', $result->getMessage());
    }

    public function testCancelPaymentSuccessReturnsCancelData(): void
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('cancel')->willReturn(['cancelled' => true, 'refund_amount' => 10000]);
        $this->registerGateway('tosspay', $gateway);

        $result = $this->service->cancelPayment('tosspay', 'txn_001', 10000, '단순 변심');

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('취소', $result->getMessage());
        $data = $result->getData();
        $this->assertTrue($data['cancelled'] ?? false);
    }
}
