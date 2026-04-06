<?php
/**
 * 내 리뷰 목록 (프론트)
 *
 * @var array $items
 * @var array $pagination
 */
$items = $items ?? [];
$pagination = $pagination ?? [];
$totalItems = $pagination['totalItems'] ?? 0;
$currentPage = $pagination['currentPage'] ?? 1;
$totalPages = $pagination['totalPages'] ?? 1;

if (!function_exists('e')) {
    function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
?>
<style>
.my-review-list { max-width: 720px; margin: 0 auto; padding: 24px 16px; }
.my-review-list__title { font-size: 1.3rem; font-weight: 700; margin-bottom: 8px; }
.my-review-list__count { font-size: 0.9rem; color: #888; margin-bottom: 20px; }
.my-review-item { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 16px 20px; margin-bottom: 12px; }
.my-review-item__product { font-size: 0.85rem; color: #667eea; font-weight: 500; margin-bottom: 8px; }
.my-review-item__rating { color: #f59e0b; margin-bottom: 6px; }
.my-review-item__content { font-size: 0.9rem; color: #444; white-space: pre-line; }
.my-review-item__images { display: flex; gap: 6px; margin-top: 8px; }
.my-review-item__img { width: 60px; height: 60px; object-fit: cover; border-radius: 6px; border: 1px solid #eee; }
.my-review-item__footer { display: flex; align-items: center; justify-content: space-between; margin-top: 10px; border-top: 1px solid #f5f5f5; padding-top: 8px; }
.my-review-item__date { font-size: 0.8rem; color: #aaa; }
.my-review-item__delete { font-size: 0.8rem; color: #ef4444; cursor: pointer; background: none; border: none; }
.my-review-list__empty { text-align: center; padding: 60px 16px; }
.my-review-list__empty p { color: #888; margin-bottom: 16px; }
</style>

<div class="my-review-list">
    <h2 class="my-review-list__title">내 후기</h2>
    <p class="my-review-list__count">총 <?= number_format($totalItems) ?>건</p>

    <?php if (empty($items)): ?>
    <div class="my-review-list__empty">
        <i class="bi bi-chat-square-text" style="font-size:2.5rem;color:#ddd;display:block;margin-bottom:12px"></i>
        <p>작성한 후기가 없습니다.</p>
    </div>
    <?php else: ?>
    <?php foreach ($items as $item):
        $rating = (int) ($item['rating'] ?? 5);
        $goodsName = $item['goods_name'] ?? '상품';
        $goodsId = (int) ($item['goods_id'] ?? 0);
        $reviewId = (int) ($item['review_id'] ?? 0);
        $images = array_filter([$item['image1'] ?? null, $item['image2'] ?? null, $item['image3'] ?? null]);
    ?>
    <div class="my-review-item" id="review-<?= $reviewId ?>">
        <div class="my-review-item__product">
            <a href="/shop/products/<?= $goodsId ?>"><?= e($goodsName) ?></a>
        </div>
        <div class="my-review-item__rating">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <i class="bi bi-star<?= $i <= $rating ? '-fill' : '' ?>"></i>
            <?php endfor; ?>
        </div>
        <div class="my-review-item__content"><?= e($item['content'] ?? '') ?></div>
        <?php if (!empty($images)): ?>
        <div class="my-review-item__images">
            <?php foreach ($images as $img): ?>
            <img src="<?= e($img) ?>" alt="후기 이미지" class="my-review-item__img">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <div class="my-review-item__footer">
            <span class="my-review-item__date"><?= e(substr($item['created_at'] ?? '', 0, 10)) ?></span>
            <button class="my-review-item__delete" onclick="MyReviews.delete(<?= $reviewId ?>)">삭제</button>
        </div>
    </div>
    <?php endforeach; ?>

    <?php
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
const MyReviews = {
    delete(reviewId) {
        if (!confirm('후기를 삭제하시겠습니까?')) return;
        MubloRequest.requestJson('/shop/reviews/delete', { review_id: reviewId })
            .then(() => {
                document.getElementById(`review-${reviewId}`)?.remove();
                Mublo.toast('후기가 삭제되었습니다.');
            });
    }
};
</script>
