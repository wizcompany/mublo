<?php
/**
 * 구매후기 관리 목록
 *
 * @var string $pageTitle
 * @var array $items
 * @var array $pagination
 * @var array $filters
 */
$currentVisible = $filters['is_visible'] ?? '';
$currentKeyword = $filters['keyword'] ?? '';

$totalItems = $pagination['totalItems'] ?? 0;
$currentPage = $pagination['currentPage'] ?? 1;
$totalPages = $pagination['totalPages'] ?? 1;
$perPage = $pagination['perPage'] ?? 20;
?>
<div class="page-container">
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '구매후기 관리') ?></h3>
                <p class="text-muted mb-0">구매후기를 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/shop/reviews/create" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>후기 등록
                </a>
            </div>
        </div>
    </div>

    <form method="get" name="fsearch" id="fsearch" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/shop/reviews">전체</a></span>
                    <span class="ov-num"><b><?= number_format($totalItems) ?></b> 건</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col-auto">
                        <select name="is_visible" class="form-select">
                            <option value="">전체 상태</option>
                            <option value="1" <?= $currentVisible === '1' ? 'selected' : '' ?>>공개</option>
                            <option value="0" <?= $currentVisible === '0' ? 'selected' : '' ?>>비공개</option>
                        </select>
                    </div>
                    <div class="col col-xl-auto">
                        <div class="search-wrapper">
                            <label for="search_keyword" class="visually-hidden">검색</label>
                            <input type="text" name="keyword" id="search_keyword" class="form-control"
                                   placeholder="내용/상품명"
                                   value="<?= htmlspecialchars($currentKeyword) ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if ($currentKeyword): ?>
                            <i class="bi bi-x-lg search-reset-icon" onclick="location.href='/admin/shop/reviews'"></i>
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
        <table class="table table-hover align-middle mb-0" id="reviewTable">
            <thead>
                <tr>
                    <th style="width:40px" class="text-center">
                        <input type="checkbox" class="form-check-input" id="checkAll">
                    </th>
                    <th style="width:50px" class="text-center">번호</th>
                    <th>상품명</th>
                    <th style="width:90px" class="text-center">평점</th>
                    <th style="width:70px" class="text-center">유형</th>
                    <th style="width:90px" class="text-center">공개</th>
                    <th style="width:90px" class="text-center">베스트</th>
                    <th style="width:70px" class="text-center">답변</th>
                    <th style="width:100px" class="text-center">등록일</th>
                    <th style="width:90px" class="text-center">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="10" class="text-center py-4 text-muted">등록된 후기가 없습니다.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $idx => $item):
                    $num = $totalItems - (($currentPage - 1) * $perPage) - $idx;
                    $rid = (int)$item['review_id'];
                    $rating = (int)$item['rating'];
                ?>
                <tr data-review-id="<?= $rid ?>">
                    <td class="text-center">
                        <input type="checkbox" name="review_ids[]" value="<?= $rid ?>" class="form-check-input list-check">
                    </td>
                    <td class="text-center"><?= $num ?></td>
                    <td>
                        <a href="/admin/shop/reviews/<?= $rid ?>/edit" class="fw-semibold text-decoration-none">
                            <?= htmlspecialchars($item['goods_name'] ?? '상품 삭제됨') ?>
                        </a>
                        <div class="text-muted small"><?= mb_substr(strip_tags($item['content'] ?? ''), 0, 50) ?></div>
                    </td>
                    <td class="text-center"><span class="star-display" data-rating="<?= $rating ?>"></span></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $item['review_type'] === 'PHOTO' ? 'info' : 'secondary' ?>">
                            <?= $item['review_type'] === 'PHOTO' ? '포토' : '텍스트' ?>
                        </span>
                    </td>
                    <td class="text-center">
                        <select name="is_visible[<?= $rid ?>]" class="form-select form-select-sm">
                            <option value="1" <?= $item['is_visible'] ? 'selected' : '' ?>>공개</option>
                            <option value="0" <?= !$item['is_visible'] ? 'selected' : '' ?>>비공개</option>
                        </select>
                    </td>
                    <td class="text-center">
                        <select name="is_best[<?= $rid ?>]" class="form-select form-select-sm">
                            <option value="1" <?= $item['is_best'] ? 'selected' : '' ?>>베스트</option>
                            <option value="0" <?= !$item['is_best'] ? 'selected' : '' ?>>일반</option>
                        </select>
                    </td>
                    <td class="text-center">
                        <?php if (!empty($item['admin_reply'])): ?>
                        <span class="badge bg-success">완료</span>
                        <?php else: ?>
                        <span class="badge bg-secondary">미답변</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><small><?= substr($item['created_at'], 0, 10) ?></small></td>
                    <td class="text-center">
                        <a href="/admin/shop/reviews/<?= $rid ?>/edit"
                           class="btn btn-sm btn-default me-1" title="수정">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-default btn-delete-review"
                                data-review-id="<?= $rid ?>" title="삭제">
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
                    $queryParams = array_filter(['is_visible' => $currentVisible, 'keyword' => $currentKeyword], fn($v) => $v !== '');
                    $buildUrl = function($page) use ($queryParams) {
                        return '/admin/shop/reviews?' . http_build_query(array_merge($queryParams, ['page' => $page]));
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

<script src="/assets/lib/star/star.js"></script>
<script>
(function() {
    document.querySelectorAll('.star-display').forEach(function(el) {
        new StarRating(el, { mode: 'display', maxScore: 5, maxStars: 5, rating: parseInt(el.dataset.rating) || 0, starSize: '0.9rem' });
    });
    document.getElementById('checkAll')?.addEventListener('change', function() {
        var checked = this.checked;
        document.querySelectorAll('.list-check').forEach(function(cb) { cb.checked = checked; });
    });
    document.getElementById('reviewTable')?.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-delete-review');
        if (!btn) return;
        if (!confirm('이 후기를 삭제하시겠습니까?')) return;
        MubloRequest.requestJson('/admin/shop/reviews/delete', { review_id: parseInt(btn.dataset.reviewId) })
            .then(function() { location.reload(); });
    });
})();

function batchModify() {
    var checked = document.querySelectorAll('.list-check:checked');
    if (checked.length === 0) { alert('수정할 항목을 선택하세요.'); return; }
    var items = {};
    checked.forEach(function(cb) {
        var row = cb.closest('tr');
        items[cb.value] = {
            is_visible: parseInt(row.querySelector('select[name^="is_visible["]').value),
            is_best: parseInt(row.querySelector('select[name^="is_best["]').value)
        };
    });
    if (!confirm(checked.length + '건의 상태를 수정하시겠습니까?')) return;
    MubloRequest.requestJson('/admin/shop/reviews/list-modify', { items: items })
        .then(function() { location.reload(); });
}

function batchDelete() {
    var checked = document.querySelectorAll('.list-check:checked');
    if (checked.length === 0) { alert('삭제할 항목을 선택하세요.'); return; }
    var ids = [];
    checked.forEach(function(cb) { ids.push(parseInt(cb.value)); });
    if (!confirm(checked.length + '건을 삭제하시겠습니까?')) return;
    MubloRequest.requestJson('/admin/shop/reviews/list-delete', { review_ids: ids })
        .then(function() { location.reload(); });
}
</script>
