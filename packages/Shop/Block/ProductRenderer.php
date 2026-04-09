<?php

namespace Mublo\Packages\Shop\Block;

use Mublo\Core\Block\Renderer\RendererInterface;
use Mublo\Core\Block\Renderer\SkinRendererTrait;
use Mublo\Entity\Block\BlockColumn;
use Mublo\Packages\Shop\Helper\ProductPresenter;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Service\ShopConfigService;

/**
 * ProductRenderer
 *
 * 상품 블록 콘텐츠 렌더러
 *
 * content_items에 저장된 상품 객체 배열을 직접 사용합니다.
 * (레거시: ID 배열인 경우 DB 조회로 폴백)
 *
 * 스킨에 전달되는 변수:
 * - $titleConfig: 타이틀 설정 (SkinRendererTrait에서 추출)
 * - $titlePartial: 타이틀 파셜 경로
 * - $contentConfig: 콘텐츠 설정
 * - $column: BlockColumn 엔티티
 * - $items: 상품 배열 (ProductPresenter 변환 완료)
 * - $config: content_config (show_count, sort, show_price, show_reward)
 */
class ProductRenderer implements RendererInterface
{
    use SkinRendererTrait;

    private ProductRepository $productRepository;
    private ShopConfigService $shopConfigService;

    public function __construct(
        ProductRepository $productRepository,
        ShopConfigService $shopConfigService
    ) {
        $this->productRepository = $productRepository;
        $this->shopConfigService = $shopConfigService;
    }

    protected function getSkinType(): string
    {
        return 'product';
    }

    protected function getSkinBasePath(): string
    {
        return MUBLO_PACKAGE_PATH . '/Shop/views/Block/';
    }

    public function render(BlockColumn $column): string
    {
        $contentItems = $column->getContentItems() ?? [];

        if (empty($contentItems)) {
            return '';
        }

        $items = $this->resolveItems($contentItems, $column->getDomainId());

        if (empty($items)) {
            return '';
        }

        $config = $column->getContentConfig() ?? [];
        $skin = $column->getContentSkin() ?: 'basic';

        return $this->renderSkin($column, $skin, [
            'items' => $items,
            'config' => $config,
        ]);
    }

    /**
     * content_items → 스킨용 상품 배열 변환
     *
     * 객체 배열: content_items의 ID로 DB 조회 후 Presenter 적용
     * ID 배열(레거시): DB 조회로 폴백
     */
    private function resolveItems(array $contentItems, int $domainId): array
    {
        $first = reset($contentItems);

        // 객체 배열 또는 ID 배열에서 ID 추출
        if (is_array($first) && isset($first['id'])) {
            $goodsIds = array_map(fn($item) => (int) $item['id'], $contentItems);
        } else {
            $goodsIds = array_map('intval', $contentItems);
        }

        if (empty($goodsIds)) {
            return [];
        }

        // DB 조회 (활성 상품만)
        $rows = $this->productRepository->findByIds($goodsIds);

        if (empty($rows)) {
            return [];
        }

        // Entity → array 변환
        $items = array_map(
            fn($entity) => $entity instanceof \Mublo\Packages\Shop\Entity\Product
                ? $entity->toArray()
                : (array) $entity,
            $rows
        );

        // 메인 이미지 배치 로드
        $mainImages = $this->productRepository->getMainImages($goodsIds);

        // ProductPresenter 적용
        $shopConfig = $this->shopConfigService->getConfig($domainId)->get('config', []);
        $presenter = new ProductPresenter($shopConfig);

        return $presenter->toList($items, $mainImages);
    }
}
