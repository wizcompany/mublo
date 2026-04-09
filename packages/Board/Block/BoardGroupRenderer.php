<?php
namespace Mublo\Packages\Board\Block;

use Mublo\Core\Block\Renderer\RendererInterface;
use Mublo\Core\Block\Renderer\SkinRendererTrait;
use Mublo\Entity\Block\BlockColumn;
use Mublo\Packages\Board\Helper\ArticlePresenter;
use Mublo\Packages\Board\Repository\BoardArticleRepository;
use Mublo\Packages\Board\Repository\BoardConfigRepository;
use Mublo\Packages\Board\Repository\BoardGroupRepository;

/**
 * BoardGroupRenderer
 *
 * 게시판 그룹 콘텐츠 렌더러
 *
 * 스킨에 전달되는 변수:
 * - $titleConfig: 타이틀 설정
 * - $contentConfig: 콘텐츠 설정
 * - $column: BlockColumn 엔티티
 * - $group: 게시판 그룹 엔티티 (nullable)
 * - $boardsData: 게시판별 데이터 배열 [{board, articles}, ...]
 *   - articles: ArticlePresenter::toList() 변환 결과
 * - $displayType: tab|list|grid
 */
class BoardGroupRenderer implements RendererInterface
{
    use SkinRendererTrait;

    private BoardArticleRepository $articleRepository;
    private BoardConfigRepository $boardRepository;
    private BoardGroupRepository $groupRepository;

    public function __construct(
        BoardArticleRepository $articleRepository,
        BoardConfigRepository $boardRepository,
        BoardGroupRepository $groupRepository
    ) {
        $this->articleRepository = $articleRepository;
        $this->boardRepository = $boardRepository;
        $this->groupRepository = $groupRepository;
    }

    /**
     * 스킨 타입 반환
     */
    protected function getSkinType(): string
    {
        return 'boardgroup';
    }

    /**
     * 스킨 기본 경로 (Package 내부)
     */
    protected function getSkinBasePath(): string
    {
        return MUBLO_PACKAGE_PATH . '/Board/views/Block/';
    }

    /**
     * {@inheritdoc}
     */
    public function render(BlockColumn $column): string
    {
        $config = $column->getContentConfig() ?? [];
        $items = $column->getContentItems() ?? [];
        $count = max($column->getPcCount(), $column->getMoCount());
        $skin = $column->getContentSkin() ?: 'tab';

        $groupId = $config['group_id'] ?? null;

        if (!$groupId && empty($items)) {
            return $this->renderEmptyContent('게시판 그룹이 설정되지 않았습니다.');
        }

        // 그룹 정보 조회
        $group = $groupId ? $this->groupRepository->find((int) $groupId) : null;

        // 게시판 목록 결정
        $boardIds = !empty($items)
            ? array_map('intval', $items)
            : ($groupId ? $this->getBoardIdsByGroup((int) $groupId, $column->getDomainId()) : []);

        if (empty($boardIds)) {
            return $this->renderEmptyContent('표시할 게시판이 없습니다.');
        }

        // 게시판별 최신글 조회
        $boardsData = $this->getBoardsWithArticles($boardIds, $column->getDomainId(), $count);

        if (empty($boardsData)) {
            return $this->renderEmptyContent('게시판을 찾을 수 없습니다.');
        }

        $displayType = $config['display_type'] ?? 'tab';

        return $this->renderSkin($column, $skin, [
            'group' => $group,
            'boardsData' => $boardsData,
            'displayType' => $displayType,
        ]);
    }

    /**
     * 그룹 내 게시판 ID 목록 조회
     */
    private function getBoardIdsByGroup(int $groupId, int $domainId): array
    {
        $boards = $this->boardRepository->findByGroup($groupId);

        return array_map(
            fn($b) => $b->getBoardId(),
            array_filter($boards, fn($b) => $b->getDomainId() === $domainId && $b->isActive())
        );
    }

    /**
     * 게시판별 최신글 데이터 조회
     */
    private function getBoardsWithArticles(array $boardIds, int $domainId, int $count): array
    {
        $result = [];

        foreach ($boardIds as $boardId) {
            $board = $this->boardRepository->find($boardId);

            if (!$board || !$board->isActive()) {
                continue;
            }

            $articlesResult = $this->articleRepository->getPaginatedList(
                $domainId,
                $boardId,
                1,
                $count,
                ['status' => 'published']
            );

            // ArticlePresenter로 스킨용 데이터 변환
            $boardSlug = $board->getBoardSlug();
            $presenter = new ArticlePresenter($board->toArray());
            $articles = $presenter->toList(
                array_map(fn($a) => $a->toArray(), $articlesResult['items'] ?? []),
                $boardSlug
            );

            $result[] = [
                'board' => $board,
                'articles' => $articles,
            ];
        }

        return $result;
    }
}
