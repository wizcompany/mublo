<?php
/**
 * 상품별 문의 목록 (프론트)
 *
 * @var array $items
 * @var array $pagination
 * @var int $goodsId
 * @var int $currentMemberId
 */
$items = $items ?? [];
$pagination = $pagination ?? [];
$goodsId = $goodsId ?? 0;
$currentMemberId = $currentMemberId ?? 0;
$totalItems = $pagination['totalItems'] ?? 0;
$currentPage = $pagination['currentPage'] ?? 1;
$totalPages = $pagination['totalPages'] ?? 1;

if (!function_exists('e')) {
    function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

$typeLabels = [
    'PRODUCT' => '상품문의',
    'STOCK' => '재고문의',
    'DELIVERY' => '배송문의',
    'OTHER' => '기타',
];
?>
<style>
.shop-inquiry-list { padding: 24px 0; }
.shop-inquiry-list__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.shop-inquiry-list__title { font-size: 1rem; font-weight: 700; color: #333; }
.shop-inquiry-list__write { padding: 8px 16px; background: #667eea; color: #fff; border: none; border-radius: 8px; font-size: 0.85rem; font-weight: 600; cursor: pointer; }
.shop-inquiry-item { border-bottom: 1px solid #f0f0f0; padding: 16px 0; }
.shop-inquiry-item:last-child { border-bottom: none; }
.shop-inquiry-item__header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; cursor: pointer; }
.shop-inquiry-item__type { display: inline-block; padding: 2px 8px; background: #f3f4f6; border-radius: 4px; font-size: 0.75rem; color: #6b7280; }
.shop-inquiry-item__status-replied { display: inline-block; padding: 2px 8px; background: #ecfdf5; color: #10b981; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.shop-inquiry-item__status-waiting { display: inline-block; padding: 2px 8px; background: #fff7ed; color: #f97316; border-radius: 4px; font-size: 0.75rem; font-weight: 600; }
.shop-inquiry-item__secret { color: #9ca3af; font-size: 0.8rem; }
.shop-inquiry-item__title { font-size: 0.9rem; font-weight: 500; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.shop-inquiry-item__date { font-size: 0.8rem; color: #aaa; margin-left: auto; white-space: nowrap; }
.shop-inquiry-item__body { display: none; margin-top: 10px; padding: 12px; background: #fafafa; border-radius: 8px; }
.shop-inquiry-item__body.is-open { display: block; }
.shop-inquiry-item__content { font-size: 0.9rem; color: #444; white-space: pre-line; line-height: 1.6; }
.shop-inquiry-item__reply { margin-top: 12px; padding: 12px; background: #f0fdf4; border-radius: 8px; border-left: 3px solid #10b981; }
.shop-inquiry-item__reply-label { font-size: 0.8rem; font-weight: 600; color: #10b981; margin-bottom: 6px; }
.shop-inquiry-item__reply-text { font-size: 0.85rem; color: #444; white-space: pre-line; }
.shop-inquiry-list__empty { text-align: center; padding: 40px; color: #888; }

/* 폼 모달 */
.inquiry-form-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1050; align-items: center; justify-content: center; }
.inquiry-form-overlay.is-open { display: flex; }
.inquiry-form-box { background: #fff; border-radius: 16px; padding: 24px; width: 100%; max-width: 500px; margin: 16px; }
.inquiry-form-box h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 16px; }
</style>

<div class="shop-inquiry-list">
    <div class="shop-inquiry-list__header">
        <span class="shop-inquiry-list__title">상품 문의 (<?= number_format($totalItems) ?>)</span>
        <?php if ($currentMemberId > 0): ?>
        <button class="shop-inquiry-list__write" onclick="InquiryList.openForm()">문의 작성</button>
        <?php endif; ?>
    </div>

    <?php if (empty($items)): ?>
    <div class="shop-inquiry-list__empty">
        <p>아직 작성된 문의가 없습니다.</p>
    </div>
    <?php else: ?>
    <?php foreach ($items as $item):
        $inquiryId = (int) ($item['inquiry_id'] ?? 0);
        $isSecret = (bool) ($item['is_secret'] ?? false);
        $memberIdOfItem = (int) ($item['member_id'] ?? 0);
        $canView = !$isSecret || $memberIdOfItem === $currentMemberId;
        $status = $item['inquiry_status'] ?? 'WAITING';
        $typeLabel = $typeLabels[$item['inquiry_type'] ?? 'PRODUCT'] ?? '문의';
    ?>
    <div class="shop-inquiry-item">
        <div class="shop-inquiry-item__header" onclick="InquiryList.toggle(<?= $inquiryId ?>)">
            <span class="shop-inquiry-item__type"><?= e($typeLabel) ?></span>
            <?php if ($status === 'REPLIED'): ?>
            <span class="shop-inquiry-item__status-replied">답변완료</span>
            <?php else: ?>
            <span class="shop-inquiry-item__status-waiting">답변대기</span>
            <?php endif; ?>
            <?php if ($isSecret): ?>
            <i class="bi bi-lock-fill shop-inquiry-item__secret" title="비밀글"></i>
            <?php endif; ?>
            <span class="shop-inquiry-item__title">
                <?php if ($canView): ?>
                <?= e($item['title'] ?? '') ?>
                <?php else: ?>
                비밀글입니다.
                <?php endif; ?>
            </span>
            <span class="shop-inquiry-item__date"><?= e(substr($item['created_at'] ?? '', 0, 10)) ?></span>
        </div>
        <?php if ($canView): ?>
        <div class="shop-inquiry-item__body" id="inquiry-body-<?= $inquiryId ?>">
            <div class="shop-inquiry-item__content"><?= e($item['content'] ?? '') ?></div>
            <?php if (!empty($item['reply'])): ?>
            <div class="shop-inquiry-item__reply">
                <div class="shop-inquiry-item__reply-label">판매자 답변</div>
                <div class="shop-inquiry-item__reply-text"><?= e($item['reply']) ?></div>
            </div>
            <?php endif; ?>
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

<!-- 문의 작성 모달 -->
<div class="inquiry-form-overlay" id="inquiryFormOverlay">
    <div class="inquiry-form-box">
        <h3>상품 문의</h3>
        <form class="Mublo-submit-form" data-target="/shop/inquiries/store" data-success-msg="문의가 등록되었습니다." data-success-reload="true">
            <input type="hidden" name="formData[goods_id]" value="<?= $goodsId ?>">
            <div class="mb-3">
                <label class="form-label">문의 유형</label>
                <select name="formData[inquiry_type]" class="form-select">
                    <option value="PRODUCT">상품문의</option>
                    <option value="STOCK">재고문의</option>
                    <option value="DELIVERY">배송문의</option>
                    <option value="OTHER">기타</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">제목 <span class="text-danger">*</span></label>
                <input type="text" name="formData[title]" class="form-control" required maxlength="100">
            </div>
            <div class="mb-3">
                <label class="form-label">내용 <span class="text-danger">*</span></label>
                <textarea name="formData[content]" class="form-control" rows="4" required></textarea>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" name="formData[is_secret]" value="1" id="isSecret">
                <label class="form-check-label" for="isSecret">비밀글로 등록</label>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">등록하기</button>
                <button type="button" class="btn btn-default" onclick="InquiryList.closeForm()">취소</button>
            </div>
        </form>
    </div>
</div>

<script>
const InquiryList = {
    toggle(id) {
        const body = document.getElementById(`inquiry-body-${id}`);
        if (body) body.classList.toggle('is-open');
    },
    openForm() {
        document.getElementById('inquiryFormOverlay').classList.add('is-open');
    },
    closeForm() {
        document.getElementById('inquiryFormOverlay').classList.remove('is-open');
    }
};

document.getElementById('inquiryFormOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) InquiryList.closeForm();
});
</script>
