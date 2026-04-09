<?php

namespace Mublo\Packages\Board\Subscriber;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Core\Event\Search\SearchEvent;
use Mublo\Core\Event\Search\SearchSourceCollectEvent;
use Mublo\Packages\Board\Repository\BoardArticleRepository;

/**
 * 게시판 검색 구독자 (Core 내장)
 *
 * SearchEvent 발행 시 게시판 게시글을 검색하여 결과를 추가한다.
 * ServiceProvider.bootSubscribers()에서 등록.
 */
class BoardSearchSubscriber implements EventSubscriberInterface
{
    private BoardArticleRepository $articleRepository;

    public function __construct(BoardArticleRepository $articleRepository)
    {
        $this->articleRepository = $articleRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SearchSourceCollectEvent::class => 'onCollect',
            SearchEvent::class              => 'onSearch',
        ];
    }

    public function onCollect(SearchSourceCollectEvent $event): void
    {
        $event->addSource('board', '게시판', true);
    }

    public function onSearch(SearchEvent $event): void
    {
        if (!$event->isSourceEnabled('board')) {
            return;
        }

        $items = $this->articleRepository->searchByKeyword(
            $event->getDomainId(),
            $event->getKeyword(),
            $event->getPerSource()
        );

        $event->addResults('board', '게시판', $items, count($items));
    }
}
