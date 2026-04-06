<?php
namespace Mublo\Core\Http;

/**
 * Class Request
 *
 * HTTP 요청 정보를 캡슐화하는 객체
 *
 * 책임:
 * - PHP 전역 변수($_SERVER, $_GET, $_POST 등) 접근을 이 클래스 하나로 제한
 * - 요청 메서드, URI, 쿼리 파라미터 보관
 * - PayloadType 판별 (JSON / FORM / QUERY)
 *
 * 금지:
 * - 인증 판단
 * - 권한 판단
 * - 비즈니스 로직
 * - DB / Session 직접 접근
 */
class Request
{
    public const PAYLOAD_JSON = 'json';
    public const PAYLOAD_FORM = 'form';
    public const PAYLOAD_QUERY = 'query';

    /**
     * 신뢰 프록시 목록
     * - 빈 배열: 프록시 헤더 무시 (REMOTE_ADDR만 사용)
     * - ['*']: 모든 프록시 신뢰
     * - ['192.168.1.0/24']: 특정 IP/CIDR만 신뢰
     */
    protected static array $trustedProxies = [];

    /**
     * HTTP Method (GET, POST, PUT, DELETE ...)
     */
    protected string $method;

    /**
     * 요청 URI (query string 제외)
     */
    protected string $uri;

    /**
     * Query Parameters ($_GET)
     */
    protected array $query = [];

    /**
     * Request Body ($_POST)
     */
    protected array $body = [];

    /**
     * Server Parameters ($_SERVER)
     */
    protected array $server = [];

    /**
     * Uploaded Files ($_FILES)
     */
    protected array $files = [];

    /**
     * Cookie Parameters ($_COOKIE)
     */
    protected array $cookies = [];

    /**
     * JSON Input (php://input 파싱 결과)
     */
    protected ?array $jsonInput = null;

    /**
     * 생성자
     * - Application 단계에서 생성됨
     */
    public function __construct(
        string $method,
        string $uri,
        array $query = [],
        array $body = [],
        array $server = [],
        array $files = [],
        array $cookies = []
    ) {
        $this->method  = strtoupper($method);
        $this->uri     = $uri;
        $this->query   = $query;
        $this->body    = $body;
        $this->server  = $server;
        $this->files   = $files;
        $this->cookies = $cookies;
    }

    /**
     * HTTP Method 반환
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * URI 반환 (query 제외)
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * 전체 Query Parameters 반환
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * 특정 Query Parameter 반환
     */
    public function query(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Request Body 반환
     */
    public function getBody(): array
    {
        return $this->body;
    }

    /**
     * 특정 Body 값 반환
     */
    public function input(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * POST 데이터 반환 (input 별칭)
     */
    public function post(string $key, $default = null)
    {
        return $this->body[$key] ?? $default;
    }

    /**
     * GET 파라미터 반환 (query 별칭)
     */
    public function get(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Server 값 반환
     */
    public function server(string $key, $default = null)
    {
        return $this->server[$key] ?? $default;
    }

    /**
     * 특정 Cookie 값 반환
     */
    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * 요청 경로
     *
     * 예:
     *  /               → /
     *  /board/list     → /board/list
     */
    public function getPath(): string
    {
        // URI는 이미 query string 제거된 상태
        return $this->uri === '' ? '/' : $this->uri;
    }

    /**
     * 호스트명 반환
     *
     * 예:
     *  localhost
     *  example.com
     *  www.example.com:8080
     */
    public function getHost(): ?string
    {
        return $this->server['HTTP_HOST'] ?? null;
    }

    /**
     * HTTPS 여부 반환
     */
    public function isHttps(): bool
    {
        if (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off') {
            return true;
        }
        if (($this->server['SERVER_PORT'] ?? null) == 443) {
            return true;
        }
        // 리버스 프록시 지원 (X-Forwarded-Proto) — 신뢰 프록시에서만 허용
        $remoteAddr = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
        if ($this->isFromTrustedProxy($remoteAddr)
            && isset($this->server['HTTP_X_FORWARDED_PROTO'])
            && $this->server['HTTP_X_FORWARDED_PROTO'] === 'https'
        ) {
            return true;
        }
        return false;
    }

    /**
     * 스킴(http/https) 반환
     */
    public function getScheme(): string
    {
        return $this->isHttps() ? 'https' : 'http';
    }

    /**
     * 스킴 + 호스트 반환 (예: https://shop.mublo.kr)
     */
    public function getSchemeAndHost(): string
    {
        return $this->getScheme() . '://' . ($this->getHost() ?? 'localhost');
    }

    /**
     * JSON 입력 설정 (php://input 파싱 결과)
     */
    public function setJsonInput(?array $jsonInput): void
    {
        $this->jsonInput = $jsonInput;
    }

    /**
     * JSON 입력 반환
     */
    public function getJsonInput(): ?array
    {
        return $this->jsonInput;
    }

    /**
     * JSON 입력에서 특정 값 반환 (키 생략 시 전체 반환)
     */
    public function json(?string $key = null, $default = null)
    {
        if ($key === null) {
            return $this->jsonInput;
        }
        return $this->jsonInput[$key] ?? $default;
    }

    /**
     * PayloadType 판별
     *
     * @return string PAYLOAD_JSON | PAYLOAD_FORM | PAYLOAD_QUERY
     */
    public function getPayloadType(): string
    {
        $contentType = $this->getContentType();

        if ($contentType && str_contains($contentType, 'application/json')) {
            return self::PAYLOAD_JSON;
        }

        if ($this->method === 'POST' && !empty($this->body)) {
            return self::PAYLOAD_FORM;
        }

        return self::PAYLOAD_QUERY;
    }

    /**
     * Content-Type 헤더 반환
     */
    public function getContentType(): ?string
    {
        return $this->server['CONTENT_TYPE'] ?? $this->server['HTTP_CONTENT_TYPE'] ?? null;
    }

    /**
     * AJAX 요청 여부 판별
     */
    public function isAjax(): bool
    {
        $xRequestedWith = $this->server['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower($xRequestedWith) === 'xmlhttprequest';
    }

    /**
     * JSON 요청 여부 판별
     */
    public function isJson(): bool
    {
        return $this->getPayloadType() === self::PAYLOAD_JSON;
    }

    /**
     * HTTP 헤더 반환
     */
    public function header(string $key, $default = null): ?string
    {
        // HTTP_로 시작하는 서버 변수 검색
        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $key));
        return $this->server[$serverKey] ?? $default;
    }

    /**
     * Bearer 토큰 추출
     */
    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }
        return null;
    }

    /**
     * 통합 입력값 반환 (PayloadType에 따라 적절한 소스에서 가져옴)
     */
    public function all(): array
    {
        $payloadType = $this->getPayloadType();

        return match ($payloadType) {
            self::PAYLOAD_JSON => $this->jsonInput ?? [],
            self::PAYLOAD_FORM => $this->body,
            default => $this->query,
        };
    }

    /**
     * 통합 입력값에서 특정 키 반환
     */
    public function getData(string $key, $default = null)
    {
        $all = $this->all();
        return $all[$key] ?? $default;
    }

    /**
     * 신뢰 프록시 설정 (Application 초기화 시 호출)
     *
     * @param array $proxies 신뢰 프록시 목록 (IP 또는 CIDR)
     */
    public static function setTrustedProxies(array $proxies): void
    {
        self::$trustedProxies = $proxies;
    }

    /**
     * 클라이언트 IP 주소 반환
     *
     * 프록시 환경 고려 (X-Forwarded-For, X-Real-IP)
     * 신뢰 프록시가 설정된 경우에만 프록시 헤더 사용
     */
    public function getClientIp(): string
    {
        $remoteAddr = $this->server['REMOTE_ADDR'] ?? '0.0.0.0';

        // Cloudflare: CF-Connecting-IP (Cloudflare가 직접 설정, 신뢰 프록시 설정 불필요)
        if (!empty($this->server['HTTP_CF_CONNECTING_IP'])) {
            return $this->server['HTTP_CF_CONNECTING_IP'];
        }

        // 신뢰 프록시가 설정되지 않았거나 현재 요청이 신뢰 프록시에서 온 게 아니면
        // REMOTE_ADDR만 반환
        if (!$this->isFromTrustedProxy($remoteAddr)) {
            return $remoteAddr;
        }

        if (!empty($this->server['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $this->server['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        if (!empty($this->server['HTTP_X_REAL_IP'])) {
            return $this->server['HTTP_X_REAL_IP'];
        }

        if (!empty($this->server['HTTP_CLIENT_IP'])) {
            return $this->server['HTTP_CLIENT_IP'];
        }

        return $remoteAddr;
    }

    /**
     * 요청이 신뢰 프록시에서 왔는지 확인
     */
    private function isFromTrustedProxy(string $remoteAddr): bool
    {
        // 신뢰 프록시가 설정되지 않음
        if (empty(self::$trustedProxies)) {
            return false;
        }

        // 모든 프록시 신뢰 ('*')
        if (in_array('*', self::$trustedProxies, true)) {
            return true;
        }

        foreach (self::$trustedProxies as $proxy) {
            if ($this->ipMatches($remoteAddr, $proxy)) {
                return true;
            }
        }

        return false;
    }

    /**
     * IP 주소가 CIDR 패턴과 일치하는지 확인
     */
    private function ipMatches(string $ip, string $cidr): bool
    {
        // 정확한 IP 매치
        if ($ip === $cidr) {
            return true;
        }

        // CIDR 패턴 (예: 192.168.1.0/24)
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $cidr);
        $bits = (int) $bits;

        if ($bits === 0) {
            return true;
        }

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - $bits);
        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    // === File Methods ===

    /**
     * 업로드된 파일 전체 배열 반환 (raw $_FILES 구조)
     */
    public function getFiles(): array
    {
        return $this->files;
    }

    /**
     * 특정 키의 파일 존재 여부
     */
    public function hasFile(string $key): bool
    {
        return isset($this->files[$key])
            && ($this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    }

    /**
     * 특정 키의 raw 파일 배열 반환
     *
     * 중첩 구조(column_images[col][img][pc]) 등을 직접 처리할 때 사용
     */
    public function getRawFile(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }
}
