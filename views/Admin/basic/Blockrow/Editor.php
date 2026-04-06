<?= editor_css() ?>
<link rel="stylesheet" href="/assets/css/admin/blockrow-form.css">
<!-- 프론트 CSS (미리보기용) — 전역 html/body 영향 있음 -->
<link rel="stylesheet" href="/assets/css/front-common.css">
<link rel="stylesheet" href="/assets/css/block.css">
<link rel="stylesheet" href="/serve/front/basic/css/front.css">
<link rel="stylesheet" href="/assets/lib/swiper/12/swiper-bundle.min.css">
<!-- blockrow-editor.css가 프론트 CSS 뒤에 로드되어 관리자 베이스를 복원 -->
<link rel="stylesheet" href="/assets/css/admin/blockrow-editor.css">
<script src="/assets/lib/swiper/12/swiper-bundle.min.js"></script>
<script src="/assets/js/MubloItemLayout.js"></script>
<script src="/assets/js/admin/blockrow-form.js"></script>

<div id="block-editor" class="block-editor">

    <!-- Toolbar -->
    <div class="be-toolbar">
        <div class="be-toolbar__left">
            <?php
            $backUrl = $isPageBased
                ? "/admin/block-row?page_id={$pageId}"
                : '/admin/block-row';
            ?>
            <a href="<?= $backUrl ?>"><i class="bi bi-arrow-left"></i> 목록</a>
            <span class="be-toolbar__title">행 에디터 <span class="badge bg-warning text-dark" style="font-size:0.6rem;vertical-align:middle">Beta</span></span>
            <span class="badge bg-secondary">#<?= $rowId ?></span>
            <span class="be-toolbar__dirty" id="be-dirty">변경됨</span>
        </div>
        <div class="be-toolbar__right">
            <a href="/admin/block-row/edit?id=<?= $rowId ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-pencil-square"></i> 기존 폼
            </a>
            <button type="button" class="btn btn-sm btn-primary" id="be-btn-save">
                <i class="bi bi-check-lg"></i> 저장
            </button>
        </div>
    </div>

    <!-- 좌우 레이아웃 -->
    <div class="be-main">

        <!-- 좌측: 미리보기(상) + 설정(하) -->
        <div class="be-left">
            <!-- 미리보기 -->
            <div class="be-preview" id="be-preview">
                <div class="be-preview__header">
                    <span class="small fw-bold">미리보기</span>
                    <div style="display:flex;gap:6px;">
                        <button type="button" class="btn btn-sm btn-link p-0" id="be-btn-refresh" title="새로고침">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-link p-0" id="be-btn-fullscreen" title="전체보기">
                            <i class="bi bi-arrows-fullscreen"></i>
                        </button>
                    </div>
                </div>
                <div class="be-preview__body" id="be-preview-body">
                    <div class="be-preview__loading" id="be-preview-loading">
                        <div class="spinner-border spinner-border-sm me-2"></div> 로딩 중...
                    </div>
                    <div class="be-preview__content" id="be-preview-content"></div>
                </div>
            </div>

            <!-- 리사이즈 핸들 -->
            <div class="be-resize-handle" id="be-resize-handle"></div>

            <!-- 설정 패널 -->
            <div class="be-settings" id="be-settings">
                <div class="be-settings__empty">로딩 중...</div>
            </div>
        </div>

        <!-- 우측: 콘텐츠 편집 -->
        <div class="be-editor" id="be-editor">
            <div class="be-editor__empty">
                <i class="bi bi-cursor-fill"></i>
                <div class="small mt-1">미리보기에서 칸을 클릭하여 편집하세요</div>
            </div>
        </div>

    </div>

</div>

<script src="/assets/js/admin/block-editor/store.js"></script>
<script src="/assets/js/admin/block-editor/adapter.js"></script>
<script src="/assets/js/admin/block-editor/preview.js"></script>
<!-- EditorPanel 하위 모듈 (html, media, items) → EditorPanel → Inspector 하위 모듈 (row, column) → Inspector → Main -->
<script src="/assets/js/admin/block-editor/editor-html.js"></script>
<script src="/assets/js/admin/block-editor/editor-media.js"></script>
<script src="/assets/js/admin/block-editor/editor-items.js"></script>
<script src="/assets/js/admin/block-editor/editor-panel.js"></script>
<script src="/assets/js/admin/block-editor/inspector-row.js"></script>
<script src="/assets/js/admin/block-editor/inspector-column.js"></script>
<script src="/assets/js/admin/block-editor/inspector.js"></script>
<script src="/assets/js/admin/block-editor/main.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    BlockRowEditor.init({
        rowId: <?= (int) $rowId ?>,
        domainId: <?= (int) $domainId ?>,
        isPageBased: <?= json_encode($isPageBased) ?>,
        pageId: <?= (int) $pageId ?>,
        contentTypes: <?= json_encode($contentTypes, JSON_UNESCAPED_UNICODE) ?>,
        contentTypeGroups: <?= json_encode($contentTypeGroups, JSON_UNESCAPED_UNICODE) ?>,
        skinLists: <?= json_encode($skinLists, JSON_UNESCAPED_UNICODE) ?>,
        positions: <?= json_encode($positions, JSON_UNESCAPED_UNICODE) ?>,
        menuOptions: <?= json_encode($menuOptions, JSON_UNESCAPED_UNICODE) ?>,
        pages: <?= json_encode($pages, JSON_UNESCAPED_UNICODE) ?>
    });
});
</script>
<?= editor_js() ?>
