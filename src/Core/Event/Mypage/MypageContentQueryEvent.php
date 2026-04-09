<?php

namespace Mublo\Core\Event\Mypage;

use Mublo\Core\Event\AbstractEvent;

/**
 * 마이페이지 콘텐츠 조회 이벤트
 *
 * Core에서 발행 → Package(Board 등)가 구독하여 데이터 제공
 * 구독자가 없으면 빈 결과 반환 (Board 미설치 시 안전)
 */
class MypageContentQueryEvent extends AbstractEvent
{
    private array $result = [
        'items' => [],
        'pagination' => [
            'totalItems' => 0,
            'perPage' => 15,
            'currentPage' => 1,
            'totalPages' => 0,
        ],
    ];

    public function __construct(
        private readonly string $contentType,
        private readonly int $memberId,
        private readonly int $domainId,
        private readonly int $page,
        private readonly int $perPage,
    ) {}

    public function getContentType(): string { return $this->contentType; }
    public function getMemberId(): int { return $this->memberId; }
    public function getDomainId(): int { return $this->domainId; }
    public function getPage(): int { return $this->page; }
    public function getPerPage(): int { return $this->perPage; }

    public function setResult(array $items, array $pagination): void
    {
        $this->result = ['items' => $items, 'pagination' => $pagination];
    }

    public function getItems(): array { return $this->result['items']; }
    public function getPagination(): array { return $this->result['pagination']; }
}
