<?php
/**
 * 기획전 목록 (프론트 기본 스킨)
 *
 * @var string $pageTitle
 * @var array  $exhibitions  진행 중인 기획전 목록
 * @var string $error        (optional) 오류 메시지
 */
$exhibitions = $exhibitions ?? [];
$error       = $error ?? '';

if (!function_exists('e')) {
    function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
?>
<style>
.shop-exhibition-list { padding: 32px 0; }
.shop-exhibition-list__title { font-size: 1.5rem; font-weight: 700; margin-bottom: 24px; }
.shop-exhibition-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
.shop-exhibition-card { border-radius: 12px; overflow: hidden; border: 1px solid #eee; transition: box-shadow 0.2s; background: #fff; }
.shop-exhibition-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,.1); }
.shop-exhibition-card__banner { position: relative; width: 100%; padding-top: 50%; background: #f5f5f5; overflow: hidden; }
.shop-exhibition-card__banner img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
.shop-exhibition-card__banner-placeholder { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #bbb; font-size: 2rem; }
.shop-exhibition-card__body { padding: 16px; }
.shop-exhibition-card__name { font-size: 1rem; font-weight: 600; color: #222; margin-bottom: 6px; text-decoration: none; display: block; }
.shop-exhibition-card__name:hover { color: #5b5bf0; }
.shop-exhibition-card__period { font-size: 0.8rem; color: #888; }
.shop-exhibition-card__badge { display: inline-block; padding: 2px 8px; background: #5b5bf0; color: #fff; border-radius: 4px; font-size: 0.75rem; margin-bottom: 8px; }
.shop-exhibition-list__empty { text-align: center; padding: 60px 0; color: #888; }
</style>

<div class="shop-exhibition-list">
    <h2 class="shop-exhibition-list__title"><?= e($pageTitle ?? '기획전') ?></h2>

    <?php if ($error): ?>
    <div class="alert alert-warning"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (empty($exhibitions)): ?>
    <div class="shop-exhibition-list__empty">
        <i class="bi bi-megaphone" style="font-size:2.5rem;color:#ddd;display:block;margin-bottom:12px"></i>
        <p>현재 진행 중인 기획전이 없습니다.</p>
    </div>
    <?php else: ?>
    <div class="shop-exhibition-grid">
        <?php foreach ($exhibitions as $ex):
            $startDate = !empty($ex['start_date']) ? substr($ex['start_date'], 0, 10) : null;
            $endDate   = !empty($ex['end_date'])   ? substr($ex['end_date'], 0, 10)   : null;
            $url       = '/shop/exhibitions/' . (int) $ex['exhibition_id'];
        ?>
        <a href="<?= $url ?>" class="shop-exhibition-card text-decoration-none">
            <div class="shop-exhibition-card__banner">
                <?php if (!empty($ex['banner_image'])): ?>
                <img src="<?= e($ex['banner_image']) ?>" alt="<?= e($ex['title']) ?>">
                <?php else: ?>
                <div class="shop-exhibition-card__banner-placeholder">
                    <i class="bi bi-image"></i>
                </div>
                <?php endif; ?>
            </div>
            <div class="shop-exhibition-card__body">
                <span class="shop-exhibition-card__badge">진행 중</span>
                <span class="shop-exhibition-card__name"><?= e($ex['title']) ?></span>
                <?php if ($startDate || $endDate): ?>
                <div class="shop-exhibition-card__period">
                    <?= $startDate ?? '' ?><?= ($startDate && $endDate) ? ' ~ ' : '' ?><?= $endDate ?? '' ?>
                </div>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
