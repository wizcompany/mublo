<?php
/** @var array $grid */
/** @var array $hiddenWidgets */
/** @var array $assetStyles */
/** @var array $assetScripts */
/** @var string $mode */
?>

<!-- 대시보드 상단 액션 -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h5 class="mb-0">대시보드</h5>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="resetDashboardLayout()">
            <i class="bi bi-arrow-counterclockwise me-1"></i>기본 레이아웃으로 복원
        </button>

        <?php if (!empty($hiddenWidgets)): ?>
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-eye-slash me-1"></i>숨긴 위젯 보기
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php
                $grouped = [];
                foreach ($hiddenWidgets as $hw) {
                    $source = $hw['source'] ?? 'package';
                    $grouped[$source][] = $hw;
                }
                $sourceLabels = ['core' => 'Core', 'package' => 'Package', 'plugin' => 'Plugin'];
                $first = true;
                ?>
                <?php foreach ($grouped as $source => $items): ?>
                    <?php if (!$first): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                    <li><h6 class="dropdown-header"><?= $sourceLabels[$source] ?? $source ?></h6></li>
                    <?php foreach ($items as $hw): ?>
                    <li>
                        <a class="dropdown-item" href="#" onclick="showWidget('<?= htmlspecialchars($hw['widget_id'], ENT_QUOTES, 'UTF-8') ?>'); return false;">
                            <i class="bi bi-plus-circle me-1"></i><?= htmlspecialchars($hw['title'], ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php $first = false; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- 위젯 그리드 (SortableJS 드래그 영역) -->
<?php if (empty($grid)): ?>
<div class="text-center text-muted py-5">
    <i class="bi bi-grid-3x3-gap fs-1 d-block mb-2"></i>
    <p>표시할 위젯이 없습니다.</p>
</div>
<?php else: ?>
    <div class="row g-4" id="widgetSortable">
        <?php foreach ($grid as $rowIndex => $row): ?>
            <?php foreach ($row as $widget): ?>
            <div class="<?= $widget['colClass'] ?> widget-col" data-widget-id="<?= htmlspecialchars($widget['widget_id'], ENT_QUOTES, 'UTF-8') ?>">
                <div class="card dashboard-widget h-100">
                    <div class="d-flex justify-content-between align-items-center px-3 pt-3 pb-0">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi bi-arrows-move text-muted drag-handle" role="button" title="드래그하여 이동"></i>
                            <span class="fw-semibold small"><?= htmlspecialchars($widget['title'], ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <button class="btn btn-link btn-sm text-muted p-0" title="숨기기" onclick="hideWidget('<?= htmlspecialchars($widget['widget_id'], ENT_QUOTES, 'UTF-8') ?>')">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="card-body">
                        <?= $widget['html'] ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 위젯 에셋 -->
<?php foreach ($assetStyles ?? [] as $href): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
<?php endforeach; ?>

<?php foreach ($assetScripts ?? [] as $src): ?>
<script src="<?= htmlspecialchars($src, ENT_QUOTES, 'UTF-8') ?>"></script>
<?php endforeach; ?>

<style>
.dashboard-widget .drag-handle {
    cursor: grab;
    font-size: 0.85rem;
}
.dashboard-widget .drag-handle:active {
    cursor: grabbing;
}
.dashboard-widget .btn-link {
    line-height: 1;
    font-size: 0.75rem;
}
/* 드래그 중 고스트 스타일 */
.widget-col.sortable-ghost .dashboard-widget {
    opacity: 0.4;
    border: 2px dashed var(--bs-primary);
}
.widget-col.sortable-chosen .dashboard-widget {
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
}
</style>

<script>
function hideWidget(widgetId) {
    MubloRequest.showConfirm('이 위젯을 숨기시겠습니까?', function() {
        MubloRequest.requestJson('/admin/dashboard/widget/hide', {
            widget_id: widgetId
        }).then(function() {
            location.reload();
        });
    }, { type: 'warning' });
}

function showWidget(widgetId) {
    MubloRequest.requestJson('/admin/dashboard/widget/show', {
        widget_id: widgetId
    }).then(function() {
        location.reload();
    });
}

function resetDashboardLayout() {
    MubloRequest.showConfirm('기본 레이아웃으로 복원하시겠습니까?\n모든 위젯 배치와 숨김 설정이 초기화됩니다.', function() {
        MubloRequest.requestJson('/admin/dashboard/layout/reset', {}).then(function() {
            location.reload();
        });
    }, { type: 'warning' });
}

// SortableJS 초기화
(function() {
    var container = document.getElementById('widgetSortable');
    if (!container) return;

    var saveTimer = null;

    Sortable.create(container, {
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        animation: 200,
        onEnd: function() {
            // debounce: 연속 드래그 시 마지막 배치만 저장
            if (saveTimer) clearTimeout(saveTimer);
            saveTimer = setTimeout(function() {
                var items = container.querySelectorAll('.widget-col');
                var widgetIds = [];
                items.forEach(function(el) {
                    widgetIds.push(el.dataset.widgetId);
                });

                MubloRequest.requestJson('/admin/dashboard/layout/reorder', {
                    widget_ids: widgetIds
                });
                // reload 없음 — DOM은 이미 이동된 상태
            }, 300);
        }
    });
})();
</script>
