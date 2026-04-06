<?php
namespace Mublo\Packages\Board\Repository;

use Mublo\Packages\Board\Entity\BoardComment;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Repository\BaseRepository;

/**
 * BoardComment Repository
 *
 * 댓글 데이터베이스 접근 담당
 *
 * 책임:
 * - board_comments 테이블 CRUD
 * - BoardComment Entity 반환
 * - 계층형 댓글 조회
 *
 * 금지:
 * - 비즈니스 로직 (Service 담당)
 */
class BoardCommentRepository extends BaseRepository
{
    protected string $table = 'board_comments';
    protected string $entityClass = BoardComment::class;
    protected string $primaryKey = 'comment_id';

    public function __construct(?Database $db = null)
    {
        $db = $db ?? DatabaseManager::getInstance()->connect();
        parent::__construct($db);
    }

    /**
     * 게시글별 댓글 목록 조회 (계층형)
     *
     * @param int $articleId 게시글 ID
     * @param bool $includeDeleted 삭제된 댓글 포함 여부
     * @return BoardComment[]
     */
    public function getCommentsByArticle(int $articleId, bool $includeDeleted = false): array
    {
        $query = $this->getDb()->table($this->table)
            ->where('article_id', '=', $articleId);

        if (!$includeDeleted) {
            $query->where('status', '=', 'published');
        }

        // path 기준 정렬 (계층 구조 유지)
        $rows = $query
            ->orderBy('path', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 게시글별 댓글 목록 조회
     *
     * author_name이 board_comments에 저장되므로 members JOIN 불필요
     *
     * @param int $articleId 게시글 ID
     * @return BoardComment[]
     */
    public function getCommentsWithAuthor(int $articleId): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('article_id', '=', $articleId)
            ->where('status', '=', 'published')
            ->orderBy('path', 'ASC')
            ->orderBy('created_at', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 대댓글 포함 삭제
     *
     * @param int $commentId 댓글 ID
     * @return int 삭제된 댓글 수
     */
    public function deleteWithChildren(int $commentId): int
    {
        // 해당 댓글의 path 조회
        $comment = $this->find($commentId);
        if (!$comment) {
            return 0;
        }

        // path가 현재 댓글 path로 시작하는 모든 댓글 삭제
        $path = $comment->getPath();

        return $this->getDb()->table($this->table)
            ->where('comment_id', '=', $commentId)
            ->orWhere('path', 'LIKE', $path . '/%')
            ->update(['status' => 'deleted']);
    }

    /**
     * 상태 변경
     */
    public function updateStatus(int $commentId, string $status): bool
    {
        $affected = $this->getDb()->table($this->table)
            ->where('comment_id', '=', $commentId)
            ->update(['status' => $status]);

        return $affected > 0;
    }

    /**
     * 게시글별 댓글 수 조회
     */
    public function countByArticle(int $articleId, ?string $status = 'published'): int
    {
        $query = $this->getDb()->table($this->table)
            ->where('article_id', '=', $articleId);

        if ($status !== null) {
            $query->where('status', '=', $status);
        }

        return $query->count();
    }

    /**
     * 회원의 특정 게시판 오늘 댓글 수 조회
     */
    public function countTodayByMember(int $boardId, int $memberId): int
    {
        return $this->getDb()->table($this->table)
            ->where('board_id', '=', $boardId)
            ->where('member_id', '=', $memberId)
            ->where('created_at', '>=', date('Y-m-d 00:00:00'))
            ->where('status', '!=', 'deleted')
            ->count();
    }

    /**
     * 회원별 댓글 수 조회
     */
    public function countByMember(int $memberId, ?string $status = 'published'): int
    {
        $query = $this->getDb()->table($this->table)
            ->where('member_id', '=', $memberId);

        if ($status !== null) {
            $query->where('status', '=', $status);
        }

        return $query->count();
    }

    /**
     * 게시판별 댓글 수 조회
     */
    public function countByBoard(int $boardId, ?string $status = 'published'): int
    {
        $query = $this->getDb()->table($this->table)
            ->where('board_id', '=', $boardId);

        if ($status !== null) {
            $query->where('status', '=', $status);
        }

        return $query->count();
    }

    /**
     * 다음 path 생성
     *
     * @param int $articleId 게시글 ID
     * @param int|null $parentId 부모 댓글 ID
     * @return string 새 path
     */
    public function generatePath(int $articleId, ?int $parentId = null): string
    {
        if ($parentId === null) {
            // 루트 댓글: 새 시퀀스 번호
            $maxPath = $this->getDb()->table($this->table)
                ->where('article_id', '=', $articleId)
                ->whereNull('parent_id')
                ->max('path');

            $nextSeq = $maxPath ? ((int) $maxPath + 1) : 1;
            return str_pad((string) $nextSeq, 10, '0', STR_PAD_LEFT);
        }

        // 대댓글: 부모 path + 시퀀스
        $parent = $this->find($parentId);
        if (!$parent) {
            return str_pad('1', 10, '0', STR_PAD_LEFT);
        }

        $parentPath = $parent->getPath();

        // 부모 아래 마지막 댓글의 path 조회
        $lastChildPath = $this->getDb()->table($this->table)
            ->where('article_id', '=', $articleId)
            ->where('parent_id', '=', $parentId)
            ->max('path');

        if ($lastChildPath) {
            // 기존 자식이 있으면 마지막 시퀀스 + 1
            $parts = explode('/', $lastChildPath);
            $lastPart = end($parts);
            $nextSeq = ((int) $lastPart) + 1;
        } else {
            $nextSeq = 1;
        }

        return $parentPath . '/' . str_pad((string) $nextSeq, 10, '0', STR_PAD_LEFT);
    }

    /**
     * depth 계산
     */
    public function calculateDepth(?int $parentId): int
    {
        if ($parentId === null) {
            return 0;
        }

        $parent = $this->find($parentId);
        return $parent ? $parent->getDepth() + 1 : 0;
    }

    /**
     * 자식 댓글 조회
     */
    public function getChildren(int $commentId): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('parent_id', '=', $commentId)
            ->where('status', '=', 'published')
            ->orderBy('created_at', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 자식 댓글 존재 여부
     */
    public function hasChildren(int $commentId): bool
    {
        return $this->getDb()->table($this->table)
            ->where('parent_id', '=', $commentId)
            ->where('status', '=', 'published')
            ->exists();
    }

    /**
     * 반응수 동기화
     */
    public function syncReactionCount(int $commentId): void
    {
        $count = $this->getDb()->table('board_reactions')
            ->where('target_type', '=', 'comment')
            ->where('target_id', '=', $commentId)
            ->count();

        $this->getDb()->table($this->table)
            ->where('comment_id', '=', $commentId)
            ->update(['reaction_count' => $count]);
    }

    /**
     * 최근 댓글 조회 (Admin용)
     */
    public function getRecentComments(int $domainId, int $limit = 10): array
    {
        $rows = $this->getDb()->table($this->table . ' AS c')
            ->select([
                'c.*',
                'a.title AS article_title',
                'b.board_name',
            ])
            ->leftJoin('board_articles AS a', 'c.article_id', '=', 'a.article_id')
            ->leftJoin('board_configs AS b', 'c.board_id', '=', 'b.board_id')
            ->where('c.domain_id', '=', $domainId)
            ->where('c.status', '=', 'published')
            ->orderBy('c.created_at', 'DESC')
            ->limit($limit)
            ->get();

        return $rows;
    }

    /**
     * 회원이 작성한 댓글 목록 (마이페이지용)
     *
     * @return array{items: array[], pagination: array}
     */
    public function getByMember(int $memberId, int $domainId, int $page = 1, int $perPage = 15): array
    {
        $query = $this->getDb()->table($this->table . ' AS c')
            ->select([
                'c.comment_id', 'c.article_id', 'c.board_id', 'c.content', 'c.created_at',
                'a.title AS article_title', 'a.slug AS article_slug',
                'b.board_name', 'b.board_slug',
            ])
            ->leftJoin('board_articles AS a', 'c.article_id', '=', 'a.article_id')
            ->leftJoin('board_configs AS b', 'c.board_id', '=', 'b.board_id')
            ->where('c.member_id', '=', $memberId)
            ->where('c.domain_id', '=', $domainId)
            ->where('c.status', '=', 'published');

        $total  = $query->count();
        $offset = ($page - 1) * $perPage;
        $rows   = $query->orderBy('c.created_at', 'DESC')->limit($perPage)->offset($offset)->get();

        return [
            'items' => $rows,
            'pagination' => [
                'totalItems'  => $total,
                'perPage'     => $perPage,
                'currentPage' => $page,
                'totalPages'  => (int) ceil($total / $perPage),
            ],
        ];
    }
}
