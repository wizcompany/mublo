<?php
namespace Mublo\Core\Block\Renderer;

use Mublo\Entity\Block\BlockColumn;

/**
 * MovieRenderer
 *
 * 동영상 콘텐츠 렌더러
 *
 * 스킨에 전달되는 변수:
 * - $titleConfig: 타이틀 설정
 * - $contentConfig: 콘텐츠 설정
 * - $column: BlockColumn 엔티티
 * - $videoType: youtube|vimeo|video
 * - $videoHtml: 렌더링된 비디오 HTML
 * - $aspectRatio: 비율 (16:9, 4:3 등)
 */
class MovieRenderer implements RendererInterface
{
    use SkinRendererTrait;

    /**
     * 스킨 타입 반환
     */
    protected function getSkinType(): string
    {
        return 'movie';
    }

    /**
     * {@inheritdoc}
     */
    public function render(BlockColumn $column): string
    {
        $config = $column->getContentConfig() ?? [];
        $skin = $column->getContentSkin() ?: 'basic';

        // video_url이 있으면 자동으로 type과 video_id를 추출
        if (!empty($config['video_url']) && empty($config['video_id'])) {
            $parsed = $this->parseVideoUrl($config['video_url']);
            $config['type'] = $parsed['type'];
            $config['video_id'] = $parsed['video_id'];
        }

        $type = $config['type'] ?? 'video';
        $aspectRatio = $config['aspect_ratio'] ?? '16:9';

        $videoHtml = match ($type) {
            'youtube' => $this->buildYoutubeHtml($config),
            'vimeo' => $this->buildVimeoHtml($config),
            default => $this->buildVideoHtml($config),
        };

        if (empty($videoHtml)) {
            return $this->renderEmptyContent('동영상이 설정되지 않았습니다.');
        }

        return $this->renderSkin($column, $skin, [
            'videoType' => $type,
            'videoHtml' => $videoHtml,
            'aspectRatio' => $aspectRatio,
        ]);
    }

    /**
     * YouTube HTML 빌드
     */
    private function buildYoutubeHtml(array $config): string
    {
        $videoId = $config['video_id'] ?? null;
        if (!$videoId) {
            return '';
        }

        $videoId = htmlspecialchars($videoId);
        $params = [];

        $autoplay = filter_var($config['autoplay'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($autoplay) {
            $params['autoplay'] = '1';
            $params['mute'] = '1'; // 브라우저 정책: 자동재생은 음소거 필수
        } elseif (filter_var($config['muted'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $params['mute'] = '1';
        }
        if (filter_var($config['loop'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $params['loop'] = '1';
            $params['playlist'] = $videoId;
        }
        if (!filter_var($config['controls'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            $params['controls'] = '0';
        }

        $queryString = !empty($params) ? '?' . http_build_query($params) : '';

        return <<<HTML
<iframe
    src="https://www.youtube.com/embed/{$videoId}{$queryString}"
    class="block-movie__iframe"
    frameborder="0"
    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
    allowfullscreen
></iframe>
HTML;
    }

    /**
     * Vimeo HTML 빌드
     */
    private function buildVimeoHtml(array $config): string
    {
        $videoId = $config['video_id'] ?? null;
        if (!$videoId) {
            return '';
        }

        $videoId = htmlspecialchars($videoId);
        $params = [];

        $autoplay = filter_var($config['autoplay'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if ($autoplay) {
            $params['autoplay'] = '1';
            $params['muted'] = '1';
        } elseif (filter_var($config['muted'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $params['muted'] = '1';
        }
        if (filter_var($config['loop'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $params['loop'] = '1';
        }

        $queryString = !empty($params) ? '?' . http_build_query($params) : '';

        return <<<HTML
<iframe
    src="https://player.vimeo.com/video/{$videoId}{$queryString}"
    class="block-movie__iframe"
    frameborder="0"
    allow="autoplay; fullscreen; picture-in-picture"
    allowfullscreen
></iframe>
HTML;
    }

    /**
     * 영상 URL에서 type과 video_id 자동 추출
     */
    private function parseVideoUrl(string $url): array
    {
        // YouTube: 다양한 URL 형식 지원
        if (preg_match('/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return ['type' => 'youtube', 'video_id' => $m[1]];
        }

        // Vimeo
        if (preg_match('/vimeo\.com\/(?:video\/)?(\d+)/', $url, $m)) {
            return ['type' => 'vimeo', 'video_id' => $m[1]];
        }

        // 기타: HTML5 video로 처리
        return ['type' => 'video', 'video_id' => ''];
    }

    /**
     * HTML5 Video HTML 빌드
     */
    private function buildVideoHtml(array $config): string
    {
        $videoUrl = $config['video_url'] ?? null;
        if (!$videoUrl) {
            return '';
        }

        $videoUrl = htmlspecialchars($videoUrl);
        $attrs = [];

        if ($config['autoplay'] ?? false) {
            $attrs[] = 'autoplay';
        }
        if ($config['muted'] ?? false) {
            $attrs[] = 'muted';
        }
        if ($config['loop'] ?? false) {
            $attrs[] = 'loop';
        }
        if ($config['controls'] ?? true) {
            $attrs[] = 'controls';
        }

        $attrsStr = implode(' ', $attrs);

        return <<<HTML
<video src="{$videoUrl}" class="block-movie__video" {$attrsStr} playsinline></video>
HTML;
    }
}
