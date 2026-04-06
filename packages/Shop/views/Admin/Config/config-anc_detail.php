<?php
/**
 * 쇼핑몰 설정 - 상세정보 탭 순서
 *
 * @var array $config 쇼핑몰 설정
 * @var array $activeTemplates 활성 상품정보 템플릿 목록
 */
$allTabTypes  = ['detail', 'template', 'review', 'qna', 'faq'];
$tabLabels    = [
    'detail'   => '상세설명',
    'template' => '상품정보',
    'review'   => '구매후기',
    'qna'      => 'Q&A',
    'faq'      => 'FAQ',
];
$dynamicTypes = ['review', 'qna', 'faq'];

// 현재 저장된 순서 파싱 (누락된 타입은 뒤에 추가)
$savedOrder = array_values(array_filter(array_map('trim', explode(',', $config['detail_tab_order'] ?? ''))));
foreach ($allTabTypes as $t) {
    if (!in_array($t, $savedOrder, true)) {
        $savedOrder[] = $t;
    }
}

// 활성화된 동적 탭
$enabledTabs = array_filter(array_map('trim', explode(',', $config['goods_view_tab'] ?? '')));

// 활성 템플릿: tab_id 기준 그룹핑
$activeTemplates = $activeTemplates ?? [];
$tplGroups = [];
foreach ($activeTemplates as $tpl) {
    $tabId   = !empty($tpl['tab_id']) ? $tpl['tab_id'] : ('tpl_' . $tpl['template_id']);
    $tabName = !empty($tpl['tab_name']) ? $tpl['tab_name'] : ($tpl['subject'] ?? '상품정보');
    if (!isset($tplGroups[$tabId])) {
        $tplGroups[$tabId] = ['tab_name' => $tabName, 'count' => 0];
    }
    $tplGroups[$tabId]['count']++;
}
?>
<div class="card mb-4">
    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-info-circle me-2 text-pastel-blue"></i>상세정보 탭 순서</div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            <i class="bi bi-arrows-move"></i> 핸들을 드래그해서 순서를 변경합니다.
            구매후기·Q&A·FAQ는 활성화 여부도 설정합니다.
        </p>
        <ul class="list-group detail-tab-sortable" id="detailTabOrderList">
            <?php foreach ($savedOrder as $type):
                $isDynamic = in_array($type, $dynamicTypes, true);
                $isEnabled = $isDynamic && in_array($type, $enabledTabs, true);
            ?>
            <li class="list-group-item py-2" data-type="<?= $type ?>" draggable="true">
                <div class="d-flex align-items-center gap-3">
                    <span class="detail-tab-handle text-muted" title="드래그해서 순서 변경">
                        <i class="bi bi-arrows-move fs-5"></i>
                    </span>
                    <?php if ($isDynamic): ?>
                    <div class="form-check mb-0">
                        <input type="checkbox" class="form-check-input tab-enable-check"
                               id="tab_en_<?= $type ?>"
                               <?= $isEnabled ? 'checked' : '' ?>>
                        <label class="form-check-label fw-medium" for="tab_en_<?= $type ?>">
                            <?= htmlspecialchars($tabLabels[$type] ?? $type) ?>
                        </label>
                    </div>
                    <?php else: ?>
                    <span class="fw-medium"><?= htmlspecialchars($tabLabels[$type] ?? $type) ?></span>
                    <span class="badge bg-secondary" style="font-weight:400;font-size:0.72em;">데이터 있을 때 표시</span>
                    <?php endif; ?>
                </div>
                <?php if ($type === 'template' && !empty($tplGroups)): ?>
                <ul class="list-unstyled ms-4 mt-1 mb-0 small text-muted">
                    <?php foreach ($tplGroups as $tabId => $grp): ?>
                    <li>
                        <i class="bi bi-file-text me-1"></i>
                        <?= htmlspecialchars($grp['tab_name']) ?>
                        <?php if ($grp['count'] > 1): ?>
                        <span class="text-secondary">(항목 <?= $grp['count'] ?>개)</span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php elseif ($type === 'template'): ?>
                <div class="ms-4 mt-1 small text-muted">등록된 상품정보 템플릿이 없습니다.</div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <input type="hidden" name="formData[detail_tab_order]" id="detailTabOrderHidden"
               value="<?= htmlspecialchars(implode(',', $savedOrder)) ?>">
        <input type="hidden" name="formData[goods_view_tab]" id="goodsViewTabHidden"
               value="<?= htmlspecialchars($config['goods_view_tab'] ?? '') ?>">
    </div>
</div>
