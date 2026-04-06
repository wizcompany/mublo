<?php

namespace Mublo\Core\Middleware;

use Mublo\Core\Http\Request;
use Mublo\Core\Context\Context;
use Mublo\Core\Response\AbstractResponse;
use Mublo\Infrastructure\Session\SessionManager;

/**
 * Session Middleware
 *
 * 세션 시작 미들웨어
 * - 도메인별 세션 분리 (멀티테넌트)
 */
class SessionMiddleware implements MiddlewareInterface
{
    private SessionManager $session;

    public function __construct(SessionManager $session)
    {
        $this->session = $session;
    }

    public function handle(Request $request, Context $context, callable $next): AbstractResponse
    {
        // 도메인 ID로 세션 시작 (멀티테넌트 분리)
        $domainId = $context->getDomainId();
        $this->session->start($domainId);

        // 슬라이딩 세션: 매 요청마다 쿠키 만료 시간 갱신
        $this->session->renewCookie();

        $response = $next($request, $context);

        // 세션 잠금 조기 해제 (동시 요청 직렬화 방지)
        $this->session->close();

        return $response;
    }
}
