<?php
/**
 * 찜 목록
 *
 * @var array $items
 * @var array $pagination
 */
$items = $items ?? [];
$pagination = $pagination ?? [];
$totalItems = $pagination['totalItems'] ?? 0;
$currentPage = $pagination['currentPage'] ?? 1;
$perPage = $pagination['perPage'] ?? 20;

if (!function_exists('e')) {
    function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
?>
<style>
.shop-wishlist { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
.shop-wishlist__title { font-size: 1.3rem; font-weight: 700; margin-bottom: 8px; color: #333; }
.shop-wishlist__count { font-size: 0.9rem; color: #888; margin-bottom: 20px; }
.shop-wishlist__grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
.shop-wishlist__item { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; transition: box-shadow 0.15s; }
.shop-wishlist__item:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
.shop-wishlist__thumb { position: relative; aspect-ratio: 1; overflow: hidden; background: #f5f5f5; }
.shop-wishlist__thumb img { width: 100%; height: 100%; object-fit: cover; }
.shop-wishlist__thumb--empty { display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 2rem; width: 100%; height: 100%; aspect-ratio: 1; }
.shop-wishlist__remove { position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; background: rgba(255,255,255,0.9); border: none; border-radius: 50%; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #ef4444; }
.shop-wishlist__info { padding: 12px; }
.shop-wishlist__name { font-size: 0.9rem; font-weight: 500; color: #333; margin-bottom: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.shop-wishlist__price { font-size: 1rem; font-weight: 700; color: #333; }
.shop-wishlist__soldout { font-size: 0.75rem; color: #ef4444; margin-top: 4px; }
.shop-wishlist__cart { display: block; margin: 8px 12px 12px; padding: 8px; background: #667eea; color: #fff; border: none; border-radius: 8px; text-align: center; font-size: 0.85rem; font-weight: 600; cursor: pointer; text-decoration: none; }
.shop-wishlist__empty { text-align: center; padding: 60px 16px; }
.shop-wishlist__empty p { color: #888; margin-bottom: 16px; }
.shop-wishlist__empty-btn { display: inline-block; padding: 10px 24px; background: #667eea; color: #fff; border-radius: 8px; text-decoration: none; font-size: 0.9rem; font-weight: 600; }
</style>

<div class="shop-wishlist">
    <h2 class="shop-wishlist__title">찜 목록</h2>
    <p class="shop-wishlist__count">총 <?= number_format($totalItems) ?>개의 상품</p>

    <?php if (empty($items)): ?>
    <div class="shop-wishlist__empty">
        <p>찜한 상품이 없습니다.</p>
        <a href="/shop/products" class="shop-wishlist__empty-btn">쇼핑 계속하기</a>
    </div>
    <?php else: ?>
    <div class="shop-wishlist__grid">
        <?php foreach ($items as $item):
            $goodsId = (int) ($item['goods_id'] ?? 0);
            $goodsName = $item['goods_name'] ?? '';
            $displayPrice = (int) ($item['display_price'] ?? 0);
            $mainImage = $item['main_image'] ?? null;
            $isActive = (bool) ($item['is_active'] ?? true);
            $stockQty = $item['stock_quantity'];
            $isSoldout = ($stockQty !== null && (int) $stockQty <= 0);
        ?>
        <div class="shop-wishlist__item" data-goods-id="<?= $goodsId ?>">
            <a href="/shop/products/<?= $goodsId ?>" class="shop-wishlist__thumb">
                <?php if ($mainImage): ?>
                <img src="<?= e($mainImage) ?>" alt="<?= e($goodsName) ?>" loading="lazy">
                <?php else: ?>
                <div class="shop-wishlist__thumb--empty"><i class="bi bi-image"></i></div>
                <?php endif; ?>
                <button class="shop-wishlist__remove" onclick="ShopWishlist.remove(event, <?= $goodsId ?>)" title="찜 해제">
                    <i class="bi bi-heart-fill"></i>
                </button>
            </a>
            <div class="shop-wishlist__info">
                <div class="shop-wishlist__name"><?= e($goodsName) ?></div>
                <div class="shop-wishlist__price"><?= number_format($displayPrice) ?>원</div>
                <?php if ($isSoldout): ?>
                <div class="shop-wishlist__soldout">품절</div>
                <?php endif; ?>
            </div>
            <?php if ($isActive && !$isSoldout): ?>
            <a href="/shop/products/<?= $goodsId ?>" class="shop-wishlist__cart">상품 보기</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <?php
    $totalPages = $pagination['totalPages'] ?? 1;
    $pageNums = $pagination['pageNums'] ?? 10;
    if ($totalPages > 1):
        $half = (int)floor($pageNums / 2);
        $startPage = max(1, $currentPage - $half);
        $endPage = min($totalPages, $startPage + $pageNums - 1);
        $startPage = max(1, $endPage - $pageNums + 1);
    ?>
    <nav class="d-flex justify-content-center mt-4">
        <ul class="pagination">
            <?php if ($currentPage > 1): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $currentPage - 1 ?>">이전</a></li>
            <?php endif; ?>
            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="?page=<?= $currentPage + 1 ?>">다음</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const ShopWishlist = {
    remove(e, goodsId) {
        e.preventDefault();
        e.stopPropagation();
        if (!confirm('찜을 해제하시겠습니까?')) return;

        MubloRequest.requestJson('/shop/wishlist/toggle', { goods_id: goodsId })
            .then(() => location.reload());
    }
};
</script>
