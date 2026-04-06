<?php

namespace Mublo\Core\Event\Tracking;

use Mublo\Core\Event\AbstractEvent;

/**
 * 페이지뷰 이벤트
 *
 * 모든 Front 페이지 렌더링 완료 시 발행.
 * 방문통계 플러그인 등이 구독하여 방문 기록을 수집한다.
 */
class PageViewedEvent extends AbstractEvent
{
    public function __construct(
        private readonly int $domainId,
        private readonly string $url,
        private readonly string $pageType,
        private readonly ?int $memberId = null,
        private readonly string $ipAddress = '',
        private readonly string $userAgent = '',
        private readonly string $referer = ''
    ) {
    }

    public function getDomainId(): int
    {
        return $this->domainId;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * 페이지 유형
     *
     * 'index', 'page', 'board_list', 'board_view',
     * 'auth', 'member', 'search', 'community', 'other'
     */
    public function getPageType(): string
    {
        return $this->pageType;
    }

    public function getMemberId(): ?int
    {
        return $this->memberId;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function getReferer(): string
    {
        return $this->referer;
    }
}
