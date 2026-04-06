<?php
/**
 * 상품정보 템플릿 목록
 *
 * @var string $pageTitle
 * @var array $items
 * @var array $pagination
 * @var array $filters
 */
$currentStatus = $filters['status'] ?? '';
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
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle) ?></h3>
                <p class="text-muted mb-0">상품 상세페이지에 공통으로 출력할 상품정보를 등록할 수 있습니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/shop/info-templates/create" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>템플릿 등록
                </a>
            </div>
        </div>
    </div>

    <form method="get" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/shop/info-templates">전체</a></span>
                    <span class="ov-num"><b><?= number_format($totalItems) ?></b> 개</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col-auto">
                        <select name="status" class="form-select">
                            <option value="">전체 상태</option>
                            <option value="Y" <?= $currentStatus === 'Y' ? 'selected' : '' ?>>사용</option>
                            <option value="N" <?= $currentStatus === 'N' ? 'selected' : '' ?>>미사용</option>
                        </select>
                    </div>
                    <div class="col col-xl-auto">
                        <div class="search-wrapper">
                            <label for="search_keyword" class="visually-hidden">검색</label>
                            <input type="text" name="keyword" id="search_keyword" class="form-control"
                                   placeholder="제목/탭명 검색"
                                   value="<?= htmlspecialchars($currentKeyword) ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if ($currentKeyword): ?>
                            <i class="bi bi-x-lg search-reset-icon" onclick="location.href='/admin/shop/info-templates'"></i>
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
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="text-center" style="width:50px">번호</th>
                    <th class="text-center" style="width:100px">탭명</th>
                    <th>제목</th>
                    <th class="text-center" style="width:120px">카테고리</th>
                    <th class="text-center" style="width:70px">상태</th>
                    <th class="text-center" style="width:60px">정렬</th>
                    <th class="text-center" style="width:90px">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">등록된 템플릿이 없습니다.</td></tr>
                <?php else: ?>
                <?php foreach ($items as $idx => $item):
                    $num = $totalItems - (($currentPage - 1) * $perPage) - $idx;
                ?>
                <tr>
                    <td class="text-center"><?= $num ?></td>
                    <td class="text-center"><?= htmlspecialchars($item['tab_name'] ?: '-') ?></td>
                    <td>
                        <a href="/admin/shop/info-templates/<?= $item['template_id'] ?>/edit" class="fw-semibold text-decoration-none">
                            <?= htmlspecialchars($item['subject']) ?>
                        </a>
                    </td>
                    <td class="text-center">
                        <span class="text-muted"><?= htmlspecialchars($item['category_name'] ?? '전체') ?></span>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?= $item['status'] === 'Y' ? 'success' : 'secondary' ?>">
                            <?= $item['status'] === 'Y' ? '사용' : '미사용' ?>
                        </span>
                    </td>
                    <td class="text-center"><?= (int) $item['sort_order'] ?></td>
                    <td class="text-center">
                        <a href="/admin/shop/info-templates/<?= $item['template_id'] ?>/edit"
                           class="btn btn-sm btn-default me-1" title="수정">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-default btn-delete"
                                data-id="<?= $item['template_id'] ?>"
                                data-name="<?= htmlspecialchars($item['subject']) ?>"
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

    <?php if ($totalPages > 1): ?>
    <div class="row gx-2 justify-content-between align-items-center my-2">
        <div class="col-auto d-none d-md-block">
            <?= $currentPage ?> / <?= $totalPages ?> 페이지
        </div>
        <div class="col-auto">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $pageNums = 10;
                    $blockStart = ((int)(($currentPage - 1) / $pageNums)) * $pageNums + 1;
                    $blockEnd = min($blockStart + $pageNums - 1, $totalPages);
                    $queryParams = array_filter(['status' => $currentStatus, 'keyword' => $currentKeyword], fn($v) => $v !== '');
                    $buildUrl = function($page) use ($queryParams) {
                        return '/admin/shop/info-templates?' . http_build_query(array_merge($queryParams, ['page' => $page]));
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
    </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-delete');
    if (!btn) return;
    var id = btn.dataset.id;
    var name = btn.dataset.name;
    if (!confirm('[' + name + '] 템플릿을 삭제하시겠습니까?')) return;
    MubloRequest.requestJson('/admin/shop/info-templates/delete', { template_id: parseInt(id) })
        .then(function() { location.reload(); });
});
</script>
