<?php
/**
 * public/index.php
 *
 * 모든 웹 요청의 진입점
 */

$__startTime = microtime(true);

// 웹 루트 경로를 자동 감지 (public, www 등 디렉토리명에 무관하게 동작)
define('MUBLO_PUBLIC_PATH', __DIR__);

// Bootstrap
$app = require __DIR__ . '/../bootstrap.php';

// 설치 여부 확인 및 라우팅
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$isInstallPath = str_starts_with($requestUri, '/install');

if (!isInstalled() && !$isInstallPath) {
    header('Location: /install');
    exit;
}

if (isInstalled() && $isInstallPath) {
    header('Location: /');
    exit;
}

if ($isInstallPath) {
    require __DIR__ . '/install/index.php';
    exit;
}

// [추가] 쿼리 통계 수집을 위한 전역 변수 초기화
//$GLOBALS['__queryCount'] = 0;
//$GLOBALS['__queryTime'] = 0;

// 애플리케이션 실행
$app->boot();
$app->run();

// 디버그 정보 (개발 모드, HTML 응답만)
if (
    ($_ENV['APP_DEBUG'] ?? 'false') === 'true'
    && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'text/html')
) {
    $elapsedMs = (microtime(true) - $__startTime) * 1000;
    
    // DB 쿼리 비중 계산
    //$dbTime = $GLOBALS['__queryTime'] ?? 0;
    //$dbRatio = ($elapsedMs > 0) ? round(($dbTime / $elapsedMs) * 100, 1) : 0;
    //$phpTime = $elapsedMs - $dbTime;

    $memUsedRaw = memory_get_usage(false);
    $memPeakRaw = memory_get_peak_usage(true);

    $memUsed  = formatBytesSmart($memUsedRaw);
    $memPeak  = formatBytesSmart($memPeakRaw);
    $memDelta = formatBytesSmart($memPeakRaw - $memUsedRaw);

    echo "\n\n";

    echo "\n<!-- Debug: {$elapsedMs}ms"
       . " | Mem: {$memUsed} ({$memUsedRaw}B)"
       . " | Peak: {$memPeak} ({$memPeakRaw}B)"
       . " | Δ: {$memDelta}"
       . " -->\n";
}

// ============================================================
// Helper Functions
// ============================================================

/**
 * 설치 완료 여부 확인
 *
 * installed.lock은 설치 마지막 단계에서만 생성되므로
 * lock + config 파일 존재만으로 설치 완료 판단.
 * DB 장애 시 설치 페이지로 리다이렉트되는 오동작 방지.
 */
function isInstalled(): bool
{
    static $result = null;

    if ($result !== null) {
        return $result;
    }

    $lockFile = MUBLO_STORAGE_PATH . '/installed.lock';
    $dbConfig = MUBLO_CONFIG_PATH . '/database.php';

    return $result = file_exists($lockFile) && file_exists($dbConfig);
}

/**
 * 바이트를 사람이 읽기 쉬운 단위로 변환 (B, KB, MB, GB, TB)
 * 단위별로 소수 정밀도를 다르게 적용
 */
function formatBytesSmart(int|float $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $base  = 1024;

    $pow = (int) floor(log($bytes, $base));
    $pow = min($pow, count($units) - 1);

    $value = $bytes / ($base ** $pow);

    // 단위별 정밀도 (작을수록 더 정확히)
    $precision = match ($units[$pow]) {
        'B'  => 0,
        'KB' => 2,
        'MB' => 3,
        'GB', 'TB' => 4,
        default => 2,
    };

    return number_format($value, $precision) . ' ' . $units[$pow];
}