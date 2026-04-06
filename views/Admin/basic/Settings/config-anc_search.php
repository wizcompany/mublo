<?php
/**
 * 검색 설정 섹션
 *
 * 전체 검색 소스 순서 및 활성화 설정
 *
 * @var array $siteConfig   사이트 설정 배열 (search_source_order, search_enabled_sources, search_per_source)
 * @var array $searchSources 사용 가능한 검색 소스 [{source, label, always}]
 */

$sourceOrder    = $siteConfig['search_source_order']    ?? ['board'];
$enabledSources = $siteConfig['search_enabled_sources'] ?? ['board'];
$perSource      = (int) ($siteConfig['search_per_source'] ?? 5);

// sourceOrder 기준으로 searchSources 정렬, 목록에 없는 소스는 뒤에 추가
$sourceMap = [];
foreach ($searchSources as $s) {
    $sourceMap[$s['source']] = $s;
}

$orderedSources = [];
foreach ($sourceOrder as $src) {
    if (isset($sourceMap[$src])) {
        $orderedSources[] = $sourceMap[$src];
        unset($sourceMap[$src]);
    }
}
// 남은 소스 (신규 패키지 활성화 등) 뒤에 추가
foreach ($sourceMap as $s) {
    $orderedSources[] = $s;
}
?>

<div class="row">
    <div class="col-12 col-lg-8 mb-4">
        <div class="card">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-search me-2 text-pastel-blue"></i>검색 소스 설정
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">드래그하여 검색 결과 출력 순서를 변경하세요. 게시판은 항상 포함됩니다.</p>

                <ul class="list-group mb-3" id="searchSourceList">
                    <?php foreach ($orderedSources as $s): ?>
                        <?php
                        $src     = htmlspecialchars($s['source']);
                        $label   = htmlspecialchars($s['label']);
                        $always  = !empty($s['always']);
                        // enabledSources 빈 배열 = 전체 활성화
                        $enabled = empty($enabledSources) || in_array($s['source'], $enabledSources, true);
                        ?>
                        <li class="list-group-item d-flex align-items-center gap-2 search-source-item"
                            data-source="<?= $src ?>"
                            draggable="true">
                            <span class="search-source-handle text-muted" style="cursor:grab;" title="드래그하여 순서 변경">
                                <i class="bi bi-arrows-move"></i>
                            </span>
                            <?php if ($always): ?>
                                <input type="checkbox" class="form-check-input search-enable-check"
                                       checked disabled>
                                <span><?= $label ?></span>
                                <span class="badge bg-secondary ms-1">항상 포함</span>
                            <?php else: ?>
                                <input type="checkbox" class="form-check-input search-enable-check"
                                       <?= $enabled ? 'checked' : '' ?>>
                                <span><?= $label ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <input type="hidden" name="formData[site][search_source_order]"
                       id="searchSourceOrderHidden"
                       value="<?= htmlspecialchars(json_encode($sourceOrder)) ?>">
                <input type="hidden" name="formData[site][search_enabled_sources]"
                       id="searchEnabledSourcesHidden"
                       value="<?= htmlspecialchars(json_encode($enabledSources)) ?>">
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-4 mb-4">
        <div class="card">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-sliders me-2 text-pastel-purple"></i>검색 옵션
            </div>
            <div class="card-body">
                <label for="search_per_source" class="form-label">소스별 최대 결과 수</label>
                <input type="number" class="form-control" id="search_per_source"
                       name="formData[site][search_per_source]"
                       value="<?= $perSource ?>" min="1" max="20">
                <small class="text-muted">각 소스(게시판, 쇼핑몰 등)에서 최대 몇 건씩 표시할지 설정합니다. (1~20)</small>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    function syncSearchSources() {
        var items = document.querySelectorAll('#searchSourceList .search-source-item[data-source]');
        var order   = [];
        var enabled = [];

        items.forEach(function (li) {
            var src   = li.dataset.source;
            var check = li.querySelector('.search-enable-check');
            order.push(src);
            if (check && check.checked) {
                enabled.push(src);
            }
        });

        document.getElementById('searchSourceOrderHidden').value   = JSON.stringify(order);
        document.getElementById('searchEnabledSourcesHidden').value = JSON.stringify(enabled);
    }

    // 체크박스 변경 시 동기화
    document.getElementById('searchSourceList').addEventListener('change', function (e) {
        if (e.target.classList.contains('search-enable-check')) {
            syncSearchSources();
        }
    });

    // HTML5 드래그앤드롭
    var list      = document.getElementById('searchSourceList');
    var dragging  = null;

    list.addEventListener('dragstart', function (e) {
        dragging = e.target.closest('.search-source-item');
        if (dragging) {
            dragging.classList.add('search-source-dragging');
            e.dataTransfer.effectAllowed = 'move';
        }
    });

    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var over = e.target.closest('.search-source-item');
        if (over && over !== dragging) {
            var items = Array.from(list.querySelectorAll('.search-source-item'));
            var overIdx    = items.indexOf(over);
            var draggingIdx = items.indexOf(dragging);
            if (draggingIdx < overIdx) {
                list.insertBefore(dragging, over.nextSibling);
            } else {
                list.insertBefore(dragging, over);
            }
        }
    });

    list.addEventListener('dragend', function () {
        if (dragging) {
            dragging.classList.remove('search-source-dragging');
            dragging = null;
        }
        syncSearchSources();
    });

    // 초기 동기화
    syncSearchSources();
})();
</script>

<style>
.search-source-item {
    user-select: none;
}
.search-source-dragging {
    opacity: 0.5;
    background: #f0f4ff;
}
.search-source-handle:hover {
    color: var(--bs-primary) !important;
}
</style>
