<?php
namespace Mublo\Packages\Board\Service;

use Mublo\Packages\Board\Entity\BoardComment;
use Mublo\Packages\Board\Repository\BoardCommentRepository;
use Mublo\Packages\Board\Repository\BoardArticleRepository;
use Mublo\Packages\Board\Repository\BoardConfigRepository;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Core\Context\Context;
use Mublo\Service\Auth\AuthService;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Packages\Board\Event\CommentCreatingEvent;
use Mublo\Packages\Board\Event\CommentCreatedEvent;
use Mublo\Packages\Board\Event\CommentDeletedEvent;
use Mublo\Core\Result\Result;

/**
 * BoardCommentService
 *
 * 댓글 비즈니스 로직 + 이벤트 발행
 */
class BoardCommentService
{
    private BoardCommentRepository $commentRepository;
    private BoardArticleRepository $articleRepository;
    private BoardConfigRepository $boardRepository;
    private MemberRepository $memberRepository;
    private BoardPermissionService $permissionService;
    private ?EventDispatcher $eventDispatcher;
    private AuthService $authService;

    public function __construct(
        BoardCommentRepository $commentRepository,
        BoardArticleRepository $articleRepository,
        BoardConfigRepository $boardRepository,
        MemberRepository $memberRepository,
        BoardPermissionService $permissionService,
        ?EventDispatcher $eventDispatcher,
        AuthService $authService
    ) {
        $this->commentRepository = $commentRepository;
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
     * 게시글 댓글 목록 조회
     */
    public function getCommentsByArticle(int $articleId): array
    {
        return $this->commentRepository->getCommentsWithAuthor($articleId);
    }

    /**
     * 댓글 작성
     */
    public function create(int $articleId, array $data, Context $context): Result
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
        if (!$this->permissionService->canComment($board, $context, $article->getCategoryId())) {
            return Result::failure('댓글 작성 권한이 없습니다.');
        }

        $memberId = $this->getCurrentUserId();
        $domainId = $article->getDomainId();
        $boardId = $article->getBoardId();

        // 레벨별 1일 댓글 제한 체크
        $memberLevel = (int) ($this->getCurrentUser()['level_value'] ?? 0);
        $commentLimit = $board->getDailyCommentLimitForLevel($memberLevel);
        if ($memberId !== null && $commentLimit !== null) {
            $todayCount = $this->commentRepository->countTodayByMember($boardId, $memberId);
            if ($todayCount >= $commentLimit) {
                return Result::failure('1일 댓글 제한(' . $commentLimit . '회)을 초과했습니다.');
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
        $creatingEvent = new CommentCreatingEvent($domainId, $boardId, $articleId, $data, $memberId);
        $this->dispatch($creatingEvent);

        if ($creatingEvent->isBlocked()) {
            return Result::failure($creatingEvent->getBlockReason() ?? '댓글 작성이 차단되었습니다.');
        }

        // 이벤트에서 수정된 데이터 사용
        $data = $creatingEvent->getData();

        // 부모 댓글 처리 (대댓글)
        $parentId = !empty($data['parent_id']) ? (int) $data['parent_id'] : null;
        $path = $this->commentRepository->generatePath($articleId, $parentId);
        $depth = $this->commentRepository->calculateDepth($parentId);

        // 데이터 정규화
        $insertData = [
            'domain_id' => $domainId,
            'board_id' => $boardId,
            'article_id' => $articleId,
            'parent_id' => $parentId,
            'member_id' => $memberId,
            'author_name' => $data['author_name'] ?? null,
            'author_password' => $data['author_password'] ?? null,
            'content' => trim($data['content'] ?? ''),
            'is_secret' => (int) !empty($data['is_secret']),
            'status' => 'published',
            'depth' => $depth,
            'path' => $path,
            'ip_address' => $context->getRequest()->getClientIp(),
        ];

        // 2. DB 저장
        $commentId = $this->commentRepository->create($insertData);
        if (!$commentId) {
            return Result::failure('댓글 저장에 실패했습니다.');
        }

        // 게시글 댓글 수 동기화
        $this->articleRepository->syncCommentCount($articleId);

        // 저장된 댓글 조회
        $comment = $this->commentRepository->find($commentId);

        // 3. Created 이벤트 발행
        $author = $memberId ? $this->memberRepository->find($memberId) : null;
        $this->dispatch(new CommentCreatedEvent($comment, $article, $author));

        return Result::success('댓글이 작성되었습니다.', ['comment_id' => $commentId]);
    }

    /**
     * 댓글 수정
     */
    public function update(int $commentId, array $data, Context $context, ?string $guestPassword = null): Result
    {
        // 댓글 조회
        $comment = $this->commentRepository->find($commentId);
        if (!$comment) {
            return Result::failure('댓글을 찾을 수 없습니다.');
        }

        // 권한 체크
        if (!$this->canModifyComment($comment, $context, $guestPassword)) {
            return Result::failure('수정 권한이 없습니다.');
        }

        // 업데이트
        $updateData = [
            'content' => trim($data['content'] ?? ''),
            'is_secret' => (bool) ($data['is_secret'] ?? false),
        ];

        $dbResult = $this->commentRepository->update($commentId, $updateData);
        if (!$dbResult) {
            return Result::failure('댓글 수정에 실패했습니다.');
        }

        return Result::success('댓글이 수정되었습니다.');
    }

    /**
     * 댓글 삭제
     */
    public function delete(int $commentId, Context $context, ?string $guestPassword = null): Result
    {
        // 댓글 조회
        $comment = $this->commentRepository->find($commentId);
        if (!$comment) {
            return Result::failure('댓글을 찾을 수 없습니다.');
        }

        // 권한 체크
        if (!$this->canModifyComment($comment, $context, $guestPassword)) {
            return Result::failure('삭제 권한이 없습니다.');
        }

        $memberId = $this->getCurrentUserId();
        $articleId = $comment->getArticleId();

        // 대댓글 포함 삭제
        $deleted = $this->commentRepository->deleteWithChildren($commentId);

        // 게시글 댓글 수 동기화
        $this->articleRepository->syncCommentCount($articleId);

        // Deleted 이벤트 발행
        $this->dispatch(new CommentDeletedEvent($comment, $memberId));

        return Result::success('댓글이 삭제되었습니다.', ['deleted_count' => $deleted]);
    }

    /**
     * 비회원 비밀번호 확인
     */
    public function verifyGuestPassword(int $commentId, string $password): bool
    {
        $comment = $this->commentRepository->find($commentId);
        if (!$comment || $comment->isMemberComment()) {
            return false;
        }

        $storedPassword = $comment->getAuthorPassword();
        if (!$storedPassword) {
            return false;
        }

        return password_verify($password, $storedPassword);
    }

    /**
     * 최근 댓글 조회 (Admin용)
     */
    public function getRecentComments(int $domainId, int $limit = 10): array
    {
        return $this->commentRepository->getRecentComments($domainId, $limit);
    }

    /**
     * 회원별 댓글 수 조회
     */
    public function countByMember(int $memberId): int
    {
        return $this->commentRepository->countByMember($memberId);
    }

    // === Private Methods ===

    /**
     * 댓글 수정/삭제 권한 체크
     */
    private function canModifyComment(BoardComment $comment, Context $context, ?string $guestPassword = null): bool
    {
        $user = $this->getCurrentUser();
        $userId = $this->getCurrentUserId();

        // 회원 작성 댓글
        if ($comment->isMemberComment()) {
            if ($user === null) {
                return false;
            }

            // 본인 댓글
            if ($userId !== null && $comment->isAuthor($userId)) {
                return true;
            }

            // 관리자
            if (!empty($user['is_super']) || !empty($user['is_admin'])) {
                return true;
            }

            // 게시판 관리자 체크
            $board = $this->boardRepository->find($comment->getBoardId());
            if ($board && $userId !== null) {
                $boardAdminIds = $board->getBoardAdminIds();
                if (!empty($boardAdminIds) && in_array($userId, $boardAdminIds, true)) {
                    return true;
                }
            }

            return false;
        }

        // 비회원 작성 댓글
        // 관리자는 비밀번호 없이 수정/삭제 가능
        if ($user !== null && (!empty($user['is_super']) || !empty($user['is_admin']))) {
            return true;
        }

        // 비회원은 비밀번호 확인 필요
        if ($guestPassword !== null) {
            return $this->verifyGuestPassword($comment->getCommentId(), $guestPassword);
        }

        return false;
    }
}
