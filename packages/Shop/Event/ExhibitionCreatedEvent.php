<?php

namespace Mublo\Packages\Shop\Event;

use Mublo\Core\Event\AbstractEvent;

class ExhibitionCreatedEvent extends AbstractEvent
{
    public function __construct(
        private readonly int $domainId,
        private readonly int $exhibitionId,
        private readonly string $title,
        private readonly ?string $slug,
    ) {}

    public function getDomainId(): int { return $this->domainId; }
    public function getExhibitionId(): int { return $this->exhibitionId; }
    public function getTitle(): string { return $this->title; }
    public function getSlug(): ?string { return $this->slug; }

    public function getUrl(): string
    {
        return '/shop/exhibitions/' . $this->exhibitionId;
    }
}
