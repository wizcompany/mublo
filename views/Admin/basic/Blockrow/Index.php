<?php
/**
 * Admin Blockrow - Index
 *
 * 블록 행 목록
 *
 * @var string $pageTitle 페이지 제목
 * @var array $rows 행 목록
 * @var array|null $pagination 페이지네이션 정보
 * @var array $positions 위치 목록
 * @var array $pages 페이지 목록
 * @var string|null $currentPosition 현재 선택된 위치
 * @var int $currentPageId 현재 선택된 페이지 ID
 */

// 컬럼 정의
$columns = $this->columns()
    ->checkbox('chk', '', ['id_key' => 'row_id', '_th_attr' => ['style' => 'width:40px']])
    ->add('sort', '', [
        'render' => fn($row) => '<i class="bi bi-arrows-move text-muted handle" style="cursor:move"></i>',
        '_th_attr' => ['style' => 'width:40px']
    ])
    ->add('row_id', 'ID', [
        'render' => fn($row) => '<small class="text-muted">#' . $row['row_id'] . '</small>',
        '_th_attr' => ['style' => 'width:60px']
    ])
    ->add('sort_order', '순서', [
        'render' => function($row) {
            $id = $row['row_id'];
            $order = $row['sort_order'] ?? 0;
            return "<input type='number' class='form-control form-control-sm sort-order-input'
                    name='sort_order[{$id}]' value='{$order}'
                    style='width:70px; text-align:center' min='0'>";
        },
        '_th_attr' => ['style' => 'width:80px', 'class' => 'text-center'],
        '_td_attr' => ['class' => 'text-center']
    ])
    ->add('admin_title', '관리제목', [
        'render' => function($row) {
            $title = $row['admin_title'] ?: '(제목없음)';
            return '<strong>' . htmlspecialchars($title) . '</strong>';
        }
    ])
    ->add('target', '출력 대상', [
        'render' => function($row) use ($positions, $pages) {
            if ($row['page_id']) {
                // 페이지 기반
                foreach ($pages as $page) {
                    if ($page['value'] == $row['page_id']) {
                        return '<span class="badge bg-primary">페이지</span> ' . htmlspecialchars($page['label']);
                    }
                }
                return '<span class="badge bg-primary">페이지</span> #' . $row['page_id'];
            } elseif ($row['position']) {
                // 위치 기반
                $label = $positions[$row['position']] ?? $row['position'];
                $menuCode = $row['position_menu'] ? ' <small class="text-muted">(' . htmlspecialchars($row['position_menu']) . ')</small>' : '';
                return '<span class="badge bg-secondary">위치</span> ' . htmlspecialchars($label) . $menuCode;
            }
            return '-';
        },
        '_th_attr' => ['style' => 'width:200px']
    ])
    ->add('column_count', '칸', [
        'render' => function($row) {
            $configured = $row['column_count'] ?? 1;
            $actual = $row['column_count_actual'] ?? 0;
            $hasContent = $row['column_has_content'] ?? [];

            // 칸이 아예 없으면 경고 표시
            if ($actual === 0) {
                return '<span class="column-box-warning" title="칸 설정 필요"><i class="bi bi-exclamation-triangle text-warning"></i></span>';
            }

            $boxes = '';
            for ($i = 0; $i < $configured; $i++) {
                if ($i < $actual) {
                    // 실제 생성된 칸
                    $hasCont = $hasContent[$i] ?? false;
                    $boxClass = $hasCont ? 'column-box column-box--filled' : 'column-box column-box--empty';
                    $title = $hasCont ? '콘텐츠 있음' : '콘텐츠 없음';
                } else {
                    // 설정됐지만 미생성된 칸
                    $boxClass = 'column-box column-box--missing';
                    $title = '칸 미생성';
                }
                $boxes .= "<span class=\"{$boxClass}\" title=\"{$title}\"></span>";
            }
            return "<div class=\"column-boxes\">{$boxes}</div>";
        },
        '_th_attr' => ['style' => 'width:100px', 'class' => 'text-center'],
        '_td_attr' => ['class' => 'text-center']
    ])
    ->add('width_type', '넓이', [
        'render' => function($row) {
            return $row['width_type'] == 0 ? '와이드' : '최대넓이';
        },
        '_th_attr' => ['style' => 'width:80px', 'class' => 'text-center'],
        '_td_attr' => ['class' => 'text-center']
    ])
    ->add('is_active', '상태', [
        'render' => function($row) {
            $id = $row['row_id'];
            $checked = !empty($row['is_active']) ? 'checked' : '';
            return '<div class="form-check form-switch mb-0 d-flex justify-content-center">'
                . "<input class=\"form-check-input\" type=\"checkbox\" role=\"switch\" {$checked} onchange=\"toggleRowActive({$id}, this)\">"
                . '</div>';
        },
        '_th_attr' => ['style' => 'width:80px', 'class' => 'text-center'],
        '_td_attr' => ['class' => 'text-center']
    ])
    ->actions('actions', '관리', function($row) {
        $id = $row['row_id'];
        $name = htmlspecialchars($row['admin_title'] ?: '행 #' . $id, ENT_QUOTES);

        $html = '<div class="btn-group btn-group-sm">';
        $html .= "<button type='button' class='btn btn-outline-info' onclick=\"previewRow({$id}, '{$name}')\" title='미리보기'><i class='bi bi-eye'></i></button>";
        $html .= "<a href='/admin/block-row/edit?id={$id}' class='btn btn-default' title='수정'><i class='bi bi-pencil'></i></a>";
        $html .= "<a href='/admin/block-row/editor?id={$id}' class='btn btn-outline-success' title='에디터 (Beta)'><i class='bi bi-layout-text-window-reverse'></i></a>";
        $html .= "<button type='button' class='btn btn-outline-primary' onclick=\"openCopyModal({$id}, '{$name}')\" title='복사'><i class='bi bi-copy'></i></button>";
        $html .= "<button type='button' class='btn btn-outline-secondary' onclick=\"openMoveModal({$id}, '{$name}')\" title='이동'><i class='bi bi-arrows-move'></i></button>";
        $html .= "<button type='button' class='btn btn-outline-danger' onclick=\"deleteRow({$id}, '{$name}')\" title='삭제'><i class='bi bi-trash'></i></button>";
        $html .= '</div>';

        return $html;
    }, ['_th_attr' => ['style' => 'width:190px']])
    ->build();
?>
<style>
/* 칸 박스 스타일 */
.column-boxes {
    display: inline-flex;
    gap: 3px;
    align-items: center;
}
.column-box {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 3px;
    border: 1px solid #999;
}
.column-box--filled {
    background-color: #198754;
    border-color: #198754;
}
.column-box--empty {
    background-color: #fff;
    border-color: #ccc;
}
.column-box--missing {
    background: repeating-linear-gradient(
        45deg,
        #f8d7da,
        #f8d7da 2px,
        #fff 2px,
        #fff 4px
    );
    border-color: #dc3545;
}
.column-box-warning {
    font-size: 16px;
}
/* 미리보기 */
.preview-iframe {
    width: 100%;
    min-height: 400px;
    border: none;
}
</style>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '블록 행 관리') ?></h3>
                <p class="text-muted mb-0">
                    <?php if ($currentPageId > 0):
                        $currentPageLabel = '';
                        foreach ($pages as $page) {
                            if ($page['value'] == $currentPageId) {
                                $currentPageLabel = $page['label'];
                                break;
                            }
                        }
                    ?>
                    <span class="badge bg-primary me-1">페이지</span>
                    <?= htmlspecialchars($currentPageLabel) ?> 페이지의 행을 관리합니다.
                    <?php else: ?>
                    위치 기반 블록 행을 관리합니다.
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <?php if ($currentPageId > 0): ?>
                <a href="/admin/block-page/edit?id=<?= $currentPageId ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>페이지 설정
                </a>
                <?php endif; ?>
                <a href="/admin/block-row/create<?= $currentPageId > 0 ? '?page_id=' . $currentPageId : '' ?>" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>행 추가
                </a>
            </div>
        </div>
    </div>

    <!-- 요약 영역 -->
    <div class="mt-4 mb-2">
        <form method="get" class="row g-2 align-items-center">
            <div class="col-auto">
                <span class="ov">
                    <span class="ov-txt"><a href="/admin/block-row">전체</a></span>
                    <span class="ov-num"><b><?= count($rows) ?></b> 개</span>
                </span>
            </div>
            <?php if ($currentPageId <= 0): ?>
            <div class="col-auto">
                <select name="position" class="form-select" onchange="this.form.submit()">
                    <option value="">위치: 전체</option>
                    <?php foreach ($positions as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $currentPosition === $key ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- 행 목록 폼 -->
    <form name="flist" id="flist">
        <div class="table-responsive">
            <?= $this->listRenderHelper
                ->setColumns($columns)
                ->setRows($rows)
                ->setSkin('table/basic')
                ->setWrapAttr(['class' => 'table table-hover align-middle', 'id' => 'row-list'])
                ->setTrAttr(fn($row) => ['data-row-id' => $row['row_id']])
                ->showHeader(true)
                ->render() ?>
        </div>

        <!-- 하단 액션바 -->
        <div class="row gx-2 justify-content-between align-items-center my-2">
            <div class="col-auto">
                <div class="d-flex gap-1">
                    <button type="button" class="btn btn-default mublo-submit"
                            data-target="/admin/block-row/list-delete"
                            data-callback="afterListDelete">
                        <i class="d-inline d-md-none bi bi-trash"></i>
                        <span class="d-none d-md-inline">선택 삭제</span>
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="updateSelectedOrders()">
                        <i class="bi bi-arrow-down-up me-1"></i>
                        <span class="d-none d-md-inline">선택 순서변경</span>
                    </button>
                </div>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-primary" onclick="saveAllOrders()">
                    <i class="bi bi-save me-1"></i>순서 일괄저장
                </button>
            </div>
        </div>
    </form>

    <!-- 페이지네이션 -->
    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                <li class="page-item <?= $i == $pagination['page'] ? 'active' : '' ?>">
                    <a class="page-link" href="?position=<?= $currentPosition ?>&page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
    <?php endif; ?>
</div>

<!-- 복사/이동 모달 -->
<div class="modal fade" id="rowActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="rowActionModalTitle">행 복사</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="actionRowId" value="">
                <input type="hidden" id="actionType" value="copy">

                <div class="mb-3">
                    <label class="form-label">대상 유형</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="targetType" id="targetPosition" value="position" checked>
                        <label class="btn btn-outline-primary" for="targetPosition">위치 기반</label>
                        <input type="radio" class="btn-check" name="targetType" id="targetPage" value="page">
                        <label class="btn btn-outline-primary" for="targetPage">페이지 기반</label>
                    </div>
                </div>

                <div id="positionSelectArea" class="mb-3">
                    <label class="form-label">대상 위치</label>
                    <select class="form-select" id="targetPositionValue">
                        <?php foreach ($positions as $key => $label): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="pageSelectArea" class="mb-3" style="display:none;">
                    <label class="form-label">대상 페이지</label>
                    <select class="form-select" id="targetPageValue">
                        <?php foreach ($pages as $page): ?>
                            <option value="<?= $page['value'] ?>"><?= htmlspecialchars($page['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($pages)): ?>
                    <div class="form-text text-warning">등록된 블록 페이지가 없습니다.</div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" id="confirmActionBtn" onclick="executeRowAction()">복사</button>
            </div>
        </div>
    </div>
</div>

<!-- 미리보기 모달 -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalTitle"><i class="bi bi-eye me-2"></i>미리보기</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div id="previewLoading" class="text-center text-muted py-5">
                    <div class="spinner-border" role="status"></div>
                    <p class="mt-2">미리보기 생성 중...</p>
                </div>
                <div id="previewError" style="display:none;" class="p-3"></div>
                <iframe id="previewFrame" class="preview-iframe" style="display:none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <a href="#" class="btn btn-primary" id="previewEditBtn">
                    <i class="bi bi-pencil me-1"></i>수정
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// 전체 선택
document.addEventListener('DOMContentLoaded', function() {
    const checkAll = document.querySelector('input[name="chk_all"]');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="chk[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = checkAll.checked;
            });
        });
    }
});

// 사용 여부 토글
function toggleRowActive(rowId, el) {
    MubloRequest.requestJson('/admin/block-row/toggle-active', {
        row_id: rowId
    }).then(response => {
        MubloRequest.showToast(response.message, 'success');
    }).catch(err => {
        el.checked = !el.checked;
        console.error(err);
    });
}

// 행 삭제 (단건)
function deleteRow(rowId, rowName) {
    MubloRequest.showConfirm(`'${rowName}'을(를) 삭제하시겠습니까?`, function() {
        MubloRequest.requestJson('/admin/block-row/delete', {
            row_id: rowId
        }).then(response => {
            MubloRequest.showToast(response.message || '삭제되었습니다.', 'success');
            location.reload();
        }).catch(err => {
            console.error(err);
        });
    }, { type: 'warning' });
}

// 일괄 삭제 후 콜백
function afterListDelete(data) {
    if (data.result === 'success') {
        MubloRequest.showToast(data.message || '삭제되었습니다.', 'success');
        location.reload();
    } else {
        MubloRequest.showAlert(data.message || '삭제에 실패했습니다.', 'error');
    }
}

// 드래그 앤 드롭 정렬
document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('row-list');
    const tbody = list ? list.querySelector('tbody') : null;

    if (tbody && typeof Sortable !== 'undefined') {
        new Sortable(tbody, {
            handle: '.handle',
            animation: 150,
            onEnd: function(evt) {
                const rowIds = [];
                tbody.querySelectorAll('tr[data-row-id]').forEach(row => {
                    rowIds.push(parseInt(row.dataset.rowId));
                });

                MubloRequest.requestJson('/admin/block-row/order-update', {
                    row_ids: rowIds
                }).then(response => {
                    if (response.result === 'success') {
                        // 드래그 성공 시 순서 입력 필드 값 업데이트
                        tbody.querySelectorAll('tr[data-row-id]').forEach((row, index) => {
                            const rowId = row.dataset.rowId;
                            const input = row.querySelector(`input[name="sort_order[${rowId}]"]`);
                            if (input) {
                                input.value = index;
                            }
                        });
                    } else {
                        MubloRequest.showAlert(response.message || '정렬 저장에 실패했습니다.', 'error');
                        location.reload();
                    }
                }).catch(err => {
                    console.error(err);
                    location.reload();
                });
            }
        });
    }

    // 대상 유형 라디오 변경 시
    document.querySelectorAll('input[name="targetType"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const positionArea = document.getElementById('positionSelectArea');
            const pageArea = document.getElementById('pageSelectArea');
            if (this.value === 'position') {
                positionArea.style.display = 'block';
                pageArea.style.display = 'none';
            } else {
                positionArea.style.display = 'none';
                pageArea.style.display = 'block';
            }
        });
    });
});

// 복사 모달 열기
function openCopyModal(rowId, rowName) {
    document.getElementById('rowActionModalTitle').textContent = `'${rowName}' 복사`;
    document.getElementById('actionRowId').value = rowId;
    document.getElementById('actionType').value = 'copy';
    document.getElementById('confirmActionBtn').textContent = '복사';
    document.getElementById('confirmActionBtn').className = 'btn btn-primary';

    // 위치 기반 기본 선택
    document.getElementById('targetPosition').checked = true;
    document.getElementById('positionSelectArea').style.display = 'block';
    document.getElementById('pageSelectArea').style.display = 'none';

    const modal = new bootstrap.Modal(document.getElementById('rowActionModal'));
    modal.show();
}

// 이동 모달 열기
function openMoveModal(rowId, rowName) {
    document.getElementById('rowActionModalTitle').textContent = `'${rowName}' 이동`;
    document.getElementById('actionRowId').value = rowId;
    document.getElementById('actionType').value = 'move';
    document.getElementById('confirmActionBtn').textContent = '이동';
    document.getElementById('confirmActionBtn').className = 'btn btn-warning';

    // 위치 기반 기본 선택
    document.getElementById('targetPosition').checked = true;
    document.getElementById('positionSelectArea').style.display = 'block';
    document.getElementById('pageSelectArea').style.display = 'none';

    const modal = new bootstrap.Modal(document.getElementById('rowActionModal'));
    modal.show();
}

// 복사/이동 실행
function executeRowAction() {
    const rowId = parseInt(document.getElementById('actionRowId').value);
    const actionType = document.getElementById('actionType').value;
    const targetType = document.querySelector('input[name="targetType"]:checked').value;

    let data = { row_id: rowId };

    if (targetType === 'position') {
        data.position = document.getElementById('targetPositionValue').value;
    } else {
        const pageValue = document.getElementById('targetPageValue').value;
        if (!pageValue) {
            MubloRequest.showAlert('대상 페이지를 선택해주세요.', 'warning');
            return;
        }
        data.page_id = parseInt(pageValue);
    }

    const url = actionType === 'copy' ? '/admin/block-row/copy' : '/admin/block-row/move';
    const actionLabel = actionType === 'copy' ? '복사' : '이동';

    MubloRequest.requestJson(url, data).then(response => {
        bootstrap.Modal.getInstance(document.getElementById('rowActionModal')).hide();
        MubloRequest.showToast(response.message || `${actionLabel}되었습니다.`, 'success');
        location.reload();
    }).catch(err => {
        console.error(err);
    });
}

// 선택된 행의 순서 변경
function updateSelectedOrders() {
    const checkedBoxes = document.querySelectorAll('input[name="chk[]"]:checked');

    if (checkedBoxes.length === 0) {
        MubloRequest.showAlert('순서를 변경할 항목을 선택해주세요.', 'warning');
        return;
    }

    const orders = {};
    let hasChange = false;

    checkedBoxes.forEach(checkbox => {
        const rowId = checkbox.value;
        const input = document.querySelector(`input[name="sort_order[${rowId}]"]`);
        if (input) {
            orders[rowId] = parseInt(input.value) || 0;
            hasChange = true;
        }
    });

    if (!hasChange) {
        MubloRequest.showAlert('변경할 순서 정보가 없습니다.', 'warning');
        return;
    }

    MubloRequest.requestJson('/admin/block-row/order-set', {
        orders: orders
    }).then(response => {
        MubloRequest.showToast(response.message || '순서가 변경되었습니다.', 'success');
        location.reload();
    }).catch(err => {
        console.error(err);
    });
}

// 전체 순서 일괄 저장
function saveAllOrders() {
    const inputs = document.querySelectorAll('.sort-order-input');

    if (inputs.length === 0) {
        MubloRequest.showAlert('저장할 항목이 없습니다.', 'warning');
        return;
    }

    const orders = {};
    inputs.forEach(input => {
        const match = input.name.match(/sort_order\[(\d+)\]/);
        if (match) {
            orders[match[1]] = parseInt(input.value) || 0;
        }
    });

    MubloRequest.requestJson('/admin/block-row/order-set', {
        orders: orders
    }).then(response => {
        MubloRequest.showToast(response.message || '순서가 저장되었습니다.', 'success');
        location.reload();
    }).catch(err => {
        console.error(err);
    });
}

// 행 미리보기
function previewRow(rowId, rowName) {
    var modal = new bootstrap.Modal(document.getElementById('previewModal'));
    var title = document.getElementById('previewModalTitle');
    var loading = document.getElementById('previewLoading');
    var errorEl = document.getElementById('previewError');
    var frame = document.getElementById('previewFrame');
    var editBtn = document.getElementById('previewEditBtn');

    title.innerHTML = '<i class="bi bi-eye me-2"></i>' + rowName + ' 미리보기';
    editBtn.href = '/admin/block-row/edit?id=' + rowId;
    loading.style.display = '';
    errorEl.style.display = 'none';
    frame.style.display = 'none';

    modal.show();

    MubloRequest.requestQuery('/admin/block-row/preview-row', {
        row_id: rowId
    }).then(function(response) {
        if (response.data && response.data.html) {
            renderPreviewIframe(response.data.html, response.data.skinCss || []);
        } else {
            loading.style.display = 'none';
            errorEl.style.display = '';
            errorEl.innerHTML = '<div class="alert alert-warning mb-0">미리보기 데이터가 없습니다.</div>';
        }
    }).catch(function() {
        loading.style.display = 'none';
        errorEl.style.display = '';
        errorEl.innerHTML = '<div class="alert alert-danger mb-0">미리보기를 불러올 수 없습니다.</div>';
    });
}

function renderPreviewIframe(html, skinCss) {
    var loading = document.getElementById('previewLoading');
    var frame = document.getElementById('previewFrame');

    var skinCssTags = '';
    if (skinCss && skinCss.length > 0) {
        skinCssTags = skinCss.map(function(path) {
            return '<link rel="stylesheet" href="' + path + '">';
        }).join('\n');
    }

    var srcdoc = '<!DOCTYPE html>\n'
        + '<html lang="ko"><head><meta charset="UTF-8">\n'
        + '<meta name="viewport" content="width=device-width, initial-scale=1.0">\n'
        + '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@100..900&display=swap">\n'
        + '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">\n'
        + '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/aos@2/dist/aos.css">\n'
        + '<link rel="stylesheet" href="/assets/css/front-common.css">\n'
        + '<link rel="stylesheet" href="/assets/css/block.css">\n'
        + skinCssTags + '\n'
        + '<style>body { margin: 0; padding: 16px 0; background: #f8f9fa; font-family: "Noto Sans KR", sans-serif; overflow-x: hidden; }\n'
        + '*, *::before, *::after { box-sizing: border-box; }\n'
        + 'ul, ol { list-style: none; margin: 0; padding: 0; }\n'
        + '.block-container--wide, .block-container--contained { max-width: 100%; }</style>\n'
        + '</head><body>\n'
        + html + '\n'
        + '<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"><\/script>\n'
        + '<script src="https://cdn.jsdelivr.net/npm/aos@2/dist/aos.js"><\/script>\n'
        + '<script src="/assets/js/MubloItemLayout.js"><\/script>\n'
        + '<script>\n'
        + 'document.addEventListener("DOMContentLoaded", function() {\n'
        + '  if (typeof AOS !== "undefined") AOS.init({ once: true });\n'
        + '  if (typeof MubloItemLayout !== "undefined") MubloItemLayout.initAll();\n'
        + '});\n'
        + '<\/script>\n'
        + '</body></html>';

    frame.srcdoc = srcdoc;
    frame.onload = function() {
        loading.style.display = 'none';
        frame.style.display = '';

        // iframe 높이 자동 조절
        try {
            var bodyHeight = frame.contentDocument.body.scrollHeight;
            frame.style.height = Math.min(Math.max(bodyHeight + 32, 200), 600) + 'px';
        } catch(e) {}
    };
}
</script>
