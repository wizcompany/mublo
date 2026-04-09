<?php
/**
 * Admin Menu - Tab 2: 메인 메뉴 트리
 *
 * Index.php에서 include되어 실행됨.
 * Index.php의 모든 PHP 변수에 접근 가능.
 *
 * @var array $tree 메뉴 트리 (계층형)
 * @var array $allActiveItems 전체 활성 메뉴 아이템 (풀 목록용)
 * @var array $providerOptions 제공자 옵션 ['plugin' => [...], 'package' => [...]]
 */

/**
 * pair_code로 묶인 트리 노드를 병합 (같은 레벨에서 연속된 pair 노드를 하나로)
 */
function mergePairedTreeNodes(array $nodes): array {
    $merged = [];
    $skipCodes = [];

    foreach ($nodes as $node) {
        $code = $node['menu_code'] ?? '';
        if (in_array($code, $skipCodes, true)) {
            continue;
        }

        $pairCode = $node['pair_code'] ?? '';
        if ($pairCode) {
            foreach ($nodes as $other) {
                $otherCode = $other['menu_code'] ?? '';
                if ($otherCode === $code) continue;
                if (($other['pair_code'] ?? '') === $pairCode) {
                    $node['_paired_label'] = $other['label'] ?? '';
                    $node['_paired_menu_code'] = $otherCode;
                    $skipCodes[] = $otherCode;
                    break;
                }
            }
        }

        if (!empty($node['children'])) {
            $node['children'] = mergePairedTreeNodes($node['children']);
        }

        $merged[] = $node;
    }

    return $merged;
}

/**
 * 트리 노드 렌더링 헬퍼 함수
 */
function renderTreeNodes(array $nodes, int $depth = 0): string {
    $nodes = mergePairedTreeNodes($nodes);
    $html = '<ul class="tree-list list-unstyled sortable-tree" data-depth="' . $depth . '">';

    foreach ($nodes as $node) {
        $url = $node['url'] ?? '';
        $minLevel = (int) ($node['min_level'] ?? 0);
        $pairCode = $node['pair_code'] ?? '';
        $pairedMenuCode = $node['_paired_menu_code'] ?? '';
        $pairedLabel = $node['_paired_label'] ?? '';

        $html .= '<li class="tree-node mb-1" data-node-id="' . $node['node_id'] . '" data-menu-code="' . htmlspecialchars($node['menu_code']) . '" data-path-code="' . htmlspecialchars($node['path_code']) . '" data-depth="' . $depth . '" data-url="' . htmlspecialchars($url) . '" data-min-level="' . $minLevel . '"';
        if ($pairCode) {
            $html .= ' data-pair-code="' . htmlspecialchars($pairCode) . '"';
        }
        if ($pairedMenuCode) {
            $html .= ' data-paired-menu-code="' . htmlspecialchars($pairedMenuCode) . '"';
        }
        $html .= '>';
        $html .= '<div class="node-content d-flex align-items-center">';

        // depth 들여쓰기
        if ($depth > 0) {
            $html .= '<span class="depth-indicator text-muted me-2">└</span>';
        }

        // 제공자 배지
        $pType = $node['provider_type'] ?? 'core';
        $pName = $node['provider_name'] ?? '';
        if ($pType === 'plugin') {
            $html .= '<span class="badge bg-info me-1" style="font-size:0.75rem">' . htmlspecialchars($pName ?: 'Plugin') . '</span>';
        } elseif ($pType === 'package') {
            $html .= '<span class="badge bg-success me-1" style="font-size:0.75rem">' . htmlspecialchars($pName ?: 'Package') . '</span>';
        } else {
            $html .= '<span class="badge bg-secondary me-1" style="font-size:0.75rem">Core</span>';
        }

        $html .= '<span class="menu-label">' . htmlspecialchars($node['label']) . '</span>';

        // pair 인디케이터
        if ($pairedLabel) {
            $html .= '<span class="pair-indicator ms-1" style="font-size:0.85rem"><span class="text-muted">↔</span> ' . htmlspecialchars($pairedLabel) . '</span>';
        }

        // URL 표시
        if (!empty($url)) {
            $html .= '<code class="menu-url text-muted small ms-2">' . htmlspecialchars($url) . '</code>';
        }

        // 접근 레벨 badge (레벨 1 이상만 표시)
        if ($minLevel > 0) {
            $html .= '<span class="badge bg-secondary bg-opacity-50 text-muted small ms-2">Lv.' . $minLevel . '+</span>';
        }

        $html .= '<span class="flex-grow-1"></span>';
        $html .= '<button type="button" class="btn btn-sm btn-outline-danger btn-remove-node" data-node-id="' . $node['node_id'] . '" title="제거"><i class="bi bi-x"></i></button>';
        $html .= '</div>';

        // 하위 메뉴 영역 (비어있어도 드롭 가능하도록)
        $html .= '<div class="children-container">';
        if (!empty($node['children'])) {
            $html .= renderTreeNodes($node['children'], $depth + 1);
        } else {
            // 빈 하위 메뉴 드롭 영역
            $html .= '<ul class="tree-list list-unstyled sortable-tree child-drop-zone" data-depth="' . ($depth + 1) . '" style="display: none;"></ul>';
        }
        $html .= '</div>';

        $html .= '</li>';
    }

    $html .= '</ul>';
    return $html;
}
?>
<div class="row mt-3">
    <!-- 왼쪽: 메뉴 아이템 풀 -->
    <div class="col-md-4">
        <div class="card">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-list-nested me-2 text-pastel-blue"></i>메뉴 아이템
            </div>
            <div class="card-body" style="max-height: calc(100vh - 280px); overflow-y: auto;">
                <select id="pool-provider-filter" class="form-select form-select-sm mb-2">
                    <option value="">전체 제공자</option>
                    <option value="core">Core</option>
                    <?php if (!empty($providerOptions['plugin'])): ?>
                    <optgroup label="Plugin">
                        <?php foreach ($providerOptions['plugin'] as $pName): ?>
                        <option value="plugin:<?= htmlspecialchars($pName) ?>"><?= htmlspecialchars($pName) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                    <?php if (!empty($providerOptions['package'])): ?>
                    <optgroup label="Package">
                        <?php foreach ($providerOptions['package'] as $pName): ?>
                        <option value="package:<?= htmlspecialchars($pName) ?>"><?= htmlspecialchars($pName) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <?php endif; ?>
                </select>
                <input type="text" class="form-control form-control-sm mb-2"
                       id="item-pool-search" placeholder="메뉴명 검색...">
                <div id="item-pool">
                    <?php foreach ($allActiveItems as $item): ?>
                    <?php
                        $pType = $item['provider_type'] ?? 'core';
                        $pName = $item['provider_name'] ?? '';
                    ?>
                    <div class="item-pool-item d-flex justify-content-between align-items-center p-2 mb-1 border rounded"
                         data-menu-code="<?= htmlspecialchars($item['menu_code']) ?>"
                         data-label="<?= htmlspecialchars($item['label']) ?>"
                         data-url="<?= htmlspecialchars($item['url'] ?? '') ?>"
                         data-min-level="<?= (int) ($item['min_level'] ?? 0) ?>"
                         data-provider-type="<?= htmlspecialchars($pType) ?>"
                         data-provider-name="<?= htmlspecialchars($pName) ?>"
                         <?php if (!empty($item['pair_code'])): ?>data-pair-code="<?= htmlspecialchars($item['pair_code']) ?>"<?php endif; ?>
                         style="cursor: grab;">
                        <span class="d-flex align-items-center gap-1">
                            <?php if ($pType === 'plugin'): ?>
                            <span class="badge bg-info" style="font-size:0.75rem"><?= htmlspecialchars($pName ?: 'Plugin') ?></span>
                            <?php elseif ($pType === 'package'): ?>
                            <span class="badge bg-success" style="font-size:0.75rem"><?= htmlspecialchars($pName ?: 'Package') ?></span>
                            <?php else: ?>
                            <span class="badge bg-secondary" style="font-size:0.75rem">Core</span>
                            <?php endif; ?>
                            <?= htmlspecialchars($item['label']) ?>
                            <?php if (!empty($item['pair_code'])): ?>
                            <span class="badge bg-warning bg-opacity-75" style="font-size:0.65rem"><?= htmlspecialchars($item['pair_code']) ?></span>
                            <?php endif; ?>
                        </span>
                        <button type="button" class="btn btn-sm btn-outline-primary btn-add-to-tree"
                                data-menu-code="<?= htmlspecialchars($item['menu_code']) ?>">
                            <i class="bi bi-plus"></i>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- 오른쪽: 메뉴 트리 -->
    <div class="col-md-8">
        <div class="card">
            <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center" style="font-size:0.9rem">
                <span><i class="bi bi-diagram-3 me-2 text-pastel-blue"></i>메인 네비게이션</span>
                <button type="button" class="btn btn-primary btn-sm" id="btn-save-tree">
                    <i class="bi bi-check-lg me-1"></i>저장
                </button>
            </div>
            <div class="card-body" style="min-height: 400px;">
                <div id="menu-tree">
                    <?php if (empty($tree)): ?>
                    <p class="text-muted text-center py-3 empty-tree-message">
                        왼쪽에서 메뉴를 드래그하거나 + 버튼을 클릭하여 추가하세요.
                    </p>
                    <ul class="tree-list list-unstyled sortable-tree" data-depth="0" style="min-height: 100px; border: 2px dashed #ddd; border-radius: 4px;"></ul>
                    <?php else: ?>
                    <?php echo renderTreeNodes($tree); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
