<?php

namespace Mublo\Infrastructure\Session;

use Mublo\Core\Session\SessionInterface;
use Mublo\Infrastructure\Redis\RedisManager;

/**
 * SessionManager
 *
 * PHP 세션 관리 인프라 클래스 (SessionInterface 구현)
 * - 파일/Redis 드라이버 지원
 * - 멀티테넌트 지원 (도메인별 세션 분리)
 * - 세션 시작/종료
 * - 데이터 저장/조회/삭제
 * - 플래시 메시지
 */
class SessionManager implements SessionInterface
{
    private bool $started = false;
    private array $config;
    private string $driver;
    private ?int $domainId = null;

    public function __construct()
    {
        $this->config = $this->loadConfig();
        $this->driver = $this->loadDriver();
    }

    /**
     * 보안 설정 로드
     */
    private function loadConfig(): array
    {
        $configPath = MUBLO_CONFIG_PATH . '/security.php';

        if (file_exists($configPath)) {
            $config = require $configPath;
            return $config['session'] ?? $this->getDefaultConfig();
        }

        return $this->getDefaultConfig();
    }

    /**
     * 드라이버 설정 로드
     */
    private function loadDriver(): string
    {
        $configPath = MUBLO_CONFIG_PATH . '/security.php';

        if (file_exists($configPath)) {
            $config = require $configPath;
            return $config['session_driver'] ?? 'file';
        }

        return 'file';
    }

    /**
     * 기본 설정
     */
    private function getDefaultConfig(): array
    {
        return [
            'lifetime' => 120,
            'cookie_secure' => false,
            'cookie_httponly' => true,
            'cookie_samesite' => 'Lax',
        ];
    }

    /**
     * 도메인 ID 설정 (멀티테넌트)
     */
    public function setDomainId(?int $domainId): void
    {
        $this->domainId = $domainId;
    }

    /**
     * 세션 시작
     *
     * @param int|null $domainId 도메인 ID (멀티테넌트 분리용)
     */
    public function start(?int $domainId = null): void
    {
        if ($this->started) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }

        // 도메인 ID 설정
        if ($domainId !== null) {
            $this->domainId = $domainId;
        }

        // 드라이버별 핸들러 설정
        $this->configureDriver();

        // 쿠키 설정
        $this->configureSession();

        session_start();
        $this->started = true;

        $this->ageFlashData();
    }

    /**
     * 드라이버별 세션 핸들러 설정
     */
    private function configureDriver(): void
    {
        if ($this->driver === 'redis') {
            // Redis 확장 체크
            if (!RedisManager::isAvailable()) {
                throw new \RuntimeException(
                    'Redis 확장이 설치되지 않았습니다. SESSION_DRIVER=file로 변경하거나 Redis 확장을 설치하세요.'
                );
            }

            // Redis 연결 체크
            if (!RedisManager::isConnected()) {
                throw new \RuntimeException(
                    'Redis 연결에 실패했습니다. Redis 서버 상태를 확인하세요.'
                );
            }

            // Redis 세션 핸들러 등록 (도메인별 prefix 적용)
            $ttl = ($this->config['lifetime'] ?? 120) * 60;
            $handler = new RedisSessionHandler($ttl, $this->domainId);
            session_set_save_handler($handler, true);
        }
        // file 드라이버는 PHP 기본 핸들러 사용
    }

    /**
     * 세션 설정 구성
     */
    private function configureSession(): void
    {
        $lifetimeSeconds = ($this->config['lifetime'] ?? 120) * 60;

        // gc_maxlifetime을 세션 lifetime과 동기화 (서버 측 세션 조기 정리 방지)
        ini_set('session.gc_maxlifetime', $lifetimeSeconds);

        session_set_cookie_params([
            'lifetime' => $lifetimeSeconds,
            'path' => '/',
            'domain' => '',
            'secure' => $this->config['cookie_secure'] ?? false,
            'httponly' => $this->config['cookie_httponly'] ?? true,
            'samesite' => $this->config['cookie_samesite'] ?? 'Lax',
        ]);

        // 도메인별 세션 이름 (멀티테넌트 분리)
        $sessionName = $this->domainId
            ? 'MUBLO_sess_' . $this->domainId
            : 'MUBLO_session';

        session_name($sessionName);
    }

    /**
     * 세션 쿠키 만료 시간 갱신 (슬라이딩 세션)
     */
    public function renewCookie(): void
    {
        if (!$this->started || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $lifetimeSeconds = ($this->config['lifetime'] ?? 120) * 60;

        setcookie(
            session_name(),
            session_id(),
            [
                'expires' => time() + $lifetimeSeconds,
                'path' => '/',
                'domain' => '',
                'secure' => $this->config['cookie_secure'] ?? false,
                'httponly' => $this->config['cookie_httponly'] ?? true,
                'samesite' => $this->config['cookie_samesite'] ?? 'Lax',
            ]
        );
    }

    /**
     * 세션 잠금 해제 (동시 요청 직렬화 방지)
     *
     * 세션 데이터를 기록하고 잠금을 해제합니다.
     * destroy()와 달리 세션 데이터는 유지됩니다.
     */
    public function close(): void
    {
        if (!$this->started || session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        session_write_close();
        $this->started = false;
    }

    /**
     * 세션 종료 (로그아웃 시)
     */
    public function destroy(): void
    {
        if (!$this->started) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        $this->started = false;
    }

    /**
     * 세션 ID 재생성 (로그인 후 보안)
     */
    public function regenerate(bool $deleteOldSession = true): bool
    {
        return session_regenerate_id($deleteOldSession);
    }

    /**
     * 세션 값 저장
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    /**
     * 세션 값 조회
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * 세션 값 존재 여부
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    /**
     * 세션 값 삭제
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    /**
     * 모든 세션 데이터 조회
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $_SESSION ?? [];
    }

    /**
     * 플래시 메시지 저장 (다음 요청에서만 유효)
     */
    public function flash(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $_SESSION['_flash']['new'][$key] = $value;
    }

    /**
     * 플래시 메시지 조회
     */
    public function getFlash(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();

        return $_SESSION['_flash']['old'][$key]
            ?? $_SESSION['_flash']['new'][$key]
            ?? $default;
    }

    /**
     * 플래시 데이터 aging 처리
     */
    private function ageFlashData(): void
    {
        unset($_SESSION['_flash']['old']);

        if (isset($_SESSION['_flash']['new'])) {
            $_SESSION['_flash']['old'] = $_SESSION['_flash']['new'];
            unset($_SESSION['_flash']['new']);
        }
    }

    /**
     * 세션 시작 보장
     */
    private function ensureStarted(): void
    {
        if (!$this->started) {
            $this->start();
        }
    }

    /**
     * 세션 ID 조회
     */
    public function getId(): string
    {
        $this->ensureStarted();
        return session_id();
    }

    /**
     * 현재 드라이버 반환
     */
    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * 현재 도메인 ID 반환
     */
    public function getDomainId(): ?int
    {
        return $this->domainId;
    }
}
