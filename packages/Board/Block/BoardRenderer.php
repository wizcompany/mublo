<?php
namespace Mublo\Packages\Board\Block;

use Mublo\Core\Block\Renderer\RendererInterface;
use Mublo\Core\Block\Renderer\SkinRendererTrait;
use Mublo\Entity\Block\BlockColumn;
use Mublo\Packages\Board\Helper\ArticlePresenter;
use Mublo\Packages\Board\Repository\BoardArticleRepository;
use Mublo\Packages\Board\Repository\BoardConfigRepository;

/**
 * BoardRenderer
 *
 * 게시판 최신글 콘텐츠 렌더러
 *
 * 스킨에 전달되는 변수:
 * - $titleConfig: 타이틀 설정 (SkinRendererTrait에서 추출)
 * - $contentConfig: 콘텐츠 설정
 * - $column: BlockColumn 엔티티
 * - $items: ArticlePresenter::toList() 변환 결과
 * - $board: BoardConfig 엔티티
 */
class BoardRenderer implements RendererInterface
{
    use SkinRendererTrait;

    private BoardArticleRepository $articleRepository;
    private BoardConfigRepository $boardRepository;

    public function __construct(
        BoardArticleRepository $articleRepository,
        BoardConfigRepository $boardRepository
    ) {
        $this->articleRepository = $articleRepository;
        $this->boardRepository = $boardRepository;
    }

    /**
     * 스킨 타입 반환
     */
    protected function getSkinType(): string
    {
        return 'board';
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
        $contentItems = $column->getContentItems() ?? [];
        $count = max($column->getPcCount(), $column->getMoCount());
        $skin = $column->getContentSkin() ?: 'basic';

        // 게시판 ID 또는 슬러그 결정
        $boardIdOrSlug = $config['board_id'] ?? ($contentItems[0] ?? null);

        if (!$boardIdOrSlug) {
            return $this->renderSkinNotFound($skin);
        }

        // 게시판 정보 조회 (ID 또는 슬러그로)
        if (is_numeric($boardIdOrSlug)) {
            $board = $this->boardRepository->find((int) $boardIdOrSlug);
        } else {
            $board = $this->boardRepository->findBySlug($column->getDomainId(), (string) $boardIdOrSlug);
        }

        if (!$board || !$board->isActive()) {
            return $this->renderEmptyContent('게시판을 찾을 수 없습니다.');
        }

        // 최신글 조회 (전역 게시판이면 도메인 필터 생략)
        $result = $this->articleRepository->getPaginatedList(
            $column->getDomainId(),
            $board->getBoardId(),
            1,
            $count,
            ['status' => 'published'],
            $board->isGlobal()
        );

        // ArticlePresenter로 스킨용 데이터 변환
        $boardSlug = $board->getBoardSlug();
        $presenter = new ArticlePresenter($board->toArray());
        $items = $presenter->toList(
            array_map(fn($a) => $a->toArray(), $result['items'] ?? []),
            $boardSlug
        );

        // 스킨 렌더링 (타이틀 + 콘텐츠 모두 스킨에서 처리)
        return $this->renderSkin($column, $skin, [
            'items' => $items,
            'board' => $board,
        ]);
    }
}
