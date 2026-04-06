<?php
namespace Mublo\Plugin\Banner\Block;

use Mublo\Core\Block\Renderer\RendererInterface;
use Mublo\Core\Block\Renderer\SkinRendererTrait;
use Mublo\Core\Event\Block\BlockContentFilterEvent;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Entity\Block\BlockColumn;
use Mublo\Plugin\Banner\Service\BannerService;

/**
 * BannerRenderer
 *
 * 배너 블록 콘텐츠 렌더러
 *
 * content_items에 저장된 배너 객체 배열을 직접 사용합니다.
 * (레거시: ID 배열인 경우 DB 조회로 폴백)
 *
 * 스킨에 전달되는 변수:
 * - $titleConfig: 타이틀 설정 (SkinRendererTrait에서 추출)
 * - $titlePartial: 타이틀 파셜 경로
 * - $contentConfig: 콘텐츠 설정
 * - $column: BlockColumn 엔티티
 * - $items: 배너 배열 (title, pc_image_url, mo_image_url, link_url, link_target, extras)
 * - $config: content_config (effect, autoplay 등)
 */
class BannerRenderer implements RendererInterface
{
    use SkinRendererTrait;

    private BannerService $bannerService;
    private ?EventDispatcher $eventDispatcher;

    public function __construct(BannerService $bannerService, ?EventDispatcher $eventDispatcher = null)
    {
        $this->bannerService = $bannerService;
        $this->eventDispatcher = $eventDispatcher;
    }

    protected function getSkinType(): string
    {
        return 'banner';
    }

    /**
     * 플러그인 내부 스킨 경로
     */
    protected function getSkinBasePath(): string
    {
        return MUBLO_PLUGIN_PATH . '/Banner/views/Block/';
    }

    public function render(BlockColumn $column): string
    {
        $contentItems = $column->getContentItems() ?? [];

        if (empty($contentItems)) {
            return '';
        }

        $items = $this->resolveItems($contentItems);

        // 패키지 필터링 이벤트 발행
        if ($this->eventDispatcher && !empty($items)) {
            $filterEvent = new BlockContentFilterEvent('banner', $items);
            $this->eventDispatcher->dispatch($filterEvent);
            $items = $filterEvent->getItems();
        }

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
     * content_items → 스킨용 배너 배열 변환
     *
     * 객체 배열: content_items의 표시 데이터 사용 + DB에서 현재 extras 조회
     * ID 배열(레거시): DB 조회로 폴백
     *
     * extras는 스냅샷에 의존하지 않고 항상 DB 현재값을 사용합니다.
     * (배너 저장 후 brand_code 변경 시에도 즉시 반영)
     */
    private function resolveItems(array $contentItems): array
    {
        $first = reset($contentItems);

        // 객체 배열: content_items의 표시 데이터 사용
        if (is_array($first) && isset($first['id'])) {
            $bannerIds = array_map(fn($item) => (int) $item['id'], $contentItems);
            $extrasMap = $this->bannerService->getExtrasMap($bannerIds);

            return array_map(fn($item) => [
                'banner_id' => $item['id'] ?? 0,
                'title' => $item['label'] ?? '',
                'pc_image_url' => $item['pc_image_url'] ?? '',
                'mo_image_url' => $item['mo_image_url'] ?? '',
                'link_url' => $item['link_url'] ?? '',
                'link_target' => $item['link_target'] ?? '_self',
                'extras' => $extrasMap[(int) ($item['id'] ?? 0)] ?? null,
            ], $contentItems);
        }

        // ID 배열(레거시): DB 조회
        $bannerIds = array_map('intval', $contentItems);
        return $this->bannerService->findByIds($bannerIds);
    }
}
