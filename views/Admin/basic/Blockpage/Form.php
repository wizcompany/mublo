<?php
/**
 * Admin Blockpage - Form
 *
 * 블록 페이지 생성/수정 폼
 *
 * @var string $pageTitle 페이지 제목
 * @var bool $isEdit 수정 모드 여부
 * @var array|null $page 페이지 데이터
 * @var int|null $rowCount 연결된 행 수
 * @var array $levelOptions 회원레벨 옵션 [level_value => level_name]
 * @var array $layoutOptions 레이아웃 옵션 [value => label]
 */

use Mublo\Helper\Form\FormHelper;
$data = FormHelper::normalizeFormData($page ?? []);

$pageId = $data['page_id'] ?? 0;
?>
<style>
.layout-option { cursor: pointer; text-align: center; }
.layout-option input[type="radio"] { position: absolute; opacity: 0; pointer-events: none; }
.layout-option .layout-box {
    display: flex; gap: 3px; width: 100px; height: 60px;
    border: 2px solid var(--bs-border-color, #dee2e6); border-radius: 6px;
    padding: 6px; margin: 0 auto 6px; transition: border-color 0.2s, background 0.2s;
    background: var(--bs-tertiary-bg, #f8f9fa);
}
.layout-option input[type="radio"]:checked + .layout-box {
    border-color: var(--bs-primary, #0d6efd); background: rgba(13,110,253,.08);
}
.layout-option:hover .layout-box { border-color: var(--bs-primary, #0d6efd); }
.layout-box .lo-sidebar { background: var(--bs-secondary-bg, #adb5bd); border-radius: 3px; flex-shrink: 0; width: 20px; }
.layout-box .lo-content { background: var(--bs-tertiary-color, #6c757d); border-radius: 3px; flex-grow: 1; }
</style>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '블록 페이지 설정') ?></h3>
                <p class="text-muted mb-0">
                    블록 페이지의 기본 정보와 SEO 설정을 관리합니다.
                    <?php if ($isEdit): ?>
                        <span class="badge bg-secondary">ID: <?= $pageId ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/block-page" class="btn btn-default">
                    <i class="bi bi-list me-1"></i>목록
                </a>
                <?php if ($isEdit): ?>
                <a href="/admin/block-row?page_id=<?= $pageId ?>" class="btn btn-outline-primary">
                    <i class="bi bi-grid-3x2 me-1"></i>행 관리
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- 메인 폼 -->
    <form method="post">
        <input type="hidden" name="formData[page_id]" value="<?= $pageId ?>">

        <!-- 기본 정보 -->
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-info-circle me-2 text-pastel-blue"></i>기본 정보
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">페이지 코드 <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">/p/</span>
                            <input type="text" name="formData[page_code]" class="form-control"
                                   value="<?= htmlspecialchars($data['page_code'] ?? '') ?>"
                                   placeholder="about" required
                                   pattern="[a-z0-9-]+" title="영문 소문자, 숫자, 하이픈만 사용">
                        </div>
                        <small class="text-muted">영문 소문자, 숫자, 하이픈(-)만 사용 가능합니다.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">페이지 제목 <span class="text-danger">*</span></label>
                        <input type="text" name="formData[page_title]" class="form-control"
                               value="<?= htmlspecialchars($data['page_title'] ?? '') ?>"
                               placeholder="회사소개" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label">페이지 설명</label>
                        <textarea name="formData[page_description]" class="form-control" rows="2"
                                  placeholder="관리용 페이지 설명"><?= htmlspecialchars($data['page_description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEO 설정 -->
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-search me-2 text-pastel-green"></i>SEO 설정
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">SEO 타이틀</label>
                        <input type="text" name="formData[seo_title]" class="form-control"
                               value="<?= htmlspecialchars($data['seo_title'] ?? '') ?>"
                               placeholder="브라우저 탭에 표시될 제목">
                        <small class="text-muted">비어있으면 페이지 제목이 사용됩니다.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">SEO 설명</label>
                        <textarea name="formData[seo_description]" class="form-control" rows="2"
                                  placeholder="검색 결과에 표시될 설명"><?= htmlspecialchars($data['seo_description'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">SEO 키워드</label>
                        <input type="text" name="formData[seo_keywords]" class="form-control"
                               value="<?= htmlspecialchars($data['seo_keywords'] ?? '') ?>"
                               placeholder="키워드1, 키워드2, 키워드3">
                    </div>
                </div>
            </div>
        </div>

        <!-- 레이아웃 설정 -->
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-layout-three-columns me-2 text-pastel-sky"></i>레이아웃 설정
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">넓이 타입</label>
                        <select name="formData[use_fullpage]" class="form-select" id="use_fullpage_select">
                            <option value="0" <?= ($data['use_fullpage'] ?? 0) == 0 ? 'selected' : '' ?>>최대넓이</option>
                            <option value="1" <?= ($data['use_fullpage'] ?? 0) == 1 ? 'selected' : '' ?>>와이드 (전체)</option>
                            <option value="2" <?= ($data['use_fullpage'] ?? 0) == 2 ? 'selected' : '' ?>>사용자 지정</option>
                        </select>
                        <div id="custom_width_group" class="mt-2" style="display:none">
                            <div class="input-group" style="max-width:200px">
                                <input type="number" name="formData[custom_width]" class="form-control"
                                       min="300" max="2400" value="<?= (int)($data['custom_width'] ?? 1200) ?>">
                                <span class="input-group-text">px</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Header 사용</label>
                        <select name="formData[use_header]" class="form-select">
                            <option value="1" <?= ($data['use_header'] ?? 1) == 1 ? 'selected' : '' ?>>사용</option>
                            <option value="0" <?= ($data['use_header'] ?? 1) == 0 ? 'selected' : '' ?>>미사용</option>
                        </select>
                        <small class="text-muted">미사용: 사이트 헤더 없이 출력</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Footer 사용</label>
                        <select name="formData[use_footer]" class="form-select">
                            <option value="1" <?= ($data['use_footer'] ?? 1) == 1 ? 'selected' : '' ?>>사용</option>
                            <option value="0" <?= ($data['use_footer'] ?? 1) == 0 ? 'selected' : '' ?>>미사용</option>
                        </select>
                        <small class="text-muted">미사용: 사이트 푸터 없이 출력</small>
                    </div>
                </div>

                <hr>

                <?php $currentLayout = (int) ($data['layout_type'] ?? 1); ?>
                <div class="mb-3">
                    <label class="form-label">레이아웃 형태</label>
                    <div class="form-text mb-2">넓이 타입 안에서의 레이아웃 구성입니다. 모바일에서는 적용되지 않습니다.</div>
                    <div class="d-flex flex-wrap gap-3" id="layout-type-selector">
                        <label class="layout-option">
                            <input type="radio" name="formData[layout_type]" value="1" <?= $currentLayout === 1 ? 'checked' : '' ?>>
                            <div class="layout-box">
                                <div class="lo-content"></div>
                            </div>
                            <small>전체</small>
                        </label>
                        <label class="layout-option">
                            <input type="radio" name="formData[layout_type]" value="2" <?= $currentLayout === 2 ? 'checked' : '' ?>>
                            <div class="layout-box">
                                <div class="lo-sidebar"></div>
                                <div class="lo-content"></div>
                            </div>
                            <small>좌측 사이드바</small>
                        </label>
                        <label class="layout-option">
                            <input type="radio" name="formData[layout_type]" value="3" <?= $currentLayout === 3 ? 'checked' : '' ?>>
                            <div class="layout-box">
                                <div class="lo-content"></div>
                                <div class="lo-sidebar"></div>
                            </div>
                            <small>우측 사이드바</small>
                        </label>
                        <label class="layout-option">
                            <input type="radio" name="formData[layout_type]" value="4" <?= $currentLayout === 4 ? 'checked' : '' ?>>
                            <div class="layout-box">
                                <div class="lo-sidebar"></div>
                                <div class="lo-content" style="min-width: 30px;"></div>
                                <div class="lo-sidebar"></div>
                            </div>
                            <small>3단</small>
                        </label>
                    </div>
                </div>

                <!-- 사이드바 설정 -->
                <div class="d-flex flex-wrap gap-4" id="bp-sidebar-options" style="display:none">
                    <div id="bp-left-group">
                        <label class="form-label">좌측 사이드바</label>
                        <div class="d-flex gap-3 align-items-center">
                            <div class="input-group" style="max-width:160px">
                                <input type="number" name="formData[sidebar_left_width]" class="form-control" min="0" max="500" value="<?= (int)($data['sidebar_left_width'] ?? 0) ?>">
                                <span class="input-group-text">px</span>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input type="checkbox" class="form-check-input" name="formData[sidebar_left_mobile]" value="1" id="bp_sidebar_left_mobile" <?= !empty($data['sidebar_left_mobile']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="bp_sidebar_left_mobile">모바일 출력</label>
                            </div>
                        </div>
                        <small class="text-muted">0 입력 시 사이트 기본값 사용</small>
                    </div>
                    <div id="bp-right-group">
                        <label class="form-label">우측 사이드바</label>
                        <div class="d-flex gap-3 align-items-center">
                            <div class="input-group" style="max-width:160px">
                                <input type="number" name="formData[sidebar_right_width]" class="form-control" min="0" max="500" value="<?= (int)($data['sidebar_right_width'] ?? 0) ?>">
                                <span class="input-group-text">px</span>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input type="checkbox" class="form-check-input" name="formData[sidebar_right_mobile]" value="1" id="bp_sidebar_right_mobile" <?= !empty($data['sidebar_right_mobile']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="bp_sidebar_right_mobile">모바일 출력</label>
                            </div>
                        </div>
                        <small class="text-muted">0 입력 시 사이트 기본값 사용</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 권한 및 상태 -->
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-shield-lock me-2 text-pastel-purple"></i>접근 권한 및 상태
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">접근 가능 레벨</label>
                        <select name="formData[allow_level]" class="form-select">
                            <option value="0" <?= ($data['allow_level'] ?? 0) == 0 ? 'selected' : '' ?>>모두 접근 가능</option>
                            <?php foreach ($levelOptions ?? [] as $levelValue => $levelName): ?>
                            <option value="<?= (int) $levelValue ?>"
                                    <?= ($data['allow_level'] ?? 0) == $levelValue ? 'selected' : '' ?>>
                                Lv.<?= (int) $levelValue ?> - <?= htmlspecialchars($levelName) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">선택한 레벨 이상의 회원만 접근</small>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">사용 여부</label>
                        <select name="formData[is_active]" class="form-select">
                            <option value="1" <?= ($data['is_active'] ?? 1) == 1 ? 'selected' : '' ?>>사용</option>
                            <option value="0" <?= ($data['is_active'] ?? 1) == 0 ? 'selected' : '' ?>>미사용</option>
                        </select>
                    </div>
                    <?php if ($isEdit): ?>
                    <div class="col-md-4">
                        <label class="form-label">연결된 행</label>
                        <p class="form-control-plaintext">
                            <span class="badge bg-secondary"><?= $rowCount ?? 0 ?>개</span>
                            <a href="/admin/block-row?page_id=<?= $pageId ?>" class="ms-2 small">관리하기</a>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 저장 버튼 -->
        <div class="sticky-act mt-3 sticky-status">
            <a href="/admin/block-page" class="btn btn-default">취소</a>
            <button type="button" class="btn btn-primary mublo-submit"
                    data-target="/admin/block-page/store"
                    data-callback="blockpageSaved">
                <i class="bi bi-check-lg me-1"></i>저장
            </button>
        </div>
    </form>

    <?php if ($isEdit): ?>
    <!-- 미리보기/정보 -->
    <div class="card mt-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
            <i class="bi bi-link-45deg me-2 text-pastel-orange"></i>페이지 URL
        </div>
        <div class="card-body">
            <p class="mb-0">
                <a href="/p/<?= htmlspecialchars($data['page_code'] ?? '') ?>" target="_blank" class="text-decoration-none">
                    <i class="bi bi-box-arrow-up-right me-1"></i>
                    /p/<?= htmlspecialchars($data['page_code'] ?? '') ?>
                </a>
                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="copyUrl()">
                    <i class="bi bi-clipboard"></i> 복사
                </button>
            </p>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
// 저장 완료 콜백
MubloRequest.registerCallback('blockpageSaved', function(response) {
    if (response.result === 'success') {
        MubloRequest.showToast(response.message || '<?= $isEdit ? '수정' : '등록' ?>되었습니다.', 'success');
        if (response.data && response.data.redirect) {
            location.href = response.data.redirect;
        }
    } else {
        MubloRequest.showAlert(response.message || '저장에 실패했습니다.', 'error');
    }
});

// 코드 중복 확인
document.querySelector('input[name="formData[page_code]"]').addEventListener('blur', function() {
    const code = this.value.trim().toLowerCase();
    if (!code) return;

    const pageId = <?= $pageId ?>;

    MubloRequest.requestJson('/admin/block-page/check-code', {
        code: code,
        exclude_id: pageId
    }).then(response => {
        if (response.result !== 'success') {
            MubloRequest.showAlert(response.message, 'warning');
            this.focus();
        }
    }).catch(err => {
        console.error(err);
    });
});

// 코드 입력 시 소문자 변환
document.querySelector('input[name="formData[page_code]"]').addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9-]/g, '');
});

// 넓이 타입 토글
(function() {
    var sel = document.getElementById('use_fullpage_select');
    var grp = document.getElementById('custom_width_group');
    if (!sel || !grp) return;
    function toggle() { grp.style.display = sel.value === '2' ? '' : 'none'; }
    sel.addEventListener('change', toggle);
    toggle();
})();

// 사이드바 설정 토글
(function() {
    var radios = document.querySelectorAll('input[name="formData[layout_type]"]');
    var opts = document.getElementById('bp-sidebar-options');
    var leftGrp = document.getElementById('bp-left-group');
    var rightGrp = document.getElementById('bp-right-group');
    if (!opts) return;
    function toggle() {
        var sel = document.querySelector('input[name="formData[layout_type]"]:checked');
        if (!sel) return;
        var v = sel.value;
        var showLeft = (v === '2' || v === '4');
        var showRight = (v === '3' || v === '4');
        opts.style.display = (showLeft || showRight) ? '' : 'none';
        if (leftGrp) leftGrp.style.display = showLeft ? '' : 'none';
        if (rightGrp) rightGrp.style.display = showRight ? '' : 'none';
    }
    radios.forEach(function(r) { r.addEventListener('change', toggle); });
    toggle();
})();

<?php if ($isEdit): ?>
// URL 복사
function copyUrl() {
    const url = window.location.origin + '/p/<?= htmlspecialchars($data['page_code'] ?? '') ?>';
    navigator.clipboard.writeText(url).then(() => {
        MubloRequest.showToast('URL이 복사되었습니다.', 'info');
    }).catch(err => {
        console.error('복사 실패:', err);
    });
}
<?php endif; ?>
</script>
