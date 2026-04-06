<?php
namespace Mublo\Packages\Board\Service;

use Mublo\Packages\Board\Entity\BoardArticle;
use Mublo\Packages\Board\Entity\BoardConfig;
use Mublo\Packages\Board\Repository\BoardArticleRepository;
use Mublo\Packages\Board\Repository\BoardConfigRepository;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Core\Context\Context;
use Mublo\Service\Auth\AuthService;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Packages\Board\Event\ArticleCreatingEvent;
use Mublo\Packages\Board\Event\ArticleCreatedEvent;
use Mublo\Packages\Board\Event\ArticleUpdatingEvent;
use Mublo\Packages\Board\Event\ArticleUpdatedEvent;
use Mublo\Packages\Board\Event\ArticleDeletingEvent;
use Mublo\Packages\Board\Event\ArticleDeletedEvent;
use Mublo\Packages\Board\Event\ArticleViewingEvent;
use Mublo\Packages\Board\Event\ArticleViewedEvent;
use Mublo\Core\Result\Result;
use Mublo\Helper\Editor\EditorHelper;
use Mublo\Helper\String\StringHelper;

/**
 * BoardArticleService
 *
 * 게시글 비즈니스 로직 + 이벤트 발행
 */
class BoardArticleService
{
    private BoardArticleRepository $articleRepository;
    private BoardConfigRepository $boardRepository;
    private MemberRepository $memberRepository;
    private BoardPermissionService $permissionService;
    private ?EventDispatcher $eventDispatcher;
    private AuthService $authService;

    public function __construct(
        BoardArticleRepository $articleRepository,
        BoardConfigRepository $boardRepository,
        MemberRepository $memberRepository,
        BoardPermissionService $permissionService,
        ?EventDispatcher $eventDispatcher,
        AuthService $authService
    ) {
        $this->articleRepository = $articleRepository;
        $this->boardRepository = $boardRepository;
        $this->memberRepository = $memberRepository;
        $this->permissionService = $permissionService;
        $this->eventDispatcher = $eventDispatcher;
        $this->authService = $authService;
    }

    /**
     * 현재 로그인 사용자 정보 조회
     */
    private function getCurrentUser(): ?array
    {
        return $this->authService->user();
    }

    /**
     * 현재 로그인 사용자 ID 조회
     */
    private function getCurrentUserId(): ?int
    {
        return $this->authService->id();
    }

    /**
     * 이벤트 발행 헬퍼
     */
    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }

    /**
     * 게시글 목록 조회 (권한 체크 포함)
     */
    public function getList(
        int $domainId,
        int $boardId,
        int $page,
        array $filters,
        Context $context
    ): Result {
        // 게시판 조회
        $board = $this->boardRepository->find($boardId);
        if (!$board) {
            return Result::failure('게시판을 찾을 수 없습니다.');
        }

        // 권한 체크
        if (!$this->permissionService->canList($board, $context)) {
            return Result::failure('목록 보기 권한이 없습니다.');
        }

        // 목록 조회
        $result = $this->articleRepository->getPaginatedList(
            $domainId,
            $boardId,
            $page,
            $filters['per_page'] ?? 20,
            $filters
        );

        return Result::success('', [
            'board' => $board->toArray(),
            'items' => array_map(fn($a) => $a->toArray(), $result['items']),
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * 게시글 상세 조회 (조회수 증가 + 이벤트)
     */
    public function getArticle(int $articleId, Context $context, bool $incrementView = true): Result
    {
        // 게시글 조회
        $articleData = $this->articleRepository->findWithAuthor($articleId);
        if (!$articleData) {
            return Result::failure('게시글을 찾을 수 없습니다.');
        }

        $article = $articleData['article'];

        // 게시판 조회
        $board = $this->boardRepository->find($article->getBoardId());
        if (!$board) {
            return Result::failure('게시판을 찾을 수 없습니다.');
        }

        // 권한 체크
        if (!$this->permissionService->canRead($board, $article, $context)) {
            return Result::failure('읽기 권한이 없습니다.');
        }

        // 조회수 증가 + 이벤트 발행
        if ($incrementView) {
            $memberId = $this->getCurrentUserId();
            $clientIp = $context->getRequest()->getClientIp();

            // 조회 차단 pre-event (포인트 소비 등)
            $viewingEvent = $this->dispatch(new ArticleViewingEvent($article, $memberId, $clientIp));
            if ($viewingEvent->isBlocked()) {
                return Result::failure($viewingEvent->getBlockReason() ?? '게시글을 볼 수 없습니다.');
            }

            $this->dispatch(new ArticleViewedEvent($article, $memberId, $clientIp));
            $this->articleRepository->incrementViewCount($articleId);
        }

        // 이전/다음 글
        $adjacent = $this->articleRepository->getAdjacentArticles($articleId, $article->getBoardId());

        return Result::success('', [
            'article' => $article->toArray(),
            'board' => $board->toArray(),
            'board_entity' => $board,
            'prev' => $adjacent['prev']?->toArray(),
            'next' => $adjacent['next']?->toArray(),
            'can_modify' => $this->permissionService->canModify($board, $article, $context),
            'can_delete' => $this->permissionService->canDelete($board, $article, $context),
            'can_comment' => $this->permissionService->canComment($board, $context, $article->getCategoryId()),
            'can_react' => $this->permissionService->canReact($board, $context),
            'can_download' => $this->permissionService->canDownload($board, $article, $context),
        ]);
    }

    /**
     * 게시글 작성
     */
    public function create(int $domainId, int $boardId, array $data, Context $context): Result
    {
        // 게시판 조회
        $board = $this->boardRepository->find($boardId);
        if (!$board) {
            return Result::failure('게시판을 찾을 수 없습니다.');
        }

        // 권한 체크
        $categoryId = $data['category_id'] ?? null;
        if (!$this->permissionService->canWrite($board, $context, $categoryId)) {
            return Result::failure('글쓰기 권한이 없습니다.');
        }

        // 공지사항 권한 체크
        if (!empty($data['is_notice']) && !$this->permissionService->canWriteNotice($board, $context)) {
            $data['is_notice'] = false;
        }

        $memberId = $this->getCurrentUserId();

        // 레벨별 1일 글쓰기 제한 체크
        $memberLevel = (int) ($this->getCurrentUser()['level_value'] ?? 0);
        $writeLimit = $board->getDailyWriteLimitForLevel($memberLevel);
        if ($memberId !== null && $writeLimit !== null) {
            $todayCount = $this->articleRepository->countTodayByMember($boardId, $memberId);
            if ($todayCount >= $writeLimit) {
                return Result::failure('1일 글쓰기 제한(' . $writeLimit . '회)을 초과했습니다.');
            }
        }

        // 작성자 정보 설정
        if ($memberId !== null) {
            // 회원: 닉네임을 author_name에 저장
            $member = $this->memberRepository->find($memberId);
            $data['author_name'] = $member ? ($member->getNickname() ?? $member->getUserId()) : '회원';
            $data['author_password'] = null;
        } else {
            // 비회원: 입력값 검증
            if (empty($data['author_name'])) {
                return Result::failure('이름을 입력해주세요.');
            }
            if (empty($data['author_password'])) {
                return Result::failure('비밀번호를 입력해주세요.');
            }
            $data['author_password'] = password_hash($data['author_password'], PASSWORD_DEFAULT);
        }

        // 1. Creating 이벤트 발행 (차단 가능)
        $creatingEvent = new ArticleCreatingEvent($domainId, $boardId, $data, $memberId);
        $this->dispatch($creatingEvent);

        if ($creatingEvent->isBlocked()) {
            return Result::failure($creatingEvent->getBlockReason() ?? '게시글 작성이 차단되었습니다.');
        }

        // 이벤트에서 수정된 데이터 사용
        $data = $creatingEvent->getData();

        // 데이터 정규화
        $insertData = $this->normalizeArticleData($data, $domainId, $boardId, $memberId, $context, $board);

        // 2. 에디터 이미지 처리 (INSERT 전에 수행하여 단일 INSERT로 완결)
        if (!empty($insertData['content'])) {
            $targetFolder = 'board/' . $board->getBoardSlug() . '/' . date('Y/m');

            $insertData['content'] = EditorHelper::processImages(
                $insertData['content'],
                $targetFolder
            );

            // 최종 content에서 썸네일 추출
            $thumbnail = EditorHelper::extractFirstImage($insertData['content']);
            if ($thumbnail !== null) {
                $insertData['thumbnail'] = $thumbnail;
            }
        }

        // 3. DB 저장 (최종 content + thumbnail 포함, 단일 INSERT)
        $articleId = $this->articleRepository->create($insertData);
        if (!$articleId) {
            return Result::failure('게시글 저장에 실패했습니다.');
        }

        // 저장된 게시글 조회
        $article = $this->articleRepository->find($articleId);

        // 4. Created 이벤트 발행
        $author = $memberId ? $this->memberRepository->find($memberId) : null;
        $this->dispatch(new ArticleCreatedEvent($article, $author));

        return Result::success('게시글이 작성되었습니다.', ['article_id' => $articleId]);
    }

    /**
     * 게시글 수정
     */
    public function update(int $articleId, array $data, Context $context): Result
    {
        // 게시글 조회
        $article = $this->articleRepository->find($articleId);
        if (!$article) {
            return Result::failure('게시글을 찾을 수 없습니다.');
        }

        // 게시판 조회
        $board = $this->boardRepository->find($article->getBoardId());
        if (!$board) {
            return Result::failure('게시판을 찾을 수 없습니다.');
        }

        // 권한 체크
        if (!$this->permissionService->canModify($board, $article, $context)) {
            return Result::failure('수정 권한이 없습니다.');
        }

        // 공지사항 권한 체크
        if (!empty($data['is_notice']) && !$this->permissionService->canWriteNotice($board, $context)) {
            $data['is_notice'] = false;
        }

        $memberId = $this->getCurrentUserId();

        // 1. Updating 이벤트 발행 (차단 가능)
        $updatingEvent = new ArticleUpdatingEvent($article, $data, $memberId);
        $this->dispatch($updatingEvent);

        if ($updatingEvent->isBlocked()) {
            return Result::failure($updatingEvent->getBlockReason() ?? '게시글 수정이 차단되었습니다.');
        }

        // 이벤트에서 수정된 데이터 사용
        $data = $updatingEvent->getNewData();

        // 기존 데이터 백업
        $oldData = $article->toArray();

        // 업데이트할 필드만 추출
        $updateData = $this->extractUpdateFields($data);

        // 비밀게시판이면 비밀글 해제 방지
        if ($board->isSecretBoard()) {
            $updateData['is_secret'] = 1;
        }

        // 제목 변경 시 slug 재생성 (명시적 slug 지정이 없는 경우)
        if (isset($updateData['title']) && !isset($data['slug'])) {
            $newTitle = trim($updateData['title']);
            if ($newTitle !== '' && $newTitle !== $article->getTitle()) {
                $updateData['slug'] = $this->generateSlug($newTitle);
            }
        }

        // 에디터 이미지 처리 (temp → board/{slug}/{Y/m}) + 썸네일 재추출
        if (!empty($updateData['content'])) {
            $targetFolder = 'board/' . $board->getBoardSlug() . '/' . date('Y/m');
            $updateData['content'] = EditorHelper::processImages(
                $updateData['content'],
                $targetFolder
            );

            // content 변경 시 썸네일 재추출 (이미지 제거 시 null로 업데이트)
            $updateData['thumbnail'] = EditorHelper::extractFirstImage($updateData['content']);
        }

        // 2. DB 업데이트
        $dbResult = $this->articleRepository->update($articleId, $updateData);
        if (!$dbResult) {
            return Result::failure('게시글 수정에 실패했습니다.');
        }

        // 수정된 게시글 조회
        $updatedArticle = $this->articleRepository->find($articleId);

        // 3. Updated 이벤트 발행
        $this->dispatch(new ArticleUpdatedEvent($updatedArticle, $oldData, $memberId));

        return Result::success('게시글이 수정되었습니다.');
    }

    /**
     * 게시글 삭제
     */
    public function delete(int $articleId, Context $context): Result
    {
        // 게시글 조회
        $article = $this->articleRepository->find($articleId);
        if (!$article) {
            return Result::failure('게시글을 찾을 수 없습니다.');
        }

        // 게시판 조회
        $board = $this->boardRepository->find($article->getBoardId());
        if (!$board) {
            return Result::failure('게시판을 찾을 수 없습니다.');
        }

        // 권한 체크
        if (!$this->permissionService->canDelete($board, $article, $context)) {
            return Result::failure('삭제 권한이 없습니다.');
        }

        $memberId = $this->getCurrentUserId();

        // 1. Deleting 이벤트 발행 (차단 가능)
        $deletingEvent = new ArticleDeletingEvent($article, $memberId);
        $this->dispatch($deletingEvent);

        if ($deletingEvent->isBlocked()) {
            return Result::failure($deletingEvent->getBlockReason() ?? '게시글 삭제가 차단되었습니다.');
        }

        // 2. 상태 변경 (soft delete)
        $dbResult = $this->articleRepository->updateStatus($articleId, 'deleted');
        if (!$dbResult) {
            return Result::failure('게시글 삭제에 실패했습니다.');
        }

        // 3. Deleted 이벤트 발행
        $this->dispatch(new ArticleDeletedEvent($article, $memberId));

        return Result::success('게시글이 삭제되었습니다.');
    }

    /**
     * 게시글 단건 조회 (Entity 반환, 권한 체크 없음)
     */
    public function findById(int $articleId): ?\Mublo\Packages\Board\Entity\BoardArticle
    {
        return $this->articleRepository->find($articleId);
    }

    /**
     * 게시글 상세 조회 (권한 체크 없이 — 세션 인증된 비회원용)
     */
    public function getArticleWithoutPermission(int $articleId, Context $context): Result
    {
        $articleData = $this->articleRepository->findWithAuthor($articleId);
        if (!$articleData) {
            return Result::failure('게시글을 찾을 수 없습니다.');
        }

        $article = $articleData['article'];
        $board = $this->boardRepository->find($article->getBoardId());
        if (!$board) {
            return Result::failure('게시판을 찾을 수 없습니다.');
        }

        $presenter = new \Mublo\Packages\Board\Helper\ArticlePresenter($board->toArray());

        return Result::success('', [
            'board' => $board->toArray(),
            'board_entity' => $board,
            'article' => $presenter->toView($article->toArray(), $board->getBoardSlug()),
            'prev' => null,
            'next' => null,
            'can_modify' => false,
            'can_delete' => false,
            'can_comment' => false,
            'can_react' => false,
            'can_download' => false,
        ]);
    }

    /**
     * 비회원 비밀번호 확인
     */
    public function verifyGuestPassword(int $articleId, string $password): bool
    {
        $article = $this->articleRepository->find($articleId);
        if (!$article || $article->isMemberArticle()) {
            return false;
        }

        $storedPassword = $article->getAuthorPassword();
        if (!$storedPassword) {
            return false;
        }

        return password_verify($password, $storedPassword);
    }

    /**
     * 공지사항 목록 조회
     */
    public function getNotices(int $domainId, int $boardId, int $limit = 10): array
    {
        $notices = $this->articleRepository->getNotices($domainId, $boardId, $limit);
        return array_map(fn($n) => $n->toArray(), $notices);
    }

    /**
     * 인기글 목록 조회
     */
    public function getPopular(int $domainId, int $limit = 10, string $orderBy = 'view_count', int $days = 7): array
    {
        $popular = $this->articleRepository->getPopular($domainId, $limit, $orderBy, $days);
        return array_map(fn($p) => $p->toArray(), $popular);
    }

    /**
     * Admin용 게시글 목록 조회
     */
    public function getAdminList(int $domainId, int $page, int $perPage, array $filters): array
    {
        return $this->articleRepository->getAdminList($domainId, $page, $perPage, $filters);
    }

    /**
     * 상태 일괄 변경
     *
     * @param int $domainId 도메인 ID (소유권 검증)
     * @param array $articleIds 게시글 ID 배열
     * @param string $status 변경할 상태
     */
    public function bulkUpdateStatus(int $domainId, array $articleIds, string $status): Result
    {
        $validStatuses = ['published', 'draft', 'deleted'];
        if (!in_array($status, $validStatuses, true)) {
            return Result::failure('유효하지 않은 상태입니다.');
        }

        if (empty($articleIds)) {
            return Result::failure('게시글을 선택해주세요.');
        }

        // 도메인 경계 검증: 모든 게시글이 해당 도메인에 속하는지 확인
        $domainArticleCount = $this->articleRepository->countByDomainAndIds($domainId, $articleIds);
        if ($domainArticleCount !== count($articleIds)) {
            return Result::failure('권한이 없는 게시글이 포함되어 있습니다.');
        }

        $affected = $this->articleRepository->bulkUpdateStatus($articleIds, $status);

        return Result::success("{$affected}개의 게시글 상태가 변경되었습니다.", ['affected' => $affected]);
    }

    // === Private Methods ===

    /**
     * 게시글 데이터 정규화
     */
    private function normalizeArticleData(
        array $data,
        int $domainId,
        int $boardId,
        ?int $memberId,
        Context $context,
        BoardConfig $board
    ): array {
        return [
            'domain_id' => $domainId,
            'board_id' => $boardId,
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'member_id' => $memberId,
            'author_name' => $data['author_name'] ?? null,
            'author_password' => $data['author_password'] ?? null,
            'title' => trim($data['title'] ?? ''),
            'slug' => !empty($data['slug'])
                ? $data['slug']
                : $this->generateSlug(trim($data['title'] ?? '')),
            'content' => $data['content'] ?? '',
            'is_notice' => !empty($data['is_notice']) ? 1 : 0,
            'is_secret' => ($board->isSecretBoard() || !empty($data['is_secret'])) ? 1 : 0,
            'status' => $data['status'] ?? 'published',
            'read_level' => isset($data['read_level']) ? (int) $data['read_level'] : null,
            'download_level' => isset($data['download_level']) ? (int) $data['download_level'] : null,
            'location_lat' => $data['location_lat'] ?? null,
            'location_lng' => $data['location_lng'] ?? null,
            'ip_address' => $context->getRequest()->getClientIp(),
        ];
    }

    /**
     * 제목 기반 슬러그 생성
     */
    private function generateSlug(string $title): ?string
    {
        $slug = StringHelper::slug($title);
        if ($slug === '') {
            return null;
        }

        // VARCHAR(100) 제한
        return mb_substr($slug, 0, 100);
    }

    /**
     * 업데이트할 필드 추출
     */
    private function extractUpdateFields(array $data): array
    {
        $allowedFields = [
            'category_id', 'title', 'slug', 'content',
            'is_notice', 'is_secret', 'status',
            'read_level', 'download_level',
            'location_lat', 'location_lng',
        ];

        $updateData = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        return $updateData;
    }
}
