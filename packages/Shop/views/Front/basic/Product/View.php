<?php
/**
 * 상품 상세 (프론트)
 *
 * @var array|null $product 상품 데이터 (ProductPresenter::toView() 변환 완료)
 * @var string|null $message 에러 메시지 (404 등)
 *
 * [Presenter 제공 필드]
 * 기본: goods_name_safe, url, goods_id, option_mode
 * 가격: sales_price, sales_price_formatted, display_price_formatted, origin_price_formatted
 *       discount_percent, discount_amount_formatted, has_discount
 *       point_amount, point_amount_formatted, has_reward
 * 이미지: images[], main_image_url, main_thumbnail_url
 * 옵션: options[], combos[] (JS에서 소비)
 * 상세: details[]
 * 상태: is_soldout, stock_label, is_new, badges[]
 * 통계: hit_formatted, review_count, average_rating_formatted, has_reviews
 *
 * @var array $viewTabs goods_view_tab CSV → 배열 (예: ['faq'])
 * 리뷰적립: reward_review_formatted, has_reward_review
 * 태그: tags_array[]
 */

$viewTabs = $viewTabs ?? [];
$hasFaqTab = in_array('faq', $viewTabs, true);

if (empty($product)) {
    echo '<div class="shop-product-view shop-product-view--error">';
    echo '<p>' . htmlspecialchars($message ?? '상품을 찾을 수 없습니다.') . '</p>';
    echo '<a href="/shop/products">상품 목록으로 돌아가기</a>';
    echo '</div>';
    return;
}
?>

<link rel="stylesheet" href="/serve/package/Shop/views/Front/basic/_assets/css/product-view.css">

<div class="shop-product-view">
    <div class="shop-product-view__top">
        <!-- ========== 갤러리 ========== -->
        <div class="shop-product-view__gallery">
            <div class="shop-product-view__main-image">
                <?php if ($product['main_image_url']): ?>
                    <img src="<?= $product['main_image_url'] ?>"
                         id="spv-main-img"
                         alt="<?= $product['goods_name_safe'] ?>">
                <?php else: ?>
                    <div class="shop-product-view__no-image">
                        <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <!-- 배지 -->
                <?php if (!empty($product['badges'])): ?>
                    <div class="shop-product-view__badges">
                        <?php foreach ($product['badges'] as $badge): ?>
                            <?php
                                $cls = match ($badge) {
                                    'new' => 'shop-badge--new',
                                    'sale' => 'shop-badge--sale',
                                    'soldout' => 'shop-badge--soldout',
                                    default => 'shop-badge--custom',
                                };
                                $label = match ($badge) {
                                    'new' => 'NEW',
                                    'sale' => $product['discount_percent'] . '%',
                                    'soldout' => '품절',
                                    default => $badge,
                                };
                            ?>
                            <span class="shop-badge <?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (count($product['images'] ?? []) > 1): ?>
                <div class="shop-product-view__thumbs">
                    <?php foreach ($product['images'] as $img): ?>
                        <button type="button"
                                class="shop-product-view__thumb <?= $img['is_main'] ? 'shop-product-view__thumb--active' : '' ?>"
                                data-image="<?= htmlspecialchars($img['image_url']) ?>">
                            <img src="<?= htmlspecialchars($img['thumbnail_url'] ?: $img['image_url']) ?>"
                                 alt="" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ========== 상품 정보 ========== -->
        <div class="shop-product-view__info">
            <h1 class="shop-product-view__name"><?= $product['goods_name_safe'] ?></h1>

            <?php if (!empty($product['goods_manufacturer_safe'])): ?>
                <p class="shop-product-view__manufacturer"><?= $product['goods_manufacturer_safe'] ?></p>
            <?php endif; ?>

            <!-- 리뷰 요약 -->
            <?php if ($product['has_reviews'] ?? false): ?>
                <div class="shop-product-view__rating">
                    <span class="shop-product-view__stars"><?= str_repeat('★', (int) round($product['average_rating'])) . str_repeat('☆', 5 - (int) round($product['average_rating'])) ?></span>
                    <span class="shop-product-view__rating-text"><?= $product['average_rating_formatted'] ?></span>
                    <a href="#spv-reviews" class="shop-product-view__review-link">리뷰 <?= $product['review_count_formatted'] ?>건</a>
                </div>
            <?php endif; ?>

            <!-- 가격 -->
            <div class="shop-product-view__price-area">
                <?php if ($product['has_discount']): ?>
                    <div class="shop-product-view__price-original">
                        <del><?= $product['display_price_formatted'] ?>원</del>
                        <span class="shop-product-view__discount-rate">-<?= $product['discount_percent'] ?>%</span>
                    </div>
                <?php endif; ?>
                <div class="shop-product-view__price-final">
                    <span class="shop-product-view__price-value" id="spv-base-price"><?= $product['sales_price_formatted'] ?></span>
                    <span class="shop-product-view__price-unit">원</span>
                </div>
                <?php if ($product['has_reward']): ?>
                    <p class="shop-product-view__reward">
                        <span class="shop-product-view__reward-icon">P</span>
                        <?= $product['point_amount_formatted'] ?>원 적립
                    </p>
                <?php endif; ?>
            </div>

            <!-- 상품 정보 테이블 -->
            <dl class="shop-product-view__meta">
                <?php if (!empty($product['goods_origin_safe'])): ?>
                    <div class="shop-product-view__meta-row">
                        <dt>원산지</dt><dd><?= $product['goods_origin_safe'] ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($product['stock_label']): ?>
                    <div class="shop-product-view__meta-row">
                        <dt>재고</dt><dd class="<?= $product['is_soldout'] ? 'shop-product-view__soldout' : '' ?>"><?= $product['stock_label'] ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($product['allowed_coupon_label']): ?>
                    <div class="shop-product-view__meta-row">
                        <dt>쿠폰</dt><dd><?= $product['allowed_coupon_label'] ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($product['has_reward_review']): ?>
                    <div class="shop-product-view__meta-row">
                        <dt>리뷰적립</dt><dd><?= $product['reward_review_formatted'] ?>원</dd>
                    </div>
                <?php endif; ?>
            </dl>

            <!-- 옵션 영역 (ShopProductOption.js가 렌더링) -->
            <div id="spv-option-area" class="shop-product-view__options"></div>

            <!-- 총 금액 -->
            <?php $hasOptions = ($product['option_mode'] ?? 'NONE') !== 'NONE'; ?>
            <div class="shop-product-view__total">
                <span class="shop-product-view__total-label">총 상품금액</span>
                <span class="shop-product-view__total-qty">(<span id="spv-total-qty"><?= $hasOptions ? '0' : '1' ?></span>개)</span>
                <span class="shop-product-view__total-price">
                    <strong id="spv-total-price"><?= $hasOptions ? '0' : $product['sales_price_formatted'] ?></strong>원
                </span>
            </div>

            <!-- 구매 버튼 -->
            <div class="shop-product-view__actions">
                <?php if ($product['is_soldout']): ?>
                    <button class="shop-product-view__btn shop-product-view__btn--soldout" disabled>품절</button>
                <?php else: ?>
                    <button type="button"
                            class="shop-product-view__btn shop-product-view__btn--cart"
                            id="spv-btn-cart">장바구니</button>
                    <button type="button"
                            class="shop-product-view__btn shop-product-view__btn--buy"
                            id="spv-btn-buy">바로구매</button>
                <?php endif; ?>
                <button type="button"
                        class="shop-product-view__btn shop-product-view__btn--wish"
                        id="spv-btn-wish"
                        aria-label="찜하기">♡</button>
            </div>

            <!-- 태그 -->
            <?php if (!empty($product['tags_array'])): ?>
                <div class="shop-product-view__tags">
                    <?php foreach ($product['tags_array'] as $tag): ?>
                        <a href="/shop/products?keyword=<?= urlencode($tag) ?>" class="shop-product-view__tag">#<?= htmlspecialchars($tag) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ========== 상세 탭 ========== -->
    <div class="shop-product-view__tabs" id="spv-tabs">
        <div class="shop-product-view__tab-nav">
            <button type="button" class="shop-product-view__tab-btn shop-product-view__tab-btn--active" data-tab="detail">상세정보</button>
            <button type="button" class="shop-product-view__tab-btn" data-tab="reviews" id="spv-tab-reviews">리뷰
                <?php if ($product['has_reviews'] ?? false): ?>
                    <span class="shop-product-view__tab-count"><?= $product['review_count_formatted'] ?></span>
                <?php endif; ?>
            </button>
            <button type="button" class="shop-product-view__tab-btn" data-tab="inquiry">상품문의</button>
            <?php if ($hasFaqTab): ?>
                <button type="button" class="shop-product-view__tab-btn" data-tab="faq">FAQ</button>
            <?php endif; ?>
        </div>

        <div class="shop-product-view__tab-content shop-product-view__tab-content--active" data-tab-content="detail">
            <?php if (!empty($product['details'])): ?>
                <?php foreach ($product['details'] as $detail): ?>
                    <?php if (count($product['details']) > 1): ?>
                        <h3 class="shop-product-view__detail-title"><?= htmlspecialchars($detail['detail_type'] ?? '상세정보') ?></h3>
                    <?php endif; ?>
                    <div class="shop-product-view__detail-content">
                        <?= $detail['detail_value'] ?? '' ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="shop-product-view__empty">등록된 상세정보가 없습니다.</p>
            <?php endif; ?>
        </div>

        <div class="shop-product-view__tab-content" data-tab-content="reviews" id="spv-reviews">
            <p class="shop-product-view__empty">리뷰 기능은 준비 중입니다.</p>
        </div>

        <div class="shop-product-view__tab-content" data-tab-content="inquiry">
            <p class="shop-product-view__empty">상품문의 기능은 준비 중입니다.</p>
        </div>

        <?php if ($hasFaqTab): ?>
            <div class="shop-product-view__tab-content" data-tab-content="faq" id="spv-faq">
                <p class="shop-product-view__empty">FAQ를 불러오는 중...</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- ShopProductOption.js -->
<script src="/serve/package/Shop/views/Front/basic/_assets/js/ShopProductOption.js"></script>
<script>
(function() {
    'use strict';

    // 서버 데이터
    var goodsId = <?= (int) $product['goods_id'] ?>;
    var basePrice = <?= (int) $product['sales_price'] ?>;
    var optionMode = '<?= $product['option_mode'] ?? 'NONE' ?>';
    var options = <?= json_encode($product['options'] ?? [], JSON_UNESCAPED_UNICODE) ?>;
    var combos = <?= json_encode($product['combos'] ?? [], JSON_UNESCAPED_UNICODE) ?>;

    // 옵션 핸들러 초기화
    var optionHandler = new ShopProductOption({
        container: '#spv-option-area',
        basePrice: basePrice,
        optionMode: optionMode,
        options: options,
        combos: combos,
        onUpdate: function(state) {
            document.getElementById('spv-total-price').textContent = state.grandTotal.toLocaleString();
            document.getElementById('spv-total-qty').textContent = state.totalQuantity;
        }
    });
    optionHandler.init();

    // 전역 참조 (장바구니 모달에서 접근 가능)
    window.shopOptionHandler = optionHandler;

    // 갤러리 썸네일 클릭
    document.querySelectorAll('.shop-product-view__thumb').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var img = document.getElementById('spv-main-img');
            if (img) img.src = this.dataset.image;
            document.querySelectorAll('.shop-product-view__thumb').forEach(function(b) {
                b.classList.remove('shop-product-view__thumb--active');
            });
            this.classList.add('shop-product-view__thumb--active');
        });
    });

    // 탭 전환
    var faqLoaded = false;
    document.querySelectorAll('.shop-product-view__tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tab = this.dataset.tab;
            document.querySelectorAll('.shop-product-view__tab-btn').forEach(function(b) {
                b.classList.remove('shop-product-view__tab-btn--active');
            });
            document.querySelectorAll('.shop-product-view__tab-content').forEach(function(c) {
                c.classList.remove('shop-product-view__tab-content--active');
            });
            this.classList.add('shop-product-view__tab-btn--active');
            var content = document.querySelector('[data-tab-content="' + tab + '"]');
            if (content) content.classList.add('shop-product-view__tab-content--active');

            // FAQ 탭 AJAX 로딩 (최초 1회)
            if (tab === 'faq' && !faqLoaded) {
                faqLoaded = true;
                loadFaqTab(content);
            }
        });
    });

    // FAQ 아코디언 렌더링
    function loadFaqTab(container) {
        MubloRequest.requestQuery('/faq/api/list').then(function(res) {
            var faqData = res.data.faq || [];
            if (!Array.isArray(faqData) || faqData.length === 0) {
                container.innerHTML = '<p class="shop-product-view__empty">등록된 FAQ가 없습니다.</p>';
                return;
            }
            var html = '<div class="spv-faq-list">';
            faqData.forEach(function(group) {
                if (group.category_name) {
                    html += '<h5 class="spv-faq-category">' + escapeHtml(group.category_name) + '</h5>';
                }
                (group.items || []).forEach(function(item) {
                    html += '<div class="spv-faq-item">'
                        + '<button type="button" class="spv-faq-question" onclick="this.parentElement.classList.toggle(\'open\')">'
                        + '<span class="spv-faq-q">Q.</span>'
                        + '<span>' + escapeHtml(item.question) + '</span>'
                        + '<svg class="spv-faq-icon" width="16" height="16" viewBox="0 0 16 16"><path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2"/></svg>'
                        + '</button>'
                        + '<div class="spv-faq-answer">' + (item.answer || '') + '</div>'
                        + '</div>';
                });
            });
            html += '</div>';
            container.innerHTML = html;
        }).catch(function() {
            container.innerHTML = '<p class="shop-product-view__empty">FAQ를 불러올 수 없습니다.</p>';
        });
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    // 장바구니
    var cartBtn = document.getElementById('spv-btn-cart');
    if (cartBtn) {
        cartBtn.addEventListener('click', function() {
            var data = optionHandler.getSubmitData();
            data.goods_id = goodsId;
            data.action = 'cart';

            MubloRequest.requestJson('/shop/cart/add', data).then(function(res) {
                if (confirm('장바구니에 담았습니다. 장바구니로 이동하시겠습니까?')) {
                    location.href = '/shop/cart';
                }
            });
        });
    }

    // 바로구매
    var buyBtn = document.getElementById('spv-btn-buy');
    if (buyBtn) {
        buyBtn.addEventListener('click', function() {
            var data = optionHandler.getSubmitData();
            data.goods_id = goodsId;
            data.action = 'direct';

            MubloRequest.requestJson('/shop/cart/add', data).then(function(res) {
                if (res.data && res.data.redirect) {
                    location.href = res.data.redirect;
                } else {
                    location.href = '/shop/checkout';
                }
            });
        });
    }

    // 찜하기
    var wishBtn = document.getElementById('spv-btn-wish');
    if (wishBtn) {
        wishBtn.addEventListener('click', function() {
            MubloRequest.requestJson('/shop/wishlist/toggle', { goods_id: goodsId }).then(function(res) {
                wishBtn.textContent = res.data && res.data.wishlisted ? '\u2665' : '\u2661';
                wishBtn.classList.toggle('shop-product-view__btn--wished', !!(res.data && res.data.wishlisted));
            });
        });
    }
})();
</script>
