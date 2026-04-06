<?php
/**
 * tests/Unit/Service/Balance/BalanceManagerTest.php
 *
 * BalanceManager 테스트
 *
 * 포인트/잔액 중앙 관리 서비스
 */

namespace Tests\Unit\Service\Balance;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Mublo\Service\Balance\BalanceManager;
use Mublo\Repository\Balance\BalanceLogRepository;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Entity\Balance\BalanceLog;
use Mublo\Infrastructure\Database\Database;
use Mublo\Core\Event\EventDispatcher;

class BalanceManagerTest extends TestCase
{
    private BalanceManager $service;
    private MockObject $logRepositoryMock;
    private MockObject $memberRepositoryMock;
    private MockObject $dbMock;
    private MockObject $eventDispatcherMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logRepositoryMock = $this->createMock(BalanceLogRepository::class);
        $this->memberRepositoryMock = $this->createMock(MemberRepository::class);
        $this->dbMock = $this->createMock(Database::class);
        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);

        $this->service = new BalanceManager(
            $this->logRepositoryMock,
            $this->memberRepositoryMock,
            $this->dbMock,
            $this->eventDispatcherMock
        );
    }

    /**
     * 샘플 BalanceLog Entity 생성
     */
    private function createBalanceLogMock(int $logId, int $amount, int $balanceAfter): BalanceLog
    {
        return BalanceLog::fromArray([
            'log_id' => $logId,
            'domain_id' => 1,
            'member_id' => 100,
            'amount' => $amount,
            'balance_before' => $balanceAfter - $amount,
            'balance_after' => $balanceAfter,
            'source_type' => 'plugin',
            'source_name' => 'MemberPoint',
            'action' => 'article_write',
            'message' => '게시글 작성 보상',
            'reference_type' => 'article',
            'reference_id' => '123',
            'ip_address' => '192.168.1.1',
            'admin_id' => null,
            'memo' => null,
            'idempotency_key' => null,
            'created_at' => '2025-01-29 10:00:00',
        ]);
    }

    /**
     * 유효한 adjust 파라미터 (domain_id 포함)
     */
    private function validAdjustParams(array $overrides = []): array
    {
        return array_merge([
            'domain_id' => 1,
            'member_id' => 100,
            'amount' => 500,
            'source_type' => 'plugin',
            'source_name' => 'MemberPoint',
            'action' => 'article_write',
            'message' => '게시글 작성 보상',
        ], $overrides);
    }

    // ========================================
    // adjust() - 잔액 조정 (핵심)
    // ========================================

    /**
     * adjust: 포인트 지급 성공
     */
    public function testAdjustAddsPointsSuccessfully(): void
    {
        $params = $this->validAdjustParams();

        // DB 트랜잭션 Mock
        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->once())->method('commit');

        // 현재 잔액 조회 (SELECT FOR UPDATE, domainId 포함)
        $this->memberRepositoryMock->expects($this->once())
            ->method('getBalanceForUpdate')
            ->with(100, 1)
            ->willReturn(1000);

        // 로그 생성
        $this->logRepositoryMock->expects($this->once())
            ->method('create')
            ->willReturn(1);

        // 잔액 업데이트
        $this->memberRepositoryMock->expects($this->once())
            ->method('updateBalance')
            ->with(100, 1500)
            ->willReturn(true);

        // 이벤트 dispatch
        $this->eventDispatcherMock->method('dispatch')
            ->willReturnArgument(0);

        $result = $this->service->adjust($params);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1, $result->get('log_id'));
        $this->assertSame(1000, $result->get('balance_before'));
        $this->assertSame(1500, $result->get('balance_after'));
    }

    /**
     * adjust: 포인트 차감 성공
     */
    public function testAdjustDeductsPointsSuccessfully(): void
    {
        $params = $this->validAdjustParams(['amount' => -300]);

        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->once())->method('commit');

        $this->memberRepositoryMock->expects($this->once())
            ->method('getBalanceForUpdate')
            ->with(100, 1)
            ->willReturn(1000);

        $this->logRepositoryMock->expects($this->once())
            ->method('create')
            ->willReturn(2);

        $this->memberRepositoryMock->expects($this->once())
            ->method('updateBalance')
            ->with(100, 700)
            ->willReturn(true);

        $this->eventDispatcherMock->method('dispatch')
            ->willReturnArgument(0);

        $result = $this->service->adjust($params);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(700, $result->get('balance_after'));
    }

    /**
     * adjust: 잔액 부족 시 실패
     */
    public function testAdjustFailsWhenInsufficientBalance(): void
    {
        $params = $this->validAdjustParams(['amount' => -2000]);

        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->once())->method('rollBack');

        $this->memberRepositoryMock->expects($this->once())
            ->method('getBalanceForUpdate')
            ->with(100, 1)
            ->willReturn(1000);

        $result = $this->service->adjust($params);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('잔액이 부족합니다', $result->getMessage());
    }

    /**
     * adjust: 회원 없음 실패
     */
    public function testAdjustFailsWhenMemberNotFound(): void
    {
        $params = $this->validAdjustParams(['member_id' => 999]);

        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->once())->method('rollBack');

        $this->memberRepositoryMock->expects($this->once())
            ->method('getBalanceForUpdate')
            ->with(999, 1)
            ->willReturn(null);

        $result = $this->service->adjust($params);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('회원을 찾을 수 없습니다', $result->getMessage());
    }

    /**
     * adjust: 멱등성 키로 중복 방지
     */
    public function testAdjustReturnsExistingResultForIdempotencyKey(): void
    {
        $existingLog = $this->createBalanceLogMock(1, 500, 1500);

        $params = $this->validAdjustParams([
            'idempotency_key' => 'article_write_123',
        ]);

        // domainId=1 스코프로 멱등성 키 조회
        $this->logRepositoryMock->expects($this->once())
            ->method('findByIdempotencyKey')
            ->with('article_write_123', 1)
            ->willReturn($existingLog);

        $result = $this->service->adjust($params);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->get('idempotent'));
        $this->assertSame(1, $result->get('log_id'));
        $this->assertSame(1500, $result->get('balance_after'));
    }

    /**
     * adjust: 필수 필드 누락 실패 (domain_id 포함)
     */
    public function testAdjustFailsWhenRequiredFieldMissing(): void
    {
        $params = [
            'domain_id' => 1,
            'member_id' => 100,
            // amount 누락
            'source_type' => 'plugin',
            'source_name' => 'MemberPoint',
        ];

        $result = $this->service->adjust($params);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('필수 필드', $result->getMessage());
    }

    /**
     * adjust: domain_id 누락 시 validation 실패
     */
    public function testAdjustFailsWhenDomainIdMissing(): void
    {
        $params = [
            'member_id' => 100,
            'amount' => 500,
            'source_type' => 'plugin',
            'source_name' => 'MemberPoint',
            'action' => 'test',
            'message' => '테스트',
        ];

        $result = $this->service->adjust($params);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('필수 필드', $result->getMessage());
    }

    // ========================================
    // getBalance() - 잔액 조회
    // ========================================

    /**
     * getBalance: 회원 잔액 조회 (domainId 포함)
     */
    public function testGetBalanceReturnsCurrentBalance(): void
    {
        $this->memberRepositoryMock->expects($this->once())
            ->method('getBalance')
            ->with(100)
            ->willReturn(5000);

        $balance = $this->service->getBalance(100);

        $this->assertSame(5000, $balance);
    }

    /**
     * getBalance: 회원 없으면 0 반환
     */
    public function testGetBalanceReturnsZeroWhenNotFound(): void
    {
        $this->memberRepositoryMock->expects($this->once())
            ->method('getBalance')
            ->with(999)
            ->willReturn(0);

        $balance = $this->service->getBalance(999);

        $this->assertSame(0, $balance);
    }

    // ========================================
    // getHistory() - 이력 조회
    // ========================================

    /**
     * getHistory: 정상 조회
     */
    public function testGetHistoryReturnsLogs(): void
    {
        $logs = [
            $this->createBalanceLogMock(1, 500, 500),
            $this->createBalanceLogMock(2, -100, 400),
        ];

        $this->logRepositoryMock->expects($this->once())
            ->method('getByMember')
            ->with(100, 1, 20)
            ->willReturn($logs);

        $this->logRepositoryMock->expects($this->once())
            ->method('countByMember')
            ->with(100)
            ->willReturn(2);

        $result = $this->service->getHistory(100, [], 1, 20);

        $this->assertArrayHasKey('items', $result);
        $this->assertCount(2, $result['items']);
    }

    /**
     * getHistory: 빈 결과
     */
    public function testGetHistoryReturnsEmptyArray(): void
    {
        $this->logRepositoryMock->expects($this->once())
            ->method('getByMember')
            ->with(100, 1, 20)
            ->willReturn([]);

        $this->logRepositoryMock->expects($this->once())
            ->method('countByMember')
            ->with(100)
            ->willReturn(0);

        $result = $this->service->getHistory(100, [], 1, 20);

        $this->assertEmpty($result['items']);
    }

    // ========================================
    // repair() - 무결성 복구
    // ========================================

    /**
     * repair: 불일치 발견 시 복구 성공
     */
    public function testRepairFixesMismatch(): void
    {
        $this->logRepositoryMock->method('getSumByMember')
            ->with(100)
            ->willReturn(1500);

        $this->memberRepositoryMock->method('getBalance')
            ->with(100)
            ->willReturn(1400);

        // 트랜잭션 Mock
        $this->dbMock->expects($this->once())->method('beginTransaction');
        $this->dbMock->expects($this->once())->method('commit');

        // 복구 로그 생성
        $this->logRepositoryMock->expects($this->once())
            ->method('create')
            ->willReturn(10);

        // 스냅샷 업데이트
        $this->memberRepositoryMock->expects($this->once())
            ->method('updateBalance')
            ->with(100, 1500)
            ->willReturn(true);

        // repair(memberId, domainId, adminId, reason) — 4인자 시그니처
        $result = $this->service->repair(100, 1, 1, '테스트 복구');

        $this->assertTrue($result->isSuccess());
        $this->assertSame(1400, $result->get('balance_before'));
        $this->assertSame(1500, $result->get('balance_after'));
    }

    /**
     * repair: 불일치 없으면 복구 불필요
     */
    public function testRepairSkipsWhenNoMismatch(): void
    {
        $this->logRepositoryMock->method('getSumByMember')
            ->with(100)
            ->willReturn(1500);

        $this->memberRepositoryMock->method('getBalance')
            ->with(100)
            ->willReturn(1500); // 일치함

        // repair(memberId, domainId, adminId, reason)
        $result = $this->service->repair(100, 1, 1, '테스트');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('불일치가 없습니다', $result->getMessage());
    }
}
