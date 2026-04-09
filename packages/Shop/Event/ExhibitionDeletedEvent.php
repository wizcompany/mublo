<?php

namespace Mublo\Packages\Shop\Event;

use Mublo\Core\Event\AbstractEvent;

class ExhibitionDeletedEvent extends AbstractEvent
{
    public function __construct(
        private readonly int $domainId,
        private readonly int $exhibitionId,
    ) {}

    public function getDomainId(): int { return $this->domainId; }
    public function getExhibitionId(): int { return $this->exhibitionId; }

    public function getUrl(): string
    {
        return '/shop/exhibitions/' . $this->exhibitionId;
    }
}
