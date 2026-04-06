<?php
namespace Mublo\Core\Block\Renderer;

use Mublo\Entity\Block\BlockColumn;

/**
 * HtmlRenderer
 *
 * HTML 직접입력 콘텐츠 렌더러
 *
 * 모드:
 * - single (기본): 단일 HTML/CSS/JS 출력
 * - slide: 여러 HTML 슬라이드를 Swiper로 출력 (MubloItemLayout.js 연동)
 *
 * 스킨에 전달되는 변수:
 * - $titleConfig: 타이틀 설정
 * - $contentConfig: 콘텐츠 설정
 * - $column: BlockColumn 엔티티
 * - $html: 정화된 HTML 콘텐츠 (single 모드)
 * - $css: CSS 문자열
 * - $js: JS 문자열
 * - $slides: 슬라이드 배열 (slide 모드, [{html: "..."}, ...])
 * - $slideAttrs: MubloItemLayout data 속성 문자열 (slide 모드)
 */
class HtmlRenderer implements RendererInterface
{
    use SkinRendererTrait;

    protected function getSkinType(): string
    {
        return 'html';
    }

    public function render(BlockColumn $column): string
    {
        $config = $column->getContentConfig() ?? [];
        $skin = $column->getContentSkin() ?: 'basic';
        $mode = $config['mode'] ?? 'single';

        $rawCss = $config['css'] ?? '';
        $rawJs = $config['js'] ?? '';

        if ($mode === 'slide') {
            return $this->renderSlideMode($column, $skin, $config, $rawCss, $rawJs);
        }

        return $this->renderSingleMode($column, $skin, $config, $rawCss, $rawJs);
    }

    private function renderSingleMode(BlockColumn $column, string $skin, array $config, string $css, string $js): string
    {
        $rawHtml = $config['html'] ?? '';

        if (empty($rawHtml)) {
            return $this->renderEmptyContent('HTML 콘텐츠가 없습니다.');
        }

        return $this->renderSkin($column, $skin, [
            'html' => $rawHtml,
            'css' => $css,
            'js' => $js,
            'slides' => null,
            'slideAttrs' => '',
        ]);
    }

    private function renderSlideMode(BlockColumn $column, string $skin, array $config, string $css, string $js): string
    {
        $slides = $config['slides'] ?? [];

        if (empty($slides)) {
            return $this->renderEmptyContent('슬라이드 콘텐츠가 없습니다.');
        }

        $attrs = $this->buildSlideAttrs($config);

        return $this->renderSkin($column, $skin, [
            'html' => '',
            'css' => $css,
            'js' => $js,
            'slides' => $slides,
            'slideAttrs' => $attrs,
        ]);
    }

    private function buildSlideAttrs(array $config): string
    {
        $parts = [
            'data-pc-style="slide"',
            'data-mo-style="slide"',
            'data-pc-cols="1"',
            'data-mo-cols="1"',
        ];

        $pcAutoplay = (int) ($config['pc_autoplay'] ?? 0);
        $moAutoplay = (int) ($config['mo_autoplay'] ?? 0);

        if ($pcAutoplay > 0) {
            $parts[] = 'data-pc-autoplay="' . $pcAutoplay . '"';
        }
        if ($moAutoplay > 0) {
            $parts[] = 'data-mo-autoplay="' . $moAutoplay . '"';
        }
        if (!empty($config['pc_loop'])) {
            $parts[] = 'data-pc-loop="true"';
        }
        if (!empty($config['mo_loop'])) {
            $parts[] = 'data-mo-loop="true"';
        }

        return implode(' ', $parts);
    }
}
