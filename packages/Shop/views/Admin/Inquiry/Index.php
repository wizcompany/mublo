<?php
/**
 * 상품문의 관리 목록
 *
 * @var string $pageTitle
 * @var array $items
 * @var array $pagination
 * @var array $filters
 */
$currentStatus = $filters['inquiry_status'] ?? '';
$currentKeyword = $filters['keyword'] ?? '';

$totalItems = $pagination['totalItems'] ?? 0;
$currentPage = $pagination['currentPage'] ?? 1;
$totalPages = $pagination['totalPages'] ?? 1;
$perPage = $pagination['perPage'] ?? 20;

$statusLabels = ['WAITING' => '대기', 'REPLIED' => '답변완료', 'CLOSED' => '종료'];
$statusColors = ['WAITING' => 'warning', 'REPLIED' => 'success', 'CLOSED' => 'secondary'];
$typeLabels = ['PRODUCT' => '상품', 'STOCK' => '재고', 'DELIVERY' => '배송', 'OTHER' => '기타'];
?>
<div class="page-container">
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '상품문의 관리') ?></h3>
                <p class="text-muted mb-0">상품 문의를 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/shop/inquiries/create" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>문의 등록
                </a>
            </div>
        </div>
    </div>

    <form method="get" name="fsearch" id="fsearch" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/shop/inquiries">전체</a></span>
                    <span class="ov-num"><b><?= number_format($totalItems) ?></b> 건</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col-auto">
                        <select name="inquiry_status" class="form-select">
                            <option value="">전체 상태</option>
                            <option value="WAITING" <?= $currentStatus === 'WAITING' ? 'selected' : '' ?>>대기</option>
                            <option value="REPLIED" <?= $currentStatus === 'REPLIED' ? 'selected' : '' ?>>답변완료</option>
                            <option value="CLOSED" <?= $currentStatus === 'CLOSED' ? 'selected' : '' ?>>종료</option>
                        </select>
                    </div>
                    <div class="col col-xl-auto">
                        <div class="search-wrapper">
                            <label for="search_keyword" class="visually-hidden">검색</label>
                            <input type="text" name="keyword" id="search_keyword" class="form-control"
                                   placeholder="제목/내용/작성자"
                                   value="<?= htmlspecialchars($currentKeyword) ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if ($currentKeyword): ?>
                            <i class="bi bi-x-lg search-reset-icon" onclick="location.href='/admin/shop/inquiries'"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-default">
                            <i class="bi bi-search me-1"></i>검색
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="inquiryTable">
            <thead>
                <tr>
                    <th style="width:40px" class="text-center">
                        <input type="checkbox" class="form-check-input" id="checkAll">
                    </th>
                    <th style="width:50px" class="text-center">번호</th>
                    <th>제목</th>
                    <th style="width:120px">상품명</th>
                    <th style="width:90px">작성자</th>
                    <th style="width:70px" class="text-center">유형</th>
                    <th style="width:70px" class="text-center">비밀</th>
                    <th style="width:100px" class="text-center">상태</th>
                    <th style="width:100px" class="text-center">등록일</th>
                    <th style="width:90px" class="text-center">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="10" class="text-center py-4 text-muted">등록된 문의가 없습니다.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $idx => $item):
                    $num = $totalItems - (($currentPage - 1) * $perPage) - $idx;
                    $qid = (int)$item['inquiry_id'];
                    $status = $item['inquiry_status'] ?? 'WAITING';
                ?>
                <tr data-inquiry-id="<?= $qid ?>">
                    <td class="text-center">
                        <input type="checkbox" name="inquiry_ids[]" value="<?= $qid ?>" class="form-check-input list-check">
                    </td>
                    <td class="text-center"><?= $num ?></td>
                    <td>
                        <a href="/admin/shop/inquiries/<?= $qid ?>/edit" class="fw-semibold text-decoration-none">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                        <?php if ($item['is_secret']): ?>
                        <i class="bi bi-lock-fill text-muted ms-1" title="비밀글"></i>
                        <?php endif; ?>
                    </td>
                    <td class="text-truncate" style="max-width:120px"><?= htmlspecialchars($item['goods_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($item['author_name'] ?? '-') ?></td>
                    <td class="text-center"><span class="badge bg-light text-dark"><?= $typeLabels[$item['inquiry_type']] ?? $item['inquiry_type'] ?></span></td>
                    <td class="text-center">
                        <?php if ($item['is_secret']): ?>
                        <span class="badge bg-dark">비밀</span>
                        <?php else: ?>
                        <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <select name="status[<?= $qid ?>]" class="form-select form-select-sm">
                            <option value="WAITING" <?= $status === 'WAITING' ? 'selected' : '' ?>>대기</option>
                            <option value="REPLIED" <?= $status === 'REPLIED' ? 'selected' : '' ?>>답변완료</option>
                            <option value="CLOSED" <?= $status === 'CLOSED' ? 'selected' : '' ?>>종료</option>
                        </select>
                    </td>
                    <td class="text-center"><small><?= substr($item['created_at'], 0, 10) ?></small></td>
                    <td class="text-center">
                        <a href="/admin/shop/inquiries/<?= $qid ?>/edit"
                           class="btn btn-sm btn-default me-1" title="수정">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-default btn-delete-inquiry"
                                data-inquiry-id="<?= $qid ?>"
                                data-inquiry-title="<?= htmlspecialchars($item['title']) ?>"
                                title="삭제">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="row gx-2 justify-content-between align-items-center my-2">
        <div class="col-auto">
            <div class="d-flex gap-1">
                <button type="button" class="btn btn-default" onclick="batchModify()">
                    <i class="d-inline d-md-none bi bi-pencil-square"></i>
                    <span class="d-none d-md-inline">선택 수정</span>
                </button>
                <button type="button" class="btn btn-default" onclick="batchDelete()">
                    <i class="d-inline d-md-none bi bi-trash"></i>
                    <span class="d-none d-md-inline">선택 삭제</span>
                </button>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="col-auto d-none d-md-block"><?= $currentPage ?> / <?= $totalPages ?> 페이지</div>
        <div class="col-auto">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $pageNums = 10;
                    $blockStart = ((int)(($currentPage - 1) / $pageNums)) * $pageNums + 1;
                    $blockEnd = min($blockStart + $pageNums - 1, $totalPages);
                    $queryParams = array_filter(['inquiry_status' => $currentStatus, 'keyword' => $currentKeyword], fn($v) => $v !== '');
                    $buildUrl = function($page) use ($queryParams) {
                        return '/admin/shop/inquiries?' . http_build_query(array_merge($queryParams, ['page' => $page]));
                    };
                    ?>
                    <?php if ($blockStart > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= $buildUrl(1) ?>"><i class="bi bi-chevron-double-left"></i></a></li>
                    <li class="page-item"><a class="page-link" href="<?= $buildUrl($blockStart - 1) ?>"><i class="bi bi-chevron-left"></i></a></li>
                    <?php endif; ?>
                    <?php for ($i = $blockStart; $i <= $blockEnd; $i++): ?>
                    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>"><a class="page-link" href="<?= $buildUrl($i) ?>"><?= $i ?></a></li>
                    <?php endfor; ?>
                    <?php if ($blockEnd < $totalPages): ?>
                    <li class="page-item"><a class="page-link" href="<?= $buildUrl($blockEnd + 1) ?>"><i class="bi bi-chevron-right"></i></a></li>
                    <li class="page-item"><a class="page-link" href="<?= $buildUrl($totalPages) ?>"><i class="bi bi-chevron-double-right"></i></a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
(function() {
    document.getElementById('checkAll')?.addEventListener('change', function() {
        var checked = this.checked;
        document.querySelectorAll('.list-check').forEach(function(cb) { cb.checked = checked; });
    });
    document.getElementById('inquiryTable')?.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-delete-inquiry');
        if (!btn) return;
        var title = btn.dataset.inquiryTitle || '문의';
        if (!confirm('[' + title + '] 문의를 삭제하시겠습니까?')) return;
        MubloRequest.requestJson('/admin/shop/inquiries/delete', { inquiry_id: parseInt(btn.dataset.inquiryId) })
            .then(function() { location.reload(); });
    });
})();

function batchModify() {
    var checked = document.querySelectorAll('.list-check:checked');
    if (checked.length === 0) { alert('수정할 항목을 선택하세요.'); return; }
    var items = {};
    checked.forEach(function(cb) {
        var row = cb.closest('tr');
        items[cb.value] = { inquiry_status: row.querySelector('select[name^="status["]').value };
    });
    if (!confirm(checked.length + '건의 상태를 수정하시겠습니까?')) return;
    MubloRequest.requestJson('/admin/shop/inquiries/list-modify', { items: items })
        .then(function() { location.reload(); });
}

function batchDelete() {
    var checked = document.querySelectorAll('.list-check:checked');
    if (checked.length === 0) { alert('삭제할 항목을 선택하세요.'); return; }
    var ids = [];
    checked.forEach(function(cb) { ids.push(parseInt(cb.value)); });
    if (!confirm(checked.length + '건을 삭제하시겠습니까?')) return;
    MubloRequest.requestJson('/admin/shop/inquiries/list-delete', { inquiry_ids: ids })
        .then(function() { location.reload(); });
}
</script>
