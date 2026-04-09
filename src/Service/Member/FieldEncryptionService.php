<?php
namespace Mublo\Service\Member;

/**
 * FieldEncryptionService
 *
 * 회원 필드 암호화/복호화 및 검색 인덱스 생성
 * - AES-256-GCM 암호화 (인증된 암호화)
 * - Blind Index (HMAC + Pepper) 생성
 *
 * 보안 설계:
 * - encryption.key: 필드 값 암호화용 (DB 털려도 복호화 불가)
 * - search.pepper: 검색 인덱스용 (DB 털려도 rainbow table 무력화)
 */
class FieldEncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    private string $encryptionKey;
    private string $searchPepper;

    public function __construct()
    {
        $config = require MUBLO_CONFIG_PATH . '/security.php';

        $this->encryptionKey = hex2bin($config['encryption']['key'] ?? '');
        $this->searchPepper = hex2bin($config['search']['pepper'] ?? '');

        if (strlen($this->encryptionKey) !== 32) {
            throw new \RuntimeException('Invalid encryption key length. Expected 32 bytes.');
        }

        if (strlen($this->searchPepper) !== 32) {
            throw new \RuntimeException('Invalid search pepper length. Expected 32 bytes.');
        }
    }

    /**
     * 필드 값 암호화
     *
     * @param string $plainText 암호화할 값
     * @return string base64 인코딩된 암호문 (nonce + tag + ciphertext)
     */
    public function encrypt(string $plainText): string
    {
        $nonce = random_bytes(12); // GCM은 12바이트 nonce 권장
        $tag = '';

        $cipherText = openssl_encrypt(
            $plainText,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($cipherText === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        // nonce(12) + tag(16) + ciphertext
        return base64_encode($nonce . $tag . $cipherText);
    }

    /**
     * 필드 값 복호화
     *
     * @param string $encrypted base64 인코딩된 암호문
     * @return string|null 복호화된 값 (실패 시 null)
     */
    public function decrypt(string $encrypted): ?string
    {
        $decoded = base64_decode($encrypted, true);

        if ($decoded === false) {
            return null;
        }

        // 최소 길이 체크: nonce(12) + tag(16) + 최소 1바이트
        if (strlen($decoded) < 12 + self::TAG_LENGTH + 1) {
            return null;
        }

        $nonce = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, self::TAG_LENGTH);
        $cipherText = substr($decoded, 12 + self::TAG_LENGTH);

        $plainText = openssl_decrypt(
            $cipherText,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag
        );

        return $plainText !== false ? $plainText : null;
    }

    /**
     * 검색 인덱스 생성 (Blind Index)
     *
     * 암호화된 필드를 검색하기 위한 HMAC 해시 생성
     * pepper가 없으면 rainbow table 공격으로 원문 추론 가능하므로
     * 반드시 pepper와 함께 사용
     *
     * @param string $value 원문 값
     * @return string 64자 hex 해시
     */
    public function createSearchIndex(string $value): string
    {
        // 정규화: 소문자 + 공백 제거 (검색 일관성)
        $normalized = strtolower(trim($value));

        return hash_hmac('sha256', $normalized, $this->searchPepper);
    }

    /**
     * 검색 인덱스 비교 (타이밍 공격 방지)
     *
     * @param string $storedIndex DB에 저장된 인덱스
     * @param string $searchValue 검색할 값
     * @return bool 일치 여부
     */
    public function matchSearchIndex(string $storedIndex, string $searchValue): bool
    {
        $searchIndex = $this->createSearchIndex($searchValue);
        return hash_equals($storedIndex, $searchIndex);
    }

    /**
     * 필드 값 처리 (암호화 + 검색 인덱스)
     *
     * @param string $value 원문 값
     * @param bool $isEncrypted 암호화 여부
     * @param bool $isSearchable 검색 가능 여부
     * @param string|null $searchIndexValue 검색 인덱스 생성에 사용할 값 (null이면 $value 사용)
     *                                       주소 타입 같은 복합 필드에서 우편번호만 인덱싱할 때 사용
     * @return array{field_value: string, search_index: string|null}
     */
    public function processFieldValue(string $value, bool $isEncrypted, bool $isSearchable, ?string $searchIndexValue = null): array
    {
        $result = [
            'field_value' => $value,
            'search_index' => null,
        ];

        // 암호화
        if ($isEncrypted) {
            $result['field_value'] = $this->encrypt($value);
        }

        // 검색 인덱스 (암호화 여부와 무관하게 검색 가능하면 생성)
        if ($isSearchable) {
            $indexValue = $searchIndexValue ?? $value;
            $result['search_index'] = $this->createSearchIndex($indexValue);
        }

        return $result;
    }

    /**
     * 필드 값 읽기 (복호화)
     *
     * @param string|null $storedValue DB에 저장된 값
     * @param bool $isEncrypted 암호화 여부
     * @return string|null 원문 값
     */
    public function readFieldValue(?string $storedValue, bool $isEncrypted): ?string
    {
        if ($storedValue === null || $storedValue === '') {
            return null;
        }

        if ($isEncrypted) {
            return $this->decrypt($storedValue);
        }

        return $storedValue;
    }
}
