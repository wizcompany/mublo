<?php
/**
 * FAQ 관리 (통합 페이지)
 *
 * 레이아웃: 좌측 카테고리 목록 + 우측 FAQ 목록/등록 전환
 *
 * @var string $pageTitle
 * @var array $categories
 * @var array $items
 * @var int|null $activeCategoryId
 * @var array $skins 사용 가능한 스킨 목록
 * @var string $currentSkin 현재 적용된 스킨
 */
$categories = $categories ?? [];
$items = $items ?? [];
$activeCategoryId = $activeCategoryId ?? null;
$skins = $skins ?? ['basic'];
$currentSkin = $currentSkin ?? 'basic';
?>

<?= editor_css() ?>

<div class="page-container">
    <!-- 페이지 헤더 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle) ?></h3>
                <p class="text-muted mb-0">카테고리별 자주 묻는 질문을 관리합니다.</p>
            </div>
            <div class="col-sm-auto d-flex align-items-center gap-2">
                <label class="form-label mb-0 text-nowrap small">프론트 스킨</label>
                <select class="form-select form-select-sm" id="skinSelect" style="width:130px;">
                    <?php foreach ($skins as $skin): ?>
                        <option value="<?= htmlspecialchars($skin) ?>" <?= $skin === $currentSkin ? 'selected' : '' ?>>
                            <?= htmlspecialchars($skin) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="saveSkin()">적용</button>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <!-- ========== 좌측: 카테고리 목록 ========== -->
        <div class="col-lg-4">
            <div class="card">
                <div class="px-3 pt-3 pb-0 fw-semibold d-flex align-items-center justify-content-between" style="font-size:0.9rem">
                    <span><i class="bi bi-folder text-pastel-blue me-2"></i>카테고리</span>
                    <div class="d-flex gap-1">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnEditCategory" style="display:none;" onclick="editCategory()">
                            <i class="bi bi-pencil me-1"></i>수정
                        </button>
                        <button type="button" class="btn btn-primary btn-sm" onclick="openCategoryModal()">
                            <i class="bi bi-plus-lg me-1"></i>추가
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($categories)): ?>
                        <div class="text-center text-muted py-4">카테고리를 먼저 추가해 주세요.</div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" id="categoryList">
                            <?php foreach ($categories as $cat): ?>
                                <button type="button"
                                        class="list-group-item list-group-item-action d-flex align-items-center <?= ((int) $cat['category_id'] === $activeCategoryId) ? 'active' : '' ?>"
                                        data-category-id="<?= $cat['category_id'] ?>"
                                        onclick="selectCategory(<?= $cat['category_id'] ?>)">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold"><?= htmlspecialchars($cat['category_name']) ?></div>
                                    </div>
                                    <?php if (!(int) $cat['is_active']): ?>
                                        <span class="badge bg-secondary ms-2">비활성</span>
                                    <?php endif; ?>
                                    <span class="badge bg-light text-dark ms-2"><?= (int) ($cat['sort_order'] ?? 0) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 카테고리 폼 (인라인) -->
            <div class="card mt-3" id="categoryFormCard" style="display:none;">
                <div class="px-3 pt-3 pb-0 fw-semibold d-flex align-items-center justify-content-between" style="font-size:0.9rem">
                    <span id="categoryFormTitle"><i class="bi bi-pencil text-pastel-green me-2"></i>카테고리 수정</span>
                    <button type="button" class="btn-close btn-sm" onclick="closeCategoryForm()"></button>
                </div>
                <div class="card-body">
                    <input type="hidden" id="catEditId" value="">
                    <div class="mb-3">
                        <label class="form-label">카테고리명 <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="catName" placeholder="예: 배송">
                    </div>
                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">정렬 순서</label>
                            <input type="number" class="form-control" id="catSortOrder" value="0">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input type="checkbox" class="form-check-input" id="catIsActive" checked>
                                <label class="form-check-label" for="catIsActive">활성</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary btn-sm" onclick="saveCategory()">
                            <i class="bi bi-check-lg me-1"></i>저장
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnDeleteCategory" style="display:none;" onclick="deleteCategory()">
                            <i class="bi bi-trash me-1"></i>삭제
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========== 우측: FAQ 목록 ========== -->
        <div class="col-lg-8">
            <div class="card">
                <div class="px-3 pt-3 pb-0 fw-semibold d-flex align-items-center justify-content-between" style="font-size:0.9rem">
                    <span>
                        <i class="bi bi-question-circle text-pastel-blue me-2"></i>FAQ 항목
                        <span class="text-muted fw-normal ms-1" id="faqCategoryLabel"></span>
                    </span>
                    <button type="button" class="btn btn-primary btn-sm" id="btnAddItem" style="display:none;" onclick="openItemModal()">
                        <i class="bi bi-plus-lg me-1"></i>FAQ 추가
                    </button>
                </div>
                <div class="card-body p-0">
                    <div id="itemList">
                        <?php if (empty($categories)): ?>
                            <div class="text-center text-muted py-5">카테고리를 먼저 추가해 주세요.</div>
                        <?php elseif ($activeCategoryId && !empty($items)): ?>
                            <!-- 초기 로드: 서버 렌더링 -->
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <?= empty($categories) ? '카테고리를 먼저 추가해 주세요.' : '좌측에서 카테고리를 선택하세요.' ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 카테고리 추가 모달 (신규용) -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">카테고리 추가</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">카테고리명 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="newCatName" placeholder="예: 배송">
                </div>
                <div class="row">
                    <div class="col-6">
                        <label class="form-label">정렬 순서</label>
                        <input type="number" class="form-control" id="newCatSortOrder" value="0">
                    </div>
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="newCatIsActive" checked>
                            <label class="form-check-label" for="newCatIsActive">활성</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveNewCategory()">저장</button>
            </div>
        </div>
    </div>
</div>

<!-- FAQ 항목 모달 (에디터 포함) -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle">FAQ 추가</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="itemEditId" value="">
                <div class="mb-3">
                    <label class="form-label">질문 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="itemQuestion" placeholder="질문을 입력하세요">
                </div>
                <div class="mb-3">
                    <label class="form-label">답변 <span class="text-danger">*</span></label>
                    <?= editor_html('itemAnswer', '', [
                        'name' => 'answer',
                        'height' => 300,
                        'toolbar' => 'full',
                        'placeholder' => '답변을 입력하세요 (HTML 사용 가능)',
                    ]) ?>
                </div>
                <div class="row">
                    <div class="col-4">
                        <label class="form-label">정렬 순서</label>
                        <input type="number" class="form-control" id="itemSortOrder" value="0">
                    </div>
                    <div class="col-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input type="checkbox" class="form-check-input" id="itemIsActive" checked>
                            <label class="form-check-label" for="itemIsActive">활성</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveItem()">저장</button>
            </div>
        </div>
    </div>
</div>

<?= editor_js() ?>

<script>
(function() {
    // ─────────────────────────────────────────
    // 상태
    // ─────────────────────────────────────────
    let currentCategoryId = <?= $activeCategoryId ? $activeCategoryId : 'null' ?>;
    let categoriesData = <?= json_encode($categories, JSON_UNESCAPED_UNICODE) ?>;
    let editorInstance = null;

    // 초기 상태
    if (currentCategoryId) {
        updateRightPanel(currentCategoryId);
        loadItems(currentCategoryId);
    }

    // ─────────────────────────────────────────
    // 에디터 초기화 (모달이 열릴 때)
    // ─────────────────────────────────────────
    const itemModalEl = document.getElementById('itemModal');
    itemModalEl.addEventListener('shown.bs.modal', function() {
        if (typeof MubloEditor !== 'undefined') {
            const el = document.getElementById('itemAnswer');
            if (el && !el.dataset.mubloEditorInitialized) {
                MubloEditor.create(el);
                el.dataset.mubloEditorInitialized = 'true';
            }
            editorInstance = MubloEditor.get('itemAnswer');
        }
    });

    function getEditorContent() {
        if (editorInstance && editorInstance.sync) {
            editorInstance.sync();
        }
        return document.getElementById('itemAnswer').value;
    }

    function setEditorContent(html) {
        const el = document.getElementById('itemAnswer');
        el.value = html || '';
        if (editorInstance && editorInstance.setHTML) {
            editorInstance.setHTML(html || '');
        }
    }

    // ─────────────────────────────────────────
    // 카테고리 선택
    // ─────────────────────────────────────────
    window.selectCategory = function(categoryId) {
        currentCategoryId = categoryId;

        // 목록 활성 상태
        document.querySelectorAll('#categoryList .list-group-item').forEach(function(btn) {
            const isActive = parseInt(btn.dataset.categoryId) === categoryId;
            btn.classList.toggle('active', isActive);
        });

        updateRightPanel(categoryId);
        closeCategoryForm();
        loadItems(categoryId);
    };

    function updateRightPanel(categoryId) {
        const cat = categoriesData.find(function(c) { return parseInt(c.category_id) === categoryId; });
        if (!cat) return;
        document.getElementById('faqCategoryLabel').textContent = '— ' + cat.category_name;
        document.getElementById('btnEditCategory').style.display = '';
        document.getElementById('btnAddItem').style.display = '';
    }

    function loadItems(categoryId) {
        MubloRequest.requestQuery('/admin/faq/items?category_id=' + categoryId)
            .then(function(response) {
                renderItems(response.data.items || []);
            });
    }

    function renderItems(items) {
        const container = document.getElementById('itemList');
        if (!items.length) {
            container.innerHTML = '<div class="text-center text-muted py-5">등록된 FAQ가 없습니다.</div>';
            return;
        }

        let html = '<table class="table table-hover mb-0"><thead><tr>'
            + '<th style="width:50px" class="text-center">번호</th>'
            + '<th>질문</th>'
            + '<th style="width:80px" class="text-center">사용</th>'
            + '<th style="width:70px" class="text-center">정렬</th>'
            + '<th style="width:110px" class="text-center">관리</th>'
            + '</tr></thead><tbody>';

        items.forEach(function(item, idx) {
            const active = parseInt(item.is_active) === 1;
            const badge = active
                ? '<span class="badge bg-success">사용</span>'
                : '<span class="badge bg-secondary">미사용</span>';

            html += '<tr>'
                + '<td class="text-center text-muted">' + (idx + 1) + '</td>'
                + '<td>'
                + '  <div class="fw-semibold">' + escapeHtml(item.question) + '</div>'
                + '  <div class="text-muted small mt-1">' + stripHtml(truncate(item.answer, 100)) + '</div>'
                + '</td>'
                + '<td class="text-center">' + badge + '</td>'
                + '<td class="text-center">' + (item.sort_order || 0) + '</td>'
                + '<td class="text-center">'
                + '  <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="openItemModal(' + item.faq_id + ')" title="수정">'
                + '    <i class="bi bi-pencil"></i>'
                + '  </button>'
                + '  <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteItem(' + item.faq_id + ')" title="삭제">'
                + '    <i class="bi bi-trash"></i>'
                + '  </button>'
                + '</td>'
                + '</tr>';
        });

        html += '</tbody></table>';
        container.innerHTML = html;
    }

    // ─────────────────────────────────────────
    // 카테고리 CRUD
    // ─────────────────────────────────────────

    // 신규 카테고리 (모달)
    window.openCategoryModal = function() {
        document.getElementById('newCatName').value = '';
        document.getElementById('newCatSortOrder').value = '0';
        document.getElementById('newCatIsActive').checked = true;
        new bootstrap.Modal(document.getElementById('categoryModal')).show();
    };

    window.saveNewCategory = function() {
        const data = {
            category_name: document.getElementById('newCatName').value,
            sort_order: document.getElementById('newCatSortOrder').value,
            is_active: document.getElementById('newCatIsActive').checked ? 1 : 0,
        };
        MubloRequest.requestJson('/admin/faq/category', data, { loading: true })
            .then(function() { location.reload(); });
    };

    // 카테고리 수정 (인라인 폼)
    window.editCategory = function() {
        if (!currentCategoryId) return;
        const cat = categoriesData.find(function(c) { return parseInt(c.category_id) === currentCategoryId; });
        if (!cat) return;

        document.getElementById('catEditId').value = cat.category_id;
        document.getElementById('catName').value = cat.category_name;
        document.getElementById('catSortOrder').value = cat.sort_order || 0;
        document.getElementById('catIsActive').checked = parseInt(cat.is_active) === 1;
        document.getElementById('categoryFormTitle').textContent = '카테고리 수정';
        document.getElementById('btnDeleteCategory').style.display = '';
        document.getElementById('categoryFormCard').style.display = '';
    };

    window.closeCategoryForm = function() {
        document.getElementById('categoryFormCard').style.display = 'none';
    };

    window.saveCategory = function() {
        const editId = document.getElementById('catEditId').value;
        if (!editId) return;

        const data = {
            category_id: parseInt(editId),
            category_name: document.getElementById('catName').value,
            sort_order: document.getElementById('catSortOrder').value,
            is_active: document.getElementById('catIsActive').checked ? 1 : 0,
        };

        MubloRequest.requestJson('/admin/faq/category', data, { method: 'PUT', loading: true })
            .then(function() { location.reload(); });
    };

    window.deleteCategory = function() {
        if (!currentCategoryId) return;
        const cat = categoriesData.find(function(c) { return parseInt(c.category_id) === currentCategoryId; });
        const name = cat ? cat.category_name : '';

        if (!confirm('"' + name + '" 카테고리를 삭제하시겠습니까?\n하위 FAQ 항목도 모두 삭제됩니다.')) {
            return;
        }

        MubloRequest.requestJson('/admin/faq/category', { category_id: currentCategoryId }, { method: 'DELETE', loading: true })
            .then(function() { location.reload(); });
    };

    // ─────────────────────────────────────────
    // FAQ 항목 CRUD
    // ─────────────────────────────────────────
    window.openItemModal = function(editId) {
        const isEdit = !!editId;
        document.getElementById('itemModalTitle').textContent = isEdit ? 'FAQ 수정' : 'FAQ 추가';
        document.getElementById('itemEditId').value = editId || '';

        if (isEdit) {
            MubloRequest.requestQuery('/admin/faq/items?category_id=' + currentCategoryId)
                .then(function(response) {
                    const items = response.data.items || [];
                    const item = items.find(function(i) { return parseInt(i.faq_id) === editId; });
                    if (item) {
                        document.getElementById('itemQuestion').value = item.question;
                        document.getElementById('itemSortOrder').value = item.sort_order || 0;
                        document.getElementById('itemIsActive').checked = parseInt(item.is_active) === 1;
                        // 모달 표시 후 에디터에 내용 설정
                        var modal = new bootstrap.Modal(document.getElementById('itemModal'));
                        itemModalEl.addEventListener('shown.bs.modal', function onShown() {
                            setEditorContent(item.answer);
                            itemModalEl.removeEventListener('shown.bs.modal', onShown);
                        });
                        modal.show();
                    }
                });
        } else {
            document.getElementById('itemQuestion').value = '';
            document.getElementById('itemSortOrder').value = '0';
            document.getElementById('itemIsActive').checked = true;
            var modal = new bootstrap.Modal(document.getElementById('itemModal'));
            itemModalEl.addEventListener('shown.bs.modal', function onShown() {
                setEditorContent('');
                itemModalEl.removeEventListener('shown.bs.modal', onShown);
            });
            modal.show();
        }
    };

    window.saveItem = function() {
        const editId = document.getElementById('itemEditId').value;
        const data = {
            category_id: currentCategoryId,
            question: document.getElementById('itemQuestion').value,
            answer: getEditorContent(),
            sort_order: document.getElementById('itemSortOrder').value,
            is_active: document.getElementById('itemIsActive').checked ? 1 : 0,
        };

        if (editId) {
            data.faq_id = parseInt(editId);
            MubloRequest.requestJson('/admin/faq/item', data, { method: 'PUT', loading: true })
                .then(function() {
                    bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
                    loadItems(currentCategoryId);
                });
        } else {
            MubloRequest.requestJson('/admin/faq/item', data, { loading: true })
                .then(function() {
                    bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
                    loadItems(currentCategoryId);
                });
        }
    };

    window.deleteItem = function(faqId) {
        if (!confirm('이 FAQ를 삭제하시겠습니까?')) return;

        MubloRequest.requestJson('/admin/faq/item', { faq_id: faqId }, { method: 'DELETE', loading: true })
            .then(function() {
                loadItems(currentCategoryId);
            });
    };

    // ─────────────────────────────────────────
    // 스킨 설정
    // ─────────────────────────────────────────
    window.saveSkin = function() {
        const skin = document.getElementById('skinSelect').value;
        MubloRequest.requestJson('/admin/faq/skin', { skin: skin }, { method: 'PUT', loading: true })
            .then(function() {});
    };

    // ─────────────────────────────────────────
    // 유틸리티
    // ─────────────────────────────────────────
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    function stripHtml(html) {
        const div = document.createElement('div');
        div.innerHTML = html || '';
        return div.textContent || div.innerText || '';
    }

    function truncate(text, max) {
        if (!text) return '';
        return text.length > max ? text.substring(0, max) + '...' : text;
    }
})();
</script>
