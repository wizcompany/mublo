<?php
/**
 * 기획전 상세 (프론트 기본 스킨)
 *
 * @var string $pageTitle
 * @var array  $exhibition
 * @var array  $items        연결된 상품/카테고리 아이템
 */
$exhibition = $exhibition ?? [];
$items      = $items ?? [];

if (!function_exists('e')) {
    function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

// 상품 아이템만 추출
$goodsItems = array_filter($items, fn($i) => ($i['target_type'] ?? '') === 'goods');
?>
<style>
.shop-exhibition-view { padding: 32px 0; }
.shop-exhibition-view__banner { width: 100%; border-radius: 12px; overflow: hidden; margin-bottom: 32px; }
.shop-exhibition-view__banner img { width: 100%; height: auto; display: block; }
.shop-exhibition-view__title { font-size: 1.6rem; font-weight: 700; margin-bottom: 8px; }
.shop-exhibition-view__period { font-size: 0.9rem; color: #888; margin-bottom: 16px; }
.shop-exhibition-view__desc { color: #555; line-height: 1.7; margin-bottom: 32px; white-space: pre-line; }
.shop-exhibition-view__products { }
.shop-exhibition-view__products-title { font-size: 1.1rem; font-weight: 600; margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid #5b5bf0; }
.shop-product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.shop-product-card { border: 1px solid #eee; border-radius: 10px; overflow: hidden; background: #fff; transition: box-shadow .2s; }
.shop-product-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
.shop-product-card__img { width: 100%; padding-top: 100%; position: relative; background: #f9f9f9; overflow: hidden; }
.shop-product-card__img img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
.shop-product-card__img-placeholder { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ddd; font-size: 1.8rem; }
.shop-product-card__body { padding: 12px; }
.shop-product-card__name { font-size: 0.9rem; font-weight: 500; color: #222; text-decoration: none; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.shop-product-card__name:hover { color: #5b5bf0; }
.shop-product-card__price { font-size: 1rem; font-weight: 700; color: #222; margin-top: 4px; }
.shop-exhibition-view__empty { text-align: center; padding: 40px; color: #aaa; background: #f9f9f9; border-radius: 8px; }
</style>

<div class="shop-exhibition-view">
    <!-- 배너 -->
    <?php if (!empty($exhibition['banner_image'])): ?>
    <div class="shop-exhibition-view__banner">
        <img src="<?= e($exhibition['banner_image']) ?>" alt="<?= e($exhibition['title']) ?>">
    </div>
    <?php endif; ?>

    <!-- 기획전 정보 -->
    <h1 class="shop-exhibition-view__title"><?= e($exhibition['title'] ?? '') ?></h1>

    <?php
    $startDate = !empty($exhibition['start_date']) ? substr($exhibition['start_date'], 0, 10) : null;
    $endDate   = !empty($exhibition['end_date'])   ? substr($exhibition['end_date'], 0, 10)   : null;
    if ($startDate || $endDate):
    ?>
    <div class="shop-exhibition-view__period">
        <i class="bi bi-calendar3 me-1"></i>
        <?= $startDate ?? '' ?><?= ($startDate && $endDate) ? ' ~ ' : '' ?><?= $endDate ?? '' ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($exhibition['description'])): ?>
    <div class="shop-exhibition-view__desc"><?= e($exhibition['description']) ?></div>
    <?php endif; ?>

    <!-- 연결 상품 -->
    <div class="shop-exhibition-view__products">
        <div class="shop-exhibition-view__products-title">기획전 상품</div>

        <?php if (empty($goodsItems)): ?>
        <div class="shop-exhibition-view__empty">
            <i class="bi bi-bag" style="font-size:1.5rem;display:block;margin-bottom:8px"></i>
            <p>등록된 상품이 없습니다.</p>
        </div>
        <?php else: ?>
        <div class="shop-product-grid">
            <?php foreach ($goodsItems as $item):
                $goodsId    = (int) ($item['goods_id'] ?? 0);
                $goodsName  = $item['goods_name'] ?? '';
                $price      = (int) ($item['display_price'] ?? 0);
                $image      = $item['product_image'] ?? null;
                $productUrl = '/shop/products/' . $goodsId;
            ?>
            <a href="<?= $productUrl ?>" class="shop-product-card text-decoration-none">
                <div class="shop-product-card__img">
                    <?php if ($image): ?>
                    <img src="<?= e($image) ?>" alt="<?= e($goodsName) ?>">
                    <?php else: ?>
                    <div class="shop-product-card__img-placeholder"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                </div>
                <div class="shop-product-card__body">
                    <span class="shop-product-card__name"><?= e($goodsName) ?></span>
                    <?php if ($price > 0): ?>
                    <div class="shop-product-card__price"><?= number_format($price) ?>원</div>
                    <?php endif; ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="mt-4">
        <a href="/shop/exhibitions" class="btn btn-default">
            <i class="bi bi-arrow-left me-1"></i>기획전 목록
        </a>
    </div>
</div>
