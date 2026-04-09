<?php
/**
 * 상품별 리뷰 목록 (프론트)
 *
 * @var array $items
 * @var array $pagination
 * @var int $goodsId
 * @var float $avgRating
 */
$items = $items ?? [];
$pagination = $pagination ?? [];
$goodsId = $goodsId ?? 0;
$avgRating = $avgRating ?? 0.0;
$totalItems = $pagination['totalItems'] ?? 0;
$currentPage = $pagination['currentPage'] ?? 1;
$totalPages = $pagination['totalPages'] ?? 1;

if (!function_exists('e')) {
    function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
?>
<style>
.shop-review-list { padding: 24px 0; }
.shop-review-list__summary { display: flex; align-items: center; gap: 16px; padding: 20px; background: #f9fafb; border-radius: 12px; margin-bottom: 20px; }
.shop-review-list__avg { font-size: 2.5rem; font-weight: 800; color: #333; line-height: 1; }
.shop-review-list__stars { color: #f59e0b; font-size: 1.2rem; }
.shop-review-list__star-empty { color: #d1d5db; }
.shop-review-list__count { font-size: 0.9rem; color: #888; margin-top: 4px; }
.shop-review-item { border-bottom: 1px solid #f0f0f0; padding: 20px 0; }
.shop-review-item:last-child { border-bottom: none; }
.shop-review-item__header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.shop-review-item__rating { color: #f59e0b; }
.shop-review-item__author { font-size: 0.85rem; color: #666; }
.shop-review-item__date { font-size: 0.8rem; color: #aaa; margin-left: auto; }
.shop-review-item__badge { display: inline-block; padding: 2px 8px; background: #667eea; color: #fff; border-radius: 4px; font-size: 0.75rem; }
.shop-review-item__content { font-size: 0.9rem; color: #444; line-height: 1.6; white-space: pre-line; }
.shop-review-item__images { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
.shop-review-item__img { width: 80px; height: 80px; object-fit: cover; border-radius: 8px; border: 1px solid #eee; cursor: pointer; }
.shop-review-item__reply { margin-top: 12px; padding: 12px 16px; background: #f5f5f5; border-radius: 8px; border-left: 3px solid #667eea; }
.shop-review-item__reply-label { font-size: 0.8rem; font-weight: 600; color: #667eea; margin-bottom: 4px; }
.shop-review-item__reply-text { font-size: 0.85rem; color: #555; white-space: pre-line; }
.shop-review-list__empty { text-align: center; padding: 40px; color: #888; }
</style>

<div class="shop-review-list">
    <div class="shop-review-list__summary">
        <div>
            <div class="shop-review-list__avg"><?= number_format($avgRating, 1) ?></div>
            <div class="shop-review-list__stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star-fill <?= $i <= round($avgRating) ? '' : 'shop-review-list__star-empty' ?>"></i>
                <?php endfor; ?>
            </div>
        </div>
        <div>
            <div class="shop-review-list__count">총 <?= number_format($totalItems) ?>개의 후기</div>
        </div>
    </div>

    <?php if (empty($items)): ?>
    <div class="shop-review-list__empty">
        <i class="bi bi-chat-square-text" style="font-size:2rem;color:#ddd;display:block;margin-bottom:8px"></i>
        <p>아직 작성된 후기가 없습니다.</p>
    </div>
    <?php else: ?>
    <?php foreach ($items as $item):
        $rating = (int) ($item['rating'] ?? 5);
        $authorName = $item['author_name'] ?? '';
        $createdAt = $item['created_at'] ?? '';
        $content = $item['content'] ?? '';
        $adminReply = $item['admin_reply'] ?? '';
        $reviewType = $item['review_type'] ?? 'TEXT';
    ?>
    <div class="shop-review-item">
        <div class="shop-review-item__header">
            <span class="shop-review-item__rating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <i class="bi bi-star<?= $i <= $rating ? '-fill' : '' ?>"></i>
                <?php endfor; ?>
            </span>
            <?php if ($reviewType === 'PHOTO'): ?>
            <span class="shop-review-item__badge">포토</span>
            <?php endif; ?>
            <span class="shop-review-item__date"><?= e(substr($createdAt, 0, 10)) ?></span>
        </div>
        <div class="shop-review-item__content"><?= e($content) ?></div>

        <?php
        $images = array_filter([$item['image1'] ?? null, $item['image2'] ?? null, $item['image3'] ?? null]);
        if (!empty($images)):
        ?>
        <div class="shop-review-item__images">
            <?php foreach ($images as $img): ?>
            <img src="<?= e($img) ?>" alt="후기 이미지" class="shop-review-item__img"
                 onclick="window.open('<?= e($img) ?>', '_blank')">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($adminReply): ?>
        <div class="shop-review-item__reply">
            <div class="shop-review-item__reply-label">판매자 답변</div>
            <div class="shop-review-item__reply-text"><?= e($adminReply) ?></div>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php
    $pageNums = $pagination['pageNums'] ?? 5;
    if ($totalPages > 1):
        $half = (int)floor($pageNums / 2);
        $startPage = max(1, $currentPage - $half);
        $endPage = min($totalPages, $startPage + $pageNums - 1);
        $startPage = max(1, $endPage - $pageNums + 1);
    ?>
    <nav class="d-flex justify-content-center mt-4">
        <ul class="pagination">
            <?php if ($currentPage > 1): ?>
            <li class="page-item"><a class="page-link" href="?goods_id=<?= $goodsId ?>&page=<?= $currentPage - 1 ?>">이전</a></li>
            <?php endif; ?>
            <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="?goods_id=<?= $goodsId ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
            <li class="page-item"><a class="page-link" href="?goods_id=<?= $goodsId ?>&page=<?= $currentPage + 1 ?>">다음</a></li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</div>
