<?php
/**
 * Admin System - Index
 *
 * 시스템 관리 페이지 (캐시 초기화 + 마이그레이션 점검 + 임시파일 정리)
 *
 * @var string $pageTitle
 * @var string $description
 * @var array $cacheInfo
 * @var array $migrationStatuses
 * @var int $totalPending
 * @var int $totalExecuted
 * @var array $tempFileInfo
 * @var array $resetItems
 * @var bool $isSuper
 */
?>
<div class="page-container">
    <!-- 고정 영역 START -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '시스템 관리') ?></h3>
                <p class="text-muted mb-0"><?= htmlspecialchars($description ?? '') ?></p>
            </div>
        </div>
    </div>
    <!-- 고정 영역 END -->

    <div class="row mt-4 g-4">

        <!-- ===== 좌측 열: 캐시 관리 + 임시파일 정리 ===== -->
        <div class="col-12 col-lg-6 d-flex flex-column gap-4">
            <!-- 캐시 관리 -->
            <div class="card">
                <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center" style="font-size:0.9rem">
                    <span><i class="bi bi-lightning-charge me-2 text-pastel-orange"></i>캐시 관리</span>
                    <span class="badge bg-secondary"><?= htmlspecialchars($cacheInfo['driver'] ?? 'file') ?></span>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-3">
                        <tbody>
                            <tr>
                                <th class="text-muted" style="width:120px">캐시 드라이버</th>
                                <td>
                                    <span class="badge <?= ($cacheInfo['driver'] ?? 'file') === 'redis' ? 'bg-danger' : 'bg-info' ?>">
                                        <?= htmlspecialchars(strtoupper($cacheInfo['driver'] ?? 'file')) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php if (isset($cacheInfo['size_human'])): ?>
                            <tr>
                                <th class="text-muted">캐시 용량</th>
                                <td id="cache-size"><?= htmlspecialchars($cacheInfo['size_human']) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th class="text-muted">저장 위치</th>
                                <td><code class="small"><?= htmlspecialchars($cacheInfo['path'] ?? '-') ?></code></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="d-grid">
                        <button type="button" class="btn btn-outline-danger" id="btn-clear-cache">
                            <i class="bi bi-trash me-1"></i>전체 캐시 초기화
                        </button>
                    </div>

                    <div id="cache-result" class="mt-3" style="display:none;"></div>
                </div>
            </div>

            <!-- 임시파일 정리 -->
            <div class="card">
                <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center" style="font-size:0.9rem">
                    <span><i class="bi bi-file-earmark-x me-2 text-pastel-purple"></i>임시파일 정리</span>
                    <?php if (($tempFileInfo['total']['count'] ?? 0) > 0): ?>
                        <span class="badge bg-warning text-dark"><?= $tempFileInfo['total']['count'] ?>개 파일</span>
                    <?php else: ?>
                        <span class="badge bg-success">깨끗함</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        에디터 이미지 업로드, 파일 첨부 등에서 저장되지 않고 남은 임시파일을 정리합니다.
                    </p>

                    <table class="table table-sm mb-3">
                        <thead>
                            <tr>
                                <th>구분</th>
                                <th class="text-center">파일 수</th>
                                <th class="text-end">용량</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><i class="bi bi-image me-1 text-info"></i>에디터 임시 이미지</td>
                                <td class="text-center" id="temp-editor-count"><?= $tempFileInfo['editor']['count'] ?? 0 ?></td>
                                <td class="text-end" id="temp-editor-size"><?= $tempFileInfo['editor']['size_human'] ?? '0 B' ?></td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-file-lock me-1 text-warning"></i>보안 임시 파일</td>
                                <td class="text-center" id="temp-secure-count"><?= $tempFileInfo['secure']['count'] ?? 0 ?></td>
                                <td class="text-end" id="temp-secure-size"><?= $tempFileInfo['secure']['size_human'] ?? '0 B' ?></td>
                            </tr>
                            <tr class="fw-bold">
                                <td>합계</td>
                                <td class="text-center" id="temp-total-count"><?= $tempFileInfo['total']['count'] ?? 0 ?></td>
                                <td class="text-end" id="temp-total-size"><?= $tempFileInfo['total']['size_human'] ?? '0 B' ?></td>
                            </tr>
                        </tbody>
                    </table>

                    <div class="row g-2 align-items-end">
                        <div class="col">
                            <label class="form-label small text-muted mb-1">보관 기간</label>
                            <select id="temp-max-age" class="form-select form-select-sm">
                                <option value="1">1시간 이상 경과</option>
                                <option value="6">6시간 이상 경과</option>
                                <option value="12">12시간 이상 경과</option>
                                <option value="24" selected>24시간 이상 경과</option>
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-outline-warning" id="btn-cleanup-temp">
                                <i class="bi bi-trash me-1"></i>임시파일 정리
                            </button>
                        </div>
                    </div>

                    <div id="temp-result" class="mt-3" style="display:none;"></div>
                </div>
            </div>
        </div>

        <!-- ===== 마이그레이션 점검 ===== -->
        <div class="col-12 col-lg-6">
            <div class="card h-100">
                <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center" style="font-size:0.9rem">
                    <span><i class="bi bi-database-check me-2 text-pastel-blue"></i>데이터베이스 마이그레이션</span>
                    <?php if ($totalPending > 0): ?>
                        <span class="badge bg-warning text-dark"><?= $totalPending ?>개 대기</span>
                    <?php else: ?>
                        <span class="badge bg-success">최신 상태</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-3">
                            <thead>
                                <tr>
                                    <th>구분</th>
                                    <th>이름</th>
                                    <th class="text-center">실행됨</th>
                                    <th class="text-center">대기</th>
                                    <th class="text-center">상태</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($migrationStatuses as $status): ?>
                                <tr>
                                    <td>
                                        <span class="badge <?= match($status['source']) {
                                            'core' => 'bg-primary',
                                            'plugin' => 'bg-info',
                                            'package' => 'bg-success',
                                            default => 'bg-secondary'
                                        } ?>"><?= htmlspecialchars(ucfirst($status['source'])) ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($status['name']) ?></td>
                                    <td class="text-center"><?= count($status['executed']) ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($status['pending'])): ?>
                                            <span class="text-warning fw-bold"><?= count($status['pending']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (empty($status['pending'])): ?>
                                            <i class="bi bi-check-circle text-success"></i>
                                        <?php else: ?>
                                            <i class="bi bi-exclamation-triangle text-warning"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($status['pending'])): ?>
                                <tr>
                                    <td colspan="5" class="ps-4 py-1">
                                        <small class="text-muted">대기 중: </small>
                                        <?php foreach ($status['pending'] as $file): ?>
                                            <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($file) ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($totalPending > 0): ?>
                    <div class="d-grid">
                        <button type="button" class="btn btn-warning" id="btn-run-migration">
                            <i class="bi bi-play-circle me-1"></i>미실행 마이그레이션 실행 (<?= $totalPending ?>개)
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-2">
                        <i class="bi bi-check-circle-fill text-success me-1"></i>
                        모든 마이그레이션이 실행된 상태입니다.
                    </div>
                    <?php endif; ?>

                    <div id="migration-result" class="mt-3" style="display:none;"></div>
                </div>
            </div>
        </div>

    </div>

    <?php if (!empty($isSuper) && !empty($resetItems)): ?>
    <!-- ===== 데이터 초기화 (SUPER 전용) ===== -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-danger">
                <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center bg-danger text-white rounded-top" style="font-size:0.9rem">
                    <span><i class="bi bi-exclamation-triangle me-2"></i>데이터 초기화</span>
                    <span class="badge bg-light text-danger">SUPER 전용</span>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning mb-4">
                        <i class="bi bi-shield-exclamation me-1"></i>
                        <strong>주의:</strong> 초기화된 데이터는 복구할 수 없습니다. 신중하게 사용해 주세요.
                    </div>

                    <!-- 항목별 초기화 카드 그리드 -->
                    <div class="row g-3">
                        <?php foreach ($resetItems as $item): ?>
                            <?php foreach ($item['categories'] as $cat): ?>
                            <div class="col-12 col-md-6 col-xl-4">
                                <div class="card h-100 border">
                                    <div class="card-body d-flex flex-column">
                                        <h6 class="card-title mb-1">
                                            <i class="<?= htmlspecialchars($cat['icon'] ?? 'bi-circle') ?> me-1"></i>
                                            <?= htmlspecialchars($cat['label']) ?>
                                            <span class="badge bg-<?= match($item['source']) { 'core' => 'primary', 'plugin' => 'info', 'package' => 'success', default => 'secondary' } ?> ms-1" style="font-size:10px">
                                                <?= htmlspecialchars($item['source'] === 'core' ? 'Core' : $item['name']) ?>
                                            </span>
                                        </h6>
                                        <p class="card-text small text-muted flex-grow-1"><?= htmlspecialchars($cat['description']) ?></p>
                                        <button type="button" class="btn btn-outline-danger btn-sm btn-reset-category"
                                                data-category="<?= htmlspecialchars($cat['key']) ?>"
                                                data-label="<?= htmlspecialchars($cat['label']) ?>"
                                                data-description="<?= htmlspecialchars($cat['description']) ?>">
                                            <i class="bi bi-trash me-1"></i>초기화
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- 전체 초기화 -->
                    <hr class="my-4">
                    <div class="card border-danger bg-danger bg-opacity-10">
                        <div class="card-body text-center">
                            <h5 class="text-danger mb-2"><i class="bi bi-exclamation-octagon me-1"></i>전체 초기화</h5>
                            <p class="text-muted small mb-3">SUPER 회원을 제외한 모든 데이터가 삭제됩니다.</p>
                            <button type="button" class="btn btn-danger" id="btn-reset-all">
                                <i class="bi bi-radioactive me-1"></i>전체 초기화 실행
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 항목별 초기화 모달 -->
    <div class="modal fade" id="resetCategoryModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="resetCategoryModalTitle">데이터 초기화</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <span id="resetCategoryDesc"></span>
                    </div>
                    <div class="mb-3">
                        <label for="resetCategoryPassword" class="form-label fw-bold">관리자 비밀번호 확인</label>
                        <input type="password" class="form-control" id="resetCategoryPassword" placeholder="비밀번호를 입력하세요" autocomplete="off">
                    </div>
                    <input type="hidden" id="resetCategoryKey" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="button" class="btn btn-danger" id="resetCategoryConfirmBtn">
                        <i class="bi bi-trash me-1"></i>초기화 실행
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 전체 초기화 모달 -->
    <div class="modal fade" id="resetAllModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-radioactive me-1"></i>전체 초기화</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-octagon me-1"></i>
                        <strong>SUPER 회원을 제외한 모든 데이터가 삭제됩니다.</strong><br>
                        <small>회원, 게시판, 블록, 메뉴, 업로드 파일, 플러그인 데이터 등이 모두 초기화됩니다.</small>
                    </div>
                    <div class="mb-3">
                        <label for="resetAllPassword" class="form-label fw-bold">관리자 비밀번호</label>
                        <input type="password" class="form-control" id="resetAllPassword" placeholder="비밀번호를 입력하세요" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label for="resetAllConfirmText" class="form-label fw-bold">확인 문구 입력</label>
                        <input type="text" class="form-control" id="resetAllConfirmText" placeholder="'전체 초기화'를 정확히 입력하세요" autocomplete="off">
                        <div class="form-text">확인을 위해 <code>전체 초기화</code>를 정확히 입력해주세요.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                    <button type="button" class="btn btn-danger" id="resetAllConfirmBtn" disabled>
                        <i class="bi bi-radioactive me-1"></i>전체 초기화 실행
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 캐시 초기화
    document.getElementById('btn-clear-cache')?.addEventListener('click', function() {
        const btn = this;
        MubloRequest.showConfirm('전체 캐시를 초기화하시겠습니까?', function() {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>초기화 중...';

            MubloRequest.requestJson('/admin/system/clearCache', {}, { method: 'POST' })
                .then(function(res) {
                    const el = document.getElementById('cache-result');
                    el.style.display = '';
                    el.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i>' + (res.message || '캐시를 초기화했습니다.') + '</div>';
                    const sizeEl = document.getElementById('cache-size');
                    if (sizeEl) sizeEl.textContent = '0 B';
                })
                .catch(function() {
                    const el = document.getElementById('cache-result');
                    el.style.display = '';
                    el.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i>캐시 초기화 중 오류가 발생했습니다.</div>';
                })
                .finally(function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-trash me-1"></i>전체 캐시 초기화';
                });
        }, { type: 'warning' });
    });

    // 임시파일 정리
    document.getElementById('btn-cleanup-temp')?.addEventListener('click', function() {
        const maxAge = document.getElementById('temp-max-age').value;
        const btn = this;
        MubloRequest.showConfirm(maxAge + '시간 이상 경과된 임시파일을 삭제하시겠습니까?', function() {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>정리 중...';

            MubloRequest.requestJson('/admin/system/cleanupTemp', { maxAgeHours: parseInt(maxAge) }, { method: 'POST' })
                .then(function(res) {
                    const el = document.getElementById('temp-result');
                    el.style.display = '';
                    el.innerHTML = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i>' + (res.message || '정리 완료') + '</div>';
                    // 수치 갱신
                    if (res.data) {
                        document.getElementById('temp-editor-count').textContent = '0';
                        document.getElementById('temp-secure-count').textContent = '0';
                        document.getElementById('temp-total-count').textContent = '0';
                        document.getElementById('temp-editor-size').textContent = '0 B';
                        document.getElementById('temp-secure-size').textContent = '0 B';
                        document.getElementById('temp-total-size').textContent = '0 B';
                    }
                })
                .catch(function() {
                    const el = document.getElementById('temp-result');
                    el.style.display = '';
                    el.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i>임시파일 정리 중 오류가 발생했습니다.</div>';
                })
                .finally(function() {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-trash me-1"></i>임시파일 정리';
                });
        }, { type: 'warning' });
    });

    // 마이그레이션 실행
    document.getElementById('btn-run-migration')?.addEventListener('click', function() {
        const btn = this;
        MubloRequest.showConfirm('미실행 마이그레이션을 실행하시겠습니까?\n실행 후 되돌릴 수 없습니다.', function() {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>실행 중...';

            MubloRequest.requestJson('/admin/system/runMigration', {}, { method: 'POST' })
                .then(function(res) {
                    const el = document.getElementById('migration-result');
                    el.style.display = '';
                    let html = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-1"></i>' + (res.message || '마이그레이션 완료') + '</div>';
                    if (res.data && res.data.executed && res.data.executed.length > 0) {
                        html += '<ul class="list-unstyled mt-2 mb-0 small">';
                        res.data.executed.forEach(function(f) {
                            html += '<li><i class="bi bi-check text-success me-1"></i>' + f + '</li>';
                        });
                        html += '</ul>';
                    }
                    el.innerHTML = html;
                    // 2초 후 페이지 새로고침 (상태 갱신)
                    setTimeout(function() { location.reload(); }, 2000);
                })
                .catch(function() {
                    const el = document.getElementById('migration-result');
                    el.style.display = '';
                    el.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-x-circle me-1"></i>마이그레이션 실행 중 오류가 발생했습니다.</div>';
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-play-circle me-1"></i>미실행 마이그레이션 실행';
                });
        }, { type: 'warning' });
    });

    // ===== 데이터 초기화 =====

    // 항목별 초기화 모달 열기
    document.querySelectorAll('.btn-reset-category').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var category = this.dataset.category;
            var label = this.dataset.label;
            var description = this.dataset.description;

            document.getElementById('resetCategoryModalTitle').textContent = '데이터 초기화: ' + label;
            document.getElementById('resetCategoryDesc').textContent = description;
            document.getElementById('resetCategoryKey').value = category;
            document.getElementById('resetCategoryPassword').value = '';

            var modal = new bootstrap.Modal(document.getElementById('resetCategoryModal'));
            modal.show();

            // 모달 열린 후 비밀번호 필드 포커스
            document.getElementById('resetCategoryModal').addEventListener('shown.bs.modal', function handler() {
                document.getElementById('resetCategoryPassword').focus();
                this.removeEventListener('shown.bs.modal', handler);
            });
        });
    });

    // 항목별 초기화 실행
    document.getElementById('resetCategoryConfirmBtn')?.addEventListener('click', function() {
        var category = document.getElementById('resetCategoryKey').value;
        var password = document.getElementById('resetCategoryPassword').value;

        if (!password) {
            MubloRequest.showAlert('비밀번호를 입력해주세요.', 'warning');
            return;
        }

        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>초기화 중...';

        MubloRequest.requestJson('/admin/system/resetData', {
            category: category,
            password: password
        }, { method: 'POST' })
            .then(function(res) {
                bootstrap.Modal.getInstance(document.getElementById('resetCategoryModal')).hide();
                MubloRequest.showToast(res.message || '초기화가 완료되었습니다.', 'success');
            })
            .catch(function() {})
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-trash me-1"></i>초기화 실행';
            });
    });

    // 비밀번호 필드 Enter 키 지원
    document.getElementById('resetCategoryPassword')?.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('resetCategoryConfirmBtn').click();
        }
    });

    // 전체 초기화 모달 열기
    document.getElementById('btn-reset-all')?.addEventListener('click', function() {
        document.getElementById('resetAllPassword').value = '';
        document.getElementById('resetAllConfirmText').value = '';
        document.getElementById('resetAllConfirmBtn').disabled = true;

        var modal = new bootstrap.Modal(document.getElementById('resetAllModal'));
        modal.show();

        document.getElementById('resetAllModal').addEventListener('shown.bs.modal', function handler() {
            document.getElementById('resetAllPassword').focus();
            this.removeEventListener('shown.bs.modal', handler);
        });
    });

    // 전체 초기화 확인 문구 검증 → 버튼 활성화
    document.getElementById('resetAllConfirmText')?.addEventListener('input', function() {
        var btn = document.getElementById('resetAllConfirmBtn');
        btn.disabled = this.value !== '전체 초기화';
    });

    // 전체 초기화 실행
    document.getElementById('resetAllConfirmBtn')?.addEventListener('click', function() {
        var password = document.getElementById('resetAllPassword').value;
        var confirmText = document.getElementById('resetAllConfirmText').value;

        if (!password) {
            MubloRequest.showAlert('비밀번호를 입력해주세요.', 'warning');
            return;
        }

        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>초기화 중...';

        MubloRequest.requestJson('/admin/system/resetAll', {
            password: password,
            confirmText: confirmText
        }, { method: 'POST' })
            .then(function(res) {
                bootstrap.Modal.getInstance(document.getElementById('resetAllModal')).hide();
                MubloRequest.showAlert(res.message || '전체 초기화가 완료되었습니다.', 'success', {
                    onClose: function() { location.reload(); }
                });
            })
            .catch(function() {})
            .finally(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-radioactive me-1"></i>전체 초기화 실행';
            });
    });
});
</script>
