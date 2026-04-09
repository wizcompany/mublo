<?php
/**
 * 주문 상세 (프론트)
 *
 * @var array $order 주문 정보 (복호화 완료)
 * @var array $orderItems 주문 상품 목록
 * @var array $orderFieldValues 커스텀 필드 값
 * @var string $statusLabel 현재 상태 라벨
 * @var array $allStates 전체 상태 목록 (타임라인용)
 * @var string|null $message 에러 메시지
 */

use Mublo\Packages\Shop\Enum\PaymentMethod;

// 에러 상태
if (!empty($message)) { ?>
    <div class="shop-order-view" style="text-align:center;padding:60px 16px">
        <p style="font-size:1.1rem;color:#555"><?= htmlspecialchars($message) ?></p>
        <a href="/shop/orders" style="display:inline-block;margin-top:16px;color:#667eea">← 주문내역으로</a>
    </div>
<?php return; }

$order = $order ?? [];
$orderItems = $orderItems ?? [];
$orderFieldValues = $orderFieldValues ?? [];
$statusLabel = $statusLabel ?? '';
$allStates = $allStates ?? [];

$currentStatus = $order['order_status'] ?? '';
$orderNo = $order['order_no'] ?? '';
$createdAt = $order['created_at'] ?? '';

// 최종 결제 금액
$totalPrice = (int) ($order['total_price'] ?? 0);
$shippingFee = (int) ($order['shipping_fee'] ?? 0);
$couponDiscount = (int) ($order['coupon_discount'] ?? 0);
$pointUsed = (int) ($order['point_used'] ?? 0);
$taxAmount = (int) ($order['tax_amount'] ?? 0);
$finalAmount = $totalPrice - $couponDiscount - $pointUsed + $shippingFee + $taxAmount;

// 결제수단 라벨
$paymentMethodRaw = $order['payment_method'] ?? '';
$paymentMethodEnum = PaymentMethod::tryFrom($paymentMethodRaw);
$paymentMethodLabel = $paymentMethodEnum ? $paymentMethodEnum->label() : $paymentMethodRaw;

// ── 타임라인: 메인 플로우 상태 추출 ──
$mainFlowActions = ['receipt', 'paid', 'preparing', 'shipping', 'delivered', 'confirmed'];
$cancelActions = ['cancel_requested', 'cancelled', 'return_requested', 'returned'];

$mainFlowStates = [];
foreach ($allStates as $s) {
    $action = $s['action'] ?? 'custom';
    if (in_array($action, $mainFlowActions, true)) {
        $mainFlowStates[] = $s;
    }
}
usort($mainFlowStates, fn($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

// 현재 상태의 sort_order
$currentSortOrder = 0;
$isCancelledOrReturned = false;
foreach ($allStates as $s) {
    if (($s['id'] ?? '') === $currentStatus) {
        $currentSortOrder = $s['sort_order'] ?? 0;
        $action = $s['action'] ?? 'custom';
        if (in_array($action, $cancelActions, true)) {
            $isCancelledOrReturned = true;
        }
        break;
    }
}

// 날짜 포맷
$dateFormatted = '';
if ($createdAt) {
    $ts = strtotime($createdAt);
    $dateFormatted = $ts ? date('Y.m.d H:i', $ts) : $createdAt;
}

/** 이스케이프 헬퍼 */
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
?>

<style>
/* ── 주문 상세 컨테이너 ── */
.shop-order-view { max-width: 960px; margin: 0 auto; padding: 24px 16px; }

/* ── 헤더 ── */
.shop-order-view__header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.shop-order-view__header-back { color: #667eea; text-decoration: none; font-size: 0.9rem; font-weight: 500; }
.shop-order-view__header-back:hover { text-decoration: underline; }
.shop-order-view__header-info { text-align: right; }
.shop-order-view__order-no { display: block; font-weight: 700; font-size: 1rem; color: #333; }
.shop-order-view__order-date { font-size: 0.85rem; color: #888; }

/* ── 상태 타임라인 ── */
.shop-order-view__timeline { display: flex; align-items: flex-start; justify-content: center; padding: 24px 20px; margin-bottom: 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow-x: auto; }
.shop-order-view__timeline-step { display: flex; flex-direction: column; align-items: center; gap: 8px; min-width: 64px; }
.shop-order-view__timeline-dot { width: 16px; height: 16px; border-radius: 50%; border: 2px solid #d1d5db; background: #fff; flex-shrink: 0; transition: all 0.2s; }
.shop-order-view__timeline-step--active .shop-order-view__timeline-dot { border-color: #667eea; background: #667eea; }
.shop-order-view__timeline-step--current .shop-order-view__timeline-dot { border-color: #667eea; background: #fff; box-shadow: 0 0 0 4px rgba(102,126,234,0.2); }
.shop-order-view__timeline-label { font-size: 0.75rem; color: #adb5bd; white-space: nowrap; }
.shop-order-view__timeline-step--active .shop-order-view__timeline-label { color: #667eea; font-weight: 600; }
.shop-order-view__timeline-step--current .shop-order-view__timeline-label { color: #667eea; font-weight: 700; }
.shop-order-view__timeline-line { flex: 1; height: 2px; background: #e5e7eb; margin: 7px 6px 0; min-width: 20px; }
.shop-order-view__timeline-line--active { background: #667eea; }

/* ── 상태 배지 (취소/반품) ── */
.shop-order-view__status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 0.9rem; font-weight: 600; margin-bottom: 20px; }
.shop-order-view__status-badge--danger { background: #fef2f2; color: #ef4444; border: 1px solid #fecaca; }
.shop-order-view__status-badge--warning { background: #fffbeb; color: #f59e0b; border: 1px solid #fde68a; }

/* ── 2컬럼 레이아웃 ── */
.shop-order-view__layout { display: flex; gap: 24px; align-items: flex-start; }
.shop-order-view__main { flex: 1; min-width: 0; }
.shop-order-view__sidebar { width: 320px; flex-shrink: 0; position: sticky; top: 20px; }

/* ── 섹션 카드 ── */
.shop-order-view__section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
.shop-order-view__section-header { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; font-weight: 700; font-size: 1rem; }
.shop-order-view__section-body { padding: 20px; }

/* ── 상품 아이템 ── */
.shop-order-view__item { display: flex; align-items: center; gap: 12px; padding: 14px 0; }
.shop-order-view__item + .shop-order-view__item { border-top: 1px solid #f0f0f0; }
.shop-order-view__item:first-child { padding-top: 0; }
.shop-order-view__item:last-child { padding-bottom: 0; }
.shop-order-view__item-image { width: 56px; height: 56px; border-radius: 8px; overflow: hidden; background: #f5f5f5; flex-shrink: 0; }
.shop-order-view__item-image img { width: 100%; height: 100%; object-fit: cover; }
.shop-order-view__item-image--empty { display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 1.2rem; width: 56px; height: 56px; background: #f5f5f5; border-radius: 8px; }
.shop-order-view__item-info { flex: 1; min-width: 0; }
.shop-order-view__item-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.shop-order-view__item-option { font-size: 0.8rem; color: #888; }
.shop-order-view__item-qty { font-size: 0.85rem; color: #555; text-align: center; min-width: 40px; }
.shop-order-view__item-price { font-weight: 600; font-size: 0.95rem; text-align: right; min-width: 80px; }

/* ── 정보 행 ── */
.shop-order-view__info-row { display: flex; align-items: baseline; margin-bottom: 12px; }
.shop-order-view__info-row:last-child { margin-bottom: 0; }
.shop-order-view__info-label { width: 80px; flex-shrink: 0; font-size: 0.85rem; color: #888; font-weight: 500; }
.shop-order-view__info-value { flex: 1; font-size: 0.9rem; color: #333; word-break: break-all; }

/* ── 결제 요약 ── */
.shop-order-view__summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.9rem; color: #555; }
.shop-order-view__summary-row:last-child { margin-bottom: 0; }
.shop-order-view__summary-divider { height: 1px; background: #e5e7eb; margin: 14px 0; }
.shop-order-view__summary-total { display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 1.1rem; }
.shop-order-view__summary-total-price { color: #667eea; }

/* ── 하단 버튼 ── */
.shop-order-view__footer { display: flex; justify-content: center; gap: 12px; margin-top: 8px; padding: 24px 0; }
.shop-order-view__btn { display: inline-block; padding: 12px 28px; border-radius: 8px; font-size: 0.9rem; font-weight: 600; text-decoration: none; text-align: center; cursor: pointer; border: none; }
.shop-order-view__btn--outline { background: #fff; color: #667eea; border: 1px solid #667eea; }
.shop-order-view__btn--outline:hover { background: #f0f4ff; }
.shop-order-view__btn--primary { background: #667eea; color: #fff; }
.shop-order-view__btn--primary:hover { background: #5a6fd6; }

/* ── 반응형 ── */
@media (max-width: 768px) {
    .shop-order-view__layout { flex-direction: column; }
    .shop-order-view__sidebar { width: 100%; position: static; }
    .shop-order-view__timeline { justify-content: flex-start; padding: 16px 12px; gap: 0; }
    .shop-order-view__timeline-step { min-width: 52px; }
    .shop-order-view__timeline-label { font-size: 0.68rem; }
    .shop-order-view__header { flex-direction: column; align-items: flex-start; gap: 8px; }
    .shop-order-view__header-info { text-align: left; }
    .shop-order-view__info-row { flex-direction: column; gap: 2px; }
    .shop-order-view__info-label { width: auto; }
    .shop-order-view__item { gap: 10px; }
    .shop-order-view__item-price { min-width: 60px; font-size: 0.85rem; }
    .shop-order-view__footer { flex-direction: column; }
    .shop-order-view__btn { width: 100%; }
}
</style>

<div class="shop-order-view">

    <!-- ── 헤더 ── -->
    <div class="shop-order-view__header">
        <a href="/shop/orders" class="shop-order-view__header-back">← 주문내역</a>
        <div class="shop-order-view__header-info">
            <span class="shop-order-view__order-no">주문번호: <?= e($orderNo) ?></span>
            <span class="shop-order-view__order-date"><?= e($dateFormatted) ?></span>
        </div>
    </div>

    <!-- ── 상태 타임라인 ── -->
    <?php if ($isCancelledOrReturned): ?>
        <div class="shop-order-view__status-badge shop-order-view__status-badge--danger">
            <?= e($statusLabel) ?>
        </div>
    <?php elseif (!empty($mainFlowStates)): ?>
        <div class="shop-order-view__timeline">
            <?php foreach ($mainFlowStates as $i => $state):
                $isCompleted = ($state['sort_order'] ?? 0) < $currentSortOrder;
                $isCurrent = ($state['id'] ?? '') === $currentStatus;
                $stepClass = $isCurrent ? ' shop-order-view__timeline-step--current' : ($isCompleted ? ' shop-order-view__timeline-step--active' : '');
                $lineActive = ($isCompleted || $isCurrent) ? ' shop-order-view__timeline-line--active' : '';
            ?>
                <?php if ($i > 0): ?>
                    <div class="shop-order-view__timeline-line<?= $lineActive ?>"></div>
                <?php endif; ?>
                <div class="shop-order-view__timeline-step<?= $stepClass ?>">
                    <div class="shop-order-view__timeline-dot"></div>
                    <div class="shop-order-view__timeline-label"><?= e($state['label'] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- ── 2컬럼 레이아웃 ── -->
    <div class="shop-order-view__layout">

        <!-- 메인 -->
        <div class="shop-order-view__main">

            <!-- 주문 상품 -->
            <div class="shop-order-view__section">
                <div class="shop-order-view__section-header">
                    주문 상품 <span style="color:#888;font-weight:400;font-size:0.85rem">(<?= count($orderItems) ?>개)</span>
                </div>
                <div class="shop-order-view__section-body">
                    <?php if (empty($orderItems)): ?>
                        <p style="color:#888;text-align:center;padding:16px 0">상품 정보가 없습니다.</p>
                    <?php else: ?>
                        <?php foreach ($orderItems as $item): ?>
                        <div class="shop-order-view__item">
                            <div class="shop-order-view__item-image">
                                <?php if (!empty($item['goods_image'])): ?>
                                    <img src="<?= e($item['goods_image']) ?>" alt="<?= e($item['goods_name'] ?? '') ?>">
                                <?php else: ?>
                                    <span class="shop-order-view__item-image--empty">&#128230;</span>
                                <?php endif; ?>
                            </div>
                            <div class="shop-order-view__item-info">
                                <div class="shop-order-view__item-name"><?= e($item['goods_name'] ?? '') ?></div>
                                <?php if (!empty($item['option_name'])): ?>
                                    <div class="shop-order-view__item-option"><?= e($item['option_name']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="shop-order-view__item-qty"><?= (int) ($item['quantity'] ?? 1) ?>개</div>
                            <div class="shop-order-view__item-price"><?= number_format((int) ($item['total_price'] ?? 0)) ?>원</div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 배송 정보 -->
            <div class="shop-order-view__section">
                <div class="shop-order-view__section-header">배송 정보</div>
                <div class="shop-order-view__section-body">
                    <div class="shop-order-view__info-row">
                        <span class="shop-order-view__info-label">수령인</span>
                        <span class="shop-order-view__info-value"><?= e($order['recipient_name'] ?? '-') ?></span>
                    </div>
                    <div class="shop-order-view__info-row">
                        <span class="shop-order-view__info-label">연락처</span>
                        <span class="shop-order-view__info-value"><?= e($order['recipient_phone'] ?? '-') ?></span>
                    </div>
                    <div class="shop-order-view__info-row">
                        <span class="shop-order-view__info-label">배송지</span>
                        <span class="shop-order-view__info-value">
                            <?php
                            $zip = $order['shipping_zip'] ?? '';
                            $addr1 = $order['shipping_address1'] ?? '';
                            $addr2 = $order['shipping_address2'] ?? '';
                            $addrText = '';
                            if ($zip) $addrText .= '[' . e($zip) . '] ';
                            $addrText .= e(trim($addr1 . ' ' . $addr2));
                            echo $addrText ?: '-';
                            ?>
                        </span>
                    </div>
                    <?php if (!empty($order['order_memo'])): ?>
                    <div class="shop-order-view__info-row">
                        <span class="shop-order-view__info-label">요청사항</span>
                        <span class="shop-order-view__info-value"><?= e($order['order_memo']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- 추가 정보 (커스텀 필드) -->
            <?php if (!empty($orderFieldValues)): ?>
            <div class="shop-order-view__section">
                <div class="shop-order-view__section-header">추가 정보</div>
                <div class="shop-order-view__section-body">
                    <?php foreach ($orderFieldValues as $fv): ?>
                    <div class="shop-order-view__info-row">
                        <span class="shop-order-view__info-label"><?= e($fv['field_label'] ?? '') ?></span>
                        <span class="shop-order-view__info-value">
                            <?php if (($fv['field_type'] ?? '') === 'file' && !empty($fv['filename'])): ?>
                                &#128206; <?= e($fv['filename']) ?>
                            <?php elseif (($fv['field_type'] ?? '') === 'address'): ?>
                                <?= e($fv['display_value'] ?? '') ?>
                            <?php else: ?>
                                <?= nl2br(e($fv['display_value'] ?? $fv['field_value'] ?? '-')) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /main -->

        <!-- 사이드바 -->
        <div class="shop-order-view__sidebar">
            <div class="shop-order-view__section">
                <div class="shop-order-view__section-header">결제 정보</div>
                <div class="shop-order-view__section-body">
                    <div class="shop-order-view__summary-row">
                        <span>상품 합계</span>
                        <span><?= number_format($totalPrice) ?>원</span>
                    </div>
                    <div class="shop-order-view__summary-row">
                        <span>배송비</span>
                        <span><?= $shippingFee > 0 ? number_format($shippingFee) . '원' : '무료' ?></span>
                    </div>
                    <?php if ($couponDiscount > 0): ?>
                    <div class="shop-order-view__summary-row">
                        <span>쿠폰 할인</span>
                        <span style="color:#e53e3e">-<?= number_format($couponDiscount) ?>원</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($pointUsed > 0): ?>
                    <div class="shop-order-view__summary-row">
                        <span>포인트 사용</span>
                        <span style="color:#e53e3e">-<?= number_format($pointUsed) ?>원</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($taxAmount > 0): ?>
                    <div class="shop-order-view__summary-row">
                        <span>세금</span>
                        <span><?= number_format($taxAmount) ?>원</span>
                    </div>
                    <?php endif; ?>

                    <div class="shop-order-view__summary-divider"></div>

                    <div class="shop-order-view__summary-total">
                        <span>결제 금액</span>
                        <span class="shop-order-view__summary-total-price"><?= number_format($finalAmount) ?>원</span>
                    </div>

                    <div class="shop-order-view__summary-divider"></div>

                    <div class="shop-order-view__summary-row">
                        <span>결제 수단</span>
                        <span style="font-weight:600"><?= e($paymentMethodLabel) ?></span>
                    </div>
                </div>
            </div>
        </div><!-- /sidebar -->

    </div><!-- /layout -->

    <!-- ── 하단 버튼 ── -->
    <div class="shop-order-view__footer">
        <a href="/shop/orders" class="shop-order-view__btn shop-order-view__btn--outline">주문내역 목록</a>
        <a href="/shop/products" class="shop-order-view__btn shop-order-view__btn--primary">쇼핑 계속하기</a>
    </div>

</div>
