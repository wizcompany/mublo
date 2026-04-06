<?php
/**
 * 체크아웃 (프론트)
 *
 * @var array $cartItems  주문 상품 목록
 * @var array $totals     합계 (totalPrice, shippingFee, totalPoint, totalQuantity, grandTotal)
 * @var array $gateways   PG 목록 [key => meta]
 * @var array $member     회원 정보 배열 (비회원이면 null)
 * @var bool $isGuest     비회원 주문 모드 여부
 * @var string $checkoutMode 'cart' | 'direct'
 * @var array $addresses  저장된 배송지 목록
 * @var array|null $defaultAddress 기본 배송지
 * @var array $orderFields 주문 추가 필드 목록
 */

$cartItems = $cartItems ?? [];
$totals = $totals ?? ['totalPrice' => 0, 'shippingFee' => 0, 'totalPoint' => 0, 'totalQuantity' => 0, 'grandTotal' => 0];
$gateways = $gateways ?? [];
$member = is_array($member ?? null) ? $member : [];
$isGuest = $isGuest ?? false;
$addresses = $addresses ?? [];
$defaultAddress = $defaultAddress ?? null;
$prefill = is_array($defaultAddress) ? $defaultAddress : [];
$orderFields = $orderFields ?? [];
?>

<style>
/* ── 체크아웃 레이아웃 ── */
.shop-checkout { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
.shop-checkout__title { font-size: 1.5rem; font-weight: 700; margin-bottom: 24px; }
.shop-checkout__layout { display: flex; gap: 24px; align-items: flex-start; }
.shop-checkout__main { flex: 1; min-width: 0; }
.shop-checkout__sidebar { width: 320px; flex-shrink: 0; position: sticky; top: 20px; }

/* ── 섹션 카드 ── */
.shop-checkout__section { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 20px; overflow: hidden; }
.shop-checkout__section-header { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; font-weight: 700; font-size: 1rem; display: flex; align-items: center; justify-content: space-between; }
.shop-checkout__section-body { padding: 20px; }

/* ── 배송지 폼 ── */
.shop-checkout__form-row { display: flex; align-items: center; margin-bottom: 14px; }
.shop-checkout__form-row:last-child { margin-bottom: 0; }
.shop-checkout__label { width: 100px; flex-shrink: 0; font-size: 0.9rem; color: #555; font-weight: 500; }
.shop-checkout__label .required { color: #e53e3e; margin-left: 2px; }
.shop-checkout__input-wrap { flex: 1; }
.shop-checkout__input { width: 100%; padding: 10px 12px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.9rem; outline: none; box-sizing: border-box; }
.shop-checkout__input:focus { border-color: #667eea; box-shadow: 0 0 0 2px rgba(102,126,234,0.15); }
.shop-checkout__input--short { max-width: 200px; }
.shop-checkout__input--readonly { background: #f9fafb; color: #888; }
.shop-checkout__zip-wrap { display: flex; gap: 8px; max-width: 260px; }
.shop-checkout__zip-btn { padding: 10px 16px; background: #555; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; white-space: nowrap; }
.shop-checkout__zip-btn:hover { background: #333; }
.shop-checkout__addr-footer { display: flex; gap: 10px; align-items: center; margin-top: 16px; padding-top: 14px; border-top: 1px solid #f0f0f0; }
.shop-checkout__addr-manage-btn { padding: 8px 16px; background: #667eea; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
.shop-checkout__addr-manage-btn:hover { background: #5a6fd6; }
.shop-checkout__save-label { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #555; cursor: pointer; }
.shop-checkout__save-label input[type="checkbox"] { accent-color: #667eea; }

/* ── 주문 상품 ── */
.shop-checkout__item { display: flex; align-items: center; gap: 12px; padding: 14px 0; }
.shop-checkout__item + .shop-checkout__item { border-top: 1px solid #f0f0f0; }
.shop-checkout__item-image { width: 56px; height: 56px; border-radius: 8px; overflow: hidden; background: #f5f5f5; flex-shrink: 0; }
.shop-checkout__item-image img { width: 100%; height: 100%; object-fit: cover; }
.shop-checkout__item-image--empty { display: flex; align-items: center; justify-content: center; color: #ccc; font-size: 1.2rem; width: 56px; height: 56px; background: #f5f5f5; border-radius: 8px; }
.shop-checkout__item-info { flex: 1; min-width: 0; }
.shop-checkout__item-name { font-weight: 600; font-size: 0.9rem; margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.shop-checkout__item-option { font-size: 0.8rem; color: #888; }
.shop-checkout__item-qty { font-size: 0.85rem; color: #555; text-align: center; min-width: 40px; }
.shop-checkout__item-price { font-weight: 600; font-size: 0.95rem; text-align: right; min-width: 80px; }

/* ── 추가 정보 (커스텀 필드) ── */
.shop-checkout__field-group { margin-bottom: 14px; }
.shop-checkout__field-group:last-child { margin-bottom: 0; }
.shop-checkout__field-label { display: block; font-size: 0.9rem; font-weight: 500; color: #555; margin-bottom: 6px; }
.shop-checkout__field-label .required { color: #e53e3e; margin-left: 2px; }
.shop-checkout__field-help { font-size: 0.8rem; color: #999; margin-top: 4px; }

/* ── 결제 수단 ── */
.shop-checkout__gateway-list { display: flex; flex-wrap: wrap; gap: 10px; }
.shop-checkout__gateway-item { display: none; }
.shop-checkout__gateway-label { display: flex; align-items: center; gap: 8px; padding: 12px 20px; border: 2px solid #e5e7eb; border-radius: 8px; cursor: pointer; font-size: 0.9rem; font-weight: 500; transition: border-color 0.15s, background 0.15s; }
.shop-checkout__gateway-label:hover { border-color: #667eea; }
.shop-checkout__gateway-item:checked + .shop-checkout__gateway-label { border-color: #667eea; background: #f0f4ff; }
.shop-checkout__no-gateway { padding: 20px; text-align: center; color: #888; font-size: 0.9rem; }

/* ── 사이드바 ── */
.shop-checkout__summary { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden; }
.shop-checkout__summary-header { padding: 16px 20px; border-bottom: 1px solid #e5e7eb; font-weight: 700; font-size: 1rem; }
.shop-checkout__summary-body { padding: 20px; }
.shop-checkout__summary-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.9rem; color: #555; }
.shop-checkout__summary-row:last-child { margin-bottom: 0; }
.shop-checkout__summary-divider { height: 1px; background: #e5e7eb; margin: 14px 0; }
.shop-checkout__summary-total { display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 1.15rem; }
.shop-checkout__summary-total-price { color: #667eea; }
.shop-checkout__summary-footer { padding: 16px 20px; border-top: 1px solid #e5e7eb; }
.shop-checkout__pay-btn { width: 100%; padding: 14px; background: #667eea; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; }
.shop-checkout__pay-btn:hover { background: #5a6fd6; }

/* ── 쿠폰 선택 ── */
.shop-checkout__coupon-select-btn { width: 100%; padding: 10px; background: #fff; border: 2px dashed #d1d5db; border-radius: 8px; color: #667eea; font-size: 0.9rem; font-weight: 500; cursor: pointer; }
.shop-checkout__coupon-select-btn:hover { border-color: #667eea; background: #f8f9ff; }
.shop-checkout__coupon-applied { display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: #f0f4ff; border-radius: 8px; }
.shop-checkout__coupon-applied-info { flex: 1; min-width: 0; }
.shop-checkout__coupon-applied-name { font-weight: 600; font-size: 0.9rem; color: #333; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.shop-checkout__coupon-applied-discount { font-size: 0.85rem; color: #667eea; font-weight: 600; }
.shop-checkout__coupon-cancel-btn { background: none; border: none; font-size: 1.3rem; color: #999; cursor: pointer; padding: 4px 8px; }
.shop-checkout__coupon-cancel-btn:hover { color: #e53e3e; }

/* 쿠폰 모달 */
.shop-checkout__coupon-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9100; justify-content: center; align-items: center; }
.shop-checkout__coupon-modal-overlay.is-open { display: flex; }
.shop-checkout__coupon-modal { background: #fff; border-radius: 14px; width: 480px; max-width: 95vw; max-height: 80vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.shop-checkout__coupon-modal-header { padding: 18px 24px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
.shop-checkout__coupon-modal-header h3 { font-size: 1.1rem; font-weight: 700; margin: 0; }
.shop-checkout__coupon-modal-close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #888; padding: 4px 8px; }
.shop-checkout__coupon-modal-close:hover { color: #333; }
.shop-checkout__coupon-modal-body { padding: 16px 24px; overflow-y: auto; flex: 1; }
.shop-checkout__coupon-item { display: flex; align-items: center; padding: 12px 14px; border: 1px solid #e5e7eb; border-radius: 10px; margin-bottom: 10px; cursor: pointer; transition: border-color 0.15s, background 0.15s; }
.shop-checkout__coupon-item:hover { border-color: #667eea; background: #f8f9ff; }
.shop-checkout__coupon-item-info { flex: 1; min-width: 0; }
.shop-checkout__coupon-item-name { font-weight: 600; font-size: 0.9rem; color: #333; margin-bottom: 2px; }
.shop-checkout__coupon-item-desc { font-size: 0.8rem; color: #888; }
.shop-checkout__coupon-item-discount { font-weight: 700; font-size: 1rem; color: #667eea; white-space: nowrap; margin-left: 12px; }
.shop-checkout__coupon-empty { text-align: center; padding: 30px 0; color: #999; font-size: 0.9rem; }

/* ── 테스트 결제 모달 ── */
.tpay-modal__overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.5); z-index: 9999; align-items: center; justify-content: center; }
.tpay-modal__box { background: #fff; border-radius: 16px; width: 380px; max-width: calc(100vw - 32px); box-shadow: 0 24px 64px rgba(0,0,0,.22); overflow: hidden; }
.tpay-modal__header { padding: 20px 24px 18px; background: linear-gradient(135deg,#f8f9ff 0%,#eef0ff 100%); border-bottom: 1px solid #e8ebff; display: flex; align-items: center; gap: 12px; }
.tpay-modal__badge { display: inline-flex; align-items: center; gap: 5px; background: #ff9800; color: #fff; font-size: 0.72rem; font-weight: 700; letter-spacing: .04em; padding: 3px 9px; border-radius: 20px; }
.tpay-modal__title { font-size: 1.05rem; font-weight: 700; color: #1a1a2e; }
.tpay-modal__body { padding: 22px 24px 4px; }
.tpay-modal__rows { border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; margin-bottom: 16px; }
.tpay-modal__row { display: flex; align-items: center; padding: 12px 16px; font-size: .88rem; }
.tpay-modal__row + .tpay-modal__row { border-top: 1px solid #f0f0f0; }
.tpay-modal__row-label { color: #888; width: 76px; flex-shrink: 0; }
.tpay-modal__row-value { font-weight: 600; color: #1a1a2e; }
.tpay-modal__row--amount .tpay-modal__row-value { font-size: 1.1rem; font-weight: 700; color: #667eea; }
.tpay-modal__notice { display: flex; align-items: flex-start; gap: 7px; background: #fff8e6; border: 1px solid #ffe9a0; border-radius: 8px; padding: 10px 13px; font-size: .8rem; color: #7a5800; line-height: 1.5; margin-bottom: 20px; }
.tpay-modal__notice-icon { flex-shrink: 0; margin-top: 1px; }
.tpay-modal__footer { padding: 0 24px 22px; display: flex; gap: 8px; }
.tpay-modal__cancel-btn { flex: 1; padding: 12px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; font-size: .9rem; color: #555; font-weight: 500; cursor: pointer; transition: background .15s; }
.tpay-modal__cancel-btn:hover { background: #f5f5f5; }
.tpay-modal__confirm-btn { flex: 2; padding: 12px; border: none; border-radius: 8px; background: #667eea; color: #fff; font-size: .9rem; font-weight: 600; cursor: pointer; transition: background .15s; }
.tpay-modal__confirm-btn:hover { background: #5a6fd6; }

/* ── 배송지 관리 모달 ── */
.addr-modal__overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9000; justify-content: center; align-items: center; }
.addr-modal__overlay.is-open { display: flex; }
.addr-modal { background: #fff; border-radius: 14px; width: 560px; max-width: 95vw; max-height: 85vh; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.addr-modal__header { padding: 18px 24px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; justify-content: space-between; }
.addr-modal__header h3 { font-size: 1.1rem; font-weight: 700; margin: 0; }
.addr-modal__close { background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #888; padding: 4px 8px; }
.addr-modal__close:hover { color: #333; }
.addr-modal__body { padding: 20px 24px; overflow-y: auto; flex: 1; }

/* 모달 - 주소 목록 */
.addr-modal__list { margin-bottom: 20px; }
.addr-modal__card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 14px 16px; margin-bottom: 10px; transition: border-color 0.15s; }
.addr-modal__card:last-child { margin-bottom: 0; }
.addr-modal__card:hover { border-color: #667eea; }
.addr-modal__card-top { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
.addr-modal__card-name { font-weight: 600; font-size: 0.95rem; }
.addr-modal__card-badge { display: inline-block; padding: 2px 8px; background: #667eea; color: #fff; border-radius: 4px; font-size: 0.72rem; font-weight: 600; }
.addr-modal__card-info { font-size: 0.85rem; color: #555; line-height: 1.5; }
.addr-modal__card-actions { display: flex; gap: 6px; margin-top: 10px; }
.addr-modal__card-btn { padding: 6px 12px; border-radius: 5px; font-size: 0.8rem; cursor: pointer; border: 1px solid #d1d5db; background: #fff; color: #333; }
.addr-modal__card-btn:hover { background: #f5f5f5; }
.addr-modal__card-btn--primary { background: #667eea; color: #fff; border-color: #667eea; }
.addr-modal__card-btn--primary:hover { background: #5a6fd6; }
.addr-modal__card-btn--danger { color: #e53e3e; border-color: #e53e3e; }
.addr-modal__card-btn--danger:hover { background: #fef2f2; }
.addr-modal__card-btn--green { color: #059669; border-color: #059669; }
.addr-modal__card-btn--green:hover { background: #ecfdf5; }
.addr-modal__empty { text-align: center; padding: 30px 0; color: #999; font-size: 0.9rem; }

/* 모달 - 추가/수정 폼 */
.addr-modal__form { border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; background: #f9fafb; }
.addr-modal__form-title { font-weight: 600; font-size: 0.95rem; margin-bottom: 14px; }
.addr-modal__form-row { display: flex; align-items: center; margin-bottom: 10px; }
.addr-modal__form-row:last-child { margin-bottom: 0; }
.addr-modal__form-label { width: 80px; font-size: 0.85rem; color: #555; flex-shrink: 0; }
.addr-modal__form-input { flex: 1; padding: 8px 10px; border: 1px solid #d1d5db; border-radius: 5px; font-size: 0.85rem; outline: none; box-sizing: border-box; }
.addr-modal__form-input:focus { border-color: #667eea; }
.addr-modal__form-input--short { max-width: 160px; }
.addr-modal__form-zip { display: flex; gap: 6px; max-width: 220px; }
.addr-modal__form-zip-btn { padding: 8px 12px; background: #555; color: #fff; border: none; border-radius: 5px; font-size: 0.8rem; cursor: pointer; white-space: nowrap; }
.addr-modal__form-btns { display: flex; gap: 8px; margin-top: 14px; align-items: center; }
.addr-modal__form-save { padding: 8px 20px; background: #667eea; color: #fff; border: none; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
.addr-modal__form-save:hover { background: #5a6fd6; }
.addr-modal__form-cancel { padding: 8px 16px; background: #fff; color: #555; border: 1px solid #d1d5db; border-radius: 6px; font-size: 0.85rem; cursor: pointer; }
.addr-modal__add-btn { width: 100%; padding: 10px; background: #fff; border: 2px dashed #d1d5db; border-radius: 8px; color: #667eea; font-size: 0.9rem; font-weight: 500; cursor: pointer; margin-bottom: 16px; }
.addr-modal__add-btn:hover { border-color: #667eea; background: #f8f9ff; }

/* ── 반응형 ── */
@media (max-width: 768px) {
    .shop-checkout__layout { flex-direction: column; }
    .shop-checkout__sidebar { width: 100%; position: static; }
    .shop-checkout__form-row { flex-direction: column; align-items: flex-start; gap: 6px; }
    .shop-checkout__label { width: auto; }
    .shop-checkout__input--short { max-width: 100%; }
    .addr-modal { width: 100%; margin: 10px; }
}
</style>

<div class="shop-checkout">
    <h2 class="shop-checkout__title">주문/결제</h2>

    <div class="shop-checkout__layout">
        <div class="shop-checkout__main">

            <?php if ($isGuest): ?>
            <!-- ── 주문자 정보 (비회원) ── -->
            <div class="shop-checkout__section">
                <div class="shop-checkout__section-header">주문자 정보</div>
                <div class="shop-checkout__section-body">
                    <div class="shop-checkout__form-row">
                        <span class="shop-checkout__label">주문자명<span class="required">*</span></span>
                        <div class="shop-checkout__input-wrap">
                            <input type="text" id="ordererName" class="shop-checkout__input shop-checkout__input--short" placeholder="이름을 입력하세요">
                        </div>
                    </div>
                    <div class="shop-checkout__form-row">
                        <span class="shop-checkout__label">연락처<span class="required">*</span></span>
                        <div class="shop-checkout__input-wrap">
                            <input type="tel" id="ordererPhone" class="shop-checkout__input shop-checkout__input--short mask-hp" placeholder="010-0000-0000">
                        </div>
                    </div>
                    <div class="shop-checkout__form-row">
                        <span class="shop-checkout__label">이메일<span class="required">*</span></span>
                        <div class="shop-checkout__input-wrap">
                            <input type="email" id="ordererEmail" class="shop-checkout__input" placeholder="주문 확인 메일을 받을 이메일">
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── 배송지 정보 ── -->
            <div class="shop-checkout__section">
                <div class="shop-checkout__section-header">
                    <span>배송지 정보</span>
                    <?php if (!$isGuest): ?>
                        <button type="button" class="shop-checkout__addr-manage-btn" id="btnOpenAddrModal">배송지 관리</button>
                    <?php endif; ?>
                </div>
                <div class="shop-checkout__section-body">
                    <div class="shop-checkout__form-row">
                        <span class="shop-checkout__label">수령인<span class="required">*</span></span>
                        <div class="shop-checkout__input-wrap">
                            <input type="text" id="recipientName" class="shop-checkout__input shop-checkout__input--short" value="<?= htmlspecialchars((string) ($prefill['recipient_name'] ?? $member['nickname'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="shop-checkout__form-row">
                        <span class="shop-checkout__label">연락처<span class="required">*</span></span>
                        <div class="shop-checkout__input-wrap">
                            <input type="tel" id="recipientPhone" class="shop-checkout__input shop-checkout__input--short mask-hp" value="<?= htmlspecialchars((string) ($prefill['recipient_phone'] ?? '')) ?>" placeholder="010-0000-0000">
                        </div>
                    </div>
                    <div class="shop-checkout__form-row">
                        <span class="shop-checkout__label">우편번호<span class="required">*</span></span>
                        <div class="shop-checkout__input-wrap">
                            <div class="shop-checkout__zip-wrap">
                                <input type="text" id="shippingZip" class="shop-checkout__input shop-checkout__input--readonly" readonly value="<?= htmlspecialchars((string) ($prefill['zip_code'] ?? '')) ?>">
                                <button type="button" class="shop-checkout__zip-btn" id="btnZipMain">검색</button>
                            </div>
                        </div>
                    </div>
                    <div class="shop-checkout__form-row">
                        <span class="shop-checkout__label">주소<span class="required">*</span></span>
                        <div class="shop-checkout__input-wrap">
                            <input type="text" id="shippingAddr1" class="shop-checkout__input shop-checkout__input--readonly" readonly placeholder="기본주소" style="margin-bottom:8px" value="<?= htmlspecialchars((string) ($prefill['address1'] ?? '')) ?>">
                            <input type="text" id="shippingAddr2" class="shop-checkout__input" placeholder="상세주소 입력" value="<?= htmlspecialchars((string) ($prefill['address2'] ?? '')) ?>">
                        </div>
                    </div>
                    <div class="shop-checkout__form-row">
                        <span class="shop-checkout__label">배송 메모</span>
                        <div class="shop-checkout__input-wrap">
                            <input type="text" id="orderMemo" class="shop-checkout__input" placeholder="배송 시 요청사항">
                        </div>
                    </div>
                    <?php if (!$isGuest): ?>
                    <div class="shop-checkout__addr-footer">
                        <label class="shop-checkout__save-label">
                            <input type="checkbox" id="chkSetDefault"> 기본 배송지로 설정
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── 주문 상품 ── -->
            <div class="shop-checkout__section">
                <div class="shop-checkout__section-header">주문 상품 (<?= count($cartItems) ?>건)</div>
                <div class="shop-checkout__section-body" style="padding-top:6px;padding-bottom:6px">
                    <?php foreach ($cartItems as $item):
                        $product = $item['product'] ?? [];
                        $productName = (string) (is_array($product) ? ($product['goods_name'] ?? '') : ($item['goods_name'] ?? '상품'));
                        $imgData = $item['product_image'] ?? null;
                        $productImage = is_array($imgData) ? (string) ($imgData['image_url'] ?? '') : (string) ($imgData ?? '');
                        $optionLabel = (string) ($item['option_label'] ?? $item['option_code'] ?? '');
                    ?>
                    <div class="shop-checkout__item">
                        <?php if ($productImage): ?>
                            <div class="shop-checkout__item-image"><img src="<?= htmlspecialchars($productImage) ?>" alt=""></div>
                        <?php else: ?>
                            <div class="shop-checkout__item-image--empty">&#128230;</div>
                        <?php endif; ?>
                        <div class="shop-checkout__item-info">
                            <div class="shop-checkout__item-name"><?= htmlspecialchars($productName) ?></div>
                            <?php if ($optionLabel): ?>
                                <div class="shop-checkout__item-option"><?= htmlspecialchars($optionLabel) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="shop-checkout__item-qty"><?= (int) $item['quantity'] ?>개</div>
                        <div class="shop-checkout__item-price"><?= number_format((int) $item['total_price']) ?>원</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- ── 쿠폰 적용 ── -->
            <?php if (!$isGuest): ?>
            <div class="shop-checkout__section">
                <div class="shop-checkout__section-header">
                    쿠폰
                    <span id="couponApplied" style="display:none;font-size:0.85rem;font-weight:500;color:#667eea"></span>
                </div>
                <div class="shop-checkout__section-body" style="padding-top:10px;padding-bottom:10px">
                    <div id="couponArea">
                        <button type="button" class="shop-checkout__coupon-select-btn" id="btnSelectCoupon">쿠폰 선택</button>
                    </div>
                    <div id="couponSelected" style="display:none">
                        <div class="shop-checkout__coupon-applied">
                            <div class="shop-checkout__coupon-applied-info">
                                <span class="shop-checkout__coupon-applied-name" id="selectedCouponName"></span>
                                <span class="shop-checkout__coupon-applied-discount" id="selectedCouponDiscount"></span>
                            </div>
                            <button type="button" class="shop-checkout__coupon-cancel-btn" id="btnCancelCoupon">&times;</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 쿠폰 선택 모달 -->
            <div class="shop-checkout__coupon-modal-overlay" id="couponModalOverlay">
                <div class="shop-checkout__coupon-modal">
                    <div class="shop-checkout__coupon-modal-header">
                        <h3>쿠폰 선택</h3>
                        <button type="button" class="shop-checkout__coupon-modal-close" id="couponModalClose">&times;</button>
                    </div>
                    <div class="shop-checkout__coupon-modal-body" id="couponModalBody">
                        <div style="text-align:center;padding:30px;color:#aaa">적용 가능한 쿠폰을 불러오는 중...</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── 추가 정보 (주문 커스텀 필드) ── -->
            <?php if (!empty($orderFields)): ?>
            <div class="shop-checkout__section">
                <div class="shop-checkout__section-header">추가 정보</div>
                <div class="shop-checkout__section-body">
                    <?php foreach ($orderFields as $field): ?>
                    <div class="shop-checkout__field-group">
                        <label class="shop-checkout__field-label" for="of_<?= $field['field_id'] ?>">
                            <?= htmlspecialchars($field['field_label']) ?>
                            <?php if ($field['is_required']): ?><span class="required">*</span><?php endif; ?>
                        </label>
                        <?= \Mublo\Service\CustomField\CustomFieldRenderer::render($field, null, [
                            'namePrefix' => 'orderFields',
                            'idPrefix' => 'of_',
                        ]) ?>
                        <?php if (!empty($field['placeholder'])): ?>
                            <div class="shop-checkout__field-help"><?= htmlspecialchars($field['placeholder']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- ── 결제 수단 ── -->
            <div class="shop-checkout__section">
                <div class="shop-checkout__section-header">결제 수단</div>
                <div class="shop-checkout__section-body">
                    <?php if (empty($gateways)): ?>
                        <div class="shop-checkout__no-gateway">등록된 결제 수단이 없습니다.</div>
                    <?php else: ?>
                        <div class="shop-checkout__gateway-list">
                            <?php $first = true; foreach ($gateways as $key => $gw): ?>
                                <input type="radio" name="payment_gateway" id="pg_<?= htmlspecialchars($key) ?>"
                                       value="<?= htmlspecialchars($key) ?>" class="shop-checkout__gateway-item"
                                       <?= $first ? 'checked' : '' ?>>
                                <label for="pg_<?= htmlspecialchars($key) ?>" class="shop-checkout__gateway-label">
                                    <?= htmlspecialchars($gw['label'] ?? $key) ?>
                                </label>
                            <?php $first = false; endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ── 사이드바 ── -->
        <div class="shop-checkout__sidebar">
            <div class="shop-checkout__summary">
                <div class="shop-checkout__summary-header">결제 금액</div>
                <div class="shop-checkout__summary-body">
                    <div class="shop-checkout__summary-row"><span>상품금액</span><span><?= number_format((int) ($totals['totalPrice'] ?? 0)) ?>원</span></div>
                    <div class="shop-checkout__summary-row"><span>배송비</span><span><?= number_format((int) ($totals['shippingFee'] ?? 0)) ?>원</span></div>
                    <div class="shop-checkout__summary-row" id="summCouponRow" style="display:none"><span>쿠폰 할인</span><span style="color:#e53e3e" id="summCouponAmount"></span></div>
                    <?php if (($totals['totalPoint'] ?? 0) > 0): ?>
                    <div class="shop-checkout__summary-row"><span>포인트 적립 예정</span><span>+<?= number_format((int) $totals['totalPoint']) ?>P</span></div>
                    <?php endif; ?>
                    <div class="shop-checkout__summary-divider"></div>
                    <div class="shop-checkout__summary-total"><span>총 결제금액</span><span class="shop-checkout__summary-total-price" id="summGrandTotal"><?= number_format((int) ($totals['grandTotal'] ?? 0)) ?>원</span></div>
                </div>
                <div class="shop-checkout__summary-footer">
                    <button type="button" class="shop-checkout__pay-btn" id="btnPay">결제하기</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ── 테스트 결제 확인 모달 ── -->
<div id="testPayModal" class="tpay-modal__overlay">
    <div class="tpay-modal__box">
        <div class="tpay-modal__header">
            <span class="tpay-modal__badge">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 1 0 0 20A10 10 0 0 0 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
                TEST
            </span>
            <span class="tpay-modal__title">테스트 결제 확인</span>
        </div>
        <div class="tpay-modal__body">
            <div class="tpay-modal__rows">
                <div class="tpay-modal__row">
                    <span class="tpay-modal__row-label">상품명</span>
                    <span class="tpay-modal__row-value" id="testPayProductName"></span>
                </div>
                <div class="tpay-modal__row">
                    <span class="tpay-modal__row-label">주문번호</span>
                    <span class="tpay-modal__row-value" id="testPayOrderNo"></span>
                </div>
                <div class="tpay-modal__row tpay-modal__row--amount">
                    <span class="tpay-modal__row-label">결제금액</span>
                    <span class="tpay-modal__row-value" id="testPayAmount"></span>
                </div>
            </div>
            <div class="tpay-modal__notice">
                <svg class="tpay-modal__notice-icon" width="14" height="14" viewBox="0 0 24 24" fill="#c87000"><path d="M1 21L12 2l11 19H1zm11-3v-2h-2v2h2zm0-4V10h-2v4h2z"/></svg>
                실제 결제가 발생하지 않는 개발/테스트 환경의 가상 결제입니다.
            </div>
        </div>
        <div class="tpay-modal__footer">
            <button type="button" class="tpay-modal__cancel-btn" id="testPayCancel">취소</button>
            <button type="button" class="tpay-modal__confirm-btn" id="testPayConfirm">결제 진행</button>
        </div>
    </div>
</div>

<?php if (!$isGuest): ?>
<!-- ── 배송지 관리 모달 ── -->
<div class="addr-modal__overlay" id="addrModalOverlay">
    <div class="addr-modal">
        <div class="addr-modal__header">
            <h3>배송지 관리</h3>
            <button type="button" class="addr-modal__close" id="addrModalClose">&times;</button>
        </div>
        <div class="addr-modal__body">
            <button type="button" class="addr-modal__add-btn" id="addrFormToggle">+ 새 배송지 추가</button>

            <!-- 추가/수정 폼 (토글) -->
            <div class="addr-modal__form" id="addrForm" style="display:none">
                <div class="addr-modal__form-title" id="addrFormTitle">새 배송지 추가</div>
                <input type="hidden" id="mAddrId" value="0">
                <div class="addr-modal__form-row">
                    <span class="addr-modal__form-label">배송지명</span>
                    <input type="text" id="mAddrName" class="addr-modal__form-input addr-modal__form-input--short" placeholder="자택, 직장 등">
                </div>
                <div class="addr-modal__form-row">
                    <span class="addr-modal__form-label">수령인 *</span>
                    <input type="text" id="mRecipient" class="addr-modal__form-input addr-modal__form-input--short">
                </div>
                <div class="addr-modal__form-row">
                    <span class="addr-modal__form-label">연락처</span>
                    <input type="tel" id="mPhone" class="addr-modal__form-input addr-modal__form-input--short mask-hp" placeholder="010-0000-0000">
                </div>
                <div class="addr-modal__form-row">
                    <span class="addr-modal__form-label">우편번호 *</span>
                    <div class="addr-modal__form-zip">
                        <input type="text" id="mZip" class="addr-modal__form-input" readonly>
                        <button type="button" class="addr-modal__form-zip-btn" id="btnZipModal">검색</button>
                    </div>
                </div>
                <div class="addr-modal__form-row">
                    <span class="addr-modal__form-label">주소 *</span>
                    <input type="text" id="mAddr1" class="addr-modal__form-input" readonly placeholder="기본주소">
                </div>
                <div class="addr-modal__form-row">
                    <span class="addr-modal__form-label">상세주소</span>
                    <input type="text" id="mAddr2" class="addr-modal__form-input" placeholder="상세주소">
                </div>
                <div class="addr-modal__form-row">
                    <span class="addr-modal__form-label"></span>
                    <label class="shop-checkout__save-label">
                        <input type="checkbox" id="mIsDefault"> 기본 배송지로 설정
                    </label>
                </div>
                <div class="addr-modal__form-btns">
                    <button type="button" class="addr-modal__form-save" id="addrFormSave">저장</button>
                    <button type="button" class="addr-modal__form-cancel" id="addrFormCancel">취소</button>
                </div>
            </div>

            <!-- 저장된 배송지 목록 -->
            <div class="addr-modal__list" id="addrList"></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$checkoutScripts = $checkoutScripts ?? [];
foreach ($checkoutScripts as $pgScript): ?>
<script><?= $pgScript ?></script>
<?php endforeach; ?>
<script src="//t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js"></script>
<script>
(function() {
    var $ = function(id) { return document.getElementById(id); };
    var isGuest = <?= $isGuest ? 'true' : 'false' ?>;

    // ============================
    // 다음 우편번호 공용 함수
    // ============================
    function openPostcode(zipEl, addr1El, focusEl) {
        new daum.Postcode({
            oncomplete: function(data) {
                zipEl.value = data.zonecode;
                addr1El.value = data.roadAddress || data.jibunAddress;
                if (focusEl) focusEl.focus();
            }
        }).open();
    }

    $('btnZipMain').addEventListener('click', function() {
        openPostcode($('shippingZip'), $('shippingAddr1'), $('shippingAddr2'));
    });

    // ============================
    // 모달 열기/닫기 (회원 전용)
    // ============================
    if (!isGuest) {
    var overlay = $('addrModalOverlay');

    $('btnOpenAddrModal').addEventListener('click', function() {
        overlay.classList.add('is-open');
        loadAddressList();
    });

    $('addrModalClose').addEventListener('click', closeModal);
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeModal();
    });

    } // end !isGuest

    function hideForm() {
        var form = $('addrForm');
        var toggle = $('addrFormToggle');
        if (form) form.style.display = 'none';
        if (toggle) toggle.style.display = '';
    }

    function closeModal() {
        var overlay = $('addrModalOverlay');
        if (overlay) overlay.classList.remove('is-open');
        hideForm();
    }

    // ============================
    // 모달 폼: 새 배송지 / 수정 (회원 전용)
    // ============================
    if (!isGuest) {
    $('addrFormToggle').addEventListener('click', function() {
        resetForm();
        $('addrFormTitle').textContent = '새 배송지 추가';
        $('addrForm').style.display = '';
        this.style.display = 'none';
    });

    $('addrFormCancel').addEventListener('click', hideForm);

    $('btnZipModal').addEventListener('click', function() {
        openPostcode($('mZip'), $('mAddr1'), $('mAddr2'));
    });

    function resetForm() {
        $('mAddrId').value = 0;
        $('mAddrName').value = '';
        $('mRecipient').value = '';
        $('mPhone').value = '';
        $('mZip').value = '';
        $('mAddr1').value = '';
        $('mAddr2').value = '';
        $('mIsDefault').checked = false;
    }

    function fillModalForm(addr) {
        $('mAddrId').value = addr.address_id;
        $('mAddrName').value = addr.address_name || '';
        $('mRecipient').value = addr.recipient_name || '';
        $('mPhone').value = addr.recipient_phone || '';
        $('mZip').value = addr.zip_code || '';
        $('mAddr1').value = addr.address1 || '';
        $('mAddr2').value = addr.address2 || '';
        $('mIsDefault').checked = !!addr.is_default;
    }

    // ============================
    // 저장 (추가 or 수정)
    // ============================
    $('addrFormSave').addEventListener('click', function() {
        var addrId = parseInt($('mAddrId').value) || 0;
        var recipient = $('mRecipient').value.trim();
        var zip = $('mZip').value.trim();
        var addr1 = $('mAddr1').value.trim();

        if (!recipient) { alert('수령인을 입력해주세요.'); return; }
        if (!zip || !addr1) { alert('우편번호와 주소를 입력해주세요.'); return; }

        var payload = {
            address_name: $('mAddrName').value.trim(),
            recipient_name: recipient,
            recipient_phone: $('mPhone').value.trim(),
            zip_code: zip,
            address1: addr1,
            address2: $('mAddr2').value.trim(),
            is_default: $('mIsDefault').checked ? 1 : 0
        };

        if (addrId > 0) {
            payload.address_id = addrId;
            MubloRequest.requestJson('/shop/address/update', payload).then(function() {
                loadAddressList();
                hideForm();
            });
        } else {
            MubloRequest.requestJson('/shop/address/store', payload).then(function() {
                loadAddressList();
                hideForm();
            });
        }
    });

    // ============================
    // 주소 목록 로드 (AJAX)
    // ============================
    function loadAddressList() {
        MubloRequest.requestQuery('/shop/address/list').then(function(res) {
            var list = (res.data || {}).addresses || [];
            renderList(list);
        });
    }

    function renderList(list) {
        var container = $('addrList');
        if (!list.length) {
            container.innerHTML = '<div class="addr-modal__empty">저장된 배송지가 없습니다.</div>';
            return;
        }
        var html = '';
        for (var i = 0; i < list.length; i++) {
            var a = list[i];
            html += '<div class="addr-modal__card" data-id="' + a.address_id + '">';
            html += '<div class="addr-modal__card-top">';
            html += '<span class="addr-modal__card-name">' + esc(a.address_name || a.recipient_name) + '</span>';
            if (a.is_default) html += ' <span class="addr-modal__card-badge">기본</span>';
            html += '</div>';
            html += '<div class="addr-modal__card-info">';
            html += esc(a.recipient_name) + ' / ' + esc(a.recipient_phone) + '<br>';
            html += '[' + esc(a.zip_code) + '] ' + esc(a.address1) + ' ' + esc(a.address2);
            html += '</div>';
            html += '<div class="addr-modal__card-actions">';
            html += '<button class="addr-modal__card-btn addr-modal__card-btn--primary" data-action="select" data-addr=\'' + esc(JSON.stringify(a)) + '\'>선택</button>';
            html += '<button class="addr-modal__card-btn" data-action="edit" data-addr=\'' + esc(JSON.stringify(a)) + '\'>수정</button>';
            if (!a.is_default) html += '<button class="addr-modal__card-btn addr-modal__card-btn--green" data-action="default" data-id="' + a.address_id + '">기본설정</button>';
            html += '<button class="addr-modal__card-btn addr-modal__card-btn--danger" data-action="delete" data-id="' + a.address_id + '">삭제</button>';
            html += '</div></div>';
        }
        container.innerHTML = html;
    }

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    // ============================
    // 목록 카드 버튼 이벤트 (위임)
    // ============================
    $('addrList').addEventListener('click', function(e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;
        var action = btn.getAttribute('data-action');

        if (action === 'select') {
            var addr = JSON.parse(btn.getAttribute('data-addr'));
            // 체크아웃 폼에 적용
            $('recipientName').value = addr.recipient_name || '';
            $('recipientPhone').value = addr.recipient_phone || '';
            $('shippingZip').value = addr.zip_code || '';
            $('shippingAddr1').value = addr.address1 || '';
            $('shippingAddr2').value = addr.address2 || '';
            $('chkSetDefault').checked = !!addr.is_default;
            closeModal();
        }

        if (action === 'edit') {
            var addr = JSON.parse(btn.getAttribute('data-addr'));
            fillModalForm(addr);
            $('addrFormTitle').textContent = '배송지 수정';
            $('addrForm').style.display = '';
            $('addrFormToggle').style.display = 'none';
        }

        if (action === 'default') {
            var id = btn.getAttribute('data-id');
            MubloRequest.requestJson('/shop/address/default', { address_id: parseInt(id) }).then(function() {
                loadAddressList();
            });
        }

        if (action === 'delete') {
            var id = btn.getAttribute('data-id');
            if (!confirm('이 배송지를 삭제하시겠습니까?')) return;
            MubloRequest.requestJson('/shop/address/delete', { address_id: parseInt(id) }).then(function() {
                loadAddressList();
            });
        }
    });

    } // end if (!isGuest) — 모달/주소 관련 코드 끝

    // ============================
    // 쿠폰 적용
    // ============================
    var selectedCoupon = null;
    var originalGrandTotal = <?= (int) ($totals['grandTotal'] ?? 0) ?>;

    if (!isGuest) {
        var couponModalOverlay = $('couponModalOverlay');
        var couponModalBody = $('couponModalBody');

        $('btnSelectCoupon').addEventListener('click', function() {
            couponModalOverlay.classList.add('is-open');
            loadApplicableCoupons();
        });

        $('couponModalClose').addEventListener('click', function() {
            couponModalOverlay.classList.remove('is-open');
        });

        couponModalOverlay.addEventListener('click', function(e) {
            if (e.target === couponModalOverlay) couponModalOverlay.classList.remove('is-open');
        });

        $('btnCancelCoupon').addEventListener('click', function() {
            selectedCoupon = null;
            $('couponArea').style.display = '';
            $('couponSelected').style.display = 'none';
            $('couponApplied').style.display = 'none';
            updateSummary(0);
        });

        function loadApplicableCoupons() {
            couponModalBody.innerHTML = '<div style="text-align:center;padding:30px;color:#aaa">불러오는 중...</div>';
            var amount = originalGrandTotal;

            MubloRequest.requestQuery('/shop/api/coupons/applicable', { order_amount: amount }).then(function(res) {
                var coupons = (res.data && res.data.coupons) || [];
                if (coupons.length === 0) {
                    couponModalBody.innerHTML = '<div class="shop-checkout__coupon-empty">적용 가능한 쿠폰이 없습니다.</div>';
                    return;
                }
                couponModalBody.innerHTML = coupons.map(function(c) {
                    var dl = couponDiscountLabel(c);
                    var method = { ORDER: '주문 할인', GOODS: '상품', CATEGORY: '카테고리', SHIPPING: '배송비' };
                    var cond = [];
                    if (c.min_order_amount > 0) cond.push(Number(c.min_order_amount).toLocaleString() + '원 이상');
                    if (c.valid_until) cond.push(c.valid_until.substring(0, 10) + '까지');
                    var desc = (method[c.coupon_method] || '') + (cond.length ? ' · ' + cond.join(' · ') : '');

                    return '<div class="shop-checkout__coupon-item" data-coupon=\'' + JSON.stringify(c).replace(/'/g, '&#39;') + '\'>'
                        + '<div class="shop-checkout__coupon-item-info">'
                        + '<div class="shop-checkout__coupon-item-name">' + (c.name || '쿠폰') + '</div>'
                        + '<div class="shop-checkout__coupon-item-desc">' + desc + '</div>'
                        + '</div>'
                        + '<div class="shop-checkout__coupon-item-discount">-' + Number(c.estimated_discount).toLocaleString() + '원</div>'
                        + '</div>';
                }).join('');
            });
        }

        couponModalBody.addEventListener('click', function(e) {
            var item = e.target.closest('.shop-checkout__coupon-item');
            if (!item) return;
            var coupon = JSON.parse(item.dataset.coupon);
            applyCoupon(coupon);
            couponModalOverlay.classList.remove('is-open');
        });

        function applyCoupon(coupon) {
            selectedCoupon = coupon;
            $('couponArea').style.display = 'none';
            $('couponSelected').style.display = '';
            $('selectedCouponName').textContent = coupon.name || '쿠폰';
            $('selectedCouponDiscount').textContent = '-' + Number(coupon.estimated_discount).toLocaleString() + '원';
            $('couponApplied').textContent = '적용됨';
            $('couponApplied').style.display = '';
            updateSummary(coupon.estimated_discount);
        }

        function updateSummary(discount) {
            var row = $('summCouponRow');
            var total = $('summGrandTotal');
            if (discount > 0) {
                row.style.display = '';
                $('summCouponAmount').textContent = '-' + Number(discount).toLocaleString() + '원';
            } else {
                row.style.display = 'none';
            }
            total.textContent = Number(originalGrandTotal - discount).toLocaleString() + '원';
        }

        function couponDiscountLabel(c) {
            if (c.discount_type === 'PERCENTAGE') {
                var l = c.discount_value + '%';
                if (c.max_discount) l += ' (최대 ' + Number(c.max_discount).toLocaleString() + '원)';
                return l;
            }
            return Number(c.discount_value).toLocaleString() + '원';
        }
    }

    // ============================
    // 결제하기
    // ============================
    var payBtn = $('btnPay');
    var isPaying = false;

    payBtn.addEventListener('click', function() {
        if (isPaying) return;

        // 비회원 주문자 정보 검증
        if (isGuest) {
            var ordererName = $('ordererName').value.trim();
            var ordererPhone = $('ordererPhone').value.trim();
            var ordererEmail = $('ordererEmail').value.trim();

            if (!ordererName) { alert('주문자명을 입력해주세요.'); return; }
            if (!ordererPhone) { alert('주문자 연락처를 입력해주세요.'); return; }
            if (!ordererEmail) { alert('이메일을 입력해주세요.'); return; }
        }

        var recipientName = $('recipientName').value.trim();
        var recipientPhone = $('recipientPhone').value.trim();
        var shippingZip = $('shippingZip').value.trim();
        var shippingAddr1 = $('shippingAddr1').value.trim();
        var shippingAddr2 = $('shippingAddr2').value.trim();
        var orderMemo = $('orderMemo').value.trim();

        if (!recipientName) { alert('수령인을 입력해주세요.'); return; }
        if (!recipientPhone) { alert('연락처를 입력해주세요.'); return; }
        if (!shippingZip || !shippingAddr1) { alert('배송 주소를 입력해주세요.'); return; }

        var selectedGateway = document.querySelector('input[name="payment_gateway"]:checked');
        if (!selectedGateway) { alert('결제 수단을 선택해주세요.'); return; }

        isPaying = true;
        payBtn.disabled = true;
        payBtn.textContent = '주문 처리 중...';

        var cartItemIds = <?= json_encode(array_column($cartItems, 'cart_item_id')) ?>;

        // 주문 추가 필드 수집
        var orderFieldValues = {};
        document.querySelectorAll('[name^="orderFields["]').forEach(function(el) {
            var match = el.name.match(/orderFields\[(\d+)\](\[(\w+)\])?/);
            if (!match) return;
            var fid = match[1];
            var subKey = match[3] || null;

            if (el.type === 'checkbox' && !subKey) {
                // 체크박스 배열: orderFields[id][]
                if (!orderFieldValues[fid]) orderFieldValues[fid] = [];
                if (el.checked) orderFieldValues[fid].push(el.value);
            } else if (el.type === 'radio') {
                if (el.checked) orderFieldValues[fid] = el.value;
            } else if (subKey) {
                // address 서브필드: orderFields[id][zipcode] etc.
                if (!orderFieldValues[fid] || typeof orderFieldValues[fid] !== 'object') orderFieldValues[fid] = {};
                orderFieldValues[fid][subKey] = el.value;
            } else {
                orderFieldValues[fid] = el.value;
            }
        });
        // hidden meta (file 타입)
        document.querySelectorAll('input[id$="_meta"][type="hidden"]').forEach(function(el) {
            var match = el.id.match(/of_(\d+)_meta/);
            if (match && el.value) orderFieldValues[match[1]] = el.value;
        });

        // Step 1: 주문 생성 + PG 결제 준비
        var paymentData = {
            payment_gateway: selectedGateway.value,
            payment_method: selectedGateway.value,
            checkout_mode: '<?= htmlspecialchars($checkoutMode) ?>',
            cart_item_ids: cartItemIds,
            recipient_name: recipientName,
            recipient_phone: recipientPhone,
            shipping_zip: shippingZip,
            shipping_address1: shippingAddr1,
            shipping_address2: shippingAddr2,
            order_memo: orderMemo,
            order_fields: orderFieldValues
        };

        // 쿠폰 적용 정보 추가
        if (selectedCoupon && selectedCoupon.coupon_id) {
            paymentData.coupon_id = selectedCoupon.coupon_id;
        }

        // 비회원 주문자 정보 추가
        if (isGuest) {
            paymentData.is_guest = true;
            paymentData.orderer_name = $('ordererName').value.trim();
            paymentData.orderer_phone = $('ordererPhone').value.trim();
            paymentData.orderer_email = $('ordererEmail').value.trim();
        }

        MubloRequest.requestJson('/shop/checkout/payment', paymentData).then(function(res) {
            var data = res.data || {};
            // Step 2: PG 결제창 처리
            handlePaymentGateway(data);
        }).catch(function() {
            resetPayBtn();
        });
    });

    // PG 핸들러 리셋 콜백 노출 (PG 플러그인 JS에서 사용)
    window.MubloPayReset = resetPayBtn;

    /**
     * PG 유형에 따라 결제창 처리
     *
     * 우선순위:
     * 1. PG 플러그인이 등록한 window.MubloPayHandlers[gateway] 핸들러
     * 2. 폴백: 내부 테스트 결제 (mode=test) → 모달 확인 후 verify
     * 3. 폴백: 기타 → 바로 verify
     */
    function handlePaymentGateway(data) {
        var gateway = data.gateway || '';

        if (window.MubloPayHandlers && window.MubloPayHandlers[gateway]) {
            window.MubloPayHandlers[gateway](data);
            return;
        }

        // 폴백: 특별한 핸들러 없는 PG (TestPay 등)
        var config = data.client_config || {};
        if (config.mode === 'test') {
            showTestPayModal(data);
        } else {
            verifyPayment(data);
        }
    }

    /**
     * 내부 테스트 결제 확인 모달
     */
    function showTestPayModal(data) {
        var modal = document.getElementById('testPayModal');
        document.getElementById('testPayProductName').textContent = data.order_name || data.order_no;
        document.getElementById('testPayOrderNo').textContent = data.order_no;
        document.getElementById('testPayAmount').textContent = (data.amount || 0).toLocaleString() + '원';
        modal.style.display = 'flex';

        document.getElementById('testPayConfirm').onclick = function() {
            modal.style.display = 'none';
            verifyPayment(data);
        };
        document.getElementById('testPayCancel').onclick = function() {
            modal.style.display = 'none';
            resetPayBtn();
        };
    }

    /**
     * Step 3: 결제 검증 요청
     */
    function verifyPayment(data) {
        MubloRequest.requestJson('/shop/checkout/verify', {
            order_no: data.order_no,
            payment_gateway: data.gateway,
            transaction_id: data.transaction_id
        }).then(function(res) {
            if (res.data && res.data.redirect) {
                location.href = res.data.redirect;
            }
        }).catch(function() {
            resetPayBtn();
        });
    }

    function resetPayBtn() {
        isPaying = false;
        payBtn.disabled = false;
        payBtn.textContent = '결제하기';
    }
})();
</script>
<?php if (!empty($orderFields)): ?>
<?= \Mublo\Service\CustomField\CustomFieldRenderer::renderFileScript('/shop/checkout/upload-file') ?>
<?php endif; ?>
