<?php
/**
 * 약관/정책 관리 - 목록
 *
 * @var string $pageTitle
 * @var \Mublo\Entity\Member\Policy[] $policies
 * @var array $pagination
 * @var array $policyTypeOptions
 * @var array $currentFilters
 */

$policyTypeColors = [
    'terms' => 'primary',
    'privacy' => 'danger',
    'marketing' => 'success',
    'location' => 'info',
    'custom' => 'secondary',
];
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '약관/정책 관리') ?></h3>
                <p class="text-muted mb-0">이용약관, 개인정보처리방침 등 정책/약관을 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/policy/create" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>정책 등록
                </a>
            </div>
        </div>
    </div>

    <!-- 검색 영역 -->
    <form method="get" name="fsearch" id="fsearch" class="mt-4 mb-2">
        <div class="row align-items-center gy-2 gy-xl-0">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/policy">전체</a></span>
                    <span class="ov-num"><b><?= number_format($pagination['totalItems'] ?? 0) ?></b> 건</span>
                </span>
            </div>
            <div class="col col-xl-auto ms-xl-auto">
                <div class="row gx-2">
                    <div class="col-auto">
                        <select name="policy_type" class="form-select">
                            <option value="">타입: 전체</option>
                            <?php foreach ($policyTypeOptions as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($currentFilters['policy_type'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-auto">
                        <select name="is_active" class="form-select">
                            <option value="">상태: 전체</option>
                            <option value="1" <?= ($currentFilters['is_active'] ?? '') === '1' ? 'selected' : '' ?>>활성</option>
                            <option value="0" <?= ($currentFilters['is_active'] ?? '') === '0' ? 'selected' : '' ?>>비활성</option>
                        </select>
                    </div>
                    <div class="col col-xl-auto">
                        <div class="search-wrapper">
                            <label for="search_keyword" class="visually-hidden">검색</label>
                            <input type="text" name="keyword" id="search_keyword" class="form-control"
                                   placeholder="제목 또는 슬러그 검색"
                                   value="<?= htmlspecialchars($currentFilters['keyword'] ?? '') ?>">
                            <i class="bi bi-search search-icon"></i>
                            <?php if (!empty($currentFilters['keyword'])): ?>
                            <i class="bi bi-x-lg search-reset-icon" onclick="location.href='/admin/policy'"></i>
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

    <!-- 정책 목록 -->
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:60px" class="text-center">순서</th>
                    <th style="width:100px">타입</th>
                    <th>제목</th>
                    <th style="width:150px">슬러그</th>
                    <th style="width:70px">버전</th>
                    <th style="width:70px" class="text-center" title="필수 동의 여부">필수</th>
                    <th style="width:70px" class="text-center" title="회원가입 시 출력 여부">회원가입</th>
                    <th style="width:70px" class="text-center">상태</th>
                    <th style="width:90px">관리</th>
                </tr>
            </thead>
            <tbody id="sortable-tbody">
                <?php if (empty($policies)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">
                            등록된 정책이 없습니다.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($policies as $policy): ?>
                        <?php
                        $policyId = $policy->getPolicyId();
                        $policyType = $policy->getPolicyType()->value;
                        ?>
                        <tr data-policy-id="<?= $policyId ?>">
                            <td class="text-center">
                                <span class="drag-handle" style="cursor:move" title="드래그하여 순서 변경">
                                    <i class="bi bi-arrows-move text-muted"></i>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $policyTypeColors[$policyType] ?? 'secondary' ?>">
                                    <?= htmlspecialchars($policy->getPolicyTypeLabel()) ?>
                                </span>
                            </td>
                            <td>
                                <a href="/admin/policy/edit/<?= $policyId ?>" class="text-decoration-none">
                                    <strong><?= htmlspecialchars($policy->getPolicyTitle()) ?></strong>
                                </a>
                                <?php if ($policy->isEssentialType()): ?>
                                    <i class="bi bi-shield-fill-check text-primary ms-1" title="필수 약관"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <code class="small"><?= htmlspecialchars($policy->getSlug()) ?></code>
                                <a href="/policy/view/<?= htmlspecialchars($policy->getSlug()) ?>" target="_blank" class="ms-1 text-muted small" title="약관 보기">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark"><?= htmlspecialchars($policy->getPolicyVersion()) ?></span>
                            </td>
                            <td class="text-center">
                                <div class="form-check d-flex justify-content-center mb-0">
                                    <input type="checkbox" class="form-check-input perm-checkbox"
                                           data-field="is_required"
                                           data-policy-id="<?= $policyId ?>"
                                           <?= $policy->isRequired() ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="form-check d-flex justify-content-center mb-0">
                                    <input type="checkbox" class="form-check-input perm-checkbox"
                                           data-field="show_in_register"
                                           data-policy-id="<?= $policyId ?>"
                                           <?= $policy->showInRegister() ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center mb-0">
                                    <input type="checkbox" class="form-check-input perm-checkbox"
                                           data-field="is_active"
                                           data-policy-id="<?= $policyId ?>"
                                           <?= $policy->isActive() ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="/admin/policy/edit/<?= $policyId ?>"
                                       class="btn btn-default" title="수정">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-default btn-delete"
                                            data-id="<?= $policyId ?>"
                                            data-name="<?= htmlspecialchars($policy->getPolicyTitle()) ?>"
                                            title="삭제">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 하단 액션바 + 페이지네이션 -->
    <div class="row gx-2 justify-content-between align-items-center my-2">
        <div class="col-auto">
            <span class="text-muted small">
                <i class="bi bi-info-circle me-1"></i>체크박스 즉시 저장 · 드래그 순서 변경
            </span>
        </div>
        <div class="col-auto d-none d-md-block">
            <?= $pagination['currentPage'] ?? 1 ?> / <?= $pagination['totalPages'] ?? 1 ?> 페이지
        </div>
        <div class="col-auto">
            <?= $this->pagination($pagination) ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 인라인 체크박스 수정
    document.querySelectorAll('.perm-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const policyId = this.dataset.policyId;
            const field = this.dataset.field;
            const value = this.checked;
            const cb = this;

            cb.disabled = true;

            MubloRequest.requestJson(`/admin/policy/quick-edit/${policyId}`, {
                field: field,
                value: value
            }, { method: 'POST' }).then(response => {
                cb.classList.add('is-valid');
                setTimeout(() => cb.classList.remove('is-valid'), 1000);
            }).catch(err => {
                cb.checked = !value;
            }).finally(() => {
                cb.disabled = false;
            });
        });
    });

    // 개별 삭제
    document.querySelectorAll('.btn-delete').forEach(btn => {
        btn.addEventListener('click', function() {
            const policyId = this.dataset.id;
            const policyName = this.dataset.name;

            MubloRequest.showConfirm(`"${policyName}" 정책을 삭제하시겠습니까?`, function() {
                MubloRequest.requestJson(`/admin/policy/delete/${policyId}`, {}, {
                    method: 'POST',
                    loading: true
                }).then(response => {
                    location.reload();
                });
            }, { type: 'warning' });
        });
    });

    // 드래그 앤 드롭 정렬
    if (typeof Sortable !== 'undefined') {
        const tbody = document.getElementById('sortable-tbody');
        if (tbody) {
            new Sortable(tbody, {
                handle: '.drag-handle',
                animation: 150,
                onEnd: function() {
                    const orderData = {};
                    tbody.querySelectorAll('tr[data-policy-id]').forEach((row, index) => {
                        orderData[row.dataset.policyId] = index + 1;
                    });

                    MubloRequest.requestJson('/admin/policy/sort', {
                        order: orderData
                    }, { method: 'POST' }).catch(() => {
                        location.reload();
                    });
                }
            });
        }
    }
});
</script>
