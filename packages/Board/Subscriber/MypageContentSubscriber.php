<?php

namespace Mublo\Packages\Board\Subscriber;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Core\Event\Mypage\MypageContentQueryEvent;
use Mublo\Packages\Board\Repository\BoardArticleRepository;
use Mublo\Packages\Board\Repository\BoardCommentRepository;

/**
 * 마이페이지 콘텐츠 구독자
 *
 * Core MypageController의 '내가 쓴 글/댓글' 요청을 Board 패키지가 처리
 */
class MypageContentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private BoardArticleRepository $articleRepository,
        private BoardCommentRepository $commentRepository,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            MypageContentQueryEvent::class => 'onMypageContentQuery',
        ];
    }

    public function onMypageContentQuery(MypageContentQueryEvent $event): void
    {
        match ($event->getContentType()) {
            'articles' => $this->handleArticles($event),
            'comments' => $this->handleComments($event),
            default => null,
        };
    }

    private function handleArticles(MypageContentQueryEvent $event): void
    {
        $result = $this->articleRepository->getByMember(
            memberId: $event->getMemberId(),
            domainId: $event->getDomainId(),
            page: $event->getPage(),
            perPage: $event->getPerPage(),
        );
        $event->setResult($result['items'], $result['pagination']);
    }

    private function handleComments(MypageContentQueryEvent $event): void
    {
        $result = $this->commentRepository->getByMember(
            memberId: $event->getMemberId(),
            domainId: $event->getDomainId(),
            page: $event->getPage(),
            perPage: $event->getPerPage(),
        );
        $event->setResult($result['items'], $result['pagination']);
    }
}
