<?php
/**
 * Admin Menu - Index
 *
 * 메뉴 관리 (탭 기반)
 *
 * View Context 접근:
 * - $this->columns() : ListColumnBuilder 팩토리
 * - $this->listRenderHelper : ListRenderHelper 인스턴스
 * - $this->pagination($data) : 페이지네이션 렌더링
 *
 * @var string $pageTitle 페이지 제목
 * @var string $activeTab 활성 탭
 * @var array $items 메뉴 아이템 목록 (페이징 적용)
 * @var array $allActiveItems 전체 활성 메뉴 아이템 (트리용)
 * @var array $tree 메뉴 트리 (계층형)
 * @var array $flatTree 메뉴 트리 (평면)
 * @var array $utilityMenus 유틸리티 메뉴
 * @var array $footerMenus 푸터 메뉴
 * @var array $mypageMenus 마이페이지 메뉴
 * @var array $levelOptions 레벨 옵션 [level_value => level_name]
 * @var array $targetOptions target 옵션
 * @var array $pagination 페이지네이션 정보
 * @var array $searchFields 검색 필드 옵션
 * @var array $currentSearch 현재 검색 조건
 * @var string $filterRaw 현재 제공자 필터 raw 값
 * @var array $providerOptions 제공자 옵션 ['plugin' => [...], 'package' => [...]]
 * @var array $enabledPlugins 활성화된 플러그인 목록
 * @var array $enabledPackages 활성화된 패키지 목록
 */

$tabs = [
    'items'   => '메뉴 아이템',
    'tree'    => '메인 메뉴',
    'utility' => '유틸리티 메뉴',
    'footer'  => '푸터 메뉴',
    'mypage'  => '마이페이지 메뉴',
];

// 제공자별 메뉴 그룹화 (utility/footer/mypage 탭 공통)
$groupedAllItems = ['core' => [], 'plugin' => [], 'package' => []];
foreach ($allActiveItems as $item) {
    $type = $item['provider_type'] ?? 'core';
    $name = $item['provider_name'] ?? '';
    if ($type === 'core') {
        $groupedAllItems['core'][] = $item;
    } elseif ($type === 'plugin') {
        $groupedAllItems['plugin'][$name][] = $item;
    } elseif ($type === 'package') {
        $groupedAllItems['package'][$name][] = $item;
    }
}

// On/Off 옵션
$onOffOptions = [
    '1' => 'ON',
    '0' => 'OFF',
];

// 상태 옵션
$statusOptions = [
    '1' => '활성',
    '0' => '비활성',
];

// 컬럼 정의 (ListColumnBuilder 사용, tab-items.php에서 $this->listRenderHelper 호출 시 필요)
$columns = $this->columns()
    ->checkbox('chk', '', ['id_key' => 'item_id', '_th_attr' => ['style' => 'width:40px']])
    ->add('row_no', 'No.', ['_th_attr' => ['style' => 'width:60px']])
    ->add('provider_type', '제공자', [
        '_th_attr' => ['style' => 'width:120px'],
        'formatter' => function ($value) {
            if ($value === 'plugin') {
                return '<span class="badge bg-info">Plugin</span>';
            }
            if ($value === 'package') {
                return '<span class="badge bg-success">Package</span>';
            }
            return '<span class="text-muted small">Core</span>';
        }
    ])
    ->add('provider_name', '제공자명', [
        '_th_attr' => ['style' => 'width:140px'],
        'formatter' => function ($value) {
            if ($value) {
                return '<small>' . htmlspecialchars($value) . '</small>';
            }
            return '';
        }
    ])
    ->add('label', '메뉴명', ['sortable' => true])
    ->add('url', 'URL', [
        'formatter' => function ($value) {
            if ($value) {
                return '<code>' . htmlspecialchars($value) . '</code>';
            }
            return '<span class="text-muted">-</span>';
        }
    ])
    ->select('min_level', '접근 레벨', $levelOptions, ['id_key' => 'item_id'])
    ->select('show_on_pc', 'PC', $onOffOptions, ['id_key' => 'item_id'])
    ->select('show_on_mobile', '모바일', $onOffOptions, ['id_key' => 'item_id'])
    ->select('is_active', '상태', $statusOptions, ['id_key' => 'item_id'])
    ->actions('actions', '관리', function ($row) {
        $id = $row['item_id'];
        return "
            <button type='button' class='btn btn-sm btn-default btn-edit-item' data-item-id='{$id}'>
                <i class='bi bi-pencil'></i>
            </button>
            <button type='button' class='btn btn-sm btn-default btn-delete-item'
                    data-item-id='{$id}'
                    data-label='" . htmlspecialchars($row['label']) . "'>
                <i class='bi bi-trash'></i>
            </button>
        ";
    }, ['_th_attr' => ['style' => 'width:100px']])
    ->build();
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '메뉴 관리') ?></h3>
                <p class="text-muted mb-0">사이트 메뉴를 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <button type="button" class="btn btn-primary" id="btn-add-item">
                    <i class="bi bi-plus-lg me-1"></i>메뉴 추가
                </button>
            </div>
        </div>
    </div>

    <!-- 탭 네비게이션 -->
    <ul class="nav nav-tabs mt-4" id="menuTabs" role="tablist">
        <?php foreach ($tabs as $tabId => $tabLabel): ?>
        <li class="nav-item" role="presentation">
            <button class="nav-link <?= $activeTab === $tabId ? 'active' : '' ?>"
                    id="<?= $tabId ?>-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#<?= $tabId ?>"
                    type="button"
                    role="tab">
                <?= htmlspecialchars($tabLabel) ?>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- 탭 컨텐츠 -->
    <div class="tab-content" id="menuTabsContent">
        <?php foreach ($tabs as $tabId => $tabLabel): ?>
        <div class="tab-pane fade <?= $activeTab === $tabId ? 'show active' : '' ?>"
             id="<?= $tabId ?>" role="tabpanel">
            <?php
            $tabFile = __DIR__ . '/tab-' . $tabId . '.php';
            if (is_file($tabFile)) {
                include $tabFile;
            }
            ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- 메뉴 아이템 추가/수정 모달 -->
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemModalTitle">메뉴 추가</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="item-form">
                    <input type="hidden" name="item_id" id="item_id" value="0">

                    <div class="mb-3">
                        <label class="form-label">메뉴명 <span class="text-danger">*</span></label>
                        <input type="text" name="label" id="item_label" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">URL</label>
                        <input type="text" name="url" id="item_url" class="form-control" placeholder="/about">
                        <div class="form-text">비워두면 클릭 불가 (부모 메뉴용)</div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-5">
                            <label class="form-label">제공자</label>
                            <select name="provider_type" id="item_provider_type" class="form-select">
                                <option value="core">Core</option>
                                <option value="plugin">Plugin</option>
                                <option value="package">Package</option>
                            </select>
                        </div>
                        <div class="col-7" id="provider_name_wrap" style="display:none">
                            <label class="form-label">제공자명</label>
                            <select id="item_provider_name_sel" class="form-select">
                                <!-- provider_type 변경 시 JS로 옵션 채움 -->
                            </select>
                            <!-- 실제 전송값 (hidden) -->
                            <input type="hidden" name="provider_name" id="item_provider_name">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">표시 대상</label>
                            <select name="visibility" id="item_visibility" class="form-select">
                                <option value="all">전체</option>
                                <option value="guest">비로그인만</option>
                                <option value="member">로그인만</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">메뉴 쌍 코드</label>
                            <input type="text" name="pair_code" id="item_pair_code" class="form-control"
                                   placeholder="예: auth, account" maxlength="30">
                            <div class="form-text">같은 코드끼리 묶음 (자동 포함)</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">접근 레벨</label>
                            <select name="min_level" id="item_min_level" class="form-select">
                                <?php foreach ($levelOptions as $value => $label): ?>
                                <option value="<?= $value ?>"><?= htmlspecialchars($label) ?> (Lv.<?= $value ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">해당 레벨 이상만 접근 가능</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">링크 타겟</label>
                            <select name="target" id="item_target" class="form-select">
                                <?php foreach ($targetOptions as $value => $label): ?>
                                <option value="<?= $value ?>"><?= htmlspecialchars($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" name="show_on_pc" id="item_show_on_pc" class="form-check-input" value="1" checked>
                                <label class="form-check-label" for="item_show_on_pc">PC에서 표시</label>
                            </div>
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" name="show_on_mobile" id="item_show_on_mobile" class="form-check-input" value="1" checked>
                                <label class="form-check-label" for="item_show_on_mobile">모바일에서 표시</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-2">
                                <input type="checkbox" name="is_active" id="item_is_active" class="form-check-input" value="1" checked>
                                <label class="form-check-label" for="item_is_active">활성화</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">취소</button>
                <button type="button" class="btn btn-primary" id="btn-save-item">저장</button>
            </div>
        </div>
    </div>
</div>

<style>
/* 심플한 트리 스타일 */
#menu-tree .tree-list {
    margin: 0;
    padding: 0;
    list-style: none;
}
.tree-node .node-content {
    display: flex;
    align-items: center;
    padding: 6px 10px;
    border-radius: 6px;
    margin: 2px 0;
    transition: background-color 0.15s;
}
.tree-node .node-content:hover {
    background-color: var(--bs-tertiary-bg, rgba(0,0,0,0.04));
}
/* 하위 메뉴 들여쓰기 - 단순하게 */
.children-container {
    margin-left: 24px;
}
/* 드래그 스타일 */
.tree-node.sortable-ghost {
    opacity: 1;
}
.tree-node.sortable-ghost > .node-content {
    background-color: var(--bs-primary-bg-subtle, rgba(13,110,253,0.15));
    border: 2px dashed var(--bs-primary, #0d6efd);
    border-radius: 6px;
}
.tree-node.sortable-ghost > .node-content > * {
    visibility: hidden;
}
.tree-node.sortable-chosen .node-content {
    background-color: var(--bs-primary-bg-subtle, rgba(13,110,253,0.12));
}
.tree-node.sortable-drag {
    opacity: 0.9;
}
/* 드롭 영역 */
.child-drop-zone {
    min-height: 32px !important;
    margin: 4px 0;
    border: 2px dashed var(--bs-border-color, #dee2e6) !important;
    border-radius: 6px;
}
/* URL 스타일 */
.menu-url {
    font-size: 0.75rem;
    padding: 1px 4px;
    background: transparent;
    opacity: 0.7;
}
#menu-tree .sortable-tree[data-depth="0"]:empty {
    min-height: 80px;
    border: 2px dashed var(--bs-border-color, #dee2e6);
    border-radius: 6px;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var itemModal = new bootstrap.Modal(document.getElementById('itemModal'));

    // ============================
    // 전체 선택
    // ============================
    const checkAll = document.querySelector('input[name="chk_all"]');
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="chk[]"]');
            checkboxes.forEach(function(checkbox) {
                checkbox.checked = checkAll.checked;
            });
        });
    }

    // ============================
    // 탭 1: 메뉴 아이템
    // ============================

    // 메뉴 추가 버튼
    document.getElementById('btn-add-item').addEventListener('click', function() {
        resetItemForm();
        document.getElementById('itemModalTitle').textContent = '메뉴 추가';
        itemModal.show();
    });

    // 메뉴 수정 버튼 (이벤트 위임)
    document.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-edit-item');
        if (btn) {
            var itemId = btn.dataset.itemId;
            loadItemForEdit(itemId);
        }

        var deleteBtn = e.target.closest('.btn-delete-item');
        if (deleteBtn) {
            var itemId = deleteBtn.dataset.itemId;
            var label = deleteBtn.dataset.label;
            MubloRequest.showConfirm('"' + label + '" 메뉴를 삭제하시겠습니까?', function() {
                deleteItem(itemId);
            }, { type: 'warning' });
        }
    });

    // 선택 삭제
    document.getElementById('btn-bulk-delete').addEventListener('click', function() {
        var checkboxes = document.querySelectorAll('input[name="chk[]"]:checked');
        if (checkboxes.length === 0) {
            MubloRequest.showAlert('삭제할 항목을 선택해주세요.', 'warning');
            return;
        }
        MubloRequest.showConfirm(checkboxes.length + '개 항목을 삭제하시겠습니까?', function() {
            var promises = [];
            checkboxes.forEach(function(checkbox) {
                promises.push(
                    MubloRequest.requestJson('/admin/menu/item-delete', { item_id: parseInt(checkbox.value) })
                );
            });

            Promise.all(promises).then(function() {
                location.reload();
            });
        }, { type: 'warning' });
    });

    // 메뉴 저장
    document.getElementById('btn-save-item').addEventListener('click', function() {
        saveItem();
    });

    function resetItemForm() {
        document.getElementById('item-form').reset();
        document.getElementById('item_id').value = '0';
        document.getElementById('item_visibility').value = 'all';
        document.getElementById('item_pair_code').value = '';
        document.getElementById('item_show_on_pc').checked = true;
        document.getElementById('item_show_on_mobile').checked = true;
        document.getElementById('item_is_active').checked = true;
        document.getElementById('item_provider_type').value = 'core';
        document.getElementById('item_provider_name').value = '';
        document.getElementById('item_provider_name_sel').innerHTML = '';
        document.getElementById('provider_name_wrap').style.display = 'none';
    }

    // 활성화된 플러그인/패키지 목록 (PHP → JS)
    var providerNameOptions = {
        plugin: <?= json_encode(array_values($enabledPlugins ?? []), JSON_UNESCAPED_UNICODE) ?>,
        package: <?= json_encode(array_values($enabledPackages ?? []), JSON_UNESCAPED_UNICODE) ?>
    };

    function updateProviderNameSelect(type, currentValue) {
        var sel = document.getElementById('item_provider_name_sel');
        var hidden = document.getElementById('item_provider_name');
        sel.innerHTML = '';

        var names = providerNameOptions[type] || [];
        names.forEach(function(name) {
            var opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            if (name === currentValue) opt.selected = true;
            sel.appendChild(opt);
        });

        // 선택값 반영
        hidden.value = sel.value || '';
    }

    // 제공자 셀렉트 변경 → hidden 동기화
    document.getElementById('item_provider_name_sel').addEventListener('change', function() {
        document.getElementById('item_provider_name').value = this.value;
    });

    // 제공자 유형 변경 시 제공자명 select 재구성
    document.getElementById('item_provider_type').addEventListener('change', function() {
        var wrap = document.getElementById('provider_name_wrap');
        if (this.value === 'core') {
            wrap.style.display = 'none';
            document.getElementById('item_provider_name').value = '';
        } else {
            wrap.style.display = '';
            updateProviderNameSelect(this.value, '');
        }
    });

    function loadItemForEdit(itemId) {
        MubloRequest.requestJson('/admin/menu/item-view?item_id=' + itemId, null, { method: 'GET' })
            .then(function(result) {
                if (result.result === 'success') {
                    var item = result.data;
                    document.getElementById('item_id').value = item.item_id;
                    document.getElementById('item_label').value = item.label || '';
                    document.getElementById('item_url').value = item.url || '';
                    document.getElementById('item_visibility').value = item.visibility || 'all';
                    document.getElementById('item_pair_code').value = item.pair_code || '';
                    document.getElementById('item_min_level').value = item.min_level || 0;
                    document.getElementById('item_target').value = item.target || '_self';
                    document.getElementById('item_show_on_pc').checked = item.show_on_pc == 1;
                    document.getElementById('item_show_on_mobile').checked = item.show_on_mobile == 1;
                    document.getElementById('item_is_active').checked = item.is_active == 1;
                    var providerType = item.provider_type || 'core';
                    document.getElementById('item_provider_type').value = providerType;
                    if (providerType !== 'core') {
                        document.getElementById('provider_name_wrap').style.display = '';
                        updateProviderNameSelect(providerType, item.provider_name || '');
                    } else {
                        document.getElementById('provider_name_wrap').style.display = 'none';
                        document.getElementById('item_provider_name').value = '';
                    }

                    document.getElementById('itemModalTitle').textContent = '메뉴 수정';
                    itemModal.show();
                } else {
                    MubloRequest.showAlert(result.message || '메뉴를 불러올 수 없습니다.', 'error');
                }
            });
    }

    function saveItem() {
        var data = {
            item_id: parseInt(document.getElementById('item_id').value) || 0,
            label: document.getElementById('item_label').value,
            url: document.getElementById('item_url').value,
            target: document.getElementById('item_target').value,
            visibility: document.getElementById('item_visibility').value || 'all',
            pair_code: document.getElementById('item_pair_code').value || '',
            min_level: parseInt(document.getElementById('item_min_level').value) || 0,
            show_on_pc: document.getElementById('item_show_on_pc').checked ? 1 : 0,
            show_on_mobile: document.getElementById('item_show_on_mobile').checked ? 1 : 0,
            is_active: document.getElementById('item_is_active').checked ? 1 : 0,
            provider_type: document.getElementById('item_provider_type').value || 'core',
            provider_name: document.getElementById('item_provider_name').value || ''
        };

        MubloRequest.requestJson('/admin/menu/item-store', data, { loading: true })
            .then(function(result) {
                if (result.result === 'success') {
                    MubloRequest.showToast(result.message, 'success');
                    location.reload();
                } else {
                    MubloRequest.showAlert(result.message || '저장에 실패했습니다.', 'error');
                }
            });
    }

    function deleteItem(itemId) {
        MubloRequest.requestJson('/admin/menu/item-delete', { item_id: itemId }, { loading: true })
            .then(function(result) {
                if (result.result === 'success') {
                    location.reload();
                } else {
                    MubloRequest.showAlert(result.message || '삭제에 실패했습니다.', 'error');
                }
            });
    }

    // ============================
    // 탭 2: 메인 메뉴 (트리)
    // ============================

    // 아이템 풀 필터 함수
    function filterItemPool() {
        var query = document.getElementById('item-pool-search').value.toLowerCase();
        var providerFilter = document.getElementById('pool-provider-filter').value;

        document.querySelectorAll('#item-pool .item-pool-item').forEach(function(item) {
            var matchesSearch = !query || item.dataset.label.toLowerCase().includes(query);
            var matchesProvider = true;
            if (providerFilter) {
                if (providerFilter.includes(':')) {
                    var parts = providerFilter.split(':');
                    matchesProvider = item.dataset.providerType === parts[0] && item.dataset.providerName === parts[1];
                } else {
                    matchesProvider = item.dataset.providerType === providerFilter;
                }
            }
            var visible = matchesSearch && matchesProvider;
            item.classList.toggle('d-none', !visible);
            item.classList.toggle('d-flex', visible);
        });
    }

    var itemPoolSearch = document.getElementById('item-pool-search');
    var poolProviderFilter = document.getElementById('pool-provider-filter');
    if (itemPoolSearch) itemPoolSearch.addEventListener('input', filterItemPool);
    if (poolProviderFilter) poolProviderFilter.addEventListener('change', filterItemPool);

    // 트리에 메뉴 추가 버튼 (이벤트 위임)
    var itemPoolEl = document.getElementById('item-pool');
    if (itemPoolEl) {
        itemPoolEl.addEventListener('click', function(e) {
            var btn = e.target.closest('.btn-add-to-tree');
            if (btn) {
                e.preventDefault();
                e.stopPropagation();
                var menuCode = btn.dataset.menuCode;
                if (menuCode) {
                    addToTree(menuCode, null);
                }
                return false;
            }
        });
    }

    // 트리 저장
    var btnSaveTree = document.getElementById('btn-save-tree');
    if (btnSaveTree) {
        btnSaveTree.addEventListener('click', function() {
            saveTree();
        });
    }

    // 트리 노드 제거
    document.addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-node')) {
            var btn = e.target.closest('.btn-remove-node');
            var nodeId = btn.dataset.nodeId;
            MubloRequest.showConfirm('이 메뉴를 트리에서 제거하시겠습니까?\n(하위 메뉴도 함께 제거됩니다)', function() {
                removeFromTree(nodeId);
            }, { type: 'warning' });
        }
    });

    function addToTree(menuCode, parentCode) {
        // item-pool에서 데이터 가져오기
        var poolItem = document.querySelector('.item-pool-item[data-menu-code="' + menuCode + '"]');
        var label = poolItem ? poolItem.dataset.label : menuCode;
        var url = poolItem ? poolItem.dataset.url : '';
        var minLevel = poolItem ? parseInt(poolItem.dataset.minLevel) || 0 : 0;
        var providerType = poolItem ? (poolItem.dataset.providerType || 'core') : 'core';
        var providerName = poolItem ? (poolItem.dataset.providerName || '') : '';
        var pairCode = poolItem ? (poolItem.dataset.pairCode || '') : '';

        // 트리에 동적으로 추가
        var rootTree = document.querySelector('#menu-tree .sortable-tree[data-depth="0"]');
        if (rootTree) {
            var newNode = createTreeNode(menuCode, label, 0, url, minLevel, providerType, providerName, pairCode);
            rootTree.appendChild(newNode);
            hideEmptyTreeMessage();
            MubloRequest.showToast('메뉴가 추가되었습니다. 저장 버튼을 눌러 저장하세요.', 'success');
        } else {
            MubloRequest.showAlert('트리를 찾을 수 없습니다.', 'error');
        }
    }

    function removeFromTree(nodeId) {
        // DOM에서 직접 제거 (저장은 별도로)
        var node = document.querySelector('.tree-node[data-node-id="' + nodeId + '"]');
        if (node) {
            node.remove();
            MubloRequest.showToast('메뉴가 제거되었습니다. 저장 버튼을 눌러 저장하세요.', 'success');
        }
    }

    function saveTree() {
        var rootTree = document.querySelector('#menu-tree > .sortable-tree, #menu-tree .sortable-tree[data-depth="0"]');
        var treeData = collectTreeData(rootTree);

        if (treeData.length === 0) {
            MubloRequest.showAlert('저장할 메뉴가 없습니다.', 'warning');
            return;
        }

        MubloRequest.requestJson('/admin/menu/tree-update', { tree: treeData }, { loading: true })
            .then(function(result) {
                if (result.result === 'success') {
                    MubloRequest.showToast(result.message || '저장되었습니다.', 'success');
                } else {
                    MubloRequest.showAlert(result.message || '저장에 실패했습니다.', 'error');
                }
            })
            .catch(function(error) {
                console.error('tree-update error:', error);
                MubloRequest.showAlert('서버 오류가 발생했습니다.', 'error');
            });
    }

    function collectTreeData(ul) {
        var data = [];
        if (!ul) return data;

        var children = ul.children;
        for (var i = 0; i < children.length; i++) {
            var li = children[i];
            if (!li.classList.contains('tree-node')) continue;

            var node = {
                menu_code: li.dataset.menuCode,
                children: []
            };

            // 하위 메뉴 찾기
            var childContainer = li.querySelector(':scope > .children-container');
            if (childContainer) {
                var childUl = childContainer.querySelector(':scope > ul.tree-list');
                if (childUl && childUl.children.length > 0) {
                    node.children = collectTreeData(childUl);
                }
            }

            data.push(node);

            // pair 아이템도 같은 레벨에 별도 노드로 추가
            if (li.dataset.pairedMenuCode) {
                data.push({
                    menu_code: li.dataset.pairedMenuCode,
                    children: []
                });
            }
        }

        return data;
    }

    // 트리에 새 노드 추가하는 헬퍼 함수
    function createTreeNode(menuCode, label, depth, url, minLevel, providerType, providerName, pairCode) {
        depth = depth || 0;
        url = url || '';
        minLevel = minLevel || 0;
        providerType = providerType || 'core';
        providerName = providerName || '';
        pairCode = pairCode || '';

        var newNode = document.createElement('li');
        newNode.className = 'tree-node mb-1';
        newNode.dataset.menuCode = menuCode;
        newNode.dataset.nodeId = 'new_' + Date.now();
        newNode.dataset.depth = depth;
        newNode.dataset.url = url;
        newNode.dataset.minLevel = minLevel;
        if (pairCode) {
            newNode.dataset.pairCode = pairCode;
        }

        var depthHtml = depth > 0 ? '<span class="depth-indicator text-muted me-2">└</span>' : '';

        var providerBadge = '';
        if (providerType === 'plugin') {
            providerBadge = '<span class="badge bg-info me-1" style="font-size:0.75rem">' + (providerName || 'Plugin') + '</span>';
        } else if (providerType === 'package') {
            providerBadge = '<span class="badge bg-success me-1" style="font-size:0.75rem">' + (providerName || 'Package') + '</span>';
        } else {
            providerBadge = '<span class="badge bg-secondary me-1" style="font-size:0.75rem">Core</span>';
        }

        var urlHtml = url ? '<code class="menu-url text-muted small ms-2">' + url + '</code>' : '';

        var levelHtml = '';
        if (minLevel > 0) {
            levelHtml = '<span class="badge bg-secondary bg-opacity-50 text-muted small ms-2">Lv.' + minLevel + '+</span>';
        }

        // pair 인디케이터 (쌍 아이템 자동 탐색)
        var pairHtml = '';
        if (pairCode) {
            var pairedItem = document.querySelector('#item-pool .item-pool-item[data-pair-code="' + pairCode + '"]:not([data-menu-code="' + menuCode + '"])');
            if (pairedItem) {
                newNode.dataset.pairedMenuCode = pairedItem.dataset.menuCode;
                pairHtml = '<span class="pair-indicator ms-1" style="font-size:0.85rem"><span class="text-muted">↔</span> ' + pairedItem.dataset.label + '</span>';
            }
        }

        newNode.innerHTML =
            '<div class="node-content d-flex align-items-center">' +
                depthHtml +
                providerBadge +
                '<span class="menu-label">' + label + '</span>' +
                pairHtml +
                urlHtml +
                levelHtml +
                '<span class="flex-grow-1"></span>' +
                '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-node" data-node-id="' + newNode.dataset.nodeId + '" title="제거"><i class="bi bi-x"></i></button>' +
            '</div>' +
            '<div class="children-container">' +
                '<ul class="tree-list list-unstyled sortable-tree child-drop-zone" data-depth="' + (depth + 1) + '" style="display: none;"></ul>' +
            '</div>';

        setTimeout(function() {
            var childList = newNode.querySelector('.child-drop-zone');
            if (childList) {
                initSortableTree(childList);
            }
        }, 0);

        return newNode;
    }

    function hideEmptyTreeMessage() {
        var msg = document.querySelector('.empty-tree-message');
        if (msg) msg.style.display = 'none';
    }

    function updateDepthIndicators() {
        document.querySelectorAll('#menu-tree .tree-node').forEach(function(node) {
            var parentList = node.closest('.tree-list');
            var depth = parseInt(parentList.dataset.depth) || 0;
            node.dataset.depth = depth;

            var nodeContent = node.querySelector('.node-content');
            var existingIndicator = nodeContent.querySelector('.depth-indicator');
            if (existingIndicator) existingIndicator.remove();

            if (depth > 0) {
                var depthEl = document.createElement('span');
                depthEl.className = 'depth-indicator text-muted me-2';
                depthEl.textContent = '└';
                nodeContent.insertBefore(depthEl, nodeContent.firstChild);
            }
        });
    }

    function initSortableTree(ul) {
        new Sortable(ul, {
            group: 'shared',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.5,
            invertSwap: true,
            invertedSwapThreshold: 0.5,
            draggable: '.tree-node, .item-pool-item',
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onStart: function(evt) {
                document.querySelectorAll('.child-drop-zone').forEach(function(zone) {
                    zone.style.display = 'block';
                });
            },
            onEnd: function(evt) {
                document.querySelectorAll('.child-drop-zone').forEach(function(zone) {
                    if (zone.children.length === 0) {
                        zone.style.display = 'none';
                    }
                });
                updateDepthIndicators();
            },
            onAdd: function(evt) {
                if (evt.item.classList.contains('item-pool-item')) {
                    var menuCode = evt.item.dataset.menuCode;
                    var label = evt.item.dataset.label;
                    var url = evt.item.dataset.url || '';
                    var minLevel = parseInt(evt.item.dataset.minLevel) || 0;
                    var depth = parseInt(evt.to.dataset.depth) || 0;
                    var providerType = evt.item.dataset.providerType || 'core';
                    var providerName = evt.item.dataset.providerName || '';
                    var pairCode = evt.item.dataset.pairCode || '';

                    var newNode = createTreeNode(menuCode, label, depth, url, minLevel, providerType, providerName, pairCode);
                    evt.item.replaceWith(newNode);
                    hideEmptyTreeMessage();
                }

                if (evt.to.children.length > 0) {
                    evt.to.style.display = 'block';
                }
            },
            onMove: function(evt) {
                return true;
            }
        });
    }

    // Sortable for item pool
    var itemPool = document.getElementById('item-pool');
    if (itemPool) {
        new Sortable(itemPool, {
            group: { name: 'shared', pull: 'clone', put: false },
            sort: false,
            animation: 150,
            filter: '.btn-add-to-tree',
            preventOnFilter: false,
            onStart: function(evt) {
                document.querySelectorAll('.child-drop-zone').forEach(function(zone) {
                    zone.style.display = 'block';
                });
            },
            onEnd: function(evt) {
                document.querySelectorAll('.child-drop-zone').forEach(function(zone) {
                    if (zone.children.length === 0) {
                        zone.style.display = 'none';
                    }
                });
            }
        });
    }

    // 모든 tree-list에 Sortable 적용
    document.querySelectorAll('#menu-tree .sortable-tree').forEach(function(ul) {
        initSortableTree(ul);
    });

    // ============================
    // 탭 3: 유틸리티 메뉴 (Sortable 드래그앤드롭)
    // ============================

    (function() {
        var pool = document.getElementById('utility-pool');
        var list = document.getElementById('utility-list');
        if (!pool || !list) return;

        function toActiveStyle(el) {
            el.classList.remove('menu-pool-item');
            el.classList.add('menu-active-item');
            el.style.cursor = '';
            var icon = el.querySelector('i');
            if (icon) { icon.className = 'bi bi-arrows-move me-2 text-muted'; icon.style.cursor = 'grab'; }
        }
        function toPoolStyle(el) {
            el.classList.remove('menu-active-item');
            el.classList.add('menu-pool-item');
            var icon = el.querySelector('i');
            if (icon) { icon.className = 'bi bi-arrows-move me-2 text-secondary'; icon.style.cursor = ''; }
        }
        function returnToGroup(el) {
            var type = el.dataset.providerType || 'core';
            var groupLabel = pool.querySelector('[data-group-label="' + type + '"]');
            if (!groupLabel) { pool.appendChild(el); return; }
            var cursor = groupLabel.nextElementSibling;
            var insertBefore = null;
            while (cursor) {
                if (cursor !== el && cursor.dataset && cursor.dataset.groupLabel !== undefined) {
                    insertBefore = cursor; break;
                }
                cursor = cursor.nextElementSibling;
            }
            if (insertBefore) { pool.insertBefore(el, insertBefore); } else { pool.appendChild(el); }
        }

        function getItemLabel(el) {
            var span = el.querySelector('.flex-grow-1') || el.querySelector(':scope > span:not(.badge):not(.pair-indicator)');
            if (!span) return '';
            var clone = span.cloneNode(true);
            clone.querySelectorAll('.badge, .pair-indicator').forEach(function(x) { x.remove(); });
            return clone.textContent.trim();
        }
        function movePairedToList(el) {
            var pairCode = el.dataset.pairCode;
            if (!pairCode) return;
            var paired = pool.querySelector('.menu-pool-item[data-pair-code="' + pairCode + '"]');
            if (!paired) return;
            var pairedLabel = getItemLabel(paired);
            if (!pairedLabel) return;
            el.dataset.pairedId = paired.dataset.itemId;
            el.dataset.pairedHtml = paired.outerHTML;
            var indicator = document.createElement('span');
            indicator.className = 'pair-indicator ms-1';
            indicator.style.fontSize = '0.85rem';
            indicator.innerHTML = '<span class="text-muted">↔</span> ' + pairedLabel;
            var labelEl = el.querySelector('.flex-grow-1') || el.querySelector(':scope > span:not(.badge):not(.pair-indicator)');
            if (labelEl) {
                if (labelEl.classList.contains('flex-grow-1')) {
                    labelEl.appendChild(indicator);
                } else {
                    labelEl.after(indicator);
                }
            }
            paired.remove();
        }
        function movePairedToPool(el) {
            if (!el.dataset.pairedId) return;
            el.querySelectorAll('.pair-indicator').forEach(function(x) { x.remove(); });
            if (el.dataset.pairedHtml) {
                var temp = document.createElement('div');
                temp.innerHTML = el.dataset.pairedHtml;
                var restored = temp.firstElementChild;
                if (restored) returnToGroup(restored);
            }
            delete el.dataset.pairedId;
            delete el.dataset.pairedHtml;
        }

        new Sortable(pool, {
            group: { name: 'utility-group', pull: true, put: true },
            draggable: '.menu-pool-item',
            sort: false,
            animation: 150,
            onAdd: function(evt) { toPoolStyle(evt.item); returnToGroup(evt.item); movePairedToPool(evt.item); }
        });
        new Sortable(list, {
            group: { name: 'utility-group', pull: true, put: true },
            animation: 150,
            onAdd: function(evt) { toActiveStyle(evt.item); movePairedToList(evt.item); }
        });

        document.getElementById('btn-save-utility').addEventListener('click', function() {
            var itemIds = [];
            list.querySelectorAll('[data-item-id]').forEach(function(el) {
                itemIds.push(parseInt(el.dataset.itemId));
                if (el.dataset.pairedId) itemIds.push(parseInt(el.dataset.pairedId));
            });
            MubloRequest.requestJson('/admin/menu/utility-update', { item_ids: itemIds }, { loading: true })
                .then(function(res) { MubloRequest.showToast(res.message || '저장되었습니다.', 'success'); reloadWithTab('utility'); });
        });

        // 페이지 로드 시 paired 아이템 한 줄 병합
        (function() {
            var items = Array.from(list.querySelectorAll(':scope > [data-pair-code]'));
            var seen = {};
            items.forEach(function(el) {
                var code = el.dataset.pairCode;
                if (!code) return;
                if (seen[code]) {
                    var primary = seen[code];
                    var secLabel = getItemLabel(el);
                    if (secLabel) {
                        primary.dataset.pairedId = el.dataset.itemId;
                        primary.dataset.pairedHtml = el.outerHTML;
                        var indicator = document.createElement('span');
                        indicator.className = 'pair-indicator ms-1';
                        indicator.style.fontSize = '0.85rem';
                        indicator.innerHTML = '<span class="text-muted">↔</span> ' + secLabel;
                        var labelEl = primary.querySelector('.flex-grow-1');
                        if (labelEl) labelEl.appendChild(indicator);
                        el.remove();
                    }
                } else {
                    seen[code] = el;
                }
            });
        })();
    })();

    // ============================
    // 탭 4: 푸터 메뉴 (Sortable 드래그앤드롭)
    // ============================

    (function() {
        var pool = document.getElementById('footer-pool');
        var list = document.getElementById('footer-list');
        if (!pool || !list) return;

        function toActiveStyle(el) {
            el.classList.remove('menu-pool-item');
            el.classList.add('menu-active-item');
            var icon = el.querySelector('i');
            if (icon) { icon.className = 'bi bi-arrows-move me-2 text-muted'; icon.style.cursor = 'grab'; }
        }
        function toPoolStyle(el) {
            el.classList.remove('menu-active-item');
            el.classList.add('menu-pool-item');
            var icon = el.querySelector('i');
            if (icon) { icon.className = 'bi bi-arrows-move me-2 text-secondary'; icon.style.cursor = ''; }
        }
        function returnToGroup(el) {
            var type = el.dataset.providerType || 'core';
            var groupLabel = pool.querySelector('[data-group-label="' + type + '"]');
            if (!groupLabel) { pool.appendChild(el); return; }
            var cursor = groupLabel.nextElementSibling;
            var insertBefore = null;
            while (cursor) {
                if (cursor !== el && cursor.dataset && cursor.dataset.groupLabel !== undefined) {
                    insertBefore = cursor; break;
                }
                cursor = cursor.nextElementSibling;
            }
            if (insertBefore) { pool.insertBefore(el, insertBefore); } else { pool.appendChild(el); }
        }

        function getItemLabel(el) {
            var span = el.querySelector('.flex-grow-1') || el.querySelector(':scope > span:not(.badge):not(.pair-indicator)');
            if (!span) return '';
            var clone = span.cloneNode(true);
            clone.querySelectorAll('.badge, .pair-indicator').forEach(function(x) { x.remove(); });
            return clone.textContent.trim();
        }
        function movePairedToList(el) {
            var pairCode = el.dataset.pairCode;
            if (!pairCode) return;
            var paired = pool.querySelector('.menu-pool-item[data-pair-code="' + pairCode + '"]');
            if (!paired) return;
            var pairedLabel = getItemLabel(paired);
            if (!pairedLabel) return;
            el.dataset.pairedId = paired.dataset.itemId;
            el.dataset.pairedHtml = paired.outerHTML;
            var indicator = document.createElement('span');
            indicator.className = 'pair-indicator ms-1';
            indicator.style.fontSize = '0.85rem';
            indicator.innerHTML = '<span class="text-muted">↔</span> ' + pairedLabel;
            var labelEl = el.querySelector('.flex-grow-1') || el.querySelector(':scope > span:not(.badge):not(.pair-indicator)');
            if (labelEl) {
                if (labelEl.classList.contains('flex-grow-1')) {
                    labelEl.appendChild(indicator);
                } else {
                    labelEl.after(indicator);
                }
            }
            paired.remove();
        }
        function movePairedToPool(el) {
            if (!el.dataset.pairedId) return;
            el.querySelectorAll('.pair-indicator').forEach(function(x) { x.remove(); });
            if (el.dataset.pairedHtml) {
                var temp = document.createElement('div');
                temp.innerHTML = el.dataset.pairedHtml;
                var restored = temp.firstElementChild;
                if (restored) returnToGroup(restored);
            }
            delete el.dataset.pairedId;
            delete el.dataset.pairedHtml;
        }

        new Sortable(pool, {
            group: { name: 'footer-group', pull: true, put: true },
            draggable: '.menu-pool-item',
            sort: false,
            animation: 150,
            onAdd: function(evt) { toPoolStyle(evt.item); returnToGroup(evt.item); movePairedToPool(evt.item); }
        });
        new Sortable(list, {
            group: { name: 'footer-group', pull: true, put: true },
            animation: 150,
            onAdd: function(evt) { toActiveStyle(evt.item); movePairedToList(evt.item); }
        });

        document.getElementById('btn-save-footer').addEventListener('click', function() {
            var itemIds = [];
            list.querySelectorAll('[data-item-id]').forEach(function(el) {
                itemIds.push(parseInt(el.dataset.itemId));
                if (el.dataset.pairedId) itemIds.push(parseInt(el.dataset.pairedId));
            });
            MubloRequest.requestJson('/admin/menu/footer-update', { item_ids: itemIds }, { loading: true })
                .then(function(res) { MubloRequest.showToast(res.message || '저장되었습니다.', 'success'); reloadWithTab('footer'); });
        });

        // 페이지 로드 시 paired 아이템 한 줄 병합
        (function() {
            var items = Array.from(list.querySelectorAll(':scope > [data-pair-code]'));
            var seen = {};
            items.forEach(function(el) {
                var code = el.dataset.pairCode;
                if (!code) return;
                if (seen[code]) {
                    var primary = seen[code];
                    var secLabel = getItemLabel(el);
                    if (secLabel) {
                        primary.dataset.pairedId = el.dataset.itemId;
                        primary.dataset.pairedHtml = el.outerHTML;
                        var indicator = document.createElement('span');
                        indicator.className = 'pair-indicator ms-1';
                        indicator.style.fontSize = '0.85rem';
                        indicator.innerHTML = '<span class="text-muted">↔</span> ' + secLabel;
                        var labelEl = primary.querySelector('.flex-grow-1');
                        if (labelEl) labelEl.appendChild(indicator);
                        el.remove();
                    }
                } else {
                    seen[code] = el;
                }
            });
        })();
    })();

    // ============================
    // 탭 5: 마이페이지 메뉴 (Sortable 드래그앤드롭)
    // ============================

    (function() {
        var pool = document.getElementById('mypage-pool');
        var list = document.getElementById('mypage-list');
        if (!pool || !list) return;

        function toActiveStyle(el) {
            el.classList.remove('menu-pool-item');
            el.classList.add('menu-active-item');
            var icon = el.querySelector('i');
            if (icon) { icon.className = 'bi bi-arrows-move me-2 text-muted'; icon.style.cursor = 'grab'; }
        }
        function toPoolStyle(el) {
            el.classList.remove('menu-active-item');
            el.classList.add('menu-pool-item');
            var icon = el.querySelector('i');
            if (icon) { icon.className = 'bi bi-arrows-move me-2 text-secondary'; icon.style.cursor = ''; }
        }
        function returnToGroup(el) {
            var type = el.dataset.providerType || 'core';
            var groupLabel = pool.querySelector('[data-group-label="' + type + '"]');
            if (!groupLabel) { pool.appendChild(el); return; }
            var cursor = groupLabel.nextElementSibling;
            var insertBefore = null;
            while (cursor) {
                if (cursor !== el && cursor.dataset && cursor.dataset.groupLabel !== undefined) {
                    insertBefore = cursor; break;
                }
                cursor = cursor.nextElementSibling;
            }
            if (insertBefore) { pool.insertBefore(el, insertBefore); } else { pool.appendChild(el); }
        }

        function getItemLabel(el) {
            var span = el.querySelector('.flex-grow-1') || el.querySelector(':scope > span:not(.badge):not(.pair-indicator)');
            if (!span) return '';
            var clone = span.cloneNode(true);
            clone.querySelectorAll('.badge, .pair-indicator').forEach(function(x) { x.remove(); });
            return clone.textContent.trim();
        }
        function movePairedToList(el) {
            var pairCode = el.dataset.pairCode;
            if (!pairCode) return;
            var paired = pool.querySelector('.menu-pool-item[data-pair-code="' + pairCode + '"]');
            if (!paired) return;
            var pairedLabel = getItemLabel(paired);
            if (!pairedLabel) return;
            el.dataset.pairedId = paired.dataset.itemId;
            el.dataset.pairedHtml = paired.outerHTML;
            var indicator = document.createElement('span');
            indicator.className = 'pair-indicator ms-1';
            indicator.style.fontSize = '0.85rem';
            indicator.innerHTML = '<span class="text-muted">↔</span> ' + pairedLabel;
            var labelEl = el.querySelector('.flex-grow-1') || el.querySelector(':scope > span:not(.badge):not(.pair-indicator)');
            if (labelEl) {
                if (labelEl.classList.contains('flex-grow-1')) {
                    labelEl.appendChild(indicator);
                } else {
                    labelEl.after(indicator);
                }
            }
            paired.remove();
        }
        function movePairedToPool(el) {
            if (!el.dataset.pairedId) return;
            el.querySelectorAll('.pair-indicator').forEach(function(x) { x.remove(); });
            if (el.dataset.pairedHtml) {
                var temp = document.createElement('div');
                temp.innerHTML = el.dataset.pairedHtml;
                var restored = temp.firstElementChild;
                if (restored) returnToGroup(restored);
            }
            delete el.dataset.pairedId;
            delete el.dataset.pairedHtml;
        }

        new Sortable(pool, {
            group: { name: 'mypage-group', pull: true, put: true },
            draggable: '.menu-pool-item',
            sort: false,
            animation: 150,
            onAdd: function(evt) { toPoolStyle(evt.item); returnToGroup(evt.item); movePairedToPool(evt.item); }
        });
        new Sortable(list, {
            group: {
                name: 'mypage-group',
                pull: function(to, from, dragEl) {
                    return !dragEl.classList.contains('is-system');
                },
                put: true
            },
            animation: 150,
            onAdd: function(evt) { toActiveStyle(evt.item); movePairedToList(evt.item); }
        });

        document.getElementById('btn-save-mypage').addEventListener('click', function() {
            var itemIds = [];
            list.querySelectorAll('[data-item-id]').forEach(function(el) {
                itemIds.push(parseInt(el.dataset.itemId));
                if (el.dataset.pairedId) itemIds.push(parseInt(el.dataset.pairedId));
            });
            MubloRequest.requestJson('/admin/menu/mypage-update', { item_ids: itemIds }, { loading: true })
                .then(function(res) { MubloRequest.showToast(res.message || '저장되었습니다.', 'success'); reloadWithTab('mypage'); });
        });

        // 페이지 로드 시 paired 아이템 한 줄 병합
        (function() {
            var items = Array.from(list.querySelectorAll(':scope > [data-pair-code]'));
            var seen = {};
            items.forEach(function(el) {
                var code = el.dataset.pairCode;
                if (!code) return;
                if (seen[code]) {
                    var primary = seen[code];
                    var secLabel = getItemLabel(el);
                    if (secLabel) {
                        primary.dataset.pairedId = el.dataset.itemId;
                        primary.dataset.pairedHtml = el.outerHTML;
                        var indicator = document.createElement('span');
                        indicator.className = 'pair-indicator ms-1';
                        indicator.style.fontSize = '0.85rem';
                        indicator.innerHTML = '<span class="text-muted">↔</span> ' + secLabel;
                        var labelEl = primary.querySelector('.flex-grow-1');
                        if (labelEl) labelEl.appendChild(indicator);
                        el.remove();
                    }
                } else {
                    seen[code] = el;
                }
            });
        })();
    })();

    function reloadWithTab(tab) {
        var url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.location.href = url.toString();
    }

    // 탭 전환 시 URL의 tab 쿼리 파라미터 동기화
    document.querySelectorAll('#menuTabs button[data-bs-toggle="tab"]').forEach(function(btn) {
        btn.addEventListener('shown.bs.tab', function(e) {
            var tabId = e.target.getAttribute('data-bs-target').replace('#', '');
            var url = new URL(window.location.href);
            if (tabId === 'items') {
                url.searchParams.delete('tab');
            } else {
                url.searchParams.set('tab', tabId);
            }
            history.replaceState(null, '', url.toString());
        });
    });
});

// 일괄 수정 후 콜백 (전역 함수)
function afterBulkUpdate(data) {
    if (data.result === 'success') {
        MubloRequest.showToast(data.message || '수정되었습니다.', 'success');
        location.reload();
    } else {
        MubloRequest.showAlert(data.message || '수정에 실패했습니다.', 'error');
    }
}
</script>
