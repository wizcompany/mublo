<?php
/**
 * Admin Head Partial
 *
 * HTML 시작, CSS/JS 로드
 */

/**
 * 스킨 에셋 버전 (캐시 버스팅)
 */
function adminAssetVersion(string $path): string
{
    $fullPath = MUBLO_VIEW_PATH . '/Admin/basic/_assets' . $path;
    return file_exists($fullPath) ? filemtime($fullPath) : time();
}
?>
<!DOCTYPE html>
<html lang="ko" data-bs-theme="">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="admin-token" content="<?= isset($csrfToken) ? htmlspecialchars($csrfToken) : ''; ?>">
<title><?= htmlspecialchars($title ?? $pageTitle ?? $siteTitle ?? 'Mublo', ENT_QUOTES, 'UTF-8'); ?></title>
<?php $faviconUrl = trim($favicon ?? ''); ?>
<?php if ($faviconUrl): ?>
<link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($faviconUrl) ?>">
<?php else: ?>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<?php endif; ?>
<?php $appIconUrl = trim($appIcon ?? ''); ?>
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($appIconUrl ?: '/assets/images/app-icon.png') ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@200..900&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap">
<link rel="stylesheet" href="/assets/lib/bootstrap/5.3.8/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/lib/bootstrap-icons/1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/assets/lib/fontawesome/7.1.0/css/all.min.css">
<link rel="stylesheet" href="/assets/lib/flatpickr/4.6.13/dist/flatpickr.min.css">
<link rel="stylesheet" href="/assets/css/theme.css">
<link rel="stylesheet" href="/serve/admin/basic/css/common.css?<?= adminAssetVersion('/css/common.css') ?>">
<link rel="stylesheet" href="/serve/admin/basic/css/admin.css?<?= adminAssetVersion('/css/admin.css') ?>">
<?= $this->assets?->renderCss() ?>
<script src="/assets/lib/bootstrap/5.3.8/js/bootstrap.bundle.min.js"></script>
<script src="/assets/lib/js-cookie/3.0.5/dist/js.cookie.min.js"></script>
<script src="/assets/lib/just-validate/4.3.0/dist/just-validate.production.min.js"></script>
<script src="/assets/lib/clipboard/2.0.11/dist/clipboard.min.js"></script>
<script src="/assets/lib/inputmask/5.0.8/dist/inputmask.min.js"></script>
<script src="/assets/lib/dayjs/1.11.19/dayjs.min.js"></script>
<script src="/assets/lib/sortablejs/1.15.0/Sortable.min.js"></script>
<script src="/assets/lib/flatpickr/4.6.13/dist/flatpickr.min.js"></script>
<script src="/assets/lib/flatpickr/4.6.13/dist/l10n/ko.js"></script>
<script>if(!localStorage.getItem('theme'))localStorage.setItem('theme','light')</script>
<script src="/assets/js/theme.js"></script>
<script src="/assets/js/MubloCore.js"></script>
<script src="/assets/js/MubloRequest.js"></script>
<script src="/assets/js/MubloModal.js"></script>
<script src="/assets/js/MubloForm.js"></script>
<script src="/assets/js/MubloAddress.js"></script>
<script src="/serve/admin/basic/js/admin.js?<?= adminAssetVersion('/js/admin.js') ?>"></script>
</head>
<body>
