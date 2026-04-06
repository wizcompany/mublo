<?php
namespace Mublo\Packages\Board\Event;

use Mublo\Core\Event\AbstractEvent;
use Mublo\Packages\Board\Entity\BoardArticle;
use Mublo\Entity\Member\Member;

/**
 * 게시글 작성 완료 이벤트 (readonly)
 *
 * 발행 시점: DB 저장 후
 * 용도: 포인트 지급, 알림 발송, 검색 인덱싱
 *
 * Note: 차단 불가 - 이미 작성이 완료된 후 발행됨
 */
class ArticleCreatedEvent extends AbstractEvent
{
    public const NAME = 'board.article.created';

    public function __construct(
        private readonly BoardArticle $article,
        private readonly ?Member $author = null
    ) {}

    public function getArticle(): BoardArticle
    {
        return $this->article;
    }

    public function getAuthor(): ?Member
    {
        return $this->author;
    }

    public function getArticleId(): int
    {
        return $this->article->getArticleId();
    }

    public function getBoardId(): int
    {
        return $this->article->getBoardId();
    }

    public function getDomainId(): int
    {
        return $this->article->getDomainId();
    }

    public function getMemberId(): ?int
    {
        return $this->article->getMemberId();
    }
}
