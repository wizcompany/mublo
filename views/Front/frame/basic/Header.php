<?php
/**
 * Mublo Front Header
 *
 * 구조: 로고(이미지/텍스트) + GNB + 검색(PC) + 유틸리티(PC) + 모바일 토글
 * 모바일 패널: 검색 + GNB(세로) + 유틸리티
 *
 * @var array  $siteConfig    사이트 설정 (site_title 등)
 * @var array  $siteImages    사이트 이미지 URLs (logo_pc, logo_mobile)
 * @var array  $menuTree      메뉴 트리 (계층 구조)
 * @var array  $utilityMenus  유틸리티 메뉴 목록
 * @var array  $currentMember 로그인 회원 정보 (null = 비로그인)
 * @var string $currentMenuCode 현재 활성 메뉴 코드
 */

$siteName = htmlspecialchars($siteConfig['site_title'] ?? 'MUBLO');
$pcLogo   = $siteImages['logo_pc'] ?? '';
$moLogo   = $siteImages['logo_mobile'] ?? '';
$menus    = $menuTree ?? [];
$member   = $currentMember ?? null;
$isLogin  = !empty($member);
$activeMenuCode = $currentMenuCode ?? '';

// visibility 필터링 (guest/member/all)
$visibilityFilter = function ($item) use ($isLogin) {
    $vis = $item['visibility'] ?? 'all';
    if ($vis === 'guest') return !$isLogin;
    if ($vis === 'member') return $isLogin;
    return true;
};

// 메인 메뉴 트리 visibility 필터링 (재귀)
$filterMenuTree = function (array $items) use (&$filterMenuTree, $visibilityFilter): array {
    $filtered = [];
    foreach ($items as $item) {
        if (!$visibilityFilter($item)) {
            continue;
        }
        if (!empty($item['children'])) {
            $item['children'] = $filterMenuTree($item['children']);
        }
        $filtered[] = $item;
    }
    return $filtered;
};
$menus = $filterMenuTree($menus);

// 유틸리티 메뉴 visibility 필터링
$filteredUtility = array_filter($utilityMenus ?? [], $visibilityFilter);
?>

<header class="mublo-header">
    <div class="mublo-container">
        <div class="mublo-header__inner">

            <!-- 로고 -->
            <div class="mublo-header__logo">
                <a href="/" class="logo-link">
                    <?php if ($pcLogo && $moLogo): ?>
                    <img src="<?= htmlspecialchars($pcLogo) ?>" alt="<?= $siteName ?>" class="logo-img logo-pc">
                    <img src="<?= htmlspecialchars($moLogo) ?>" alt="<?= $siteName ?>" class="logo-img logo-mobile">
                    <?php elseif ($pcLogo): ?>
                    <img src="<?= htmlspecialchars($pcLogo) ?>" alt="<?= $siteName ?>" class="logo-img">
                    <?php elseif ($moLogo): ?>
                    <img src="<?= htmlspecialchars($moLogo) ?>" alt="<?= $siteName ?>" class="logo-img">
                    <?php else: ?>
                    <span class="logo-text--main"><?= $siteName ?></span><span class="logo-text--dot">.</span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- GNB (PC) -->
            <nav class="mublo-header__nav" id="main-nav">
                <div class="nav-wrapper">
                    <?= $this->menu($menus, $activeMenuCode) ?>
                </div>
            </nav>

            <!-- 검색 (PC) -->
            <div class="mublo-header__search">
                <form action="/search" method="get" class="header-search-form">
                    <input type="text" name="q" class="header-search-input"
                           placeholder="검색어 입력..."
                           value="<?= htmlspecialchars($_GET['q'] ?? '') ?>">
                    <button type="submit" class="header-search-btn">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </button>
                </form>
            </div>

            <!-- 유틸리티 메뉴 (PC) -->
            <?php if (!empty($filteredUtility)): ?>
            <ul class="mublo-header__utility">
                <?php foreach ($filteredUtility as $item): ?>
                <li>
                    <a href="<?= htmlspecialchars($item['url'] ?? '#') ?>"
                       <?= !empty($item['target']) && $item['target'] === '_blank' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                        <?php if (!empty($item['icon'])): ?>
                        <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                        <?php endif; ?>
                        <span><?= htmlspecialchars($item['label'] ?? '') ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <!-- 모바일 토글 -->
            <button class="mublo-header__toggle" id="mubloPanelToggle" aria-label="메뉴 열기">
                <span class="line"></span>
                <span class="line"></span>
                <span class="line"></span>
            </button>

        </div>
    </div>
</header>

<!-- 모바일 패널 -->
<div class="mublo-panel" id="mubloPanel">
    <div class="mublo-panel__header">
        <span class="mublo-panel__title">Menu</span>
        <button class="mublo-panel__close" id="mubloPanelClose" aria-label="메뉴 닫기">&times;</button>
    </div>
    <div class="mublo-panel__body">
        <!-- 검색 -->
        <div class="mublo-panel__search">
            <form action="/search" method="get" class="panel-search-form">
                <input type="text" name="q" class="panel-search-input" placeholder="검색어 입력...">
                <button type="submit" class="panel-search-btn">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                </button>
            </form>
        </div>
        <!-- GNB (세로) -->
        <nav class="mublo-panel__nav">
            <?= $this->menu($menus, $activeMenuCode) ?>
        </nav>
        <!-- 유틸리티 -->
        <?php if (!empty($filteredUtility)): ?>
        <div class="mublo-panel__utility">
            <?php foreach ($filteredUtility as $item): ?>
            <a href="<?= htmlspecialchars($item['url'] ?? '#') ?>"
               class="mublo-panel__utility-link"
               <?= !empty($item['target']) && $item['target'] === '_blank' ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <?php if (!empty($item['icon'])): ?>
                <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
                <?php endif; ?>
                <span><?= htmlspecialchars($item['label'] ?? '') ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<div class="mublo-panel__backdrop" id="mubloPanelBackdrop"></div>

<script>
(function() {
    var btn = document.getElementById('mubloPanelToggle');
    var panel = document.getElementById('mubloPanel');
    var close = document.getElementById('mubloPanelClose');
    var backdrop = document.getElementById('mubloPanelBackdrop');
    if (!btn || !panel) return;
    function open() { panel.classList.add('is-open'); backdrop.classList.add('is-open'); document.body.style.overflow = 'hidden'; }
    function shut() { panel.classList.remove('is-open'); backdrop.classList.remove('is-open'); document.body.style.overflow = ''; }
    btn.addEventListener('click', open);
    if (close) close.addEventListener('click', shut);
    if (backdrop) backdrop.addEventListener('click', shut);
})();
</script>
