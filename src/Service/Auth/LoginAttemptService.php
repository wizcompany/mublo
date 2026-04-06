<?php
namespace Mublo\Service\Auth;

use Mublo\Infrastructure\Database\Database;

/**
 * LoginAttemptService
 *
 * 로그인 시도 Rate Limiting
 * - IP 기반 제한: 동일 IP에서 과도한 시도 차단
 * - 계정 기반 제한: 동일 계정에 대한 무차별 대입 차단
 * - 자동 잠금 해제: 잠금 시간 경과 후 자동 해제
 */
class LoginAttemptService
{
    private Database $db;
    private array $config;

    public function __construct(Database $db, array $config = [])
    {
        $this->db = $db;
        $this->config = array_merge([
            'enabled' => true,
            'max_attempts_per_user' => 5,
            'max_attempts_per_ip' => 20,
            'decay_seconds' => 900,       // 15분
            'lockout_seconds' => 600,     // 10분 잠금
            'cleanup_probability' => 5,   // 5% 확률로 오래된 기록 삭제
        ], $config);
    }

    /**
     * 로그인 시도 전 rate limit 확인
     *
     * @return array{allowed: bool, message: string, remaining: int, retry_after: int}
     */
    public function check(int $domainId, string $userId, string $ipAddress): array
    {
        if (!$this->config['enabled']) {
            return ['allowed' => true, 'message' => '', 'remaining' => 999, 'retry_after' => 0];
        }

        try {
            return $this->doCheck($domainId, $userId, $ipAddress);
        } catch (\Throwable) {
            // 테이블 미존재 등 DB 에러 시 로그인 차단하지 않음
            return ['allowed' => true, 'message' => '', 'remaining' => 999, 'retry_after' => 0];
        }
    }

    private function doCheck(int $domainId, string $userId, string $ipAddress): array
    {
        $this->maybeCleanup();

        $window = $this->config['decay_seconds'];
        $since = date('Y-m-d H:i:s', time() - $window);

        // 계정 기반 확인
        $userAttempts = (int) $this->db->selectOne(
            "SELECT COUNT(*) as cnt FROM login_attempts
             WHERE domain_id = ? AND user_id = ? AND attempted_at >= ? AND is_successful = 0",
            [$domainId, $userId, $since]
        )['cnt'];

        $maxUser = $this->config['max_attempts_per_user'];
        if ($userAttempts >= $maxUser) {
            $retryAfter = $this->getRetryAfter($domainId, $userId, null, $window);
            return [
                'allowed' => false,
                'message' => "로그인 시도가 너무 많습니다. {$this->formatSeconds($retryAfter)} 후 다시 시도해주세요.",
                'remaining' => 0,
                'retry_after' => $retryAfter,
            ];
        }

        // IP 기반 확인
        $ipAttempts = (int) $this->db->selectOne(
            "SELECT COUNT(*) as cnt FROM login_attempts
             WHERE ip_address = ? AND attempted_at >= ? AND is_successful = 0",
            [$ipAddress, $since]
        )['cnt'];

        $maxIp = $this->config['max_attempts_per_ip'];
        if ($ipAttempts >= $maxIp) {
            $retryAfter = $this->getRetryAfter(null, null, $ipAddress, $window);
            return [
                'allowed' => false,
                'message' => "요청이 너무 많습니다. {$this->formatSeconds($retryAfter)} 후 다시 시도해주세요.",
                'remaining' => 0,
                'retry_after' => $retryAfter,
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
            'remaining' => $maxUser - $userAttempts,
            'retry_after' => 0,
        ];
    }

    /**
     * 로그인 시도 기록
     */
    public function record(int $domainId, string $userId, string $ipAddress, bool $success): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        try {
            $this->doRecord($domainId, $userId, $ipAddress, $success);
        } catch (\Throwable) {
            // 테이블 미존재 등 DB 에러 시 무시
        }
    }

    private function doRecord(int $domainId, string $userId, string $ipAddress, bool $success): void
    {
        $this->db->insert(
            "INSERT INTO login_attempts (domain_id, user_id, ip_address, is_successful, attempted_at)
             VALUES (?, ?, ?, ?, NOW())",
            [$domainId, $userId, $ipAddress, $success ? 1 : 0]
        );

        // 성공 시 해당 계정의 실패 기록 초기화
        if ($success) {
            $this->clearFailedAttempts($domainId, $userId);
        }
    }

    /**
     * 특정 계정의 실패 기록 초기화 (로그인 성공 시)
     */
    public function clearFailedAttempts(int $domainId, string $userId): void
    {
        try {
            $this->db->execute(
                "DELETE FROM login_attempts WHERE domain_id = ? AND user_id = ? AND is_successful = 0",
                [$domainId, $userId]
            );
        } catch (\Throwable) {
            // 테이블 미존재 시 무시
        }
    }

    /**
     * 가장 오래된 시도 기준 남은 대기 시간 계산
     */
    private function getRetryAfter(?int $domainId, ?string $userId, ?string $ipAddress, int $window): int
    {
        if ($userId !== null && $domainId !== null) {
            $oldest = $this->db->selectOne(
                "SELECT MIN(attempted_at) as oldest FROM login_attempts
                 WHERE domain_id = ? AND user_id = ? AND attempted_at >= ? AND is_successful = 0",
                [$domainId, $userId, date('Y-m-d H:i:s', time() - $window)]
            );
        } else {
            $oldest = $this->db->selectOne(
                "SELECT MIN(attempted_at) as oldest FROM login_attempts
                 WHERE ip_address = ? AND attempted_at >= ? AND is_successful = 0",
                [$ipAddress, date('Y-m-d H:i:s', time() - $window)]
            );
        }

        if (!$oldest || !$oldest['oldest']) {
            return $this->config['lockout_seconds'];
        }

        $oldestTime = strtotime($oldest['oldest']);
        $unlockAt = $oldestTime + $window;
        $remaining = $unlockAt - time();

        return max(1, $remaining);
    }

    /**
     * 초를 사람이 읽기 쉬운 형식으로 변환
     */
    private function formatSeconds(int $seconds): string
    {
        if ($seconds >= 60) {
            $minutes = (int) ceil($seconds / 60);
            return "{$minutes}분";
        }
        return "{$seconds}초";
    }

    /**
     * 확률적으로 오래된 기록 삭제 (GC)
     */
    private function maybeCleanup(): void
    {
        if (random_int(1, 100) <= $this->config['cleanup_probability']) {
            $cutoff = date('Y-m-d H:i:s', time() - 86400); // 24시간 이전
            $this->db->execute(
                "DELETE FROM login_attempts WHERE attempted_at < ?",
                [$cutoff]
            );
        }
    }
}
