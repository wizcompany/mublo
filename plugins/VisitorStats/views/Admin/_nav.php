<?php
/**
 * 방문자 통계 탭 네비게이션
 *
 * @var string $currentTab 현재 활성 탭 (dashboard|realtime|pages|referrers|environment)
 */
$currentTab = $currentTab ?? 'dashboard';
$activeCode = $_GET['activeCode'] ?? 'P_VisitorStats_001';

$tabs = [
    'dashboard'         => ['label' => '대시보드',  'url' => '/admin/visitor-stats/dashboard',         'icon' => 'bi-speedometer2'],
    'realtime'          => ['label' => '실시간',    'url' => '/admin/visitor-stats/realtime',           'icon' => 'bi-lightning'],
    'pages'             => ['label' => '페이지별',  'url' => '/admin/visitor-stats/pages',              'icon' => 'bi-file-earmark-text'],
    'referrers'         => ['label' => '유입 경로', 'url' => '/admin/visitor-stats/referrers',          'icon' => 'bi-signpost-split'],
    'campaigns'         => ['label' => '캠페인',    'url' => '/admin/visitor-stats/campaigns',          'icon' => 'bi-megaphone'],
    'conversions'       => ['label' => '전환 목록', 'url' => '/admin/visitor-stats/conversions',        'icon' => 'bi-check2-circle'],
    'conversion-stats'  => ['label' => '전환 통계', 'url' => '/admin/visitor-stats/conversion-stats',   'icon' => 'bi-graph-up-arrow'],
    'environment'       => ['label' => '환경',      'url' => '/admin/visitor-stats/environment',        'icon' => 'bi-display'],
    'campaign-settings' => ['label' => '키 설정',   'url' => '/admin/visitor-stats/campaign-settings',  'icon' => 'bi-key'],
];
?>
<div class="content-header d-flex justify-content-between align-items-center">
    <h2><?= htmlspecialchars($pageTitle ?? '방문자 통계', ENT_QUOTES, 'UTF-8') ?></h2>
</div>

<ul class="nav nav-tabs mt-3 mb-3">
    <?php foreach ($tabs as $key => $tab): ?>
    <li class="nav-item">
        <a class="nav-link<?= $currentTab === $key ? ' active' : '' ?>"
           href="<?= $tab['url'] ?>?activeCode=<?= htmlspecialchars($activeCode, ENT_QUOTES, 'UTF-8') ?>">
            <i class="bi <?= $tab['icon'] ?> me-1"></i><?= $tab['label'] ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<style>
.vs-card-header { font-size: 0.9rem; font-weight: 600; }
.vs-card-header i { font-size: 1rem; }
.vs-metric { border: 1px solid var(--bs-border-color, #e9ecef); }
.vs-metric-val { font-size: 1.5rem; font-weight: 700; line-height: 1.3; }
.vs-metric-change { margin-top: 2px; }
.vs-metric-change .up { color: #10b981; }
.vs-metric-change .down { color: #f43f5e; }
.vs-metric-change .flat { color: #94a3b8; }
.vs-table td, .vs-table th { padding: .65rem .75rem; }
.vs-table th { color: #64748b; font-weight: 500; font-size: .8rem; }

/* 다크모드 */
[data-bs-theme="dark"] .vs-metric { border-color: #3f3f46; background-color: #27272a; }
[data-bs-theme="dark"] .vs-metric-change .up { color: #34d399; }
[data-bs-theme="dark"] .vs-metric-change .down { color: #fb7185; }
[data-bs-theme="dark"] .vs-table th { color: #a1a1aa; }
</style>
