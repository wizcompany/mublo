<?php
namespace Mublo\Infrastructure\Security;

use Mublo\Infrastructure\Crypto\CryptoManager;

/**
 * Class CsrfManager
 *
 * CSRF 토큰 관리 서비스
 *
 * 책임:
 * - CSRF 토큰 생성
 * - CSRF 토큰 검증
 * - 세션 기반 토큰 저장/조회
 */
class CsrfManager
{
    private const SESSION_KEY = '_csrf_token';
    private const TOKEN_LENGTH = 32;

    protected CryptoManager $crypto;

    public function __construct()
    {
        $this->crypto = new CryptoManager();
    }

    /**
     * CSRF 토큰 생성 및 세션 저장
     *
     * @return string 생성된 토큰
     */
    public function generateToken(): string
    {
        $this->ensureSession();

        $token = $this->crypto->generateToken(self::TOKEN_LENGTH);
        $_SESSION[self::SESSION_KEY] = $token;

        return $token;
    }

    /**
     * 현재 CSRF 토큰 반환 (없으면 생성)
     *
     * @return string
     */
    public function getToken(): string
    {
        $this->ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY])) {
            return $this->generateToken();
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * CSRF 토큰 검증
     *
     * @param string $token 검증할 토큰
     * @return bool
     */
    public function validateToken(string $token): bool
    {
        $this->ensureSession();

        if (!isset($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return $this->crypto->secureCompare($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * CSRF 토큰 재생성 (로그인 후 등)
     *
     * @return string 새 토큰
     */
    public function regenerateToken(): string
    {
        $this->ensureSession();

        // 기존 토큰 삭제
        unset($_SESSION[self::SESSION_KEY]);

        return $this->generateToken();
    }

    /**
     * CSRF 토큰 삭제
     */
    public function clearToken(): void
    {
        $this->ensureSession();

        unset($_SESSION[self::SESSION_KEY]);
    }

    /**
     * 세션 시작 확인
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
