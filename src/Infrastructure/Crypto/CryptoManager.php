<?php

namespace Mublo\Infrastructure\Crypto;

/**
 * CryptoManager
 *
 * 암호화/복호화 전담 인프라 클래스
 * - AES-256-CBC 암호화
 * - 키 생성
 * - 해시 생성
 *
 * 사용처:
 * - 데이터베이스 비밀번호 암호화
 * - 쿠키 암호화
 * - 토큰 생성
 */
class CryptoManager
{
    private const CIPHER = 'AES-256-CBC';
    private const IV_LENGTH = 16;

    /**
     * 데이터 암호화
     *
     * @param string $data 암호화할 데이터
     * @param string $key 암호화 키
     * @return string base64 인코딩된 암호문 (IV 포함)
     */
    public function encrypt(string $data, string $key): string
    {
        $iv = random_bytes(self::IV_LENGTH);
        $encrypted = openssl_encrypt($data, self::CIPHER, $key, 0, $iv);

        // IV(바이너리) + 암호문(base64) → 전체 base64
        return base64_encode($iv . $encrypted);
    }

    /**
     * 데이터 복호화
     *
     * @param string $encrypted base64 인코딩된 암호문
     * @param string $key 복호화 키
     * @return string 복호화된 데이터 (실패 시 빈 문자열)
     */
    public function decrypt(string $encrypted, string $key): string
    {
        $decoded = base64_decode($encrypted);

        if ($decoded === false || strlen($decoded) < self::IV_LENGTH) {
            return '';
        }

        // IV 추출 (처음 16바이트)
        $iv = substr($decoded, 0, self::IV_LENGTH);

        // 암호문 추출 (나머지, base64 문자열)
        $ciphertext = substr($decoded, self::IV_LENGTH);

        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, $key, 0, $iv);

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * 암호화 키 생성
     *
     * @param int $length 키 길이 (바이트, 기본 32)
     * @return string hex 인코딩된 키
     */
    public function generateKey(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * 짧은 키 생성 (16바이트)
     *
     * @return string hex 인코딩된 키
     */
    public function generateShortKey(): string
    {
        return $this->generateKey(16);
    }

    /**
     * 안전한 랜덤 토큰 생성
     *
     * @param int $length 토큰 길이 (바이트)
     * @return string hex 인코딩된 토큰
     */
    public function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * HMAC 해시 생성
     *
     * @param string $data 해시할 데이터
     * @param string $key 해시 키
     * @param string $algo 알고리즘 (기본 sha256)
     * @return string 해시값
     */
    public function hmac(string $data, string $key, string $algo = 'sha256'): string
    {
        return hash_hmac($algo, $data, $key);
    }

    /**
     * 타이밍 공격 방지 문자열 비교
     *
     * @param string $known 알려진 값
     * @param string $user 사용자 입력값
     * @return bool 일치 여부
     */
    public function secureCompare(string $known, string $user): bool
    {
        return hash_equals($known, $user);
    }
}
