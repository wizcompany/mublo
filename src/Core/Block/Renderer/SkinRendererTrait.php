<?php
namespace Mublo\Core\Block\Renderer;

use Mublo\Core\Rendering\AssetManager;
use Mublo\Entity\Block\BlockColumn;

/**
 * SkinRendererTrait
 *
 * 블록 렌더러의 스킨 기반 렌더링 공통 기능
 *
 * 스킨에 전달되는 표준 변수:
 * - $titleConfig: 타이틀 설정 (show, text, position, color, size, more_url, copytext 등)
 * - $titlePartial: 타이틀 파셜 경로 (스킨 오버라이드 또는 공유 파셜)
 * - $contentConfig: 콘텐츠 설정 (렌더러별 다름)
 * - $column: BlockColumn 엔티티
 * - $assets: AssetManager (CSS/JS 수집, null이면 미사용)
 * - 각 렌더러별 추가 데이터
 */
trait SkinRendererTrait
{
    public ?AssetManager $assetManager = null;
    /**
     * 스킨 타입 (하위 클래스에서 정의)
     * 예: 'board', 'image', 'menu'
     */
    abstract protected function getSkinType(): string;

    /**
     * 스킨으로 렌더링
     *
     * @param BlockColumn $column 블록 칸 엔티티
     * @param string $skin 스킨명 (basic, gallery 등)
     * @param array $data 스킨에 전달할 추가 데이터
     * @return string 렌더링된 HTML
     */
    protected function renderSkin(BlockColumn $column, string $skin, array $data = []): string
    {
        $skinPath = $this->getSkinPath($skin);

        if (!is_file($skinPath)) {
            return $this->renderSkinNotFound($skin);
        }

        // 타이틀 설정 추출
        $titleConfig = $this->extractTitleConfig($column);

        // 타이틀 파셜 경로 결정 (스킨 오버라이드 우선)
        $titlePartial = $this->resolveTitlePartial($skin);

        // 스킨에 전달할 데이터 준비
        $skinData = array_merge([
            'column' => $column,
            'titleConfig' => $titleConfig,
            'titlePartial' => $titlePartial,
            'contentConfig' => $column->getContentConfig() ?? [],
            'skinDir' => dirname($skinPath),
            'assets' => $this->assetManager,
        ], $data);

        // 스킨 렌더링
        extract($skinData);
        ob_start();
        include $skinPath;
        return ob_get_clean();
    }

    /**
     * 스킨 기본 경로 반환
     *
     * 기본값: views/Block/ (Core 블록)
     * Plugin 렌더러에서 오버라이드하여 플러그인 내부 경로 지정 가능
     *
     * 예: return MUBLO_PLUGIN_PATH . '/Banner/views/Block/';
     */
    protected function getSkinBasePath(): string
    {
        return MUBLO_VIEW_PATH . '/Block/';
    }

    /**
     * 스킨 경로 반환
     */
    protected function getSkinPath(string $skin): string
    {
        $type = $this->getSkinType();
        return $this->getSkinBasePath() . $type . '/' . $skin . '/' . $skin . '.php';
    }

    /**
     * 타이틀 파셜 경로 결정
     *
     * 1. 스킨 디렉토리에 title.php가 있으면 사용 (오버라이드)
     * 2. 없으면 공유 파셜 사용
     */
    protected function resolveTitlePartial(string $skin): string
    {
        $type = $this->getSkinType();

        // 1. 스킨 오버라이드 (플러그인 or Core)
        $skinTitle = $this->getSkinBasePath() . $type . '/' . $skin . '/title.php';
        if (is_file($skinTitle)) {
            return $skinTitle;
        }

        // 2. 공유 파셜: views/Block/_shared/title.php (항상 Core)
        return MUBLO_VIEW_PATH . '/Block/_shared/title.php';
    }

    /**
     * 스킨을 찾을 수 없을 때 렌더링
     */
    protected function renderSkinNotFound(string $skin): string
    {
        $type = $this->getSkinType();
        return <<<HTML
<div class="block-error block-skin-not-found">
    <p>스킨을 찾을 수 없습니다: {$type}/{$skin}</p>
</div>
HTML;
    }

    /**
     * 타이틀 설정 추출
     */
    protected function extractTitleConfig(BlockColumn $column): array
    {
        return [
            'show' => $column->showTitle(),
            'text' => $column->getTitleText(),
            'position' => $column->getTitlePosition(),
            'color' => $column->getTitleColor(),
            'size_pc' => $column->getTitlePcSize(),
            'size_mo' => $column->getTitleMobileSize(),
            'pc_image' => $column->getTitlePcImage(),
            'mo_image' => $column->getTitleMobileImage(),
            'more_url' => $column->hasMoreLink() ? $column->getMoreUrl() : null,
            'copytext' => $column->getCopytext(),
            'copytext_color' => $column->getCopytextColor(),
        ];
    }

    /**
     * 빈 콘텐츠 렌더링
     */
    protected function renderEmptyContent(string $message = '등록된 콘텐츠가 없습니다.'): string
    {
        return <<<HTML
<div class="block-empty">
    <p>{$message}</p>
</div>
HTML;
    }
}
