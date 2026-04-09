<?php
/**
 * 쿠폰함 (프론트)
 *
 * 내 쿠폰 목록 + 다운로드 가능 쿠폰 + 프로모션 코드 등록
 * 모든 데이터는 API로 로드 (SPA 방식)
 */
?>

<style>
.shop-coupon { max-width: 720px; margin: 0 auto; padding: 24px 16px; }
.shop-coupon__title { font-size: 1.3rem; font-weight: 700; margin-bottom: 20px; color: #333; }

/* 프로모션 코드 */
.shop-coupon__promo { display: flex; gap: 8px; margin-bottom: 24px; }
.shop-coupon__promo-input { flex: 1; padding: 10px 14px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.9rem; text-transform: uppercase; outline: none; }
.shop-coupon__promo-input:focus { border-color: #667eea; box-shadow: 0 0 0 2px rgba(102,126,234,0.15); }
.shop-coupon__promo-btn { padding: 10px 20px; background: #667eea; color: #fff; border: none; border-radius: 8px; font-size: 0.9rem; font-weight: 600; cursor: pointer; white-space: nowrap; }
.shop-coupon__promo-btn:hover { background: #5a6fd6; }

/* 탭 */
.shop-coupon__tabs { display: flex; gap: 0; border-bottom: 2px solid #e5e7eb; margin-bottom: 20px; }
.shop-coupon__tab { padding: 10px 20px; font-size: 0.9rem; font-weight: 600; color: #888; border: none; background: none; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -2px; }
.shop-coupon__tab--active { color: #667eea; border-bottom-color: #667eea; }

/* 쿠폰 카드 */
.shop-coupon__card { display: flex; border: 1px solid #e5e7eb; border-radius: 12px; margin-bottom: 12px; overflow: hidden; background: #fff; transition: box-shadow 0.15s; }
.shop-coupon__card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.shop-coupon__card-left { flex: 1; padding: 16px 20px; min-width: 0; }
.shop-coupon__card-name { font-weight: 700; font-size: 0.95rem; color: #333; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.shop-coupon__card-discount { font-size: 1.2rem; font-weight: 700; color: #667eea; margin-bottom: 6px; }
.shop-coupon__card-info { font-size: 0.8rem; color: #888; line-height: 1.5; }
.shop-coupon__card-info span { display: inline-block; margin-right: 12px; }
.shop-coupon__card-right { display: flex; align-items: center; justify-content: center; width: 90px; border-left: 1px dashed #e5e7eb; flex-shrink: 0; }
.shop-coupon__card-btn { padding: 8px 14px; background: #667eea; color: #fff; border: none; border-radius: 6px; font-size: 0.8rem; font-weight: 600; cursor: pointer; }
.shop-coupon__card-btn:hover { background: #5a6fd6; }
.shop-coupon__card-btn:disabled { background: #d1d5db; cursor: not-allowed; }
.shop-coupon__card-status { font-size: 0.8rem; font-weight: 600; color: #888; }

/* 빈 상태 */
.shop-coupon__empty { text-align: center; padding: 40px 16px; color: #888; font-size: 0.9rem; }
.shop-coupon__loading { text-align: center; padding: 40px 16px; color: #aaa; font-size: 0.9rem; }

/* 반응형 */
@media (max-width: 640px) {
    .shop-coupon { padding: 16px 12px; }
    .shop-coupon__card-right { width: 70px; }
    .shop-coupon__card-discount { font-size: 1rem; }
    .shop-coupon__promo { flex-direction: column; }
}
</style>

<div class="shop-coupon">
    <h2 class="shop-coupon__title">쿠폰함</h2>

    <!-- 프로모션 코드 입력 -->
    <div class="shop-coupon__promo">
        <input type="text" class="shop-coupon__promo-input" id="promoCodeInput" placeholder="프로모션 코드를 입력하세요" maxlength="30">
        <button type="button" class="shop-coupon__promo-btn" id="btnRegisterPromo">등록</button>
    </div>

    <!-- 탭 -->
    <div class="shop-coupon__tabs">
        <button type="button" class="shop-coupon__tab shop-coupon__tab--active" data-tab="my">내 쿠폰</button>
        <button type="button" class="shop-coupon__tab" data-tab="download">다운로드</button>
    </div>

    <!-- 내 쿠폰 목록 -->
    <div id="tabMy">
        <div class="shop-coupon__loading" id="myLoading">불러오는 중...</div>
        <div id="myList"></div>
    </div>

    <!-- 다운로드 가능 쿠폰 -->
    <div id="tabDownload" style="display:none">
        <div class="shop-coupon__loading" id="dlLoading">불러오는 중...</div>
        <div id="dlList"></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 탭 전환
    var tabs = document.querySelectorAll('.shop-coupon__tab');
    var tabMy = document.getElementById('tabMy');
    var tabDl = document.getElementById('tabDownload');

    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            tabs.forEach(function(t) { t.classList.remove('shop-coupon__tab--active'); });
            this.classList.add('shop-coupon__tab--active');
            var target = this.dataset.tab;
            tabMy.style.display = target === 'my' ? '' : 'none';
            tabDl.style.display = target === 'download' ? '' : 'none';
        });
    });

    // 할인 표시 헬퍼
    function discountLabel(coupon) {
        if (coupon.discount_type === 'PERCENTAGE') {
            var label = coupon.discount_value + '%';
            if (coupon.max_discount) label += ' (최대 ' + Number(coupon.max_discount).toLocaleString() + '원)';
            return label;
        }
        return Number(coupon.discount_value).toLocaleString() + '원';
    }

    // 적용 대상 표시
    function methodLabel(coupon) {
        var m = { ORDER: '주문 할인', GOODS: '상품 할인', CATEGORY: '카테고리 할인', SHIPPING: '배송비 할인' };
        return m[coupon.coupon_method] || '할인';
    }

    // 조건 텍스트
    function conditionText(coupon) {
        var parts = [];
        if (coupon.min_order_amount > 0) parts.push(Number(coupon.min_order_amount).toLocaleString() + '원 이상 주문 시');
        if (coupon.valid_until) parts.push(coupon.valid_until.substring(0, 10) + '까지');
        return parts.join(' · ') || '제한 없음';
    }

    // 내 쿠폰 렌더
    function renderMyCoupons(coupons) {
        var container = document.getElementById('myList');
        document.getElementById('myLoading').style.display = 'none';

        if (!coupons || coupons.length === 0) {
            container.innerHTML = '<div class="shop-coupon__empty">보유한 쿠폰이 없습니다.</div>';
            return;
        }

        container.innerHTML = coupons.map(function(c) {
            return '<div class="shop-coupon__card">'
                + '<div class="shop-coupon__card-left">'
                + '<div class="shop-coupon__card-name">' + (c.name || '쿠폰') + '</div>'
                + '<div class="shop-coupon__card-discount">' + discountLabel(c) + ' ' + methodLabel(c) + '</div>'
                + '<div class="shop-coupon__card-info">'
                + '<span>' + conditionText(c) + '</span>'
                + '</div></div>'
                + '<div class="shop-coupon__card-right">'
                + '<span class="shop-coupon__card-status">보유</span>'
                + '</div></div>';
        }).join('');
    }

    // 다운로드 가능 쿠폰 렌더
    function renderDownloadable(coupons) {
        var container = document.getElementById('dlList');
        document.getElementById('dlLoading').style.display = 'none';

        if (!coupons || coupons.length === 0) {
            container.innerHTML = '<div class="shop-coupon__empty">다운로드 가능한 쿠폰이 없습니다.</div>';
            return;
        }

        container.innerHTML = coupons.map(function(c) {
            return '<div class="shop-coupon__card">'
                + '<div class="shop-coupon__card-left">'
                + '<div class="shop-coupon__card-name">' + (c.name || '쿠폰') + '</div>'
                + '<div class="shop-coupon__card-discount">' + discountLabel(c) + ' ' + methodLabel(c) + '</div>'
                + '<div class="shop-coupon__card-info">'
                + '<span>' + conditionText(c) + '</span>'
                + '</div></div>'
                + '<div class="shop-coupon__card-right">'
                + '<button type="button" class="shop-coupon__card-btn btn-download" data-id="' + c.coupon_group_id + '">받기</button>'
                + '</div></div>';
        }).join('');
    }

    // 데이터 로드
    function loadMyCoupons() {
        MubloRequest.requestQuery('/shop/api/coupons/my').then(function(res) {
            renderMyCoupons((res.data && res.data.coupons) || []);
        });
    }

    function loadDownloadable() {
        MubloRequest.requestQuery('/shop/api/coupons/downloadable').then(function(res) {
            renderDownloadable((res.data && res.data.coupons) || []);
        });
    }

    loadMyCoupons();
    loadDownloadable();

    // 다운로드 버튼
    document.getElementById('dlList').addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-download');
        if (!btn) return;

        btn.disabled = true;
        btn.textContent = '처리 중...';

        MubloRequest.requestJson('/shop/api/coupons/download', {
            coupon_group_id: parseInt(btn.dataset.id)
        }).then(function() {
            btn.textContent = '완료';
            loadMyCoupons();
            loadDownloadable();
        }).catch(function() {
            btn.disabled = false;
            btn.textContent = '받기';
        });
    });

    // 프로모션 코드 등록
    var promoInput = document.getElementById('promoCodeInput');
    var promoBtn = document.getElementById('btnRegisterPromo');

    promoBtn.addEventListener('click', function() {
        var code = promoInput.value.trim();
        if (!code) { alert('프로모션 코드를 입력해주세요.'); return; }

        promoBtn.disabled = true;
        promoBtn.textContent = '등록 중...';

        MubloRequest.requestJson('/shop/api/coupons/register', { code: code }).then(function(res) {
            alert(res.message || '쿠폰이 등록되었습니다.');
            promoInput.value = '';
            loadMyCoupons();
        }).catch(function() {}).finally(function() {
            promoBtn.disabled = false;
            promoBtn.textContent = '등록';
        });
    });

    promoInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') { e.preventDefault(); promoBtn.click(); }
    });
});
</script>
