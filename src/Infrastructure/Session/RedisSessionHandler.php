<?php

namespace Mublo\Infrastructure\Session;

use SessionHandlerInterface;
use Mublo\Infrastructure\Redis\RedisManager;

/**
 * RedisSessionHandler
 *
 * Redis 기반 세션 핸들러
 * - PHP SessionHandlerInterface 구현
 * - Redis에 세션 데이터 저장
 * - TTL 기반 자동 만료
 * - 멀티테넌트 지원 (도메인별 분리)
 */
class RedisSessionHandler implements SessionHandlerInterface
{
    private string $prefix;
    private int $ttl;

    /**
     * @param int $ttl 세션 TTL (초)
     * @param int|null $domainId 도메인 ID (멀티테넌트)
     */
    public function __construct(int $ttl = 7200, ?int $domainId = null)
    {
        $this->ttl = $ttl;

        // 도메인별 prefix 설정
        $this->prefix = $domainId
            ? "sess:d{$domainId}:"
            : 'sess:';
    }

    /**
     * 세션 열기
     */
    public function open(string $path, string $name): bool
    {
        try {
            return RedisManager::isConnected();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 세션 닫기
     */
    public function close(): bool
    {
        return true;
    }

    /**
     * 세션 데이터 읽기
     */
    public function read(string $id): string|false
    {
        try {
            $redis = RedisManager::getInstance();
            $data = $redis->get($this->prefix . $id);

            return $data !== false ? $data : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * 세션 데이터 쓰기
     */
    public function write(string $id, string $data): bool
    {
        try {
            $redis = RedisManager::getInstance();
            return $redis->setex($this->prefix . $id, $this->ttl, $data);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 세션 삭제
     */
    public function destroy(string $id): bool
    {
        try {
            $redis = RedisManager::getInstance();
            $redis->del($this->prefix . $id);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * 가비지 컬렉션 (Redis TTL이 처리하므로 불필요)
     */
    public function gc(int $max_lifetime): int|false
    {
        // Redis TTL이 자동으로 만료 처리
        return 0;
    }
}
