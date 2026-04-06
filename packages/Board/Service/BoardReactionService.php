<?php
namespace Mublo\Packages\Board\Service;

use Mublo\Packages\Board\Entity\BoardReaction;
use Mublo\Packages\Board\Repository\BoardReactionRepository;
use Mublo\Packages\Board\Repository\BoardArticleRepository;
use Mublo\Packages\Board\Repository\BoardCommentRepository;
use Mublo\Packages\Board\Repository\BoardConfigRepository;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Core\Context\Context;
use Mublo\Service\Auth\AuthService;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Packages\Board\Event\ReactionAddedEvent;
use Mublo\Packages\Board\Event\ReactionRemovedEvent;

/**
 * BoardReactionService
 *
 * 반응 비즈니스 로직 + 이벤트 발행
 */
class BoardReactionService
{
    private BoardReactionRepository $reactionRepository;
    private BoardArticleRepository $articleRepository;
    private BoardCommentRepository $commentRepository;
    private BoardConfigRepository $boardRepository;
    private MemberRepository $memberRepository;
    private BoardPermissionService $permissionService;
    private ?EventDispatcher $eventDispatcher;
    private AuthService $authService;

    public function __construct(
        BoardReactionRepository $reactionRepository,
        BoardArticleRepository $articleRepository,
        BoardCommentRepository $commentRepository,
        BoardConfigRepository $boardRepository,
        MemberRepository $memberRepository,
        BoardPermissionService $permissionService,
        ?EventDispatcher $eventDispatcher,
        AuthService $authService
    ) {
        $this->reactionRepository = $reactionRepository;
        $this->articleRepository = $articleRepository;
        $this->commentRepository = $commentRepository;
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
     * 반응 토글 (있으면 삭제, 없으면 추가)
     */
    public function toggle(
        string $targetType,
        int $targetId,
        string $reactionType,
        Context $context
    ): array {
        // 로그인 체크
        $user = $this->getCurrentUser();
        $memberId = $this->getCurrentUserId();
        if ($user === null || $memberId === null) {
            return [
                'success' => false,
                'message' => '로그인이 필요합니다.',
            ];
        }

        // 대상 조회 및 권한 체크
        $targetInfo = $this->getTargetInfo($targetType, $targetId);
        if (!$targetInfo) {
            return [
                'success' => false,
                'message' => '대상을 찾을 수 없습니다.',
            ];
        }

        $board = $this->boardRepository->find($targetInfo['board_id']);
        if (!$board || !$this->permissionService->canReact($board, $context)) {
            return [
                'success' => false,
                'message' => '반응 권한이 없습니다.',
            ];
        }

        // 기존 반응 확인
        $existing = $this->reactionRepository->findByTargetAndMember($targetType, $targetId, $memberId);

        if ($existing && $existing->getReactionType() !== $reactionType) {
            // 이미 다른 반응을 한 상태 → 변경 불가, 안내 메시지
            $existingLabel = $this->getReactionLabel($board, $existing->getReactionType());
            $counts = $this->reactionRepository->countByTargetGroupByType($targetType, $targetId);
            return [
                'success' => false,
                'message' => "이미 '{$existingLabel}' 반응을 하였습니다.",
                'counts' => $counts,
                'my_reaction' => $existing->getReactionType(),
            ];
        }

        // 반응 토글 (같은 타입이면 삭제, 없으면 추가)
        $result = $this->reactionRepository->toggle(
            $targetInfo['domain_id'],
            $targetInfo['board_id'],
            $targetType,
            $targetId,
            $memberId,
            $reactionType
        );

        // 이벤트 발행
        if ($result['action'] === 'added') {
            $reaction = $this->reactionRepository->findByTargetAndMember($targetType, $targetId, $memberId);
            if ($reaction) {
                $this->dispatch(new ReactionAddedEvent($reaction, $targetInfo['author_id']));
            }
        } elseif ($result['action'] === 'removed') {
            $this->dispatch(new ReactionRemovedEvent(
                0, // reactionId (이미 삭제됨)
                $targetType,
                $targetId,
                $result['old_type'],
                $memberId,
                $targetInfo['author_id'],
                $targetInfo['domain_id'],
                $targetInfo['board_id']
            ));
        }

        // 반응 수 동기화
        $this->syncReactionCount($targetType, $targetId);

        // 현재 반응 상태 조회
        $counts = $this->reactionRepository->countByTargetGroupByType($targetType, $targetId);
        $myReaction = $this->reactionRepository->findByTargetAndMember($targetType, $targetId, $memberId);

        return [
            'success' => true,
            'action' => $result['action'],
            'message' => match ($result['action']) {
                'added' => '반응을 추가했습니다.',
                'removed' => '반응을 취소했습니다.',
                default => '',
            },
            'counts' => $counts,
            'my_reaction' => $myReaction?->getReactionType(),
        ];
    }

    /**
     * 대상별 반응 정보 조회
     */
    public function getReactionInfo(string $targetType, int $targetId, Context $context): array
    {
        $counts = $this->reactionRepository->countByTargetGroupByType($targetType, $targetId);
        $total = $this->reactionRepository->countByTarget($targetType, $targetId);

        $myReaction = null;
        $memberId = $this->getCurrentUserId();
        if ($memberId !== null) {
            $reaction = $this->reactionRepository->findByTargetAndMember($targetType, $targetId, $memberId);
            $myReaction = $reaction?->getReactionType();
        }

        return [
            'total' => $total,
            'counts' => $counts,
            'my_reaction' => $myReaction,
        ];
    }

    /**
     * 반응자 목록 조회
     */
    public function getReactors(string $targetType, int $targetId, int $limit = 50): array
    {
        return $this->reactionRepository->getReactorsWithMember($targetType, $targetId, $limit);
    }

    /**
     * 회원별 반응 목록 조회
     */
    public function getMemberReactions(int $memberId, ?string $targetType = null, int $limit = 100): array
    {
        $reactions = $this->reactionRepository->findByMember($memberId, $targetType, $limit);
        return array_map(fn($r) => $r->toArray(), $reactions);
    }

    /**
     * 게시판별 반응 통계
     */
    public function getStatsByBoard(int $boardId): array
    {
        return $this->reactionRepository->getStatsByBoard($boardId);
    }

    // === Private Methods ===

    /**
     * 반응 타입의 라벨 조회
     */
    private function getReactionLabel($board, string $reactionType): string
    {
        $config = $board->getReactionConfig();
        if ($config && isset($config[$reactionType]['label'])) {
            return $config[$reactionType]['label'];
        }
        return $reactionType;
    }

    /**
     * 대상 정보 조회
     */
    private function getTargetInfo(string $targetType, int $targetId): ?array
    {
        if ($targetType === 'article') {
            $article = $this->articleRepository->find($targetId);
            if (!$article) {
                return null;
            }
            return [
                'domain_id' => $article->getDomainId(),
                'board_id' => $article->getBoardId(),
                'author_id' => $article->getMemberId(),
            ];
        }

        if ($targetType === 'comment') {
            $comment = $this->commentRepository->find($targetId);
            if (!$comment) {
                return null;
            }
            return [
                'domain_id' => $comment->getDomainId(),
                'board_id' => $comment->getBoardId(),
                'author_id' => $comment->getMemberId(),
            ];
        }

        return null;
    }

    /**
     * 반응 수 동기화
     */
    private function syncReactionCount(string $targetType, int $targetId): void
    {
        if ($targetType === 'article') {
            $this->articleRepository->syncReactionCount($targetId);
        } elseif ($targetType === 'comment') {
            $this->commentRepository->syncReactionCount($targetId);
        }
    }
}
