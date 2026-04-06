<?php

/**
 * Framework Helper Functions
 *
 * 전역 헬퍼 함수 정의
 */

use Mublo\Core\Env\Env;

if (!function_exists('env')) {
    /**
     * 환경 변수 조회 헬퍼
     *
     * @param string $key 환경 변수 키
     * @param mixed $default 기본값
     * @return mixed 환경 변수 값 또는 기본값
     *
     * @example
     *   env('DB_HOST', 'localhost')
     *   env('APP_DEBUG', false)
     */
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('base_path')) {
    /**
     * 프로젝트 루트 경로 반환
     *
     * @param string $path 상대 경로 (선택)
     * @return string 전체 경로
     */
    function base_path(string $path = ''): string
    {
        static $basePath = null;

        if ($basePath === null) {
            // src/Helper/EnvHelpers.php -> 프로젝트 루트 (2단계 상위)
            $basePath = dirname(__DIR__, 2);
        }

        return $path ? $basePath . DIRECTORY_SEPARATOR . ltrim($path, '/\\') : $basePath;
    }
}

if (!function_exists('config_path')) {
    /**
     * config 디렉토리 경로 반환
     *
     * @param string $path 상대 경로 (선택)
     * @return string 전체 경로
     */
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('storage_path')) {
    /**
     * storage 디렉토리 경로 반환
     *
     * @param string $path 상대 경로 (선택)
     * @return string 전체 경로
     */
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

// =========================================================================
// 에디터 헬퍼 함수
// =========================================================================

if (!function_exists('editor_html')) {
    /**
     * 에디터 HTML 출력
     *
     * @param string $id 에디터 ID
     * @param string $content 초기 콘텐츠
     * @param array $options 옵션 [height, toolbar, placeholder, ...]
     * @return string 에디터 HTML
     */
    function editor_html(string $id, string $content = '', array $options = []): string
    {
        return \Mublo\Helper\Editor\EditorHelper::html($id, $content, $options);
    }
}

if (!function_exists('editor_css')) {
    /**
     * 에디터 CSS 출력 (head 영역)
     *
     * @return string CSS 링크 태그
     */
    function editor_css(): string
    {
        return \Mublo\Helper\Editor\EditorHelper::css();
    }
}

if (!function_exists('editor_js')) {
    /**
     * 에디터 JS 출력 (body 끝)
     *
     * @return string JS 스크립트 태그
     */
    function editor_js(): string
    {
        return \Mublo\Helper\Editor\EditorHelper::js();
    }
}

if (!function_exists('editor_sync_js')) {
    /**
     * 폼 제출 전 에디터 동기화 JS
     *
     * @param string $id 에디터 ID
     * @return string JS 코드
     */
    function editor_sync_js(string $id): string
    {
        return \Mublo\Helper\Editor\EditorHelper::syncJs($id);
    }
}
