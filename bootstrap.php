<?php
/**
 * bootstrap.php
 *
 * 프레임워크 부트스트랩 파일
 *
 * 역할:
 * - 파일 시스템 경로 상수 정의
 * - Composer Autoload 로딩
 * - .env 환경 변수 로딩
 * - PHP 런타임 설정
 * - Application 객체 생성
 *
 * 금지:
 * - 라우팅 처리
 * - 인증 / CSRF / 세션 로직
 * - DB 접근
 * - 플러그인 실행
 * - HTML / URL 출력
 */

// ==================================================
// Filesystem Path Constants
// - 도메인과 무관한 절대 경로만 정의
// ==================================================
define('MUBLO_ROOT_PATH', __DIR__);
define('MUBLO_SRC_PATH', MUBLO_ROOT_PATH . '/src');
define('MUBLO_CONFIG_PATH', MUBLO_ROOT_PATH . '/config');
// MUBLO_PUBLIC_PATH: index.php에서 미리 정의된 경우 사용 (www, public_html 등 지원)
// 미정의 시 DOCUMENT_ROOT(웹 요청) 또는 /public(CLI) 사용
if (!defined('MUBLO_PUBLIC_PATH')) {
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($docRoot && is_dir($docRoot)) {
        define('MUBLO_PUBLIC_PATH', rtrim($docRoot, '/'));
    } else {
        define('MUBLO_PUBLIC_PATH', MUBLO_ROOT_PATH . '/public');
    }
    unset($docRoot);
}
define('MUBLO_STORAGE_PATH', MUBLO_ROOT_PATH . '/storage');
define('MUBLO_PUBLIC_STORAGE_PATH', MUBLO_PUBLIC_PATH . '/storage');
define('MUBLO_PLUGIN_PATH', MUBLO_ROOT_PATH . '/plugins');
define('MUBLO_PACKAGE_PATH', MUBLO_ROOT_PATH . '/packages');
define('MUBLO_ASSETS_PATH', MUBLO_PUBLIC_PATH . '/assets');
define('MUBLO_VIEW_PATH', MUBLO_ROOT_PATH . '/views');

// ==================================================
// URI Constants (Domain-agnostic)
// - 도메인 / 스킴 / 포트 포함 금지
// ==================================================
define('MUBLO_ASSET_URI', '/assets');
define('MUBLO_PUBLIC_STORAGE_URI', '/storage');

// ==================================================
// Composer Autoload
// ==================================================
require_once MUBLO_ROOT_PATH . '/vendor/autoload.php';

// ==================================================
// Helper Functions (env, base_path, etc.)
// ==================================================
require_once MUBLO_SRC_PATH . '/Helper/EnvHelpers.php';

// ==================================================
// Load Environment Variables (.env)
// ==================================================
use Dotenv\Dotenv;

$envFiles = [
    '.env',
    '.env.local',
    '.env.' . ($_ENV['APP_ENV'] ?? 'production'),
    '.env.' . ($_ENV['APP_ENV'] ?? 'production') . '.local',
];
foreach ($envFiles as $file) {
    $dotenv = Dotenv::createImmutable(MUBLO_ROOT_PATH, $file);
    $dotenv->safeLoad();
}

// ==================================================
// PHP Runtime Configuration
// ==================================================
$isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

if ($isDebug) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

// 기본 타임존
date_default_timezone_set('Asia/Seoul');

// ==================================================
// Boot Application
// ==================================================
use Mublo\Core\App\Application;

return new Application();
