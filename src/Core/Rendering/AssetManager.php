<?php
namespace Mublo\Core\Rendering;

/**
 * AssetManager — CSS/JS 에셋 수집 + 렌더링
 *
 * 블록 스킨이나 Plugin에서 필요한 CSS/JS를 수집하고,
 * FrontViewRenderer에서 플레이스홀더 치환을 통해
 * CSS는 <head>에, JS는 </body> 앞에 출력합니다.
 *
 * 사용법:
 * - View에서: $this->assets->addCss('/path/to/style.css')
 * - 블록 스킨에서: $assets->addCss('/path/to/style.css')
 */
class AssetManager
{
    private array $css = [];
    private array $js = [];

    public function addCss(string $path): void
    {
        if (!in_array($path, $this->css, true)) {
            $this->css[] = $path;
        }
    }

    public function addJs(string $path): void
    {
        if (!in_array($path, $this->js, true)) {
            $this->js[] = $path;
        }
    }

    /**
     * 등록된 CSS 경로 배열 반환
     */
    public function getCssPaths(): array
    {
        return $this->css;
    }

    /**
     * 등록된 JS 경로 배열 반환
     */
    public function getJsPaths(): array
    {
        return $this->js;
    }

    public function renderCss(): string
    {
        $html = '';
        foreach ($this->css as $path) {
            $url = htmlspecialchars($this->versionedPath($path), ENT_QUOTES, 'UTF-8');
            $html .= '<link rel="stylesheet" href="' . $url . '">' . "\n";
        }
        return $html;
    }

    public function renderJs(): string
    {
        $html = '';
        foreach ($this->js as $path) {
            $url = htmlspecialchars($this->versionedPath($path), ENT_QUOTES, 'UTF-8');
            $html .= '<script src="' . $url . '"></script>' . "\n";
        }
        return $html;
    }

    private function versionedPath(string $path): string
    {
        $file = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/') . '/' . ltrim($path, '/');
        if (is_file($file)) {
            return $path . '?' . filemtime($file);
        }
        return $path;
    }
}
