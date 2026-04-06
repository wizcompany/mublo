<?php
/**
 * 내 문의 목록 (프론트)
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

$typeLabels = ['PRODUCT' => '상품문의', 'STOCK' => '재고문의', 'DELIVERY' => '배송문의', 'OTHER' => '기타'];
?>
<style>
.my-inquiry-list { max-width: 720px; margin: 0 auto; padding: 24px 16px; }
.my-inquiry-list__title { font-size: 1.3rem; font-weight: 700; margin-bottom: 8px; }
.my-inquiry-list__count { font-size: 0.9rem; color: #888; margin-bottom: 20px; }
.my-inquiry-item { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 12px; overflow: hidden; }
.my-inquiry-item__header { padding: 14px 20px; cursor: pointer; display: flex; align-items: center; gap: 8px; }
.my-inquiry-item__type { padding: 2px 8px; background: #f3f4f6; border-radius: 4px; font-size: 0.75rem; color: #6b7280; }
.my-inquiry-item__title { flex: 1; font-size: 0.9rem; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.my-inquiry-item__badge-replied { padding: 2px 8px; background: #ecfdf5; color: #10b981; border-radius: 4px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
.my-inquiry-item__badge-waiting { padding: 2px 8px; background: #fff7ed; color: #f97316; border-radius: 4px; font-size: 0.75rem; font-weight: 600; white-space: nowrap; }
.my-inquiry-item__date { font-size: 0.8rem; color: #aaa; white-space: nowrap; }
.my-inquiry-item__body { display: none; padding: 16px 20px; border-top: 1px solid #f0f0f0; background: #fafafa; }
.my-inquiry-item__body.is-open { display: block; }
.my-inquiry-item__product { font-size: 0.8rem; color: #667eea; margin-bottom: 8px; }
.my-inquiry-item__content { font-size: 0.9rem; color: #444; white-space: pre-line; }
.my-inquiry-item__reply { margin-top: 12px; padding: 12px; background: #f0fdf4; border-radius: 8px; border-left: 3px solid #10b981; }
.my-inquiry-item__reply-label { font-size: 0.8rem; font-weight: 600; color: #10b981; margin-bottom: 4px; }
.my-inquiry-item__reply-text { font-size: 0.85rem; color: #444; white-space: pre-line; }
.my-inquiry-item__actions { margin-top: 10px; text-align: right; }
.my-inquiry-item__delete { font-size: 0.8rem; color: #ef4444; background: none; border: none; cursor: pointer; }
.my-inquiry-list__empty { text-align: center; padding: 60px 16px; }
.my-inquiry-list__empty p { color: #888; }
</style>

<div class="my-inquiry-list">
    <h2 class="my-inquiry-list__title">내 문의</h2>
    <p class="my-inquiry-list__count">총 <?= number_format($totalItems) ?>건</p>

    <?php if (empty($items)): ?>
    <div class="my-inquiry-list__empty">
        <i class="bi bi-question-circle" style="font-size:2.5rem;color:#ddd;display:block;margin-bottom:12px"></i>
        <p>작성한 문의가 없습니다.</p>
    </div>
    <?php else: ?>
    <?php foreach ($items as $item):
        $inquiryId = (int) ($item['inquiry_id'] ?? 0);
        $status = $item['inquiry_status'] ?? 'WAITING';
        $typeLabel = $typeLabels[$item['inquiry_type'] ?? 'PRODUCT'] ?? '문의';
        $goodsId = (int) ($item['goods_id'] ?? 0);
        $goodsName = $item['goods_name'] ?? '상품';
    ?>
    <div class="my-inquiry-item" id="inquiry-<?= $inquiryId ?>">
        <div class="my-inquiry-item__header" onclick="MyInquiries.toggle(<?= $inquiryId ?>)">
            <span class="my-inquiry-item__type"><?= e($typeLabel) ?></span>
            <span class="my-inquiry-item__title"><?= e($item['title'] ?? '') ?></span>
            <?php if ($status === 'REPLIED'): ?>
            <span class="my-inquiry-item__badge-replied">답변완료</span>
            <?php else: ?>
            <span class="my-inquiry-item__badge-waiting">답변대기</span>
            <?php endif; ?>
            <span class="my-inquiry-item__date"><?= e(substr($item['created_at'] ?? '', 0, 10)) ?></span>
        </div>
        <div class="my-inquiry-item__body" id="inquiry-body-<?= $inquiryId ?>">
            <div class="my-inquiry-item__product">
                <a href="/shop/products/<?= $goodsId ?>"><?= e($goodsName) ?></a>
            </div>
            <div class="my-inquiry-item__content"><?= e($item['content'] ?? '') ?></div>
            <?php if (!empty($item['reply'])): ?>
            <div class="my-inquiry-item__reply">
                <div class="my-inquiry-item__reply-label">판매자 답변</div>
                <div class="my-inquiry-item__reply-text"><?= e($item['reply']) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($status === 'WAITING'): ?>
            <div class="my-inquiry-item__actions">
                <button class="my-inquiry-item__delete" onclick="MyInquiries.delete(<?= $inquiryId ?>)">삭제</button>
            </div>
            <?php endif; ?>
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
const MyInquiries = {
    toggle(id) {
        document.getElementById(`inquiry-body-${id}`)?.classList.toggle('is-open');
    },
    delete(inquiryId) {
        if (!confirm('문의를 삭제하시겠습니까?')) return;
        MubloRequest.requestJson('/shop/inquiries/delete', { inquiry_id: inquiryId })
            .then(() => {
                document.getElementById(`inquiry-${inquiryId}`)?.remove();
                Mublo.toast('문의가 삭제되었습니다.');
            });
    }
};
</script>
