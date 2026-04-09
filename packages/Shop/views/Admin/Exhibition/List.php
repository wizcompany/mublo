<?php
/**
 * 기획전 관리 목록
 *
 * @var string $pageTitle
 * @var array  $items
 * @var array  $pagination
 * @var array  $filters
 */
$currentActive  = $filters['is_active'] ?? '';
$currentKeyword = $filters['keyword'] ?? '';

$totalItems  = $pagination['totalItems'] ?? 0;
$currentPage = $pagination['currentPage'] ?? 1;
$totalPages  = $pagination['totalPages'] ?? 1;
$perPage     = $pagination['perPage'] ?? 20;
?>
<div class="page-container">
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '기획전 관리') ?></h3>
                <p class="text-muted mb-0">기획전을 등록하고 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/shop/exhibitions/create" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>기획전 등록
                </a>
            </div>
        </div>
    </div>

    <form method="get" name="fsearch" id="fsearch" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/shop/exhibitions">전체</a></span>
                    <span class="ov-num"><b><?= number_format($totalItems) ?></b> 건</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col-auto">
                        <select name="is_active" class="form-select">
                            <option value="">전체 상태</option>
                            <option value="1" <?= $currentActive === '1' ? 'selected' : '' ?>>활성</option>
                            <option value="0" <?= $currentActive === '0' ? 'selected' : '' ?>>비활성</option>
                        </select>
                    </div>
                    <div class="col col-xl-auto">
                        <div class="search-wrapper">
                            <label for="search_keyword" class="visually-hidden">검색</label>
                            <input type="text" name="keyword" id="search_keyword" class="form-control"
                                   placeholder="기획전명"
                                   value="<?= htmlspecialchars($currentKeyword) ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if ($currentKeyword): ?>
                            <i class="bi bi-x-lg search-reset-icon" onclick="location.href='/admin/shop/exhibitions'"></i>
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
                    <th style="width:50px" class="text-center">번호</th>
                    <th>기획전명</th>
                    <th style="width:120px" class="text-center">시작일</th>
                    <th style="width:120px" class="text-center">종료일</th>
                    <th style="width:80px" class="text-center">상태</th>
                    <th style="width:70px" class="text-center">정렬</th>
                    <th style="width:100px" class="text-center">등록일</th>
                    <th style="width:90px" class="text-center">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">등록된 기획전이 없습니다.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $idx => $item):
                    $num = $totalItems - (($currentPage - 1) * $perPage) - $idx;
                    $eid = (int) $item['exhibition_id'];
                    $now = time();
                    $isOngoing = $item['is_active']
                        && (empty($item['start_date']) || strtotime($item['start_date']) <= $now)
                        && (empty($item['end_date'])   || strtotime($item['end_date']) >= $now);
                ?>
                <tr>
                    <td class="text-center"><?= $num ?></td>
                    <td>
                        <a href="/admin/shop/exhibitions/<?= $eid ?>/edit" class="fw-semibold text-decoration-none">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                        <?php if (!empty($item['slug'])): ?>
                        <span class="text-muted small ms-1">/<?= htmlspecialchars($item['slug']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><small><?= $item['start_date'] ? substr($item['start_date'], 0, 10) : '-' ?></small></td>
                    <td class="text-center"><small><?= $item['end_date'] ? substr($item['end_date'], 0, 10) : '-' ?></small></td>
                    <td class="text-center">
                        <?php if ($isOngoing): ?>
                        <span class="badge bg-success">진행 중</span>
                        <?php elseif (!$item['is_active']): ?>
                        <span class="badge bg-secondary">비활성</span>
                        <?php else: ?>
                        <span class="badge bg-warning text-dark">대기/종료</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= (int) $item['sort_order'] ?></td>
                    <td class="text-center"><small><?= substr($item['created_at'], 0, 10) ?></small></td>
                    <td class="text-center">
                        <a href="/admin/shop/exhibitions/<?= $eid ?>/edit"
                           class="btn btn-sm btn-default me-1" title="수정">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-default btn-delete"
                                data-id="<?= $eid ?>" title="삭제">
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
    <div class="row justify-content-end my-2">
        <div class="col-auto">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                    $pageNums   = 10;
                    $blockStart = ((int)(($currentPage - 1) / $pageNums)) * $pageNums + 1;
                    $blockEnd   = min($blockStart + $pageNums - 1, $totalPages);
                    $qp         = array_filter(['is_active' => $currentActive, 'keyword' => $currentKeyword], fn($v) => $v !== '');
                    $buildUrl   = fn($p) => '/admin/shop/exhibitions?' . http_build_query(array_merge($qp, ['page' => $p]));
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
(function() {
    document.querySelector('table')?.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-delete');
        if (!btn) return;
        if (!confirm('이 기획전을 삭제하시겠습니까?\n연결된 아이템도 함께 삭제됩니다.')) return;
        MubloRequest.requestJson('/admin/shop/exhibitions/delete', { exhibition_id: parseInt(btn.dataset.id) })
            .then(function() { location.reload(); });
    });
})();
</script>
