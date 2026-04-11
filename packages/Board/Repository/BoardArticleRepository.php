<?php
namespace Mublo\Packages\Board\Repository;

use Mublo\Packages\Board\Entity\BoardArticle;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Repository\BaseRepository;

/**
 * BoardArticle Repository
 *
 * 게시글 데이터베이스 접근 담당
 *
 * 책임:
 * - board_articles 테이블 CRUD
 * - BoardArticle Entity 반환
 * - 복합 쿼리 (필터, 페이지네이션)
 *
 * 금지:
 * - 비즈니스 로직 (Service 담당)
 */
class BoardArticleRepository extends BaseRepository
{
    protected string $table = 'board_articles';
    protected string $entityClass = BoardArticle::class;
    protected string $primaryKey = 'article_id';

    /**
     * LIKE 검색용 와일드카드 이스케이프
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }

    public function __construct(?Database $db = null)
    {
        $db = $db ?? DatabaseManager::getInstance()->connect();
        parent::__construct($db);
    }

    /**
     * 게시판별 게시글 목록 조회 (페이지네이션)
     *
     * @param int $domainId 도메인 ID
     * @param int $boardId 게시판 ID
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @param array $filters 필터 조건
     * @return array ['items' => BoardArticle[], 'pagination' => [...]]
     */
    public function getPaginatedList(
        int $domainId,
        int $boardId,
        int $page = 1,
        int $perPage = 20,
        array $filters = [],
        bool $isGlobal = false
    ): array {
        $query = $this->getDb()->table($this->table . ' AS a')
            ->where('a.board_id', '=', $boardId);

        // 전역 게시판이 아닌 경우에만 도메인 필터를 적용
        if (!$isGlobal) {
            $query->where('a.domain_id', '=', $domainId);
        }

        // 상태 필터 (기본: published)
        $status = $filters['status'] ?? 'published';
        if ($status !== 'all') {
            $query->where('a.status', '=', $status);
        }

        // 카테고리 필터
        if (!empty($filters['category_id'])) {
            $query->where('a.category_id', '=', (int) $filters['category_id']);
        }

        // 공지사항 필터
        if (isset($filters['is_notice'])) {
            $query->where('a.is_notice', '=', (int) $filters['is_notice']);
        }

        // 검색
        if (!empty($filters['keyword']) && !empty($filters['search_field'])) {
            $keyword = '%' . $this->escapeLike($filters['keyword']) . '%';
            $field = $filters['search_field'];

            if ($field === 'title') {
                $query->where('a.title', 'LIKE', $keyword);
            } elseif ($field === 'content') {
                $query->where('a.content', 'LIKE', $keyword);
            } elseif ($field === 'title_content') {
                $query->whereRaw('(a.title LIKE ? OR a.content LIKE ?)', [$keyword, $keyword]);
            }
        }

        // 회원 필터
        if (!empty($filters['member_id'])) {
            $query->where('a.member_id', '=', (int) $filters['member_id']);
        }

        // 전체 개수
        $total = $query->count();

        // 정렬 및 페이지네이션
        $offset = ($page - 1) * $perPage;
        $rows = $query
            ->orderBy('a.is_notice', 'DESC')
            ->orderBy('a.created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'items' => $this->toEntities($rows),
            'pagination' => [
                'totalItems' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * 공지사항 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @param int $boardId 게시판 ID
     * @param int $limit 조회 개수
     * @return BoardArticle[]
     */
    public function getNotices(int $domainId, int $boardId, int $limit = 10, bool $isGlobal = false): array
    {
        $query = $this->getDb()->table($this->table)
            ->where('board_id', '=', $boardId)
            ->where('is_notice', '=', 1)
            ->where('status', '=', 'published');

        if (!$isGlobal) {
            $query->where('domain_id', '=', $domainId);
        }

        $rows = $query
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 이전/다음 글 조회
     *
     * @param int $articleId 현재 게시글 ID
     * @param int $boardId 게시판 ID
     * @return array ['prev' => ?BoardArticle, 'next' => ?BoardArticle]
     */
    public function getAdjacentArticles(int $articleId, int $boardId): array
    {
        // 이전 글 (더 작은 ID 중 가장 큰 것)
        $prevRow = $this->getDb()->table($this->table)
            ->where('board_id', '=', $boardId)
            ->where('article_id', '<', $articleId)
            ->where('status', '=', 'published')
            ->where('is_notice', '=', 0)
            ->orderBy('article_id', 'DESC')
            ->first();

        // 다음 글 (더 큰 ID 중 가장 작은 것)
        $nextRow = $this->getDb()->table($this->table)
            ->where('board_id', '=', $boardId)
            ->where('article_id', '>', $articleId)
            ->where('status', '=', 'published')
            ->where('is_notice', '=', 0)
            ->orderBy('article_id', 'ASC')
            ->first();

        return [
            'prev' => $prevRow ? BoardArticle::fromArray($prevRow) : null,
            'next' => $nextRow ? BoardArticle::fromArray($nextRow) : null,
        ];
    }

    /**
     * 조회수 증가
     */
    public function incrementViewCount(int $articleId): void
    {
        $table = $this->getDb()->prefixTable($this->table);
        $this->getDb()->execute(
            "UPDATE {$table} SET view_count = view_count + 1 WHERE article_id = ?",
            [$articleId]
        );
    }

    /**
     * 댓글수 동기화
     */
    public function syncCommentCount(int $articleId): void
    {
        $count = $this->getDb()->table('board_comments')
            ->where('article_id', '=', $articleId)
            ->where('status', '=', 'published')
            ->count();

        $this->getDb()->table($this->table)
            ->where('article_id', '=', $articleId)
            ->update(['comment_count' => $count]);
    }

    /**
     * 반응수 동기화
     */
    public function syncReactionCount(int $articleId): void
    {
        $count = $this->getDb()->table('board_reactions')
            ->where('target_type', '=', 'article')
            ->where('target_id', '=', $articleId)
            ->count();

        $this->getDb()->table($this->table)
            ->where('article_id', '=', $articleId)
            ->update(['reaction_count' => $count]);
    }

    /**
     * 게시판별 게시글 수 조회
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
     * 회원의 특정 게시판 오늘 글 수 조회
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
     * 회원별 게시글 수 조회
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
     * 슬러그로 게시글 조회
     */
    public function findBySlug(int $boardId, string $slug): ?BoardArticle
    {
        return $this->findOneBy([
            'board_id' => $boardId,
            'slug' => $slug,
        ]);
    }

    /**
     * 슬러그 중복 검사
     */
    public function existsBySlug(int $boardId, string $slug): bool
    {
        return $this->existsBy([
            'board_id' => $boardId,
            'slug' => $slug,
        ]);
    }

    /**
     * 슬러그 중복 검사 (자기 자신 제외)
     */
    public function existsBySlugExceptSelf(int $boardId, string $slug, int $articleId): bool
    {
        return $this->getDb()->table($this->table)
            ->where('board_id', '=', $boardId)
            ->where('slug', '=', $slug)
            ->where('article_id', '!=', $articleId)
            ->exists();
    }

    /**
     * 상태 변경
     */
    public function updateStatus(int $articleId, string $status): bool
    {
        $affected = $this->getDb()->table($this->table)
            ->where('article_id', '=', $articleId)
            ->update(['status' => $status]);

        return $affected > 0;
    }

    /**
     * 특정 도메인에 속하는 게시글 수 조회 (ID 목록 기준)
     *
     * @param int $domainId 도메인 ID
     * @param array $articleIds 게시글 ID 배열
     * @return int 해당 도메인에 속하는 게시글 수
     */
    public function countByDomainAndIds(int $domainId, array $articleIds): int
    {
        if (empty($articleIds)) {
            return 0;
        }

        return (int) $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->whereIn('article_id', $articleIds)
            ->count();
    }

    /**
     * 일괄 상태 변경
     */
    public function bulkUpdateStatus(array $articleIds, string $status): int
    {
        if (empty($articleIds)) {
            return 0;
        }

        return $this->getDb()->table($this->table)
            ->whereIn('article_id', $articleIds)
            ->update(['status' => $status]);
    }

    /**
     * 도메인별 전체 게시글 조회 (통합 피드용)
     *
     * @param int $domainId 도메인 ID
     * @param int $page 페이지
     * @param int $perPage 페이지당 개수
     * @param array $filters 필터
     * @return array
     */
    public function getAllByDomain(
        int $domainId,
        int $page = 1,
        int $perPage = 20,
        array $filters = []
    ): array {
        $query = $this->getDb()->table($this->table . ' AS a')
            ->select([
                'a.*',
                'b.board_name',
                'b.board_slug',
                'g.group_name',
                'g.group_slug',
            ])
            ->leftJoin('board_configs AS b', 'a.board_id', '=', 'b.board_id')
            ->leftJoin('board_groups AS g', 'b.group_id', '=', 'g.group_id')
            ->where('a.domain_id', '=', $domainId)
            ->where('a.status', '=', 'published')
            ->where('b.is_active', '=', 1);

        // 그룹 필터
        if (!empty($filters['group_id'])) {
            $query->where('b.group_id', '=', (int) $filters['group_id']);
        }

        // 게시판 필터 (단일)
        if (!empty($filters['board_id'])) {
            $query->where('a.board_id', '=', (int) $filters['board_id']);
        }

        // 게시판 필터 (복수 - 권한 기반)
        if (!empty($filters['board_ids'])) {
            $query->whereIn('a.board_id', $filters['board_ids']);
        }

        // 검색
        if (!empty($filters['keyword']) && !empty($filters['search_field'])) {
            $keyword = '%' . $this->escapeLike($filters['keyword']) . '%';
            $field = $filters['search_field'];

            if ($field === 'title') {
                $query->where('a.title', 'LIKE', $keyword);
            } elseif ($field === 'content') {
                $query->where('a.content', 'LIKE', $keyword);
            } elseif ($field === 'title_content') {
                $query->whereRaw('(a.title LIKE ? OR a.content LIKE ?)', [$keyword, $keyword]);
            }
        }

        // 전체 개수
        $total = $query->count();

        // 정렬 (허용된 컬럼만)
        $allowedOrderBy = ['created_at', 'view_count', 'reaction_count', 'comment_count', 'title'];
        $orderBy = in_array($filters['order_by'] ?? 'created_at', $allowedOrderBy, true)
            ? ($filters['order_by'] ?? 'created_at')
            : 'created_at';
        $orderDir = $filters['order_dir'] ?? 'DESC';
        $query->orderBy('a.' . $orderBy, $orderDir);

        // 페이지네이션
        $offset = ($page - 1) * $perPage;
        $rows = $query->limit($perPage)->offset($offset)->get();

        // 결과에 게시판/그룹 정보 포함 (작성자는 author_name 사용)
        $items = [];
        foreach ($rows as $row) {
            $article = BoardArticle::fromArray($row);
            $items[] = [
                'article' => $article,
                'board_name' => $row['board_name'] ?? '',
                'board_slug' => $row['board_slug'] ?? '',
                'group_name' => $row['group_name'] ?? '',
                'group_slug' => $row['group_slug'] ?? '',
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'totalItems' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * 인기글 조회 (조회수/반응수 기준)
     *
     * @param int $domainId 도메인 ID
     * @param int $limit 조회 개수
     * @param string $orderBy 정렬 기준 (view_count, reaction_count)
     * @param int $days 최근 N일
     * @return BoardArticle[]
     */
    public function getPopular(
        int $domainId,
        int $limit = 10,
        string $orderBy = 'view_count',
        int $days = 7
    ): array {
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $allowedOrderBy = ['view_count', 'reaction_count', 'comment_count', 'created_at'];
        $safeOrderBy = in_array($orderBy, $allowedOrderBy, true) ? $orderBy : 'view_count';

        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('status', '=', 'published')
            ->where('created_at', '>=', $since)
            ->orderBy($safeOrderBy, 'DESC')
            ->limit($limit)
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 인기글 페이지네이션 조회 (통합 피드용)
     *
     * @param int $domainId 도메인 ID
     * @param int $page 페이지
     * @param int $perPage 페이지당 개수
     * @param array $filters 필터 (days, board_ids, group_id, keyword, search_field)
     * @return array ['items' => [...], 'pagination' => [...]]
     */
    public function getPopularPaginated(
        int $domainId,
        int $page = 1,
        int $perPage = 20,
        array $filters = []
    ): array {
        $days = $filters['days'] ?? 7;
        $since = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        $query = $this->getDb()->table($this->table . ' AS a')
            ->select([
                'a.*',
                'b.board_name',
                'b.board_slug',
                'g.group_name',
                'g.group_slug',
            ])
            ->leftJoin('board_configs AS b', 'a.board_id', '=', 'b.board_id')
            ->leftJoin('board_groups AS g', 'b.group_id', '=', 'g.group_id')
            ->where('a.domain_id', '=', $domainId)
            ->where('a.status', '=', 'published')
            ->where('b.is_active', '=', 1)
            ->where('a.created_at', '>=', $since);

        // 게시판 필터 (권한 기반)
        if (!empty($filters['board_ids'])) {
            $query->whereIn('a.board_id', $filters['board_ids']);
        }

        // 그룹 필터
        if (!empty($filters['group_id'])) {
            $query->where('b.group_id', '=', (int) $filters['group_id']);
        }

        // 검색
        if (!empty($filters['keyword']) && !empty($filters['search_field'])) {
            $keyword = '%' . $this->escapeLike($filters['keyword']) . '%';
            $field = $filters['search_field'];

            if ($field === 'title') {
                $query->where('a.title', 'LIKE', $keyword);
            } elseif ($field === 'content') {
                $query->where('a.content', 'LIKE', $keyword);
            } elseif ($field === 'title_content') {
                $query->whereRaw('(a.title LIKE ? OR a.content LIKE ?)', [$keyword, $keyword]);
            }
        }

        $total = $query->count();

        // 인기순 정렬
        $offset = ($page - 1) * $perPage;
        $rows = $query
            ->orderBy('a.view_count', 'DESC')
            ->orderBy('a.reaction_count', 'DESC')
            ->orderBy('a.created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $article = BoardArticle::fromArray($row);
            $items[] = [
                'article' => $article,
                'board_name' => $row['board_name'] ?? '',
                'board_slug' => $row['board_slug'] ?? '',
                'group_name' => $row['group_name'] ?? '',
                'group_slug' => $row['group_slug'] ?? '',
            ];
        }

        return [
            'items' => $items,
            'pagination' => [
                'totalItems' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    /**
     * 게시글 상세 조회
     *
     * author_name이 board_articles에 저장되므로 members JOIN 불필요
     */
    public function findWithAuthor(int $articleId): ?array
    {
        $article = $this->find($articleId);

        if (!$article) {
            return null;
        }

        return [
            'article' => $article,
        ];
    }

    /**
     * Admin용 전체 게시글 목록 조회
     */
    public function getAdminList(
        int $domainId,
        int $page = 1,
        int $perPage = 20,
        array $filters = []
    ): array {
        $query = $this->getDb()->table($this->table . ' AS a')
            ->select([
                'a.*',
                'b.board_name',
                'b.board_slug',
                'm.user_id AS author_userid',
            ])
            ->leftJoin('board_configs AS b', 'a.board_id', '=', 'b.board_id')
            ->leftJoin('members AS m', 'a.member_id', '=', 'm.member_id')
            ->where('a.domain_id', '=', $domainId);

        // 게시판 필터
        if (!empty($filters['board_id'])) {
            $query->where('a.board_id', '=', (int) $filters['board_id']);
        }

        // 상태 필터
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('a.status', '=', $filters['status']);
        }

        // 검색
        if (!empty($filters['keyword']) && !empty($filters['search_field'])) {
            $keyword = '%' . $this->escapeLike($filters['keyword']) . '%';
            $field = $filters['search_field'];

            if ($field === 'title') {
                $query->where('a.title', 'LIKE', $keyword);
            } elseif ($field === 'content') {
                $query->where('a.content', 'LIKE', $keyword);
            } elseif ($field === 'author') {
                $query->where('a.author_name', 'LIKE', $keyword);
            }
        }

        // 전체 개수
        $total = $query->count();

        // 정렬 및 페이지네이션
        $offset = ($page - 1) * $perPage;
        $rows = $query
            ->orderBy('a.created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'items' => $rows,
            'pagination' => [
                'totalItems' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * 전체 검색용 게시글 조회 (제목+내용 LIKE, 공개 게시글만)
     *
     * @param int    $domainId 도메인 ID
     * @param string $keyword  검색 키워드
     * @param int    $limit    최대 결과 수
     * @return array [{title, url, summary, thumbnail, date, meta(board_name)}]
     */
    public function searchByKeyword(int $domainId, string $keyword, int $limit = 5): array
    {
        $kw = '%' . $this->escapeLike($keyword) . '%';

        $rows = $this->getDb()->table($this->table . ' AS a')
            ->select(['a.article_id', 'a.title', 'a.content', 'a.thumbnail', 'a.created_at',
                      'b.board_slug', 'b.board_name'])
            ->leftJoin('board_configs AS b', 'a.board_id', '=', 'b.board_id')
            ->where('a.domain_id', '=', $domainId)
            ->where('a.status', '=', 'published')
            ->where('b.is_active', '=', 1)
            ->whereRaw('(a.title LIKE ? OR a.content LIKE ?)', [$kw, $kw])
            ->orderBy('a.created_at', 'DESC')
            ->limit($limit)
            ->get();

        $items = [];
        foreach ($rows as $row) {
            $summary = strip_tags($row['content'] ?? '');
            $summary = mb_strlen($summary) > 100 ? mb_substr($summary, 0, 100) . '...' : $summary;
            $items[] = [
                'title'     => $row['title'] ?? '',
                'url'       => '/board/' . ($row['board_slug'] ?? '') . '/view/' . $row['article_id'],
                'summary'   => $summary,
                'thumbnail' => $row['thumbnail'] ?? null,
                'date'      => isset($row['created_at']) ? substr($row['created_at'], 0, 10) : null,
                'meta'      => $row['board_name'] ?? '',
            ];
        }

        return $items;
    }

    /**
     * 회원이 작성한 게시글 목록 (마이페이지용)
     *
     * @return array{items: array[], pagination: array}
     */
    public function getByMember(int $memberId, int $domainId, int $page = 1, int $perPage = 15): array
    {
        $query = $this->getDb()->table($this->table . ' AS a')
            ->select([
                'a.article_id', 'a.board_id', 'a.title', 'a.status',
                'a.view_count', 'a.comment_count', 'a.created_at', 'a.thumbnail',
                'b.board_name', 'b.board_slug',
            ])
            ->leftJoin('board_configs AS b', 'a.board_id', '=', 'b.board_id')
            ->where('a.member_id', '=', $memberId)
            ->where('a.domain_id', '=', $domainId)
            ->where('a.status', '=', 'published');

        $total  = $query->count();
        $offset = ($page - 1) * $perPage;
        $rows   = $query->orderBy('a.created_at', 'DESC')->limit($perPage)->offset($offset)->get();

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
