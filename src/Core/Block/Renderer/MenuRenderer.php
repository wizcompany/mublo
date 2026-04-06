<?php
namespace Mublo\Core\Block\Renderer;

use Mublo\Entity\Block\BlockColumn;
use Mublo\Repository\Menu\MenuTreeRepository;

/**
 * MenuRenderer
 *
 * 메뉴 콘텐츠 렌더러
 *
 * 스킨에 전달되는 변수:
 * - $titleConfig: 타이틀 설정
 * - $contentConfig: 콘텐츠 설정
 * - $column: BlockColumn 엔티티
 * - $menuTree: 메뉴 트리 배열
 * - $orientation: horizontal|vertical
 * - $maxDepth: 최대 깊이
 */
class MenuRenderer implements RendererInterface
{
    use SkinRendererTrait;

    private MenuTreeRepository $treeRepository;

    public function __construct(MenuTreeRepository $treeRepository)
    {
        $this->treeRepository = $treeRepository;
    }

    /**
     * 스킨 타입 반환
     */
    protected function getSkinType(): string
    {
        return 'menu';
    }

    /**
     * {@inheritdoc}
     */
    public function render(BlockColumn $column): string
    {
        $config = $column->getContentConfig() ?? [];
        $skin = $column->getContentSkin() ?: 'basic';
        $depth = (int) ($config['depth'] ?? 2);
        $orientation = $config['orientation'] ?? 'horizontal';

        // 메뉴 데이터 조회
        $menuItems = $this->getMenuItems($column->getDomainId());

        if (empty($menuItems)) {
            return $this->renderEmptyContent('메뉴가 없습니다.');
        }

        // 트리 구조로 변환
        $menuTree = $this->buildTree($menuItems);

        return $this->renderSkin($column, $skin, [
            'menuTree' => $menuTree,
            'orientation' => $orientation,
            'maxDepth' => $depth,
        ]);
    }

    /**
     * 메뉴 아이템 조회
     */
    private function getMenuItems(int $domainId): array
    {
        try {
            return $this->treeRepository->findTreeWithItems($domainId, true);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * 플랫 배열을 트리 구조로 변환
     */
    private function buildTree(array $items): array
    {
        $tree = [];
        $itemsByCode = [];

        foreach ($items as $item) {
            $code = $item['menu_code'] ?? '';
            $itemsByCode[$code] = $item;
            $itemsByCode[$code]['children'] = [];
        }

        foreach ($itemsByCode as $code => &$item) {
            $parentCode = $item['parent_code'] ?? null;

            if (empty($parentCode)) {
                $tree[$code] = &$item;
            } else {
                if (isset($itemsByCode[$parentCode])) {
                    $itemsByCode[$parentCode]['children'][$code] = &$item;
                }
            }
        }

        return $tree;
    }
}
