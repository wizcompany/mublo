<?php
namespace Mublo\Packages\Board\Subscriber;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Packages\Board\Event\ReactionAddedEvent;
use Mublo\Packages\Board\Event\ReactionRemovedEvent;
use Mublo\Packages\Board\Service\BoardPointService;

/**
 * ReactionPointSubscriber
 *
 * 반응(좋아요 등) 이벤트 발생 시 포인트 적립/회수 처리
 */
class ReactionPointSubscriber implements EventSubscriberInterface
{
    public function __construct(private BoardPointService $pointService) {}

    public static function getSubscribedEvents(): array
    {
        return [
            ReactionAddedEvent::class   => 'onReactionAdded',
            ReactionRemovedEvent::class => 'onReactionRemoved',
        ];
    }

    public function onReactionAdded(ReactionAddedEvent $event): void
    {
        $targetAuthorId = $event->getTargetAuthorId();
        if ($targetAuthorId === null) return;
        if ($targetAuthorId === $event->getMemberId()) return; // 자추 방지

        $reaction   = $event->getReaction();
        $domainId   = $reaction->getDomainId();
        $reactionId = $event->getReactionId();

        $this->pointService->award($domainId, $targetAuthorId, 'reaction_received', [
            'board_id'        => $reaction->getBoardId(),
            'reaction_type'   => $reaction->getReactionType(),
            'reference_type'  => $event->getTargetType(),
            'reference_id'    => (string) $event->getTargetId(),
            'idempotency_key' => "bp_reaction_{$domainId}_{$reactionId}",
        ]);
    }

    public function onReactionRemoved(ReactionRemovedEvent $event): void
    {
        $targetAuthorId = $event->getTargetAuthorId();
        if ($targetAuthorId === null) return;
        if ($targetAuthorId === $event->getMemberId()) return;

        $domainId = $event->getDomainId();
        if ($domainId === null) return;

        $this->pointService->revoke($domainId, $targetAuthorId, 'reaction_received', [
            'board_id'        => $event->getBoardId(),
            'reaction_type'   => $event->getReactionType(),
            'reference_type'  => $event->getTargetType(),
            'reference_id'    => (string) $event->getTargetId(),
            'idempotency_key' => "bp_reaction_revoke_{$domainId}_{$event->getTargetType()}_{$event->getTargetId()}_{$event->getMemberId()}",
        ]);
    }
}
