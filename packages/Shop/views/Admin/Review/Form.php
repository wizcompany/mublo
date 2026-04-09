<?php
/**
 * 구매후기 등록/수정
 *
 * @var string $pageTitle
 * @var array|null $review
 */
$r = $review ?? [];
$isEdit = !empty($r['review_id']);
$btnLabel = $isEdit ? '수정' : '등록';
?>
<?= editor_css() ?>

<div class="page-container">
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle) ?></h3>
                <p class="text-muted mb-0">구매후기를 <?= $isEdit ? '수정' : '등록' ?>합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0 d-flex gap-2">
                <a href="/admin/shop/reviews" class="btn btn-secondary btn-sm">목록</a>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveReview()">
                    <i class="bi bi-check-lg me-1"></i><?= $btnLabel ?>
                </button>
            </div>
        </div>
    </div>

    <form id="reviewForm" enctype="multipart/form-data">
        <input type="hidden" name="formData[review_id]" value="<?= (int)($r['review_id'] ?? 0) ?>">

        <div class="mt-4">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">상품 ID</label>
                            <input type="number" name="formData[goods_id]" class="form-control"
                                   value="<?= (int)($r['goods_id'] ?? 0) ?>" <?= $isEdit ? 'readonly' : '' ?>>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">내용</label>
                            <?= editor_html('review_content', $r['content'] ?? '', ['height' => 400, 'name' => 'formData[content]']) ?>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="card mb-3">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-chat-dots me-2 text-pastel-blue"></i>관리자 답변
                            <?php if (!empty($r['admin_reply_at'])): ?>
                            <small class="text-muted fw-normal ms-2"><?= date('Y-m-d H:i', strtotime($r['admin_reply_at'])) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <textarea id="admin_reply" class="form-control" rows="4"
                                      placeholder="답변을 입력하세요..."><?= htmlspecialchars($r['admin_reply'] ?? '') ?></textarea>
                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-primary" onclick="saveReply()">
                                    <i class="bi bi-check-lg me-1"></i>답변 저장
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <?php if ($isEdit): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">주문번호</label>
                            <div><?= htmlspecialchars($r['order_no'] ?? '-') ?></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">평점</label>
                            <div id="starRating"></div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">공개</label>
                                <select name="formData[is_visible]" class="form-select">
                                    <option value="1" <?= ((int)($r['is_visible'] ?? 1)) === 1 ? 'selected' : '' ?>>공개</option>
                                    <option value="0" <?= ((int)($r['is_visible'] ?? 1)) === 0 ? 'selected' : '' ?>>비공개</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label fw-semibold">베스트</label>
                                <select name="formData[is_best]" class="form-select">
                                    <option value="0" <?= ((int)($r['is_best'] ?? 0)) === 0 ? 'selected' : '' ?>>일반</option>
                                    <option value="1" <?= ((int)($r['is_best'] ?? 0)) === 1 ? 'selected' : '' ?>>베스트</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="form-text">등록일: <?= substr($r['created_at'] ?? '', 0, 16) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sticky-act mt-3 sticky-status">
                <a href="/admin/shop/reviews" class="btn btn-secondary me-2">목록</a>
                <button type="button" class="btn btn-primary" onclick="saveReview()">
                    <i class="bi bi-check-lg me-1"></i><?= $btnLabel ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?= editor_js() ?>
<script src="/assets/lib/star/star.js"></script>

<script>
new StarRating('#starRating', {
    mode: 'input', maxScore: 5, maxStars: 5, step: 1,
    rating: <?= (int)($r['rating'] ?? 5) ?>,
    inputName: 'formData[rating]', showScore: true, starSize: '2rem'
});

function saveReview() {
    if (typeof MubloEditor !== 'undefined') {
        MubloEditor.get('review_content')?.sync();
    }
    var fd = new FormData(document.getElementById('reviewForm'));
    MubloRequest.sendRequest({
        method: 'POST', url: '/admin/shop/reviews/store',
        payloadType: MubloRequest.PayloadType.FORM, data: fd,
    }).then(function() { location.href = '/admin/shop/reviews'; });
}

<?php if ($isEdit): ?>
function saveReply() {
    MubloRequest.requestJson('/admin/shop/reviews/reply', {
        review_id: <?= (int)$r['review_id'] ?>,
        admin_reply: document.getElementById('admin_reply').value.trim()
    }).then(function() { alert('답변이 저장되었습니다.'); location.reload(); });
}
<?php endif; ?>
</script>
