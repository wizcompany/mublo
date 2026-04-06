<?php
/**
 * 장바구니 (프론트)
 * @var array $groups 배송 그룹별 상품 데이터 [groupKey => { template_id, template_name, shipping_fee, goods }]
 * @var array $totals 합계 정보 { itemTotal, shippingTotal, pointTotal, grandTotal }
 * @var array $productData 옵션 모달용 상품 데이터
 */
?>

<style>
/* ── 장바구니 레이아웃 ── */
.shop-cart { max-width: 960px; margin: 0 auto; padding: 24px 16px; }
.shop-cart__title { font-size: 1.5rem; font-weight: 700; margin-bottom: 24px; }

/* ── 빈 장바구니 ── */
.shop-cart__empty { text-align: center; padding: 60px 16px; }
.shop-cart__empty-icon { color: #d1d5db; margin-bottom: 12px; }
.shop-cart__empty-icon svg { width: 56px; height: 56px; }
.shop-cart__empty-text { font-size: 1rem; color: #888; margin-bottom: 20px; }
.shop-cart__empty-btn { display: inline-block; padding: 10px 28px; background: #667eea; color: #fff; border-radius: 8px; text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: background 0.15s; }
.shop-cart__empty-btn:hover { background: #5a6fd6; color: #fff; }

/* ── 인라인 아이콘 ── */
.shop-cart__icon { display: inline-flex; align-items: center; vertical-align: middle; }
.shop-cart__icon svg { width: 16px; height: 16px; }
.shop-cart__icon--truck svg { color: #667eea; }
.shop-cart__icon--img svg { width: 24px; height: 24px; color: #d1d5db; }
.shop-cart__icon--plus { color: #667eea; font-weight: 700; font-size: 0.85rem; }

/* ── 배송 그룹 카드 ── */
.shop-cart__group { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 16px; overflow: hidden; }
.shop-cart__group-header { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; background: #f9fafb; border-bottom: 1px solid #e5e7eb; }
.shop-cart__group-title { font-size: 0.9rem; font-weight: 600; color: #333; display: flex; align-items: center; gap: 8px; }
.shop-cart__group-shipping { font-size: 0.8rem; color: #888; }
.shop-cart__group-shipping--free { color: #22c55e; font-weight: 600; }

/* ── 전체선택 헤더 ── */
.shop-cart__select-header { display: flex; align-items: center; padding: 10px 20px; border-bottom: 1px solid #f0f0f0; }
.shop-cart__select-header label { font-size: 0.8rem; color: #888; cursor: pointer; display: flex; align-items: center; gap: 6px; }
.shop-cart__select-header input[type="checkbox"] { accent-color: #667eea; width: 16px; height: 16px; }

/* ── 상품 행 ── */
.shop-cart__item { display: flex; align-items: center; padding: 16px 20px; gap: 14px; border-bottom: 1px solid #f0f0f0; }
.shop-cart__item:last-child { border-bottom: none; }
.shop-cart__item--extra { background: #fafbfc; padding-left: 34px; }
.shop-cart__item--unavailable { opacity: 0.5; }

/* 체크박스 */
.shop-cart__item-check { flex-shrink: 0; }
.shop-cart__item-check input[type="checkbox"] { accent-color: #667eea; width: 16px; height: 16px; cursor: pointer; }

/* 이미지 */
.shop-cart__item-image { width: 72px; height: 72px; border-radius: 8px; overflow: hidden; background: #f5f5f5; flex-shrink: 0; }
.shop-cart__item-image img { width: 100%; height: 100%; object-fit: cover; }
.shop-cart__item-image--empty { display: flex; align-items: center; justify-content: center; }

/* 상품 정보 */
.shop-cart__item-info { flex: 1; min-width: 0; }
.shop-cart__item-name { font-weight: 600; font-size: 0.9rem; color: #111; margin-bottom: 4px; word-break: break-word; }
.shop-cart__item-option { font-size: 0.8rem; color: #888; }
.shop-cart__item-badge { display: inline-block; padding: 2px 8px; background: #fef2f2; color: #ef4444; font-size: 0.75rem; border-radius: 4px; margin-top: 4px; font-weight: 500; }
.shop-cart__item-extra-label { font-size: 0.8rem; color: #888; display: flex; align-items: center; gap: 6px; }

/* 수량 */
.shop-cart__item-qty { flex-shrink: 0; display: flex; align-items: center; border: 1px solid #d1d5db; border-radius: 6px; overflow: hidden; }
.shop-cart__qty-btn { width: 30px; height: 30px; border: none; background: #f9fafb; color: #555; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: background 0.1s; }
.shop-cart__qty-btn:hover { background: #e5e7eb; }
.shop-cart__qty-input { width: 40px; height: 30px; border: none; border-left: 1px solid #e5e7eb; border-right: 1px solid #e5e7eb; text-align: center; font-size: 0.85rem; font-weight: 600; color: #111; background: #fff; outline: none; }

/* 가격 */
.shop-cart__item-price { flex-shrink: 0; min-width: 90px; text-align: right; font-weight: 700; font-size: 0.95rem; color: #111; }

/* 삭제 */
.shop-cart__remove-btn { width: 28px; height: 28px; border: none; background: none; color: #bbb; font-size: 1.1rem; cursor: pointer; border-radius: 4px; display: flex; align-items: center; justify-content: center; transition: color 0.1s, background 0.1s; line-height: 1; }
.shop-cart__remove-btn:hover { color: #ef4444; background: #fef2f2; }

/* 옵션변경 */
.shop-cart__option-change { padding: 8px 20px 12px 34px; border-bottom: 1px solid #f0f0f0; }
.shop-cart__option-btn { padding: 5px 12px; border: 1px solid #d1d5db; background: #fff; color: #555; border-radius: 6px; font-size: 0.8rem; cursor: pointer; display: inline-flex; align-items: center; gap: 5px; transition: border-color 0.15s; }
.shop-cart__option-btn:hover { border-color: #667eea; color: #667eea; }

/* ── 합계 ── */
.shop-cart__summary { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 24px; margin-top: 20px; }
.shop-cart__summary-row { display: flex; align-items: center; justify-content: center; }
.shop-cart__summary-item { flex: 1; text-align: center; }
.shop-cart__summary-label { font-size: 0.8rem; color: #888; margin-bottom: 6px; }
.shop-cart__summary-value { font-size: 1.15rem; font-weight: 700; color: #333; }
.shop-cart__summary-value--primary { color: #667eea; font-size: 1.3rem; }
.shop-cart__summary-op { flex-shrink: 0; font-size: 1.2rem; color: #bbb; padding: 0 8px; align-self: flex-end; margin-bottom: 4px; }

/* ── 하단 버튼 ── */
.shop-cart__actions { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; }
.shop-cart__continue-btn { padding: 10px 24px; border: 1px solid #d1d5db; background: #fff; color: #555; border-radius: 8px; text-decoration: none; font-size: 0.9rem; transition: border-color 0.15s; }
.shop-cart__continue-btn:hover { border-color: #667eea; color: #667eea; }
.shop-cart__order-btn { padding: 12px 40px; background: #667eea; color: #fff; border: none; border-radius: 8px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: background 0.15s; }
.shop-cart__order-btn:hover { background: #5a6fd6; }

/* ── 옵션 변경 모달 ── */
.shop-cart__modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 9000; align-items: center; justify-content: center; }
.shop-cart__modal-overlay.is-open { display: flex; }
.shop-cart__modal { background: #fff; border-radius: 14px; width: 480px; max-width: 90vw; max-height: 80vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.2); }
.shop-cart__modal-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; border-bottom: 1px solid #e5e7eb; }
.shop-cart__modal-title { font-size: 1.05rem; font-weight: 700; color: #111; }
.shop-cart__modal-close { width: 32px; height: 32px; border: none; background: none; font-size: 1.3rem; color: #888; cursor: pointer; border-radius: 6px; display: flex; align-items: center; justify-content: center; }
.shop-cart__modal-close:hover { background: #f3f4f6; color: #333; }
.shop-cart__modal-body { padding: 20px 24px; }
.shop-cart__modal-footer { display: flex; justify-content: flex-end; gap: 8px; padding: 16px 24px; border-top: 1px solid #e5e7eb; }
.shop-cart__modal-btn { padding: 8px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: background 0.15s; }
.shop-cart__modal-btn--cancel { border: 1px solid #d1d5db; background: #fff; color: #555; }
.shop-cart__modal-btn--cancel:hover { background: #f3f4f6; }
.shop-cart__modal-btn--confirm { border: none; background: #667eea; color: #fff; }
.shop-cart__modal-btn--confirm:hover { background: #5a6fd6; }

/* ── 반응형 ── */
@media (max-width: 640px) {
    .shop-cart { padding: 16px 12px; }
    .shop-cart__item { flex-wrap: wrap; gap: 10px; padding: 14px 16px; }
    .shop-cart__item-image { width: 60px; height: 60px; }
    .shop-cart__item-info { min-width: calc(100% - 100px); }
    .shop-cart__item-price { width: 100%; text-align: right; padding-top: 6px; }
    .shop-cart__summary-row { flex-wrap: wrap; gap: 12px; }
    .shop-cart__summary-item { flex: unset; width: 100%; }
    .shop-cart__summary-op { display: none; }
    .shop-cart__actions { flex-direction: column; gap: 10px; }
    .shop-cart__order-btn { width: 100%; }
}
</style>

<div class="shop-cart">
    <h1 class="shop-cart__title">장바구니</h1>

    <?php if (empty($groups)): ?>
        <div class="shop-cart__empty">
            <div class="shop-cart__empty-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    <line x1="10" y1="11" x2="16" y2="11"/>
                </svg>
            </div>
            <p class="shop-cart__empty-text">장바구니가 비어있습니다.</p>
            <a href="/shop/products" class="shop-cart__empty-btn">쇼핑 계속하기</a>
        </div>
    <?php else: ?>
        <form id="cartForm">
            <?php foreach ($groups as $groupKey => $group): ?>
                <div class="shop-cart__group">
                    <div class="shop-cart__group-header">
                        <span class="shop-cart__group-title">
                            <span class="shop-cart__icon shop-cart__icon--truck">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
                                    <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
                                </svg>
                            </span>
                            <?= htmlspecialchars($group['template_name']) ?>
                        </span>
                        <?php if ($group['shipping_fee'] > 0): ?>
                            <span class="shop-cart__group-shipping">배송비 <?= number_format($group['shipping_fee']) ?>원</span>
                        <?php else: ?>
                            <span class="shop-cart__group-shipping shop-cart__group-shipping--free">무료배송</span>
                        <?php endif; ?>
                    </div>

                    <div class="shop-cart__select-header">
                        <label><input type="checkbox" class="group-check" data-group="<?= htmlspecialchars($groupKey) ?>" checked> 전체선택</label>
                    </div>

                    <?php foreach ($group['goods'] as $goodsId => $goods): ?>
                        <?php foreach ($goods['options'] as $opt): ?>
                            <div class="shop-cart__item <?= !$goods['goods_info']['is_available'] ? 'shop-cart__item--unavailable' : '' ?>"
                                 data-cart-id="<?= $opt['cart_item_id'] ?>" data-group="<?= htmlspecialchars($groupKey) ?>">
                                <div class="shop-cart__item-check">
                                    <input type="checkbox" class="cart-check" name="cart_item_ids[]" value="<?= $opt['cart_item_id'] ?>" checked>
                                </div>
                                <?php if (!empty($goods['goods_info']['product_image'])): ?>
                                    <div class="shop-cart__item-image">
                                        <img src="<?= htmlspecialchars($goods['goods_info']['product_image']) ?>" alt="">
                                    </div>
                                <?php else: ?>
                                    <div class="shop-cart__item-image shop-cart__item-image--empty">
                                        <span class="shop-cart__icon shop-cart__icon--img">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                                <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>
                                            </svg>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                <div class="shop-cart__item-info">
                                    <div class="shop-cart__item-name"><?= htmlspecialchars($goods['goods_info']['goods_name']) ?></div>
                                    <?php if ($opt['option_label']): ?>
                                        <div class="shop-cart__item-option"><?= htmlspecialchars($opt['option_label']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!$goods['goods_info']['is_available']): ?>
                                        <span class="shop-cart__item-badge">품절</span>
                                    <?php endif; ?>
                                </div>
                                <div class="shop-cart__item-qty">
                                    <button type="button" class="shop-cart__qty-btn" onclick="ShopCart.updateQty(<?= $opt['cart_item_id'] ?>, -1)">&minus;</button>
                                    <input type="text" class="shop-cart__qty-input" value="<?= $opt['quantity'] ?>" readonly>
                                    <button type="button" class="shop-cart__qty-btn" onclick="ShopCart.updateQty(<?= $opt['cart_item_id'] ?>, 1)">&plus;</button>
                                </div>
                                <div class="shop-cart__item-price"><?= number_format($opt['total_price']) ?>원</div>
                                <div class="shop-cart__item-remove">
                                    <button type="button" class="shop-cart__remove-btn" onclick="ShopCart.remove(<?= $opt['cart_item_id'] ?>)">&times;</button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php foreach ($goods['extras'] as $ext): ?>
                            <div class="shop-cart__item shop-cart__item--extra"
                                 data-cart-id="<?= $ext['cart_item_id'] ?>" data-group="<?= htmlspecialchars($groupKey) ?>">
                                <div class="shop-cart__item-check">
                                    <input type="checkbox" class="cart-check" name="cart_item_ids[]" value="<?= $ext['cart_item_id'] ?>" checked>
                                </div>
                                <div class="shop-cart__item-info">
                                    <div class="shop-cart__item-extra-label">
                                        <span class="shop-cart__icon--plus">+</span>
                                        추가옵션: <?= htmlspecialchars($ext['option_label'] ?? $ext['option_code'] ?? '') ?>
                                    </div>
                                </div>
                                <div class="shop-cart__item-qty">
                                    <button type="button" class="shop-cart__qty-btn" onclick="ShopCart.updateQty(<?= $ext['cart_item_id'] ?>, -1)">&minus;</button>
                                    <input type="text" class="shop-cart__qty-input" value="<?= $ext['quantity'] ?>" readonly>
                                    <button type="button" class="shop-cart__qty-btn" onclick="ShopCart.updateQty(<?= $ext['cart_item_id'] ?>, 1)">&plus;</button>
                                </div>
                                <div class="shop-cart__item-price"><?= number_format($ext['total_price']) ?>원</div>
                                <div class="shop-cart__item-remove">
                                    <button type="button" class="shop-cart__remove-btn" onclick="ShopCart.remove(<?= $ext['cart_item_id'] ?>)">&times;</button>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <?php if ($goods['goods_info']['option_mode'] !== 'NONE'): ?>
                            <div class="shop-cart__option-change">
                                <button type="button" class="shop-cart__option-btn" onclick="ShopCart.changeOption(<?= $goodsId ?>)">
                                    &#9998; 옵션변경
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </form>

        <!-- 합계 -->
        <div class="shop-cart__summary">
            <div class="shop-cart__summary-row">
                <div class="shop-cart__summary-item">
                    <div class="shop-cart__summary-label">상품금액</div>
                    <div class="shop-cart__summary-value" id="cartItemTotal"><?= number_format($totals['itemTotal']) ?>원</div>
                </div>
                <div class="shop-cart__summary-op">+</div>
                <div class="shop-cart__summary-item">
                    <div class="shop-cart__summary-label">배송비</div>
                    <div class="shop-cart__summary-value" id="cartShippingTotal"><?= number_format($totals['shippingTotal']) ?>원</div>
                </div>
                <div class="shop-cart__summary-op">=</div>
                <div class="shop-cart__summary-item">
                    <div class="shop-cart__summary-label">총 결제금액</div>
                    <div class="shop-cart__summary-value shop-cart__summary-value--primary" id="cartGrandTotal"><?= number_format($totals['grandTotal']) ?>원</div>
                </div>
            </div>
        </div>

        <div class="shop-cart__actions">
            <a href="/shop/products" class="shop-cart__continue-btn">쇼핑 계속하기</a>
            <button type="button" class="shop-cart__order-btn" onclick="ShopCart.checkout()">주문하기</button>
        </div>
    <?php endif; ?>
</div>

<!-- 옵션 변경 모달 -->
<div class="shop-cart__modal-overlay" id="optionChangeOverlay">
    <div class="shop-cart__modal">
        <div class="shop-cart__modal-header">
            <span class="shop-cart__modal-title">옵션 변경</span>
            <button type="button" class="shop-cart__modal-close" onclick="ShopCart.closeOptionModal()">&times;</button>
        </div>
        <div class="shop-cart__modal-body" id="optionChangeBody"></div>
        <div class="shop-cart__modal-footer">
            <button type="button" class="shop-cart__modal-btn shop-cart__modal-btn--cancel" onclick="ShopCart.closeOptionModal()">취소</button>
            <button type="button" class="shop-cart__modal-btn shop-cart__modal-btn--confirm" id="optionChangeConfirm">변경</button>
        </div>
    </div>
</div>

<script>
const productData = <?= json_encode($productData ?? [], JSON_UNESCAPED_UNICODE) ?>;

const ShopCart = {
    updateQty(cartItemId, delta) {
        const row = document.querySelector(`[data-cart-id="${cartItemId}"]`);
        if (!row) return;
        const input = row.querySelector('.shop-cart__qty-input');
        const newQty = Math.max(1, parseInt(input.value) + delta);

        MubloRequest.requestJson('/shop/cart/update', {
            cart_item_id: cartItemId,
            quantity: newQty
        }).then(() => location.reload());
    },

    remove(cartItemId) {
        if (!confirm('삭제하시겠습니까?')) return;
        MubloRequest.requestJson('/shop/cart/remove', {
            cart_item_id: cartItemId
        }).then(() => location.reload());
    },

    changeOption(goodsId) {
        const data = productData[goodsId];
        if (!data) return;

        this._currentGoodsId = goodsId;
        const body = document.getElementById('optionChangeBody');
        body.innerHTML = this._renderOptionForm(data);

        // 수량 입력
        const qtyWrap = document.createElement('div');
        qtyWrap.className = 'shop-cart__modal-qty';
        qtyWrap.innerHTML = '<label style="font-size:.85rem;font-weight:600;margin-bottom:4px;display:block">수량</label>'
            + '<div style="display:flex;align-items:center;gap:8px">'
            + '<button type="button" onclick="ShopCart._changeQty(-1)" style="width:30px;height:30px;border:1px solid #e5e7eb;background:#fff;border-radius:6px;cursor:pointer">-</button>'
            + '<input id="optionQtyInput" type="number" min="1" value="1" style="width:60px;text-align:center;border:1px solid #e5e7eb;border-radius:6px;height:30px">'
            + '<button type="button" onclick="ShopCart._changeQty(1)" style="width:30px;height:30px;border:1px solid #e5e7eb;background:#fff;border-radius:6px;cursor:pointer">+</button>'
            + '</div>';
        body.appendChild(qtyWrap);

        document.getElementById('optionChangeConfirm').onclick = () => ShopCart.confirmOptionChange();
        this.openOptionModal();
    },

    _currentGoodsId: null,

    _renderOptionForm(data) {
        if (data.option_mode === 'NONE' || !data.options || data.options.length === 0) {
            return '<p style="color:#888;font-size:.875rem">이 상품은 옵션이 없습니다.</p>';
        }

        if (data.option_mode === 'COMBINATION' && data.combos && data.combos.length > 0) {
            let html = '<div style="margin-bottom:12px"><label style="font-size:.85rem;font-weight:600;margin-bottom:4px;display:block">옵션 선택</label>'
                + '<select id="optionComboSelect" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:.875rem">'
                + '<option value="">옵션을 선택하세요</option>';
            data.combos.forEach(combo => {
                const price = combo.extra_price > 0 ? ` (+${combo.extra_price.toLocaleString()}원)` : (combo.extra_price < 0 ? ` (${combo.extra_price.toLocaleString()}원)` : '');
                const stock = combo.stock_qty !== undefined && combo.stock_qty !== null && combo.stock_qty <= 0 ? ' [품절]' : '';
                const disabled = stock ? ' disabled' : '';
                html += `<option value="${combo.combo_id}"${disabled}>${combo.combo_label}${price}${stock}</option>`;
            });
            html += '</select></div>';
            return html;
        }

        // SELECT / CHOICE
        let html = '';
        data.options.forEach(opt => {
            html += '<div style="margin-bottom:12px">'
                + `<label style="font-size:.85rem;font-weight:600;margin-bottom:4px;display:block">${opt.option_name || '옵션'}</label>`;

            if (opt.option_type === 'CHOICE') {
                html += '<div style="display:flex;flex-wrap:wrap;gap:6px">';
                (opt.values || []).forEach(v => {
                    const price = v.extra_price > 0 ? ` +${v.extra_price.toLocaleString()}원` : '';
                    html += `<label style="cursor:pointer">`
                        + `<input type="checkbox" name="opt_${opt.option_id}" value="${v.option_value_id}" style="margin-right:3px">`
                        + `${v.option_value}${price}</label>`;
                });
                html += '</div>';
            } else {
                html += `<select name="opt_${opt.option_id}" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px;font-size:.875rem">`
                    + '<option value="">선택하세요</option>';
                (opt.values || []).forEach(v => {
                    const price = v.extra_price > 0 ? ` (+${v.extra_price.toLocaleString()}원)` : (v.extra_price < 0 ? ` (${v.extra_price.toLocaleString()}원)` : '');
                    html += `<option value="${v.option_value_id}">${v.option_value}${price}</option>`;
                });
                html += '</select>';
            }
            html += '</div>';
        });
        return html;
    },

    _changeQty(delta) {
        const input = document.getElementById('optionQtyInput');
        if (input) input.value = Math.max(1, parseInt(input.value || 1) + delta);
    },

    confirmOptionChange() {
        const goodsId = this._currentGoodsId;
        const data = productData[goodsId];
        if (!data) return;

        const qty = Math.max(1, parseInt(document.getElementById('optionQtyInput')?.value || 1));
        const payload = {
            goods_id: goodsId,
            optionMode: data.option_mode,
            quantity: qty,
            selectedOptions: [],
            selectedExtras: [],
        };

        if (data.option_mode === 'COMBINATION') {
            const sel = document.getElementById('optionComboSelect');
            if (sel && sel.value) {
                payload.selectedOptions = [{ combo_id: parseInt(sel.value) }];
            }
        } else {
            const body = document.getElementById('optionChangeBody');
            (data.options || []).forEach(opt => {
                if (opt.option_type === 'CHOICE') {
                    const checked = body.querySelectorAll(`input[name="opt_${opt.option_id}"]:checked`);
                    checked.forEach(cb => {
                        payload.selectedExtras.push({ option_id: opt.option_id, option_value_id: parseInt(cb.value) });
                    });
                } else {
                    const sel = body.querySelector(`select[name="opt_${opt.option_id}"]`);
                    if (sel && sel.value) {
                        payload.selectedOptions.push({ option_id: opt.option_id, option_value_id: parseInt(sel.value) });
                    }
                }
            });
        }

        MubloRequest.requestJson('/shop/cart/update-option', payload)
            .then(() => {
                this.closeOptionModal();
                location.reload();
            });
    },

    openOptionModal() {
        document.getElementById('optionChangeOverlay').classList.add('is-open');
    },

    closeOptionModal() {
        document.getElementById('optionChangeOverlay').classList.remove('is-open');
    },

    checkout() {
        const checked = document.querySelectorAll('.cart-check:checked');
        const ids = Array.from(checked).map(el => parseInt(el.value));

        if (ids.length === 0) {
            alert('주문할 상품을 선택해주세요.');
            return;
        }

        MubloRequest.requestJson('/shop/cart/prepare-checkout', {
            cart_item_ids: ids
        }).then(res => {
            location.href = res.data?.redirect || '/shop/checkout';
        });
    }
};

// 그룹 전체선택
document.querySelectorAll('.group-check').forEach(el => {
    el.addEventListener('change', function() {
        const group = this.dataset.group;
        document.querySelectorAll(`[data-group="${group}"] .cart-check`).forEach(cb => {
            cb.checked = this.checked;
        });
    });
});

// 모달 오버레이 클릭 시 닫기
document.getElementById('optionChangeOverlay')?.addEventListener('click', function(e) {
    if (e.target === this) ShopCart.closeOptionModal();
});
</script>
