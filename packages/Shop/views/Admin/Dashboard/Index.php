<?php
/**
 * 쇼핑몰 관리자 대시보드
 *
 * @var string $pageTitle
 * @var int    $today_revenue
 * @var int    $month_revenue
 * @var int    $today_orders
 * @var int    $pending_orders
 * @var array  $order_status_counts   [['order_status' => ..., 'cnt' => ...]]
 * @var array  $recent_orders
 * @var array  $revenue_trend         [['date' => ..., 'label' => ..., 'revenue' => ..., 'orders' => ...]]
 * @var array  $top_products          [['goods_id' => ..., 'goods_name' => ..., 'total_qty' => ..., 'total_revenue' => ...]]
 */

$statusLabels = [
    'receipt'          => '입금대기',
    'paid'             => '결제완료',
    'preparing'        => '상품준비',
    'shipping'         => '배송중',
    'delivered'        => '배송완료',
    'confirmed'        => '구매확정',
    'cancel_requested' => '취소요청',
    'cancelled'        => '취소완료',
    'return_requested' => '반품요청',
    'returned'         => '반품완료',
];

$statusCountMap = [];
foreach ($order_status_counts as $row) {
    $statusCountMap[$row['order_status']] = (int) $row['cnt'];
}

$trendLabels = array_column($revenue_trend, 'label');
$trendRevenue = array_column($revenue_trend, 'revenue');
$trendOrders  = array_column($revenue_trend, 'orders');
?>

<div class="page-container">
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '쇼핑몰 대시보드') ?></h3>
                <p class="text-muted mb-0">쇼핑몰 현황을 한눈에 확인하세요.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <small class="text-muted"><?= date('Y년 m월 d일') ?> 기준</small>
            </div>
        </div>
    </div>

    <!-- ── 메트릭 카드 (4개) ── -->
    <div class="row g-3 mt-2">
        <div class="col-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;border-radius:50%;background:#eff6ff;color:#3b82f6;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bi bi-currency-exchange" style="font-size:18px"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small">오늘 매출</div>
                            <div class="fw-bold fs-5"><?= number_format($today_revenue) ?>원</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;border-radius:50%;background:#ecfdf5;color:#10b981;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bi bi-graph-up-arrow" style="font-size:18px"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small">이번 달 매출</div>
                            <div class="fw-bold fs-5"><?= number_format($month_revenue) ?>원</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;border-radius:50%;background:#fff7ed;color:#f97316;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bi bi-bag-check" style="font-size:18px"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small">오늘 주문</div>
                            <div class="fw-bold fs-5"><?= number_format($today_orders) ?>건</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div style="width:44px;height:44px;border-radius:50%;background:#fef2f2;color:#ef4444;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bi bi-hourglass-split" style="font-size:18px"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small">처리 대기</div>
                            <div class="fw-bold fs-5"><?= number_format($pending_orders) ?>건</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 매출 추이 차트 + 주문 상태 현황 ── -->
    <div class="row g-3 mt-1">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                    <h6 class="mb-0 fw-semibold text-pastel-blue">
                        <i class="bi bi-bar-chart-line me-1"></i>최근 14일 매출 추이
                    </h6>
                </div>
                <div class="card-body pt-2">
                    <canvas id="revenueTrendChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                    <h6 class="mb-0 fw-semibold text-pastel-purple">
                        <i class="bi bi-pie-chart me-1"></i>주문 상태 현황
                    </h6>
                </div>
                <div class="card-body pt-2">
                    <canvas id="statusDonutChart" height="170"></canvas>
                    <div class="mt-2" id="statusLegend"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── 최근 주문 + 상위 상품 ── -->
    <div class="row g-3 mt-1">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-1 d-flex align-items-center justify-content-between">
                    <h6 class="mb-0 fw-semibold text-pastel-green">
                        <i class="bi bi-clock-history me-1"></i>최근 주문
                    </h6>
                    <a href="/admin/shop/orders" class="text-muted small text-decoration-none">전체 보기 →</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">주문번호</th>
                                    <th>주문자</th>
                                    <th class="text-end">금액</th>
                                    <th class="text-center pe-3">상태</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($recent_orders)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">주문이 없습니다.</td></tr>
                            <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                            <tr>
                                <td class="ps-3">
                                    <a href="/admin/shop/orders/<?= htmlspecialchars($order['order_no']) ?>"
                                       class="text-decoration-none fw-semibold">
                                        <?= htmlspecialchars($order['order_no']) ?>
                                    </a>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($order['orderer_name'] ?? '-') ?></td>
                                <td class="text-end fw-semibold"><?= number_format((int) $order['final_price']) ?>원</td>
                                <td class="text-center pe-3">
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                        <?= htmlspecialchars($statusLabels[$order['order_status']] ?? $order['order_status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-1">
                    <h6 class="mb-0 fw-semibold text-pastel-orange">
                        <i class="bi bi-trophy me-1"></i>판매 상위 상품 (최근 30일)
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (empty($top_products)): ?>
                        <p class="text-muted text-center py-3">데이터가 없습니다.</p>
                    <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($top_products as $idx => $product): ?>
                        <div class="d-flex align-items-center gap-3 p-2 rounded-2" style="background:#fafafa">
                            <div style="width:28px;height:28px;border-radius:50%;background:#fff7ed;color:#f97316;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">
                                <?= $idx + 1 ?>
                            </div>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold small text-truncate">
                                    <?= htmlspecialchars($product['goods_name']) ?>
                                </div>
                                <div class="text-muted" style="font-size:0.75rem">
                                    <?= number_format((int) $product['total_qty']) ?>개 판매
                                </div>
                            </div>
                            <div class="fw-bold small text-nowrap">
                                <?= number_format((int) $product['total_revenue']) ?>원
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function() {
    var trendLabels  = <?= json_encode($trendLabels) ?>;
    var trendRevenue = <?= json_encode($trendRevenue) ?>;
    var trendOrders  = <?= json_encode($trendOrders) ?>;

    var statusMap = <?= json_encode($statusCountMap) ?>;
    var statusLabels = <?= json_encode($statusLabels) ?>;

    // ── 매출 추이 차트 ──
    var ctx1 = document.getElementById('revenueTrendChart');
    if (ctx1) {
        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: '매출(원)',
                        data: trendRevenue,
                        backgroundColor: 'rgba(129,140,248,0.25)',
                        borderColor: '#818cf8',
                        borderWidth: 1.5,
                        borderRadius: 4,
                        yAxisID: 'y',
                    },
                    {
                        label: '주문수',
                        data: trendOrders,
                        type: 'line',
                        borderColor: '#34d399',
                        backgroundColor: 'transparent',
                        pointBackgroundColor: '#34d399',
                        tension: 0.3,
                        yAxisID: 'y2',
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } },
                scales: {
                    y:  { beginAtZero: true, position: 'left',  ticks: { callback: v => (v/10000).toFixed(0) + '만' } },
                    y2: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false } },
                }
            }
        });
    }

    // ── 주문 상태 도넛 ──
    var ctx2 = document.getElementById('statusDonutChart');
    if (ctx2) {
        var statusKeys = Object.keys(statusMap);
        var statusValues = statusKeys.map(k => statusMap[k]);
        var statusDisplayLabels = statusKeys.map(k => statusLabels[k] || k);
        var COLORS = ['#818cf8','#34d399','#fbbf24','#38bdf8','#f472b6','#fb923c','#f87171','#a78bfa','#2dd4bf','#a3e635'];

        new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: statusDisplayLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: COLORS.slice(0, statusKeys.length),
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true,
                cutout: '65%',
                plugins: { legend: { display: false } },
            }
        });

        // 범례 렌더링
        var legendEl = document.getElementById('statusLegend');
        if (legendEl && statusKeys.length > 0) {
            legendEl.innerHTML = statusKeys.map(function(k, i) {
                var count = statusMap[k] || 0;
                return '<div class="d-flex align-items-center gap-2 mb-1">'
                    + '<span style="width:10px;height:10px;border-radius:50%;background:' + COLORS[i] + ';flex-shrink:0"></span>'
                    + '<span class="small text-muted flex-grow-1">' + (statusLabels[k] || k) + '</span>'
                    + '<span class="small fw-semibold">' + count.toLocaleString() + '건</span>'
                    + '</div>';
            }).join('');
        }
    }
})();
</script>
