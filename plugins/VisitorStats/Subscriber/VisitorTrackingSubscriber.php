<?php

namespace Mublo\Plugin\VisitorStats\Subscriber;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Core\Event\Rendering\SiteContextReadyEvent;
use Mublo\Core\Session\SessionInterface;
use Mublo\Plugin\VisitorStats\Service\UserAgentParser;
use Mublo\Plugin\VisitorStats\Service\VisitorCollector;

/**
 * VisitorTrackingSubscriber
 *
 * SiteContextReadyEvent 구독 — 프론트 페이지 방문 시 통계 수집
 */
class VisitorTrackingSubscriber implements EventSubscriberInterface
{
    /** 제외할 확장자 */
    private const EXCLUDED_EXTENSIONS = [
        'css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'avif',
        'ico', 'woff', 'woff2', 'ttf', 'eot', 'map', 'xml', 'json',
        'mp3', 'mp4', 'webm', 'pdf', 'zip', 'gz',
    ];

    public function __construct(
        private VisitorCollector $collector,
        private SessionInterface $session,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            SiteContextReadyEvent::class => 'onSiteContextReady',
        ];
    }

    public function onSiteContextReady(SiteContextReadyEvent $event): void
    {
        $context = $event->getContext();
        $request = $event->getRequest();

        // 프론트만 추적
        if (!$context->isFront()) {
            return;
        }

        // AJAX 요청 제외
        if ($request->isAjax()) {
            return;
        }

        $uri = $request->getUri();

        // 정적 파일 제외
        if ($this->isStaticFile($uri)) {
            return;
        }

        $userAgent = $request->header('User-Agent') ?? '';

        // 봇 제외
        if (UserAgentParser::isBot($userAgent)) {
            return;
        }

        $domainId = $context->getDomainId() ?? 1;
        $siteDomain = $context->getDomain() ?? '';

        try {
            $this->collector->track(
                domainId: $domainId,
                ipAddress: $request->getClientIp(),
                userAgent: $userAgent,
                uri: $uri,
                referer: $request->header('Referer'),
                memberId: $this->session->get('member_id') ? (int) $this->session->get('member_id') : null,
                siteDomain: $siteDomain,
                campaignKey: $request->query('k')
            );
        } catch (\Throwable $e) {
            // 통계 수집 실패가 사이트 동작을 방해하면 안 됨
            error_log('[VisitorStats] Track failed: ' . $e->getMessage());
        }
    }

    private function isStaticFile(string $uri): bool
    {
        // 쿼리스트링 제거
        $path = strtok($uri, '?');
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($ext, self::EXCLUDED_EXTENSIONS, true);
    }
}
