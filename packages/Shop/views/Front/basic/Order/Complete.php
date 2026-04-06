<?php
/**
 * 주문 완료 (프론트)
 * @var array $order      주문 정보 (grand_total, shipping_fee, total_price, payment_method 등)
 * @var array $orderItems 주문 상품 목록
 * @var string|null $message 오류 메시지 (주문 없을 때)
 */
$order      = $order ?? [];
$orderItems = $orderItems ?? [];
$message    = $message ?? null;
?>

<?php if ($message): ?>
<div class="shop-order-complete text-center py-5">
    <i class="bi bi-x-circle text-danger" style="font-size:4rem"></i>
    <h2 class="mt-3">주문 정보를 찾을 수 없습니다</h2>
    <p class="text-muted"><?= htmlspecialchars($message) ?></p>
    <a href="/shop/products" class="btn btn-primary mt-3">쇼핑 계속하기</a>
</div>
<?php return; endif; ?>

<style>
.shop-complete { max-width: 640px; margin: 40px auto; padding: 0 16px; }
.shop-complete__icon { font-size: 4rem; color: #22c55e; }
.shop-complete__title { font-size: 1.6rem; font-weight: 700; margin: 12px 0 4px; }
.shop-complete__orderno { color: #888; font-size: 0.95rem; margin-bottom: 32px; }
.shop-complete__card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
.shop-complete__card-title { font-weight: 700; font-size: 1rem; margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #f0f0f0; }
.shop-complete__row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.9rem; }
.shop-complete__row:last-child { margin-bottom: 0; }
.shop-complete__row--total { font-size: 1rem; font-weight: 700; padding-top: 10px; border-top: 1px solid #e5e7eb; margin-top: 6px; }
.shop-complete__label { color: #6b7280; }
.shop-complete__item { display: flex; align-items: center; gap: 12px; padding: 10px 0; }
.shop-complete__item + .shop-complete__item { border-top: 1px solid #f5f5f5; }
.shop-complete__item-img { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; background: #f5f5f5; flex-shrink: 0; }
.shop-complete__item-img--empty { display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 1.2rem; }
.shop-complete__item-info { flex: 1; min-width: 0; }
.shop-complete__item-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.shop-complete__item-option { font-size: 0.8rem; color: #888; }
.shop-complete__item-price { font-size: 0.9rem; font-weight: 600; text-align: right; min-width: 80px; }
.shop-complete__actions { display: flex; gap: 12px; margin-top: 8px; }
.shop-complete__actions .btn { flex: 1; }
</style>

<div class="shop-complete text-center">
    <i class="bi bi-check-circle-fill shop-complete__icon"></i>
    <div class="shop-complete__title">주문이 완료되었습니다</div>
    <div class="shop-complete__orderno">주문번호: <strong><?= htmlspecialchars($order['order_no'] ?? '') ?></strong></div>

    <?php if (!empty($orderItems)): ?>
    <div class="shop-complete__card text-start">
        <div class="shop-complete__card-title">주문 상품</div>
        <?php foreach ($orderItems as $item): ?>
        <div class="shop-complete__item">
            <?php if (!empty($item['goods_image'])): ?>
            <img src="<?= htmlspecialchars($item['goods_image']) ?>" alt="" class="shop-complete__item-img">
            <?php else: ?>
            <div class="shop-complete__item-img shop-complete__item-img--empty"><i class="bi bi-image"></i></div>
            <?php endif; ?>
            <div class="shop-complete__item-info">
                <div class="shop-complete__item-name"><?= htmlspecialchars($item['goods_name'] ?? '') ?></div>
                <?php if (!empty($item['option_name'])): ?>
                <div class="shop-complete__item-option"><?= htmlspecialchars($item['option_name']) ?></div>
                <?php endif; ?>
                <div class="shop-complete__item-option">수량 <?= (int) ($item['quantity'] ?? 1) ?>개</div>
            </div>
            <div class="shop-complete__item-price"><?= number_format((int) ($item['total_price'] ?? 0)) ?>원</div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="shop-complete__card text-start">
        <div class="shop-complete__card-title">결제 정보</div>
        <div class="shop-complete__row">
            <span class="shop-complete__label">상품 금액</span>
            <span><?= number_format((int) ($order['total_price'] ?? 0)) ?>원</span>
        </div>
        <div class="shop-complete__row">
            <span class="shop-complete__label">배송비</span>
            <span><?= number_format((int) ($order['shipping_fee'] ?? 0)) ?>원</span>
        </div>
        <div class="shop-complete__row shop-complete__row--total">
            <span>결제 금액</span>
            <span><?= number_format((int) ($order['grand_total'] ?? 0)) ?>원</span>
        </div>
        <div class="shop-complete__row mt-2">
            <span class="shop-complete__label">결제 수단</span>
            <?php
                $pm = $order['payment_method'] ?? '';
                $pmLabel = \Mublo\Packages\Shop\Enum\PaymentMethod::tryFrom($pm)?->label() ?? ($pm ?: '-');
            ?>
            <span><?= htmlspecialchars($pmLabel) ?></span>
        </div>
    </div>

    <?php if (!empty($order['recipient_name'])): ?>
    <div class="shop-complete__card text-start">
        <div class="shop-complete__card-title">배송 정보</div>
        <div class="shop-complete__row">
            <span class="shop-complete__label">수령인</span>
            <span><?= htmlspecialchars($order['recipient_name']) ?></span>
        </div>
        <div class="shop-complete__row">
            <span class="shop-complete__label">배송지</span>
            <span><?= htmlspecialchars(trim(($order['shipping_address1'] ?? '') . ' ' . ($order['shipping_address2'] ?? ''))) ?></span>
        </div>
        <?php if (!empty($order['shipping_zip'])): ?>
        <div class="shop-complete__row">
            <span class="shop-complete__label">우편번호</span>
            <span><?= htmlspecialchars($order['shipping_zip']) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="shop-complete__actions">
        <a href="/shop/orders" class="btn btn-outline-primary">주문내역</a>
        <a href="/shop/products" class="btn btn-primary">쇼핑 계속하기</a>
    </div>
</div>
