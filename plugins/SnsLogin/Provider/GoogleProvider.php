<?php
namespace Mublo\Plugin\SnsLogin\Provider;

use Mublo\Plugin\SnsLogin\Contract\SnsProviderInterface;
use Mublo\Plugin\SnsLogin\Dto\SnsUserInfo;
use RuntimeException;

/**
 * Google OAuth2 제공자 (OpenID Connect)
 *
 * 사전 준비: https://console.cloud.google.com 에서
 * OAuth2 클라이언트 ID/Secret 생성 + Redirect URI 등록 필요
 */
class GoogleProvider implements SnsProviderInterface
{
    private const AUTH_URL     = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL    = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function __construct(
        private string $clientId,
        private string $clientSecret,
        private string $callbackUrl,
    ) {}

    public function getName(): string        { return 'google'; }
    public function getLabel(): string       { return 'Google'; }
    public function getButtonClass(): string { return 'btn-sns--google'; }

    public function getAuthorizationUrl(string $state): string
    {
        return self::AUTH_URL . '?' . http_build_query([
            'client_id'     => $this->clientId,
            'redirect_uri'  => $this->callbackUrl,
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'offline',
        ]);
    }

    public function exchangeCode(string $code): array
    {
        $response = $this->httpPost(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri'  => $this->callbackUrl,
            'grant_type'    => 'authorization_code',
        ]);

        if (empty($response['access_token'])) {
            throw new RuntimeException('Google 토큰 발급 실패: ' . ($response['error_description'] ?? '알 수 없는 오류'));
        }

        return $response;
    }

    public function getUserInfo(string $accessToken): SnsUserInfo
    {
        $response = $this->httpGet(self::USERINFO_URL, $accessToken);

        if (empty($response['sub'])) {
            throw new RuntimeException('Google 사용자 정보 조회 실패');
        }

        return new SnsUserInfo(
            provider:     'google',
            uid:          $response['sub'],
            email:        $response['email'] ?? null,
            nickname:     $response['name'] ?? null,
            profileImage: $response['picture'] ?? null,
        );
    }

    private function httpPost(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Google CURL 요청 실패: ' . $error);
        }
        curl_close($ch);

        return json_decode($body, true) ?? [];
    }

    private function httpGet(string $url, string $accessToken): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$accessToken}"],
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Google CURL 요청 실패: ' . $error);
        }
        curl_close($ch);

        return json_decode($body, true) ?? [];
    }
}
