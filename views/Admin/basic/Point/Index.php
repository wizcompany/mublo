<?php
/**
 * Admin Point - Index
 *
 * 포인트 내역 목록
 *
 * @var string $pageTitle
 * @var array $items
 * @var array $pagination
 * @var array $currentFilters
 * @var array $sourceTypes
 */
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '포인트 내역') ?></h3>
                <p class="text-muted mb-0">회원 포인트 변경 내역을 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/point/adjust" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-1"></i>포인트 수동 조정
                </a>
            </div>
        </div>
    </div>

    <!-- 검색/필터 영역 -->
    <form method="get" name="fsearch" id="fsearch" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/point">전체</a></span>
                    <span class="ov-num"><b><?= number_format($pagination['totalItems'] ?? 0) ?></b> 건</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2 gy-2">
                    <div class="col-auto">
                        <select name="source_type" class="form-select">
                            <option value="">구분 전체</option>
                            <?php foreach ($sourceTypes as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($currentFilters['source_type'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <input type="date" name="start_date" class="form-control"
                               value="<?= htmlspecialchars($currentFilters['start_date'] ?? '') ?>"
                               placeholder="시작일">
                    </div>
                    <div class="col-auto">
                        <input type="date" name="end_date" class="form-control"
                               value="<?= htmlspecialchars($currentFilters['end_date'] ?? '') ?>"
                               placeholder="종료일">
                    </div>
                    <div class="col-auto">
                        <input type="number" name="member_id" class="form-control" style="width:120px"
                               value="<?= $currentFilters['member_id'] ?? '' ?>"
                               placeholder="회원 ID">
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

    <!-- 포인트 내역 테이블 -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:60px">No</th>
                    <th style="width:100px">회원</th>
                    <th style="width:100px">변동</th>
                    <th style="width:100px">변경 후</th>
                    <th style="width:80px">구분</th>
                    <th>내용</th>
                    <th style="width:160px; white-space:nowrap">일시</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox me-2"></i>포인트 내역이 없습니다.
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= $item['log_id'] ?></td>
                        <td>
                            <a href="/admin/member/edit/<?= $item['member_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars($item['user_id']) ?>
                            </a>
                        </td>
                        <td class="<?= $item['amount'] > 0 ? 'text-primary' : 'text-danger' ?>">
                            <strong><?= $item['amount'] > 0 ? '+' : '' ?><?= number_format($item['amount']) ?></strong>
                        </td>
                        <td><?= number_format($item['balance_after']) ?></td>
                        <td>
                            <?php
                            $badgeClass = match ($item['source_type']) {
                                'plugin' => 'bg-info',
                                'package' => 'bg-success',
                                'admin' => 'bg-warning',
                                'system' => 'bg-secondary',
                                default => 'bg-light',
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>">
                                <?= $sourceTypes[$item['source_type']] ?? $item['source_type'] ?>
                            </span>
                        </td>
                        <td>
                            <span title="<?= htmlspecialchars($item['source_name'] . ' / ' . $item['action']) ?>">
                                <?= htmlspecialchars($item['message']) ?>
                            </span>
                            <?php if ($item['admin_id']): ?>
                            <span class="badge bg-secondary ms-1">관리자</span>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap">
                            <small class="text-muted"><?= $item['created_at'] ?></small>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 페이지네이션 -->
    <?php if (!empty($pagination) && ($pagination['totalPages'] ?? 1) > 1): ?>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="text-muted">
            <?= $pagination['currentPage'] ?? 1 ?> / <?= $pagination['totalPages'] ?? 1 ?> 페이지
        </div>
        <nav>
            <?= $this->pagination($pagination) ?>
        </nav>
    </div>
    <?php endif; ?>
</div>
