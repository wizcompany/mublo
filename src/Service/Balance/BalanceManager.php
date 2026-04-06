<?php
namespace Mublo\Service\Balance;

use Mublo\Repository\Balance\BalanceLogRepository;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Entity\Balance\BalanceLog;
use Mublo\Infrastructure\Database\Database;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Core\Event\Balance\BalanceAdjustingEvent;
use Mublo\Core\Event\Balance\BalanceAdjustedEvent;
use Mublo\Core\Result\Result;

/**
 * Class BalanceManager
 *
 * 포인트/잔액 중앙 관리 서비스
 *
 * 책임:
 * - 잔액 조정 (adjust) - 단일 진입점
 * - 잔액 조회 (getBalance)
 * - 이력 조회 (getHistory)
 * - 무결성 검증 (verifyIntegrity)
 * - 무결성 복구 (repair) - 관리자 전용
 *
 * 핵심 원칙:
 * - 원장 불변성: balance_logs는 INSERT ONLY
 * - 원장 = 진실: Source of Truth는 balance_logs, point_balance는 스냅샷
 * - 동시성 제어: SELECT ... FOR UPDATE (Pessimistic Lock)
 * - 트랜잭션 원자성: 원장 기록 + 스냅샷 업데이트 = 하나의 트랜잭션
 * - 음수 거부: 기본 정책 - 잔액 부족 시 차감 실패
 */
class BalanceManager
{
    private BalanceLogRepository $logRepository;
    private MemberRepository $memberRepository;
    private Database $db;
    private ?EventDispatcher $eventDispatcher;

    /**
     * 필수 필드 목록
     */
    private const REQUIRED_FIELDS = [
        'domain_id',
        'member_id',
        'amount',
        'source_type',
        'source_name',
        'action',
        'message',
    ];

    public function __construct(
        BalanceLogRepository $logRepository,
        MemberRepository $memberRepository,
        Database $db,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->logRepository = $logRepository;
        $this->memberRepository = $memberRepository;
        $this->db = $db;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * 이벤트 발행 헬퍼
     */
    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }

    // ========================================
    // 핵심 메서드: adjust()
    // ========================================

    /**
     * 잔액 조정 (지급/차감)
     *
     * @param array $params [
     *   'member_id' => int,         // 필수
     *   'amount' => int,            // 필수 (+지급, -차감)
     *   'source_type' => string,    // 필수 ('plugin', 'package', 'admin', 'system')
     *   'source_name' => string,    // 필수 ('MemberPoint', 'Shop' 등)
     *   'action' => string,         // 필수 ('article_write', 'purchase' 등)
     *   'message' => string,        // 필수 (사용자 친화적 메시지)
     *   'reference_type' => ?string,
     *   'reference_id' => ?string,
     *   'admin_id' => ?int,
     *   'memo' => ?string,
     *   'ip_address' => ?string,
     *   'idempotency_key' => ?string, // 중복 요청 방지
     * ]
     * @return Result success data: ['log_id', 'balance_before', 'balance_after', 'idempotent']
     */
    public function adjust(array $params): Result
    {
        // 1. 필수 필드 검증
        $validation = $this->validateParams($params);
        if (!$validation['valid']) {
            return Result::failure($validation['message']);
        }

        // 2. 멱등성 키 체크 (도메인 스코프 적용)
        $idempotencyKey = $params['idempotency_key'] ?? null;
        $domainId = (int) $params['domain_id'];
        if ($idempotencyKey) {
            $existing = $this->logRepository->findByIdempotencyKey($idempotencyKey, $domainId);
            if ($existing) {
                return Result::success('이미 처리된 요청입니다.', [
                    'log_id' => $existing->getLogId(),
                    'balance_before' => $existing->getBalanceBefore(),
                    'balance_after' => $existing->getBalanceAfter(),
                    'idempotent' => true,
                ]);
            }
        }

        $memberId = (int) $params['member_id'];
        $amount = (int) $params['amount'];

        $this->db->beginTransaction();

        try {
            // 3. SELECT ... FOR UPDATE (행 락킹 + 도메인 소유 검증)
            $currentBalance = $this->memberRepository->getBalanceForUpdate($memberId, $domainId);

            if ($currentBalance === null) {
                $this->db->rollBack();
                return Result::failure('회원을 찾을 수 없습니다.');
            }

            // 4. 잔액 검증 (차감 시)
            $newBalance = $currentBalance + $amount;
            if ($amount < 0 && $newBalance < 0) {
                $this->db->rollBack();
                return Result::failure('잔액이 부족합니다.');
            }

            // 5. BalanceAdjustingEvent 발행 (차단 가능)
            $adjustingEvent = new BalanceAdjustingEvent($memberId, $amount, $currentBalance);
            $this->dispatch($adjustingEvent);

            if ($adjustingEvent->isBlocked()) {
                $this->db->rollBack();
                return Result::failure($adjustingEvent->getBlockReason() ?? '잔액 조정이 차단되었습니다.');
            }

            // 6. 원장 기록 (INSERT)
            $logData = [
                'domain_id' => (int) $params['domain_id'],
                'member_id' => $memberId,
                'amount' => $amount,
                'balance_before' => $currentBalance,
                'balance_after' => $newBalance,
                'source_type' => $params['source_type'],
                'source_name' => $params['source_name'],
                'action' => $params['action'],
                'message' => $params['message'],
                'reference_type' => $params['reference_type'] ?? null,
                'reference_id' => $params['reference_id'] ?? null,
                'ip_address' => $params['ip_address'] ?? null,
                'admin_id' => $params['admin_id'] ?? null,
                'memo' => $params['memo'] ?? null,
                'idempotency_key' => $idempotencyKey,
            ];

            $logId = $this->logRepository->create($logData);

            // 7. 스냅샷 업데이트 (UPDATE) — 실패 시 롤백
            $updated = $this->memberRepository->updateBalance($memberId, $newBalance);
            if (!$updated) {
                throw new \RuntimeException("잔액 스냅샷 업데이트 실패 (member_id={$memberId})");
            }

            $this->db->commit();

            // 8. BalanceAdjustedEvent 발행 (트랜잭션 완료 후)
            $this->dispatch(new BalanceAdjustedEvent($memberId, $logId, $newBalance));

            return Result::success('포인트가 조정되었습니다.', [
                'log_id' => $logId,
                'balance_before' => $currentBalance,
                'balance_after' => $newBalance,
            ]);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ========================================
    // 조회 메서드
    // ========================================

    /**
     * 잔액 조회
     *
     * @param int $memberId 회원 ID
     * @param int|null $domainId 도메인 ID (지정 시 도메인 소유 검증)
     */
    public function getBalance(int $memberId, ?int $domainId = null): int
    {
        $balance = $this->memberRepository->getBalance($memberId, $domainId);
        return $balance ?? 0;
    }

    /**
     * 이력 조회 (특정 회원)
     *
     * @return array ['items' => BalanceLog[], 'pagination' => array]
     */
    public function getHistory(int $memberId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $items = $this->logRepository->getByMember($memberId, $page, $perPage);
        $total = $this->logRepository->countByMember($memberId);

        return [
            'items' => $items,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * 관리자용 포인트 로그 목록 조회 (페이지네이션)
     *
     * @param int $domainId 도메인 ID
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 항목 수
     * @param array $filters 필터 조건 [member_id, source_type, start_date, end_date]
     * @return array ['items' => BalanceLog[], 'pagination' => array]
     */
    public function getPaginatedLogs(int $domainId, int $page = 1, int $perPage = 20, array $filters = []): array
    {
        return $this->logRepository->getPaginatedList($domainId, $page, $perPage, $filters);
    }

    // ========================================
    // 무결성 검증
    // ========================================

    /**
     * 단일 회원 무결성 검증
     *
     * Source of Truth: SUM(balance_logs.amount)
     * 스냅샷: members.point_balance
     *
     * @param int $memberId 회원 ID
     * @param int $domainId 도메인 ID (멀티도메인 경계 보장)
     */
    public function verifyIntegrity(int $memberId, int $domainId): array
    {
        $ledgerSum = $this->logRepository->getSumByMember($memberId, $domainId);
        $snapshot = $this->memberRepository->getBalance($memberId, $domainId) ?? 0;

        $isValid = ($ledgerSum === $snapshot);

        return [
            'valid' => $isValid,
            'member_id' => $memberId,
            'ledger_sum' => $ledgerSum,
            'snapshot' => $snapshot,
            'diff' => $ledgerSum - $snapshot,
        ];
    }

    /**
     * 무결성 복구 (관리자 전용)
     *
     * 원장 기준으로 스냅샷 복구
     */
    public function repair(int $memberId, int $domainId, int $adminId, string $reason): Result
    {
        $check = $this->verifyIntegrity($memberId, $domainId);

        if ($check['valid']) {
            return Result::failure('불일치가 없습니다. 복구가 필요하지 않습니다.');
        }

        $this->db->beginTransaction();

        try {
            // 복구 로그 생성 (원장 INSERT)
            $logData = [
                'domain_id' => $domainId,
                'member_id' => $memberId,
                'amount' => $check['diff'],
                'balance_before' => $check['snapshot'],
                'balance_after' => $check['ledger_sum'],
                'source_type' => 'system',
                'source_name' => 'BalanceReconciler',
                'action' => 'system_repair',
                'message' => "무결성 복구: {$reason}",
                'admin_id' => $adminId,
                'memo' => "Diff: {$check['diff']}, Snapshot: {$check['snapshot']}, Ledger: {$check['ledger_sum']}",
            ];

            $this->logRepository->create($logData);

            // 스냅샷 업데이트
            $this->memberRepository->updateBalance($memberId, $check['ledger_sum']);

            $this->db->commit();

            return Result::success('무결성이 복구되었습니다.', [
                'balance_before' => $check['snapshot'],
                'balance_after' => $check['ledger_sum'],
            ]);
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ========================================
    // 헬퍼 메서드
    // ========================================

    /**
     * 파라미터 유효성 검증
     */
    private function validateParams(array $params): array
    {
        foreach (self::REQUIRED_FIELDS as $field) {
            if (!isset($params[$field]) || $params[$field] === '') {
                return [
                    'valid' => false,
                    'message' => "필수 필드가 누락되었습니다: {$field}",
                ];
            }
        }

        if (!is_numeric($params['amount']) || (int) $params['amount'] === 0) {
            return [
                'valid' => false,
                'message' => 'amount는 0이 아닌 정수여야 합니다.',
            ];
        }

        return ['valid' => true];
    }
}
