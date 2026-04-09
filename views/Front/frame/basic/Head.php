<?php
/**
 * Front Head (basic frame skin)
 *
 * HTML 시작, <head>, meta 태그, CSS 로드, <body> 시작
 *
 * @var string|null $pageTitle 페이지 제목 (Controller에서 전달)
 * @var string|null $seoTitle SEO 타이틀 (Controller에서 전달, 페이지별 오버라이드)
 * @var string|null $seoDescription SEO 설명 (Controller에서 전달, 페이지별 오버라이드)
 * @var string|null $seoKeywords SEO 키워드 (Controller에서 전달, 페이지별 오버라이드)
 * @var string|null $csrfToken CSRF 토큰
 * @var array $siteConfig 사이트 설정 (도메인별)
 * @var array $seoConfig SEO 설정 (도메인별)
 * @var array $siteImages 사이트 이미지 URLs (favicon, og_image 등)
 * @var string $currentUrl 현재 페이지 URL
 */

/**
 * 스킨 에셋 버전 (캐시 버스팅)
 */
function frontAssetVersion(string $path): string
{
    $fullPath = __DIR__ . '/_assets' . $path;
    return file_exists($fullPath) ? (string) filemtime($fullPath) : (string) time();
}

// 빈 문자열도 fallback 되도록 처리
$title = (!empty($seoTitle) ? $seoTitle : null)
    ?? (!empty($pageTitle) ? $pageTitle : null)
    ?? (!empty($seoConfig['meta_title']) ? $seoConfig['meta_title'] : null)
    ?? ($siteConfig['site_title'] ?? '');
$description = (!empty($seoDescription) ? $seoDescription : null)
    ?? ($seoConfig['meta_description'] ?? '');
$keywords = (!empty($seoKeywords) ? $seoKeywords : null)
    ?? ($seoConfig['meta_keywords'] ?? '');
$siteName = $siteConfig['site_title'] ?? '';

$gaId = trim($seoConfig['google_analytics'] ?? '');
$googleVerify = trim($seoConfig['google_site_verification'] ?? '');
$naverVerify = trim($seoConfig['naver_site_verification'] ?? '');
$favicon = trim($siteImages['favicon'] ?? $seoConfig['favicon'] ?? '');
$appIcon = trim($siteImages['app_icon'] ?? $seoConfig['app_icon'] ?? '');
$ogImage = trim((!empty($seoImage) ? $seoImage : null) ?? $siteImages['og_image'] ?? $seoConfig['og_image'] ?? '');
$metaPixel = trim($seoConfig['meta_pixel_id'] ?? '');
$kakaoPixel = trim($seoConfig['kakao_pixel_id'] ?? '');
$naverAna = trim($seoConfig['naver_analytics_id'] ?? '');
$customHead = trim($seoConfig['custom_head_script'] ?? '');
$canonicalUrl = $currentUrl ?? '';
?>
<!DOCTYPE html>
<html lang="ko">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken ?? '') ?>">
<?php if ($googleVerify): ?>
<meta name="google-site-verification" content="<?= htmlspecialchars($googleVerify) ?>">
<?php endif; ?>
<?php if ($naverVerify): ?>
<meta name="naver-site-verification" content="<?= htmlspecialchars($naverVerify) ?>">
<?php endif; ?>
<?php if ($description): ?>
<meta name="description" content="<?= htmlspecialchars($description) ?>">
<?php endif; ?>
<?php if ($keywords): ?>
<meta name="keywords" content="<?= htmlspecialchars($keywords) ?>">
<?php endif; ?>
<title><?= htmlspecialchars($title) ?></title>
<?php if ($canonicalUrl): ?>
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
<?php endif; ?>
<?php if ($favicon): ?>
<link rel="icon" type="image/x-icon" href="<?= htmlspecialchars($favicon) ?>">
<?php else: ?>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<?php endif; ?>
<link rel="apple-touch-icon" sizes="180x180" href="<?= htmlspecialchars($appIcon ?: '/assets/images/app-icon.png') ?>">
<?php if ($title): ?>
<meta property="og:title" content="<?= htmlspecialchars($title) ?>">
<?php endif; ?>
<?php if ($description): ?>
<meta property="og:description" content="<?= htmlspecialchars($description) ?>">
<?php endif; ?>
<meta property="og:type" content="website">
<?php if ($canonicalUrl): ?>
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
<?php endif; ?>
<?php if ($siteName): ?>
<meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
<?php endif; ?>
<?php if ($ogImage): ?>
<meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
<?php endif; ?>
<meta name="twitter:card" content="<?= $ogImage ? 'summary_large_image' : 'summary' ?>">
<?php if ($title): ?>
<meta name="twitter:title" content="<?= htmlspecialchars($title) ?>">
<?php endif; ?>
<?php if ($description): ?>
<meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
<?php endif; ?>
<?php if ($ogImage): ?>
<meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
<?php endif; ?>
<?php if ($gaId): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaId) ?>"></script>
<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= htmlspecialchars($gaId) ?>');</script>
<?php endif; ?>
<?php if ($metaPixel): ?>
<script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init','<?= htmlspecialchars($metaPixel) ?>');fbq('track','PageView');</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= htmlspecialchars($metaPixel) ?>&ev=PageView&noscript=1"/></noscript>
<?php endif; ?>
<?php if ($kakaoPixel): ?>
<script type="text/javascript" charset="UTF-8" src="//t1.daumcdn.net/kas/static/kp.js"></script>
<script type="text/javascript">kakaoPixel('<?= htmlspecialchars($kakaoPixel) ?>').pageView();</script>
<meta name="kakao-pixel" data-kakao-pixel="<?= htmlspecialchars($kakaoPixel) ?>">
<?php endif; ?>
<?php if ($naverAna): ?>
<script type="text/javascript" src="//wcs.naver.net/wcslog.js"></script>
<script type="text/javascript">if(!wcs_add)var wcs_add={};wcs_add["wa"]="<?= htmlspecialchars($naverAna) ?>";if(window.wcs){wcs_do();}</script>
<?php endif; ?>
<?php if ($customHead): ?>
<?= $customHead ?>
<?php endif; ?>

<!-- Google Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Serif+KR:wght@200..900&display=swap">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:ital,wght@0,200..800;1,200..800">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap">

<!-- Icon Fonts -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.1.0/css/all.min.css">

<!-- Swiper -->
<link rel="stylesheet" href="/assets/lib/swiper/12/swiper-bundle.min.css">
<script src="/assets/lib/swiper/12/swiper-bundle.min.js"></script>

<!-- AOS (Animate On Scroll) -->
<link rel="stylesheet" href="/assets/lib/aos/2/dist/aos.css">

<!-- Front Common CSS (컴포넌트 공통) -->
<link rel="stylesheet" href="/assets/css/front-common.css">

<!-- Block System CSS -->
<link rel="stylesheet" href="/assets/css/block.css">

<!-- Front Skin CSS -->
<link rel="stylesheet" href="/serve/front/<?= htmlspecialchars($frameSkin ?? 'basic') ?>/css/front.css?<?= frontAssetVersion('/css/front.css') ?>">

<?php if (!empty($siteConfig)): ?>
<!-- 도메인별 CSS 변수 -->
<style>
:root {
    <?php if (!empty($siteConfig['primary_color'])): ?>
    --color-primary: <?= htmlspecialchars($siteConfig['primary_color']) ?>;
    <?php endif; ?>
    <?php
    $layoutMaxWidth = (int) ($siteConfig['layout_max_width'] ?? 0);
    $contentMaxWidth = (int) ($siteConfig['content_max_width'] ?? 0);
    $sidebarLeftWidth = (int) ($siteConfig['layout_left_width'] ?? 0);
    $sidebarRightWidth = (int) ($siteConfig['layout_right_width'] ?? 0);
    ?>
    <?php if ($layoutMaxWidth > 0): ?>
    --site-max-width: <?= $layoutMaxWidth ?>px;
    <?php endif; ?>
    <?php if ($contentMaxWidth > 0): ?>
    --content-max-width: <?= $contentMaxWidth ?>px;
    <?php endif; ?>
    <?php if ($sidebarLeftWidth > 0): ?>
    --sidebar-left-width: <?= $sidebarLeftWidth ?>px;
    <?php endif; ?>
    <?php if ($sidebarRightWidth > 0): ?>
    --sidebar-right-width: <?= $sidebarRightWidth ?>px;
    <?php endif; ?>
}
</style>
<?php endif; ?>

<!-- Core JS (defer) -->
<script defer src="/assets/js/MubloCore.js"></script>
<script defer src="/assets/js/MubloRequest.js"></script>
<script defer src="/assets/js/MubloForm.js"></script>
<script defer src="/assets/js/MubloModal.js"></script>
<script defer src="/assets/js/MubloAddress.js"></script>
<script defer src="/assets/js/MubloTracking.js"></script>
<script defer src="/assets/js/MubloItemLayout.js"></script>

<!-- MUBLO_CSS -->
</head>
<body>
