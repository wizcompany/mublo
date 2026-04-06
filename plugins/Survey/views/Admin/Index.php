<?php
/**
 * @var string $pageTitle
 * @var array  $items
 * @var array  $pagination
 * @var array  $search
 * @var array  $statuses
 */
$keyword     = $search['keyword'] ?? '';
$totalItems  = $pagination['totalItems'] ?? 0;
$currentPage = $pagination['currentPage'] ?? 1;
$totalPages  = $pagination['totalPages'] ?? 1;

$statusBadge = [
    'draft'  => 'secondary',
    'active' => 'success',
    'closed' => 'dark',
];
$statusLabel = [
    'draft'  => '초안',
    'active' => '진행중',
    'closed' => '종료',
];
?>
<div class="page-container">
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle) ?></h3>
                <p class="text-muted mb-0">설문을 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/survey/surveys/create" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-lg me-1"></i>새 설문
                </a>
            </div>
        </div>
    </div>

    <form method="get" class="mt-4 mb-2">
        <div class="row align-items-center gy-2">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/survey/surveys">전체</a></span>
                    <span class="ov-num"><b><?= number_format($totalItems) ?></b> 건</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col">
                        <div class="search-wrapper">
                            <input type="text" name="keyword" class="form-control"
                                   placeholder="설문 제목 검색"
                                   value="<?= htmlspecialchars($keyword) ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if ($keyword): ?>
                            <i class="bi bi-x-lg search-reset-icon"
                               onclick="location.href='/admin/survey/surveys'"></i>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-default">검색</button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>제목</th>
                    <th class="text-center" style="width:90px">상태</th>
                    <th class="text-center" style="width:80px">응답</th>
                    <th style="width:200px">기간</th>
                    <th class="text-end" style="width:160px">관리</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($items)): ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-5">등록된 설문이 없습니다.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <a href="/admin/survey/surveys/<?= $item['survey_id'] ?>/edit"
                           class="text-dark fw-medium text-decoration-none">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                        <?php if ($item['description']): ?>
                        <div class="text-muted small text-truncate" style="max-width:400px">
                            <?= htmlspecialchars($item['description']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-<?= $statusBadge[$item['status']] ?? 'secondary' ?>">
                            <?= $statusLabel[$item['status']] ?? $item['status'] ?>
                        </span>
                    </td>
                    <td class="text-center text-muted small">
                        <?= $item['response_limit'] > 0
                            ? '/ ' . number_format($item['response_limit'])
                            : '무제한' ?>
                    </td>
                    <td class="text-muted small">
                        <?php if ($item['start_at'] || $item['end_at']): ?>
                            <?= $item['start_at'] ? substr($item['start_at'], 0, 10) : '∞' ?>
                            ~
                            <?= $item['end_at']   ? substr($item['end_at'],   0, 10) : '∞' ?>
                        <?php else: ?>
                            <span class="text-muted">기간 없음</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="/admin/survey/surveys/<?= $item['survey_id'] ?>/result"
                           class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-bar-chart-line"></i> 결과
                        </a>
                        <a href="/admin/survey/surveys/<?= $item['survey_id'] ?>/edit"
                           class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button type="button" class="btn btn-sm btn-outline-danger"
                                data-delete-id="<?= $item['survey_id'] ?>"
                                data-delete-title="<?= htmlspecialchars($item['title']) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1):
        $pageNums   = 10;
        $blockStart = ((int)(($currentPage - 1) / $pageNums)) * $pageNums + 1;
        $blockEnd   = min($blockStart + $pageNums - 1, $totalPages);
        $baseUrl    = '/admin/survey/surveys?' . ($keyword ? 'keyword=' . urlencode($keyword) . '&' : '') . 'page=';
    ?>
    <div class="row gx-2 justify-content-center my-3">
        <div class="col-auto">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php if ($blockStart > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $baseUrl ?>1"><i class="bi bi-chevron-double-left"></i></a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="<?= $baseUrl ?><?= $blockStart - 1 ?>"><i class="bi bi-chevron-left"></i></a>
                    </li>
                    <?php endif; ?>
                    <?php for ($i = $blockStart; $i <= $blockEnd; $i++): ?>
                    <li class="page-item <?= $i === $currentPage ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $baseUrl ?><?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($blockEnd < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="<?= $baseUrl ?><?= $blockEnd + 1 ?>"><i class="bi bi-chevron-right"></i></a>
                    </li>
                    <li class="page-item">
                        <a class="page-link" href="<?= $baseUrl ?><?= $totalPages ?>"><i class="bi bi-chevron-double-right"></i></a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('[data-delete-id]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var id    = this.dataset.deleteId;
        var title = this.dataset.deleteTitle;
        if (!confirm('[' + title + '] 설문과 모든 응답 데이터를 삭제합니다. 계속하시겠습니까?')) return;

        MubloRequest.requestJson('/admin/survey/surveys/' + id + '/delete', {}, { method: 'POST' })
            .then(function () { location.reload(); });
    });
});
</script>
