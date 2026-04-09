<?php
/**
 * 상품문의 등록/수정
 *
 * @var string $pageTitle
 * @var array|null $inquiry
 */
$q = $inquiry ?? [];
$isEdit = !empty($q['inquiry_id']);
$btnLabel = $isEdit ? '수정' : '등록';
$statusLabels = ['WAITING' => '대기', 'REPLIED' => '답변완료', 'CLOSED' => '종료'];
$statusColors = ['WAITING' => 'warning', 'REPLIED' => 'success', 'CLOSED' => 'secondary'];
$typeLabels = ['PRODUCT' => '상품', 'STOCK' => '재고', 'DELIVERY' => '배송', 'OTHER' => '기타'];
?>
<?= editor_css() ?>

<div class="page-container">
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle) ?></h3>
                <p class="text-muted mb-0">상품 문의를 <?= $isEdit ? '수정' : '등록' ?>합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0 d-flex gap-2">
                <a href="/admin/shop/inquiries" class="btn btn-secondary btn-sm">목록</a>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveInquiry()">
                    <i class="bi bi-check-lg me-1"></i><?= $btnLabel ?>
                </button>
            </div>
        </div>
    </div>

    <form id="inquiryForm" enctype="multipart/form-data">
        <input type="hidden" name="formData[inquiry_id]" value="<?= (int)($q['inquiry_id'] ?? 0) ?>">

        <div class="mt-4">
            <div class="row g-4">
                <div class="col-lg-8">
                    <?php if (!$isEdit): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">상품 ID</label>
                            <input type="number" name="formData[goods_id]" class="form-control" value="0">
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">제목</label>
                            <input type="text" name="formData[title]" class="form-control"
                                   value="<?= htmlspecialchars($q['title'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">내용</label>
                            <?= editor_html('inquiry_content', $q['content'] ?? '', ['height' => 400, 'name' => 'formData[content]']) ?>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="card mb-3">
                        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                            <i class="bi bi-chat-dots me-2 text-pastel-blue"></i>관리자 답변
                            <?php if (!empty($q['replied_at'])): ?>
                            <small class="text-muted fw-normal ms-2"><?= date('Y-m-d H:i', strtotime($q['replied_at'])) ?></small>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <textarea id="reply_content" class="form-control" rows="5"
                                      placeholder="답변을 입력하세요..."><?= htmlspecialchars($q['reply'] ?? '') ?></textarea>
                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-sm btn-primary" onclick="saveAnswer()">
                                    <i class="bi bi-check-lg me-1"></i>답변 저장
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">작성자</label>
                            <?php if ($isEdit): ?>
                            <div><?= htmlspecialchars($q['author_name'] ?? '-') ?></div>
                            <?php else: ?>
                            <input type="text" name="formData[author_name]" class="form-control" placeholder="작성자명">
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">문의 유형</label>
                            <?php if ($isEdit): ?>
                            <div><span class="badge bg-light text-dark"><?= $typeLabels[$q['inquiry_type'] ?? ''] ?? ($q['inquiry_type'] ?? '-') ?></span></div>
                            <?php else: ?>
                            <select name="formData[inquiry_type]" class="form-select">
                                <?php foreach ($typeLabels as $val => $label): ?>
                                <option value="<?= $val ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">비밀글</label>
                            <select name="formData[is_secret]" class="form-select">
                                <option value="0" <?= ((int)($q['is_secret'] ?? 0)) === 0 ? 'selected' : '' ?>>공개</option>
                                <option value="1" <?= ((int)($q['is_secret'] ?? 0)) === 1 ? 'selected' : '' ?>>비밀글</option>
                            </select>
                        </div>
                    </div>

                    <?php if ($isEdit): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <label class="form-label fw-semibold">상태</label>
                            <div>
                                <?php $st = $q['inquiry_status'] ?? 'WAITING'; ?>
                                <span class="badge bg-<?= $statusColors[$st] ?? 'secondary' ?>"><?= $statusLabels[$st] ?? $st ?></span>
                            </div>
                            <div class="form-text mt-2">등록일: <?= substr($q['created_at'] ?? '', 0, 16) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sticky-act mt-3 sticky-status">
                <a href="/admin/shop/inquiries" class="btn btn-secondary me-2">목록</a>
                <button type="button" class="btn btn-primary" onclick="saveInquiry()">
                    <i class="bi bi-check-lg me-1"></i><?= $btnLabel ?>
                </button>
            </div>
        </div>
    </form>
</div>

<?= editor_js() ?>

<script>
function saveInquiry() {
    if (typeof MubloEditor !== 'undefined') {
        MubloEditor.get('inquiry_content')?.sync();
    }
    var fd = new FormData(document.getElementById('inquiryForm'));
    MubloRequest.sendRequest({
        method: 'POST', url: '/admin/shop/inquiries/store',
        payloadType: MubloRequest.PayloadType.FORM, data: fd,
    }).then(function() { location.href = '/admin/shop/inquiries'; });
}

<?php if ($isEdit): ?>
function saveAnswer() {
    MubloRequest.requestJson('/admin/shop/inquiries/answer', {
        inquiry_id: <?= (int)$q['inquiry_id'] ?>,
        reply: document.getElementById('reply_content').value.trim()
    }).then(function() { alert('답변이 저장되었습니다.'); location.reload(); });
}
<?php endif; ?>
</script>
