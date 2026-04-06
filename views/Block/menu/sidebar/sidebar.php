<?php
/**
 * Block Skin: menu/sidebar
 *
 * 사이드바 메뉴 스킨 (좌/우 사이드바용)
 * - 수직 리스트 형태
 * - 하위 메뉴 아코디언 토글
 * - 현재 페이지 활성 표시
 *
 * @var array $titleConfig 타이틀 설정
 * @var string $titlePartial 타이틀 파셜 경로
 * @var array $contentConfig 콘텐츠 설정
 * @var \Mublo\Entity\Block\BlockColumn $column 블록 칸 엔티티
 * @var array $menuTree 메뉴 트리 배열
 * @var string $orientation horizontal|vertical (무시, 항상 vertical)
 * @var int $maxDepth 최대 깊이
 * @var \Mublo\Core\Rendering\AssetManager|null $assets
 */

$blockId = 'block-sidebar-menu-' . ($column->getColumnId() ?? uniqid());
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

/**
 * 사이드바 메뉴 HTML 빌드 (재귀)
 */
function buildSidebarMenuHtml(array $items, int $maxDepth, int $currentDepth, string $currentPath): string
{
    $html = '';

    foreach ($items as $item) {
        $label = htmlspecialchars($item['label'] ?? '');
        $url = htmlspecialchars($item['url'] ?? '#');
        $target = $item['target'] ?? '_self';
        $icon = $item['icon'] ?? '';
        $hasChildren = !empty($item['children']) && $currentDepth < $maxDepth;

        // 현재 경로 또는 하위 경로 활성 체크
        $rawUrl = $item['url'] ?? '';
        $isActive = ($rawUrl === $currentPath);
        $isParentActive = !$isActive && $rawUrl !== '' && $rawUrl !== '#'
            && str_starts_with($currentPath, rtrim($rawUrl, '/') . '/');

        $liClasses = ['sidebar-menu__item'];
        if ($isActive) $liClasses[] = 'sidebar-menu__item--active';
        if ($isParentActive) $liClasses[] = 'sidebar-menu__item--parent-active';
        if ($hasChildren) $liClasses[] = 'sidebar-menu__item--has-children';

        $childHtml = '';
        if ($hasChildren) {
            $nextDepth = $currentDepth + 1;
            $isOpen = $isActive || $isParentActive || hasActiveChild($item['children'], $currentPath);
            if ($isOpen) $liClasses[] = 'sidebar-menu__item--open';

            $childHtml = '<ul class="sidebar-menu__sub sidebar-menu__sub--depth' . $nextDepth . '">'
                . buildSidebarMenuHtml($item['children'], $maxDepth, $nextDepth, $currentPath)
                . '</ul>';
        }

        $iconHtml = '';
        if ($icon) {
            $iconHtml = '<i class="' . htmlspecialchars($icon) . ' sidebar-menu__icon"></i>';
        }

        $toggleBtn = $hasChildren
            ? '<button type="button" class="sidebar-menu__toggle" aria-label="하위 메뉴 열기/닫기"><i class="bi bi-chevron-down"></i></button>'
            : '';

        $liClassStr = implode(' ', $liClasses);
        $html .= <<<HTML
<li class="{$liClassStr}">
    <div class="sidebar-menu__row">
        <a href="{$url}" target="{$target}" class="sidebar-menu__link">{$iconHtml}<span>{$label}</span></a>
        {$toggleBtn}
    </div>
    {$childHtml}
</li>
HTML;
    }

    return $html;
}

/**
 * 하위 항목 중 활성 메뉴가 있는지 재귀 체크
 */
function hasActiveChild(array $children, string $currentPath): bool
{
    foreach ($children as $child) {
        $url = $child['url'] ?? '';
        if ($url === $currentPath || ($url !== '' && $url !== '#' && str_starts_with($currentPath, rtrim($url, '/') . '/'))) {
            return true;
        }
        if (!empty($child['children']) && hasActiveChild($child['children'], $currentPath)) {
            return true;
        }
    }
    return false;
}
?>
<div id="<?= $blockId ?>" class="sidebar-menu">
    <?php include $titlePartial; ?>

    <nav class="sidebar-menu__nav">
        <ul class="sidebar-menu__list">
            <?= buildSidebarMenuHtml($menuTree ?? [], $maxDepth ?? 3, 1, $currentPath) ?>
        </ul>
    </nav>
</div>

<script>
(function() {
    var container = document.getElementById('<?= $blockId ?>');
    if (!container) return;

    container.addEventListener('click', function(e) {
        var toggle = e.target.closest('.sidebar-menu__toggle');
        if (!toggle) return;

        e.preventDefault();
        var li = toggle.closest('.sidebar-menu__item--has-children');
        if (li) {
            li.classList.toggle('sidebar-menu__item--open');
        }
    });
})();
</script>
