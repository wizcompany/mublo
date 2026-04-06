<?php
namespace Mublo\Plugin\Faq\Block;

use Mublo\Core\Block\Renderer\RendererInterface;
use Mublo\Core\Block\Renderer\SkinRendererTrait;
use Mublo\Entity\Block\BlockColumn;
use Mublo\Plugin\Faq\Service\FaqService;

/**
 * FaqRenderer
 *
 * FAQ 블록 콘텐츠 렌더러
 *
 * content_items: 카테고리 slug 배열 (DualListbox로 선택)
 * 미선택 시 전체 FAQ 표시
 *
 * 스킨에 전달되는 변수:
 * - $titleConfig: 타이틀 설정 (SkinRendererTrait에서 추출)
 * - $titlePartial: 타이틀 파셜 경로
 * - $contentConfig: 콘텐츠 설정
 * - $column: BlockColumn 엔티티
 * - $grouped: FAQ 그룹 배열 [{category_name, category_slug, items: [{faq_id, question, answer}]}, ...]
 * - $config: content_config (max_items, show_category 등)
 */
class FaqRenderer implements RendererInterface
{
    use SkinRendererTrait;

    private FaqService $faqService;

    public function __construct(FaqService $faqService)
    {
        $this->faqService = $faqService;
    }

    protected function getSkinType(): string
    {
        return 'faq';
    }

    protected function getSkinBasePath(): string
    {
        return MUBLO_PLUGIN_PATH . '/Faq/views/Block/';
    }

    public function render(BlockColumn $column): string
    {
        $domainId = $column->getDomainId();
        $contentItems = $column->getContentItems() ?? [];
        $config = $column->getContentConfig() ?? [];

        // 카테고리 slug 추출
        $slugs = $this->extractSlugs($contentItems);

        // FAQ 데이터 조회
        if (empty($slugs)) {
            $grouped = $this->faqService->getGroupedAll($domainId);
        } else {
            $grouped = $this->buildGroupedFromSlugs($domainId, $slugs);
        }

        if (empty($grouped)) {
            return '';
        }

        // max_items 적용
        $maxItems = (int) ($config['max_items'] ?? 0);
        if ($maxItems > 0) {
            $grouped = $this->limitItems($grouped, $maxItems);
        }

        $skin = $column->getContentSkin() ?: 'basic';

        return $this->renderSkin($column, $skin, [
            'grouped' => $grouped,
            'config' => $config,
        ]);
    }

    /**
     * content_items에서 카테고리 slug 추출
     */
    private function extractSlugs(array $contentItems): array
    {
        if (empty($contentItems)) {
            return [];
        }

        return array_map(function ($item) {
            return is_array($item) ? ($item['id'] ?? '') : (string) $item;
        }, $contentItems);
    }

    /**
     * slug 배열로 grouped 형식 데이터 구성
     */
    private function buildGroupedFromSlugs(int $domainId, array $slugs): array
    {
        $bySlug = $this->faqService->getByCategorySlugs($domainId, $slugs);
        $categories = $this->faqService->getCategories($domainId);

        // 카테고리명 매핑
        $catNameMap = [];
        foreach ($categories as $cat) {
            $catNameMap[$cat['category_slug']] = $cat['category_name'];
        }

        $grouped = [];
        foreach ($slugs as $slug) {
            if (!isset($bySlug[$slug])) {
                continue;
            }
            $grouped[] = [
                'category_name' => $catNameMap[$slug] ?? $slug,
                'category_slug' => $slug,
                'items' => $bySlug[$slug],
            ];
        }

        return $grouped;
    }

    /**
     * FAQ 항목 수 제한
     */
    private function limitItems(array $grouped, int $maxItems): array
    {
        $count = 0;
        $result = [];

        foreach ($grouped as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if ($count >= $maxItems) {
                    break 2;
                }
                $items[] = $item;
                $count++;
            }
            if (!empty($items)) {
                $group['items'] = $items;
                $result[] = $group;
            }
        }

        return $result;
    }
}
