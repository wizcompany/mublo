<?php
/**
 * 쇼핑몰 설정 - 메인 페이지
 *
 * @var string $pageTitle 페이지 제목
 * @var array $config 쇼핑몰 설정 (shop_config 테이블)
 * @var array $anchor 탭 메뉴
 */

use Mublo\Packages\Shop\Enum\DiscountType;
use Mublo\Packages\Shop\Enum\RewardType;
use Mublo\Packages\Shop\Enum\PaymentMethod;
$anchor = $anchor ?? [];
$config = $config ?? [];

// 결제 수단 JSON → 배열
$paymentMethods = [];
if (!empty($config['payment_methods'])) {
    $decoded = is_string($config['payment_methods']) ? json_decode($config['payment_methods'], true) : $config['payment_methods'];
    $paymentMethods = is_array($decoded) ? $decoded : [];
}

// 사용 PG 목록 JSON → 배열
$paymentPgKeys = [];
if (!empty($config['payment_pg_keys'])) {
    $decoded = is_string($config['payment_pg_keys']) ? json_decode($config['payment_pg_keys'], true) : $config['payment_pg_keys'];
    $paymentPgKeys = is_array($decoded) ? $decoded : [];
}
?>
<form name="frm" id="frm">
    <div class="page-container form-container">
        <div class="d-flex flex-column flex-sm-row gap-2 align-items-sm-center">
            <div class="flex-grow-1">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '쇼핑몰 설정') ?></h3>
                <p class="text-muted mb-0">쇼핑몰 운영에 필요한 기본 설정을 관리합니다.</p>
            </div>
            <div class="flex-grow-1 flex-sm-grow-0">
                <button type="button"
                    class="btn btn-primary mublo-submit"
                    data-target="/admin/shop/config/store"
                    data-callback="shopConfigSaved">
                    <i class="bi bi-check-lg me-1"></i>저장
                </button>
            </div>
        </div>

        <div class="sticky-spy mt-3" data-bs-spy="scroll" data-bs-target="#shop-nav" data-bs-smooth-scroll="true" tabindex="0">
            <div class="sticky-top">
                <nav id="shop-nav" class="navbar">
                    <ul class="nav nav-tabs w-100">
                        <?php $isFirst = true; foreach ($anchor as $id => $tabs): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $isFirst ? 'active' : '' ?>" href="#<?= $id ?>">
                                <?= $tabs ?>
                            </a>
                        </li>
                        <?php $isFirst = false; endforeach; ?>
                    </ul>
                </nav>
            </div>

            <div class="sticky-section">
                <?php foreach ($anchor as $id => $section): ?>
                <section id="<?= htmlspecialchars($id) ?>"
                        class="mb-2 pt-2"
                        data-section="<?= htmlspecialchars($id) ?>">
                    <h5 class="mb-3"><?= htmlspecialchars($section) ?></h5>
                    <?php
                    $configFile = __DIR__ . '/config-' . $id . '.php';
                    if (is_file($configFile)) {
                        include $configFile;
                    }
                    ?>
                </section>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="sticky-act mt-3 sticky-status">
            <button type="button"
                class="btn btn-primary mublo-submit"
                data-target="/admin/shop/config/store"
                data-callback="shopConfigSaved">
                <i class="bi bi-check-lg me-1"></i>저장
            </button>
        </div>
    </div>
</form>

<script>
MubloRequest.registerCallback('shopConfigSaved', function(response) {
    if (response.result === 'success') {
        alert(response.message || '설정이 저장되었습니다.');
        location.reload();
    } else {
        alert(response.message || '저장에 실패했습니다.');
    }
});

// ── 상세정보 탭 순서 + 활성화 → hidden 동기화 ──
var dynamicTabTypes = ['review', 'qna', 'faq'];

function syncDetailTabOrder() {
    var items = document.querySelectorAll('#detailTabOrderList li[data-type]');

    var order = Array.from(items).map(function(li) { return li.dataset.type; });
    document.getElementById('detailTabOrderHidden').value = order.join(',');

    var enabled = Array.from(items)
        .filter(function(li) { return dynamicTabTypes.indexOf(li.dataset.type) !== -1; })
        .filter(function(li) {
            var cb = li.querySelector('.tab-enable-check');
            return cb && cb.checked;
        })
        .map(function(li) { return li.dataset.type; });
    document.getElementById('goodsViewTabHidden').value = enabled.join(',');
}

// ── 드래그앤드롭 정렬 (HTML5 native) ──
(function() {
    var list = document.getElementById('detailTabOrderList');
    if (!list) return;
    var dragging = null;

    list.addEventListener('dragstart', function(e) {
        dragging = e.target.closest('li[data-type]');
        if (!dragging) return;
        setTimeout(function() { dragging.classList.add('detail-tab-dragging'); }, 0);
        e.dataTransfer.effectAllowed = 'move';
    });

    list.addEventListener('dragend', function() {
        if (dragging) {
            dragging.classList.remove('detail-tab-dragging');
            dragging = null;
        }
        list.querySelectorAll('li').forEach(function(li) {
            li.classList.remove('detail-tab-over');
        });
        syncDetailTabOrder();
    });

    list.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        var target = e.target.closest('li[data-type]');
        if (!target || target === dragging) return;

        var rect = target.getBoundingClientRect();
        var mid  = rect.top + rect.height / 2;
        list.querySelectorAll('li').forEach(function(li) { li.classList.remove('detail-tab-over'); });
        target.classList.add('detail-tab-over');

        if (e.clientY < mid) {
            list.insertBefore(dragging, target);
        } else {
            list.insertBefore(dragging, target.nextSibling);
        }
    });

    list.addEventListener('dragleave', function(e) {
        var target = e.target.closest('li[data-type]');
        if (target) target.classList.remove('detail-tab-over');
    });

    // 핸들에만 cursor:grab + li draggable 제어
    list.querySelectorAll('.detail-tab-handle').forEach(function(handle) {
        handle.addEventListener('mousedown', function() {
            handle.closest('li').setAttribute('draggable', 'true');
        });
        handle.addEventListener('mouseup', function() {
            handle.closest('li').setAttribute('draggable', 'false');
        });
    });
    list.querySelectorAll('li[data-type]').forEach(function(li) {
        li.setAttribute('draggable', 'false');
    });
})();

// 체크박스 변경 시 동기화
(function() {
    var list = document.getElementById('detailTabOrderList');
    if (list) {
        list.addEventListener('change', function(e) {
            if (e.target.classList.contains('tab-enable-check')) {
                syncDetailTabOrder();
            }
        });
    }
})();

// ── 주문서 약관 체크박스 → hidden 동기화 ──
function syncCheckoutPolicies() {
    var checks = document.querySelectorAll('.checkout-policy-check:checked');
    var ids = Array.from(checks).map(function(c) { return parseInt(c.value); });
    var hidden = document.getElementById('checkoutPoliciesHidden');
    if (hidden) hidden.value = ids.length > 0 ? JSON.stringify(ids) : '';
}
document.querySelectorAll('.checkout-policy-check').forEach(function(cb) {
    cb.addEventListener('change', syncCheckoutPolicies);
});

// ── 할인/적립 유형 변경 시 등급별 테이블 토글 ──
(function() {
    var discountType = document.getElementById('discount_type');
    var discountTable = document.getElementById('discountLevelTable');
    var discountValueWrap = document.getElementById('discountValueWrap');

    if (discountType && discountTable) {
        discountType.addEventListener('change', function() {
            var isLevel = this.value === 'LEVEL';
            discountTable.style.display = isLevel ? 'block' : 'none';
            if (discountValueWrap) discountValueWrap.style.display = isLevel ? 'none' : '';
        });
        // 초기 상태
        if (discountType.value === 'LEVEL' && discountValueWrap) discountValueWrap.style.display = 'none';
    }

    var rewardType = document.getElementById('reward_type');
    var rewardTable = document.getElementById('rewardLevelTable');
    var rewardValueWrap = document.getElementById('rewardValueWrap');

    if (rewardType && rewardTable) {
        rewardType.addEventListener('change', function() {
            var isLevel = this.value === 'LEVEL';
            rewardTable.style.display = isLevel ? 'block' : 'none';
            if (rewardValueWrap) rewardValueWrap.style.display = isLevel ? 'none' : '';
        });
        // 초기 상태
        if (rewardType.value === 'LEVEL' && rewardValueWrap) rewardValueWrap.style.display = 'none';
    }
})();
</script>
