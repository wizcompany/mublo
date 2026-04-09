<?php
/**
 * 주문 목록 (프론트)
 *
 * @var array $orders 주문 목록
 * @var array $pagination 페이지네이션 정보
 * @var array $allStates 전체 상태 목록 (라벨 매핑용)
 */

$orders = $orders ?? [];
$pagination = $pagination ?? [];
$allStates = $allStates ?? [];

// 상태 id → {label, action} 매핑
$statusMap = [];
foreach ($allStates as $s) {
    $statusMap[$s['id'] ?? ''] = $s;
}

// 상태 action → 배지 색상
function badgeColor(string $action): string
{
    return match ($action) {
        'receipt'                            => '#f59e0b',
        'paid'                               => '#3b82f6',
        'preparing', 'shipping'              => '#667eea',
        'delivered', 'confirmed'             => '#22c55e',
        'cancelled', 'returned'              => '#ef4444',
        'cancel_requested', 'return_requested' => '#f97316',
        default                              => '#6b7280',
    };
}

function badgeBg(string $action): string
{
    return match ($action) {
        'receipt'                            => '#fffbeb',
        'paid'                               => '#eff6ff',
        'preparing', 'shipping'              => '#eef2ff',
        'delivered', 'confirmed'             => '#f0fdf4',
        'cancelled', 'returned'              => '#fef2f2',
        'cancel_requested', 'return_requested' => '#fff7ed',
        default                              => '#f3f4f6',
    };
}

/** 이스케이프 헬퍼 */
if (!function_exists('e')) {
    function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}
?>

<style>
/* ── 주문 목록 컨테이너 ── */
.shop-order-list { max-width: 720px; margin: 0 auto; padding: 24px 16px; }
.shop-order-list__title { font-size: 1.3rem; font-weight: 700; margin-bottom: 20px; color: #333; }

/* ── 주문 카드 ── */
.shop-order-list__card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 16px; overflow: hidden; transition: box-shadow 0.15s; }
.shop-order-list__card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.shop-order-list__card-header { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; padding: 14px 20px; border-bottom: 1px solid #f0f0f0; }
.shop-order-list__card-date { font-size: 0.85rem; color: #888; }
.shop-order-list__card-no { font-size: 0.85rem; color: #555; font-weight: 500; }
.shop-order-list__card-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 0.75rem; font-weight: 600; margin-left: auto; }
.shop-order-list__card-body { display: flex; align-items: center; gap: 14px; padding: 16px 20px; }
.shop-order-list__card-thumb { width: 52px; height: 52px; border-radius: 8px; object-fit: cover; background: #f5f5f5; flex-shrink: 0; }
.shop-order-list__card-thumb--empty { width: 52px; height: 52px; border-radius: 8px; background: #f5f5f5; display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 1.2rem; flex-shrink: 0; }
.shop-order-list__card-name { flex: 1; font-size: 0.9rem; color: #333; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; min-width: 0; }
.shop-order-list__card-price { font-weight: 700; font-size: 1rem; color: #333; white-space: nowrap; }
.shop-order-list__card-footer { padding: 10px 20px; border-top: 1px solid #f0f0f0; text-align: right; }
.shop-order-list__card-footer a { font-size: 0.85rem; color: #667eea; text-decoration: none; font-weight: 500; }
.shop-order-list__card-footer a:hover { text-decoration: underline; }

/* ── 빈 상태 ── */
.shop-order-list__empty { text-align: center; padding: 60px 16px; }
.shop-order-list__empty p { font-size: 1rem; color: #888; margin-bottom: 16px; }
.shop-order-list__empty-btn { display: inline-block; padding: 10px 24px; background: #667eea; color: #fff; border-radius: 8px; text-decoration: none; font-size: 0.9rem; font-weight: 600; }
.shop-order-list__empty-btn:hover { background: #5a6fd6; }

/* ── 반응형 ── */
@media (max-width: 768px) {
    .shop-order-list { padding: 16px 12px; }
    .shop-order-list__card-header { padding: 12px 16px; }
    .shop-order-list__card-body { padding: 14px 16px; gap: 10px; }
    .shop-order-list__card-footer { padding: 10px 16px; }
    .shop-order-list__card-name { font-size: 0.85rem; }
    .shop-order-list__card-price { font-size: 0.9rem; }
}
</style>

<div class="shop-order-list">

    <h2 class="shop-order-list__title">주문내역</h2>

    <?php if (empty($orders)): ?>
        <div class="shop-order-list__empty">
            <p>주문 내역이 없습니다.</p>
            <a href="/shop/products" class="shop-order-list__empty-btn">쇼핑하러 가기</a>
        </div>
    <?php else: ?>

        <?php foreach ($orders as $order):
            $orderNo = $order['order_no'] ?? '';
            $createdAt = $order['created_at'] ?? '';
            $dateText = '';
            if ($createdAt) {
                $ts = strtotime($createdAt);
                $dateText = $ts ? date('Y.m.d', $ts) : $createdAt;
            }

            // 상태 라벨/색상
            $st = $statusMap[$order['order_status'] ?? ''] ?? null;
            $label = $st['label'] ?? ($order['order_status'] ?? '-');
            $action = $st['action'] ?? 'custom';
            $color = badgeColor($action);
            $bg = badgeBg($action);

            // 최종 결제 금액
            $finalAmount = (int) ($order['total_price'] ?? 0)
                         - (int) ($order['coupon_discount'] ?? 0)
                         - (int) ($order['point_used'] ?? 0)
                         + (int) ($order['shipping_fee'] ?? 0)
                         + (int) ($order['tax_amount'] ?? 0);

            // 대표 상품 (첫 번째 아이템)
            $items = $order['items'] ?? [];
            $firstItem = $items[0] ?? null;
            $itemCount = count($items);
            $productName = '';
            if ($firstItem) {
                $productName = $firstItem['goods_name'] ?? '';
                if ($itemCount > 1) {
                    $productName .= ' 외 ' . ($itemCount - 1) . '건';
                }
            }
            $thumbImage = $firstItem['goods_image'] ?? '';
        ?>
        <div class="shop-order-list__card">
            <div class="shop-order-list__card-header">
                <span class="shop-order-list__card-date"><?= e($dateText) ?></span>
                <span class="shop-order-list__card-no">주문번호: <?= e($orderNo) ?></span>
                <span class="shop-order-list__card-badge" style="color:<?= $color ?>;background:<?= $bg ?>"><?= e($label) ?></span>
            </div>
            <div class="shop-order-list__card-body">
                <?php if ($thumbImage): ?>
                    <img src="<?= e($thumbImage) ?>" alt="" class="shop-order-list__card-thumb">
                <?php else: ?>
                    <span class="shop-order-list__card-thumb--empty">&#128230;</span>
                <?php endif; ?>
                <span class="shop-order-list__card-name"><?= e($productName ?: '상품 정보 없음') ?></span>
                <span class="shop-order-list__card-price"><?= number_format($finalAmount) ?>원</span>
            </div>
            <div class="shop-order-list__card-footer">
                <a href="/shop/order/<?= e($orderNo) ?>">주문 상세보기 &rarr;</a>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (!empty($pagination) && ($pagination['totalPages'] ?? 1) > 1): ?>
            <?php $this->component('pagination', $pagination) ?>
        <?php endif; ?>

    <?php endif; ?>

</div>
