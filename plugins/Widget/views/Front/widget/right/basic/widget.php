<?php
/**
 * Widget Right Skin - basic
 *
 * @var array  $items    위젯 항목 배열
 * @var string $position 위치 (right)
 * @var array  $config   위젯 설정 (right_width 등)
 */
if (empty($items)) return;
$itemSize = (int) ($config['right_width'] ?? 50);
?>
<style>
#mublo-widget-right {
    position: fixed;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    z-index: 1100;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
#mublo-widget-right .widget-item {
    width: <?= $itemSize ?>px;
    height: <?= $itemSize ?>px;
    border-radius: 50%;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    transition: transform 0.2s;
}
#mublo-widget-right .widget-item:hover {
    transform: scale(1.1);
}
#mublo-widget-right .widget-item a {
    display: block;
    width: 100%;
    height: 100%;
}
#mublo-widget-right .widget-item img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}
@media (max-width: 768px) {
    #mublo-widget-right {
        display: none !important;
    }
}
</style>

<div id="mublo-widget-right">
    <?php foreach ($items as $item):
        $type = $item['item_type'] ?? 'link';
        $url = $item['link_url'] ?? '';
        $target = $item['link_target'] ?? '_blank';
        $image = htmlspecialchars($item['icon_image'] ?? '');
        $alt = htmlspecialchars($item['title'] ?? '');
    ?>
    <div class="widget-item">
        <?php if ($type === 'tel'): ?>
        <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+\-]/', '', $url)) ?>"><img src="<?= $image ?>" alt="<?= $alt ?>"></a>
        <?php else: ?>
        <a href="<?= htmlspecialchars($url) ?>" target="<?= htmlspecialchars($target) ?>"><img src="<?= $image ?>" alt="<?= $alt ?>"></a>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
