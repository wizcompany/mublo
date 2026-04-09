<?php
namespace Mublo\Core\Middleware;

use Mublo\Core\Http\Request;
use Mublo\Core\Context\Context;
use Mublo\Core\Response\AbstractResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\ViewResponse;
use Mublo\Infrastructure\Security\CsrfManager;

/**
 * CSRF Middleware
 *
 * POST/PUT/DELETE 요청에 대한 CSRF 토큰 검증
 */
class CsrfMiddleware implements MiddlewareInterface
{
    protected CsrfManager $csrfManager;

    /**
     * CSRF 검증을 건너뛸 메서드
     */
    protected array $excludeMethods = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * CSRF 검증을 건너뛸 경로 패턴 (PG 콜백 등 외부 서버 요청)
     * Plugin/Package가 boot()에서 addExcludePath()로 등록
     */
    protected array $excludePaths = [];

    public function __construct(CsrfManager $csrfManager)
    {
        $this->csrfManager = $csrfManager;
    }

    /**
     * CSRF 예외 경로 등록 (Plugin/Package에서 호출)
     *
     * @param string $pathPattern 경로에 포함되면 CSRF 스킵 (str_contains 매칭)
     */
    public function addExcludePath(string $pathPattern): void
    {
        $this->excludePaths[] = $pathPattern;
    }

    public function handle(Request $request, Context $context, callable $next): AbstractResponse
    {
        // GET, HEAD, OPTIONS 요청은 검증 스킵
        if (in_array($request->getMethod(), $this->excludeMethods, true)) {
            return $next($request, $context);
        }

        // Bearer 토큰 인증 요청은 CSRF 스킵 (Worker API 등 외부 클라이언트)
        $authHeader = $request->header('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return $next($request, $context);
        }

        // 등록된 예외 경로 확인 (PG 콜백, 트래킹 API 등)
        $path = $request->getPath();
        foreach ($this->excludePaths as $pattern) {
            if (str_contains($path, $pattern)) {
                return $next($request, $context);
            }
        }

        // CSRF 토큰 추출
        $token = $this->getTokenFromRequest($request);

        // 토큰 검증
        if (!$token || !$this->csrfManager->validateToken($token)) {
            if ($request->isAjax()) {
                return JsonResponse::error('CSRF token mismatch', null, 419);
            }

            // Front/Admin 에러 뷰 분기
            $errorView = $context->isAdmin() ? 'Error/403' : 'error/forbidden';

            return ViewResponse::view($errorView)
                ->fullPage()
                ->withData(['message' => '페이지가 만료되었습니다. 다시 시도해주세요.']);
        }

        return $next($request, $context);
    }

    /**
     * 요청에서 CSRF 토큰 추출
     *
     * 우선순위:
     * 1. X-CSRF-Token 헤더
     * 2. JSON body의 _token 필드
     * 3. POST body의 _token 필드
     */
    protected function getTokenFromRequest(Request $request): ?string
    {
        // 헤더에서 추출 (MubloRequest.js에서 사용)
        $headerToken = $request->header('X-CSRF-Token');
        if ($headerToken) {
            return $headerToken;
        }

        // JSON body에서 추출
        if ($request->isJson()) {
            $jsonToken = $request->json('_token');
            if ($jsonToken) {
                return $jsonToken;
            }
        }

        // POST body에서 추출
        $postToken = $request->post('_token');
        if ($postToken) {
            return $postToken;
        }

        return null;
    }
}
