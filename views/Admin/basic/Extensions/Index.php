<?php
/**
 * Admin Extensions - Index
 *
 * 확장 기능 관리 페이지 (플러그인/패키지)
 *
 * @var string $pageTitle 페이지 제목
 * @var array $plugins 플러그인 목록 (manifest + enabled)
 * @var array $packages 패키지 목록 (manifest + enabled)
 */
?>
<form name="frm" id="frm">
<div class="page-container form-container">
    <!-- 고정 영역 START -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '확장 기능') ?></h3>
                <p class="text-muted mb-0">사용할 플러그인과 패키지를 선택하고, 드래그하여 관리자 메뉴 순서를 변경할 수 있습니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <button type="button"
                    class="btn btn-primary w-100 mublo-submit"
                    data-target="/admin/extensions/update"
                    data-callback="extensionsSaved">
                    <i class="bi bi-check-lg me-1"></i>저장
                </button>
            </div>
        </div>
    </div>
    <!-- 고정 영역 END -->

    <!-- 2열 레이아웃 -->
    <div class="row mt-4">
        <!-- 플러그인 (좌측) -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-puzzle me-2"></i>플러그인
                    <span class="badge bg-secondary ms-2"><?= count($plugins) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($plugins)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-puzzle fs-1 d-block mb-3"></i>
                        <p class="mb-0">설치된 플러그인이 없습니다.</p>
                        <small>plugins/ 디렉토리에 플러그인을 추가하세요.</small>
                    </div>
                    <?php else: ?>
                    <div class="row g-3" id="sortable-plugins">
                        <?php foreach ($plugins as $name => $manifest):
                            if (!empty($manifest['hidden'])) continue;
                            if (!empty($manifest['super_only']) && empty($isSuper)) continue;
                        ?>
                        <div class="col-4" data-name="<?= htmlspecialchars($name) ?>">
                            <div class="card h-100 ext-card <?= ($manifest['enabled'] ?? false) ? 'border-primary' : '' ?>" style="cursor: grab;">
                                <div class="card-body py-3">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               class="form-check-input ext-check"
                                               name="formData[plugins][]"
                                               value="<?= htmlspecialchars($name) ?>"
                                               id="plugin_<?= htmlspecialchars($name) ?>"
                                               <?= ($manifest['enabled'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="plugin_<?= htmlspecialchars($name) ?>">
                                            <i class="<?= htmlspecialchars($manifest['icon'] ?? 'bi-puzzle') ?> me-1"></i>
                                            <?= htmlspecialchars($manifest['label'] ?? $name) ?>
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-2 ps-4" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= htmlspecialchars($manifest['description'] ?? '') ?>
                                    </p>
                                    <div class="ps-4">
                                        <small class="text-muted">
                                            <i class="bi bi-tag me-1"></i>v<?= htmlspecialchars($manifest['version'] ?? '1.0.0') ?>
                                        </small>
                                        <?php if (!empty($manifest['author'])): ?>
                                        <small class="text-muted ms-2">
                                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($manifest['author']) ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 패키지 (우측) -->
        <div class="col-12 col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <i class="bi bi-box me-2"></i>패키지
                    <span class="badge bg-secondary ms-2"><?= count($packages) ?></span>
                </div>
                <div class="card-body">
                    <?php if (empty($packages)): ?>
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-box fs-1 d-block mb-3"></i>
                        <p class="mb-0">설치된 패키지가 없습니다.</p>
                        <small>packages/ 디렉토리에 패키지를 추가하세요.</small>
                    </div>
                    <?php else: ?>
                    <div class="row g-3" id="sortable-packages">
                        <?php foreach ($packages as $name => $manifest): ?>
                        <div class="col-4" data-name="<?= htmlspecialchars($name) ?>">
                            <div class="card h-100 ext-card <?= ($manifest['enabled'] ?? false) ? 'border-primary' : '' ?>" style="cursor: grab;">
                                <div class="card-body py-3">
                                    <div class="form-check">
                                        <input type="checkbox"
                                               class="form-check-input ext-check"
                                               name="formData[packages][]"
                                               value="<?= htmlspecialchars($name) ?>"
                                               id="package_<?= htmlspecialchars($name) ?>"
                                               <?= ($manifest['enabled'] ?? false) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-bold" for="package_<?= htmlspecialchars($name) ?>">
                                            <i class="<?= htmlspecialchars($manifest['icon'] ?? 'bi-box') ?> me-1"></i>
                                            <?= htmlspecialchars($manifest['label'] ?? $name) ?>
                                        </label>
                                    </div>
                                    <p class="text-muted small mb-2 ps-4" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?= htmlspecialchars($manifest['description'] ?? '') ?>
                                    </p>
                                    <div class="ps-4">
                                        <small class="text-muted">
                                            <i class="bi bi-tag me-1"></i>v<?= htmlspecialchars($manifest['version'] ?? '1.0.0') ?>
                                        </small>
                                        <?php if (!empty($manifest['author'])): ?>
                                        <small class="text-muted ms-2">
                                            <i class="bi bi-person me-1"></i><?= htmlspecialchars($manifest['author']) ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</form>

<script>
// 체크박스 토글 시 border 업데이트
document.querySelectorAll('.ext-check').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var card = this.closest('.ext-card');
        if (this.checked) {
            card.classList.add('border-primary');
        } else {
            card.classList.remove('border-primary');
        }
    });
});

// 드래그 정렬
['sortable-plugins', 'sortable-packages'].forEach(function(id) {
    var el = document.getElementById(id);
    if (el) {
        new Sortable(el, {
            animation: 150,
            ghostClass: 'opacity-25',
            chosenClass: 'shadow',
            dragClass: 'shadow-lg',
        });
    }
});

MubloRequest.registerCallback('extensionsSaved', function(response) {
    if (response.result === 'success') {
        alert(response.message || '확장 기능 설정이 저장되었습니다.');
        location.reload();
    } else {
        alert(response.message || '저장에 실패했습니다.');
    }
});
</script>
