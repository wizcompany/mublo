<?php
/**
 * Admin Menu - Tab 3: 유틸리티 메뉴
 *
 * Index.php에서 include되어 실행됨.
 * Index.php의 모든 PHP 변수에 접근 가능.
 *
 * @var array $utilityMenus 유틸리티 메뉴 (현재 활성)
 * @var array $allActiveItems 전체 활성 메뉴 아이템
 * @var array $groupedAllItems 제공자별 그룹화된 전체 아이템 ['core' => [], 'plugin' => [...], 'package' => [...]]
 */
?>
<div class="card mt-3">
    <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center" style="font-size:0.9rem">
        <span><i class="bi bi-tools me-2 text-pastel-orange"></i>유틸리티 메뉴 (상단 우측)</span>
        <button type="button" class="btn btn-primary btn-sm" id="btn-save-utility">
            <i class="bi bi-check-lg me-1"></i>저장
        </button>
    </div>
    <div class="card-body p-0">
        <div class="row g-0">
            <!-- 좌측: 추가 가능한 메뉴 (드래그 소스) — 평탄 구조 -->
            <div class="col-md-5 p-3 border-end" style="overflow-y:auto; max-height:calc(100vh - 340px);">
                <h6 class="mb-3 fw-bold text-muted">추가 가능한 메뉴 <small class="fw-normal" style="font-size:0.7rem">→ 드래그하여 추가</small></h6>
                <div id="utility-pool">
                    <?php if (!empty($groupedAllItems['core'])): ?>
                    <div class="pool-group-label mb-1" data-group-label="core">
                        <span class="badge bg-secondary" style="font-size:0.75rem">Core</span>
                    </div>
                    <?php foreach ($groupedAllItems['core'] as $item): ?>
                    <?php if (!$item['show_in_utility']): ?>
                    <div class="menu-pool-item d-flex align-items-center p-2 mb-1 border rounded"
                         data-item-id="<?= $item['item_id'] ?>"
                         data-pair-code="<?= htmlspecialchars($item['pair_code'] ?? '') ?>"
                         data-provider-type="core"
                         data-provider-name=""
                         style="cursor:grab;">
                        <i class="bi bi-arrows-move me-2 text-secondary" style="font-size:1.1rem; min-width:1.1rem;"></i>
                        <span class="badge bg-secondary me-2" style="font-size:0.75rem">Core</span>
                        <span style="font-size:0.9rem"><?= htmlspecialchars($item['label']) ?></span>
                        <?php if (!empty($item['pair_code'])): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem"><?= htmlspecialchars($item['pair_code']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($groupedAllItems['plugin'])): ?>
                    <div class="pool-group-label mb-1 mt-2" data-group-label="plugin">
                        <span class="badge bg-info" style="font-size:0.75rem">Plugin</span>
                    </div>
                    <?php foreach ($groupedAllItems['plugin'] as $pName => $pItems): ?>
                    <?php $avail = array_filter($pItems, fn($i) => !$i['show_in_utility']); ?>
                    <?php if (!empty($avail)): ?>
                    <div class="pool-provider-label small text-info fw-semibold ms-2 mb-1">— <?= htmlspecialchars($pName) ?></div>
                    <?php foreach ($avail as $item): ?>
                    <div class="menu-pool-item d-flex align-items-center p-2 mb-1 border rounded"
                         data-item-id="<?= $item['item_id'] ?>"
                         data-pair-code="<?= htmlspecialchars($item['pair_code'] ?? '') ?>"
                         data-provider-type="plugin"
                         data-provider-name="<?= htmlspecialchars($pName) ?>"
                         style="cursor:grab;">
                        <i class="bi bi-arrows-move me-2 text-secondary" style="font-size:1.1rem; min-width:1.1rem;"></i>
                        <span class="badge bg-info me-2" style="font-size:0.75rem"><?= htmlspecialchars($pName) ?></span>
                        <span style="font-size:0.9rem"><?= htmlspecialchars($item['label']) ?></span>
                        <?php if (!empty($item['pair_code'])): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem"><?= htmlspecialchars($item['pair_code']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (!empty($groupedAllItems['package'])): ?>
                    <div class="pool-group-label mb-1 mt-2" data-group-label="package">
                        <span class="badge bg-success" style="font-size:0.75rem">Package</span>
                    </div>
                    <?php foreach ($groupedAllItems['package'] as $pName => $pItems): ?>
                    <?php $avail = array_filter($pItems, fn($i) => !$i['show_in_utility']); ?>
                    <?php if (!empty($avail)): ?>
                    <div class="pool-provider-label small text-success fw-semibold ms-2 mb-1">— <?= htmlspecialchars($pName) ?></div>
                    <?php foreach ($avail as $item): ?>
                    <div class="menu-pool-item d-flex align-items-center p-2 mb-1 border rounded"
                         data-item-id="<?= $item['item_id'] ?>"
                         data-pair-code="<?= htmlspecialchars($item['pair_code'] ?? '') ?>"
                         data-provider-type="package"
                         data-provider-name="<?= htmlspecialchars($pName) ?>"
                         style="cursor:grab;">
                        <i class="bi bi-arrows-move me-2 text-secondary" style="font-size:1.1rem; min-width:1.1rem;"></i>
                        <span class="badge bg-success me-2" style="font-size:0.75rem"><?= htmlspecialchars($pName) ?></span>
                        <span style="font-size:0.9rem"><?= htmlspecialchars($item['label']) ?></span>
                        <?php if (!empty($item['pair_code'])): ?>
                        <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem"><?= htmlspecialchars($item['pair_code']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php $hasAny = false; foreach ($allActiveItems as $_i) { if (!$_i['show_in_utility']) { $hasAny = true; break; } } ?>
                    <?php if (!$hasAny): ?>
                    <p class="text-muted text-center small py-3">추가 가능한 메뉴가 없습니다.</p>
                    <?php endif; ?>
                </div>
            </div>
            <!-- 우측: 현재 메뉴 (드래그 대상) -->
            <div class="col-md-7 p-3">
                <h6 class="mb-3 fw-bold text-muted">현재 메뉴 <small class="fw-normal" style="font-size:0.7rem">← 드래그로 추가·순서변경 / 좌측으로 드래그하면 제거</small></h6>
                <div id="utility-list" style="min-height:80px; background:var(--bs-secondary-bg); border-radius:6px; padding:6px;">
                    <?php if (empty($utilityMenus)): ?>
                    <p class="text-muted text-center py-3 small">여기에 메뉴를 드래그하세요</p>
                    <?php else: ?>
                    <?php foreach ($utilityMenus as $menu): ?>
                    <?php $pt = $menu['provider_type'] ?? 'core'; $pn = $menu['provider_name'] ?? ''; ?>
                    <div class="menu-active-item d-flex align-items-center p-2 mb-1 border rounded"
                         data-item-id="<?= $menu['item_id'] ?>"
                         data-pair-code="<?= htmlspecialchars($menu['pair_code'] ?? '') ?>"
                         data-provider-type="<?= htmlspecialchars($pt) ?>"
                         data-provider-name="<?= htmlspecialchars($pn) ?>">
                        <i class="bi bi-arrows-move me-2 text-muted" style="cursor:grab; font-size:1.1rem; min-width:1.1rem;"></i>
                        <?php if ($pt === 'plugin'): ?>
                        <span class="badge bg-info me-2" style="font-size:0.75rem"><?= htmlspecialchars($pn ?: 'Plugin') ?></span>
                        <?php elseif ($pt === 'package'): ?>
                        <span class="badge bg-success me-2" style="font-size:0.75rem"><?= htmlspecialchars($pn ?: 'Package') ?></span>
                        <?php else: ?>
                        <span class="badge bg-secondary me-2" style="font-size:0.75rem">Core</span>
                        <?php endif; ?>
                        <span class="flex-grow-1" style="font-size:0.9rem">
                            <?= htmlspecialchars($menu['label']) ?>
                            <?php $minLevel = (int) ($menu['min_level'] ?? 0); ?>
                            <?php if ($minLevel > 0): ?>
                            <span class="badge bg-secondary ms-1">Lv.<?= $minLevel ?>+</span>
                            <?php endif; ?>
                            <?php if (!empty($menu['pair_code'])): ?>
                            <span class="badge bg-warning text-dark ms-1" style="font-size:0.65rem"><?= htmlspecialchars($menu['pair_code']) ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
