<?php
/**
 * 배송 템플릿 등록/수정 폼
 *
 * @var bool $isEdit 수정 모드 여부
 * @var array|null $template 배송 템플릿 데이터
 * @var array $shippingMethodOptions 배송 방법 옵션
 * @var array $deliveryCompanies 택배사 목록
 */

$t = $template ?? [];
?>

<div class="content-header d-flex justify-content-between align-items-center">
    <h2><?= $isEdit ? '배송 템플릿 수정' : '배송 템플릿 등록' ?></h2>
    <?php if ($isEdit): ?>
        <button type="button" class="btn btn-outline-danger btn-sm"
            onclick="if(confirm('이 배송 템플릿을 삭제하시겠습니까?')) MubloRequest.requestJson('/admin/shop/shipping/<?= $t['shipping_id'] ?>/delete', {}, {callback: 'shippingDeleted'})">
            <i class="bi bi-trash me-1"></i>삭제
        </button>
    <?php endif; ?>
</div>

<form id="shippingForm">
    <?php if ($isEdit): ?>
        <input type="hidden" name="formData[shipping_id]" value="<?= $t['shipping_id'] ?>">
    <?php endif; ?>

    <!-- 기본 정보 -->
    <div class="card mb-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-info-circle me-2 text-pastel-blue"></i>기본 정보</div>
        <div class="card-body">
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">템플릿명 <span class="text-danger">*</span></label>
                <div class="col-sm-6">
                    <input type="text" name="formData[name]" class="form-control"
                           value="<?= htmlspecialchars($t['name'] ?? '') ?>" required>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">카테고리</label>
                <div class="col-sm-4">
                    <input type="text" name="formData[category]" class="form-control"
                           value="<?= htmlspecialchars($t['category'] ?? '') ?>"
                           placeholder="분류 코드 (선택)">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">배송 방법</label>
                <div class="col-sm-4">
                    <select name="formData[shipping_method]" class="form-select" id="shippingMethod">
                        <?php foreach ($shippingMethodOptions as $val => $lbl): ?>
                            <option value="<?= $val ?>" <?= ($t['shipping_method'] ?? 'PAID') === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-2"></div>
                <div class="col-sm-6">
                    <div class="form-check">
                        <input type="hidden" name="formData[is_active]" value="0">
                        <input type="checkbox" name="formData[is_active]" class="form-check-input" value="1"
                            <?= ($t['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label">활성화</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 배송비 설정 -->
    <div class="card mb-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-currency-dollar me-2 text-pastel-green"></i>배송비 설정</div>
        <div class="card-body">
            <div class="row mb-3" id="rowBasicCost">
                <label class="col-sm-2 col-form-label">기본 배송비</label>
                <div class="col-sm-3">
                    <div class="input-group">
                        <input type="number" name="formData[basic_cost]" class="form-control"
                               value="<?= (int)($t['basic_cost'] ?? 3000) ?>" min="0">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
            </div>
            <div class="row mb-3" id="rowFreeThreshold">
                <label class="col-sm-2 col-form-label">무료 배송 기준</label>
                <div class="col-sm-3">
                    <div class="input-group">
                        <input type="number" name="formData[free_threshold]" class="form-control"
                               value="<?= (int)($t['free_threshold'] ?? 50000) ?>" min="0">
                        <span class="input-group-text">원 이상</span>
                    </div>
                </div>
            </div>
            <div class="row mb-3" id="rowGoodsPerUnit">
                <label class="col-sm-2 col-form-label">수량 단위</label>
                <div class="col-sm-3">
                    <div class="input-group">
                        <input type="number" name="formData[goods_per_unit]" class="form-control"
                               value="<?= (int)($t['goods_per_unit'] ?? 1) ?>" min="1">
                        <span class="input-group-text">개 당</span>
                    </div>
                </div>
                <div class="col-sm-4 pt-2">
                    <span class="form-text">해당 수량마다 기본 배송비가 적용됩니다.</span>
                </div>
            </div>
            <div class="row mb-3" id="rowPriceRanges">
                <label class="col-sm-2 col-form-label">구간별 배송비</label>
                <div class="col-sm-8">
                    <div id="priceRangesContainer"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="btnAddRange">
                        <i class="bi bi-plus-lg me-1"></i>구간 추가
                    </button>
                    <input type="hidden" name="formData[price_ranges]" id="priceRangesJson"
                           value="<?= htmlspecialchars($t['price_ranges'] ?? '[]') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-sm-2"></div>
                <div class="col-sm-6">
                    <div class="form-check">
                        <input type="hidden" name="formData[extra_cost_enabled]" value="0">
                        <input type="checkbox" name="formData[extra_cost_enabled]" class="form-check-input" value="1"
                            <?= ($t['extra_cost_enabled'] ?? 0) ? 'checked' : '' ?>>
                        <label class="form-check-label">도서산간 추가배송비 사용</label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 반품/교환 -->
    <div class="card mb-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-arrow-return-left me-2 text-pastel-purple"></i>반품 / 교환</div>
        <div class="card-body">
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">반품 배송비</label>
                <div class="col-sm-3">
                    <div class="input-group">
                        <input type="number" name="formData[return_cost]" class="form-control"
                               value="<?= (int)($t['return_cost'] ?? 3000) ?>" min="0">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">교환 배송비</label>
                <div class="col-sm-3">
                    <div class="input-group">
                        <input type="number" name="formData[exchange_cost]" class="form-control"
                               value="<?= (int)($t['exchange_cost'] ?? 6000) ?>" min="0">
                        <span class="input-group-text">원</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 배송 정보 -->
    <div class="card mb-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-truck me-2 text-pastel-sky"></i>배송 정보</div>
        <div class="card-body">
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">배송 수단</label>
                <div class="col-sm-4">
                    <select name="formData[delivery_method]" class="form-select">
                        <option value="COURIER" <?= ($t['delivery_method'] ?? 'COURIER') === 'COURIER' ? 'selected' : '' ?>>택배</option>
                        <option value="POSTAL" <?= ($t['delivery_method'] ?? '') === 'POSTAL' ? 'selected' : '' ?>>우편</option>
                        <option value="PICKUP" <?= ($t['delivery_method'] ?? '') === 'PICKUP' ? 'selected' : '' ?>>직접수령</option>
                        <option value="OWN" <?= ($t['delivery_method'] ?? '') === 'OWN' ? 'selected' : '' ?>>자체배송</option>
                        <option value="ETC" <?= ($t['delivery_method'] ?? '') === 'ETC' ? 'selected' : '' ?>>기타</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">택배사</label>
                <div class="col-sm-4">
                    <select name="formData[delivery_company_id]" class="form-select">
                        <option value="0">선택 안함</option>
                        <?php foreach ($deliveryCompanies as $company): ?>
                            <option value="<?= $company['company_id'] ?>"
                                <?= (int)($t['delivery_company_id'] ?? 0) === (int)$company['company_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($company['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">배송 안내</label>
                <div class="col-sm-6">
                    <input type="text" name="formData[shipping_guide]" class="form-control"
                           value="<?= htmlspecialchars($t['shipping_guide'] ?? '') ?>"
                           placeholder="예: 평균 2~3일 소요">
                </div>
            </div>
        </div>
    </div>

    <!-- 출고지 주소 -->
    <div class="card mb-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-geo-alt me-2 text-pastel-orange"></i>출고지</div>
        <div class="card-body">
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">우편번호</label>
                <div class="col-sm-3">
                    <input type="text" name="formData[origin_zipcode]" class="form-control"
                           value="<?= htmlspecialchars($t['origin_zipcode'] ?? '') ?>" maxlength="10">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">주소</label>
                <div class="col-sm-6">
                    <input type="text" name="formData[origin_address1]" class="form-control mb-2"
                           value="<?= htmlspecialchars($t['origin_address1'] ?? '') ?>" placeholder="기본 주소">
                    <input type="text" name="formData[origin_address2]" class="form-control"
                           value="<?= htmlspecialchars($t['origin_address2'] ?? '') ?>" placeholder="상세 주소">
                </div>
            </div>
        </div>
    </div>

    <!-- 반품지 주소 -->
    <div class="card mb-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-geo-alt me-2 text-pastel-blue"></i>반품/교환지</div>
        <div class="card-body">
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">우편번호</label>
                <div class="col-sm-3">
                    <input type="text" name="formData[return_zipcode]" class="form-control"
                           value="<?= htmlspecialchars($t['return_zipcode'] ?? '') ?>" maxlength="10">
                </div>
            </div>
            <div class="row mb-3">
                <label class="col-sm-2 col-form-label">주소</label>
                <div class="col-sm-6">
                    <input type="text" name="formData[return_address1]" class="form-control mb-2"
                           value="<?= htmlspecialchars($t['return_address1'] ?? '') ?>" placeholder="기본 주소">
                    <input type="text" name="formData[return_address2]" class="form-control"
                           value="<?= htmlspecialchars($t['return_address2'] ?? '') ?>" placeholder="상세 주소">
                </div>
            </div>
        </div>
    </div>

    <!-- 하단 버튼 -->
    <div class="text-end">
        <a href="/admin/shop/shipping" class="btn btn-secondary me-2">목록</a>
        <button type="button" class="btn btn-primary mublo-submit"
            data-target="/admin/shop/shipping/store"><?= $isEdit ? '수정' : '등록' ?></button>
    </div>
</form>

<script>
(function() {
    const methodSelect = document.getElementById('shippingMethod');
    const rowBasicCost = document.getElementById('rowBasicCost');
    const rowFreeThreshold = document.getElementById('rowFreeThreshold');
    const rowGoodsPerUnit = document.getElementById('rowGoodsPerUnit');
    const rowPriceRanges = document.getElementById('rowPriceRanges');

    function toggleFields() {
        const method = methodSelect.value;
        rowBasicCost.style.display = method === 'FREE' ? 'none' : '';
        rowFreeThreshold.style.display = method === 'COND' ? '' : 'none';
        rowGoodsPerUnit.style.display = method === 'QUANTITY' ? '' : 'none';
        rowPriceRanges.style.display = method === 'AMOUNT' ? '' : 'none';
    }

    methodSelect.addEventListener('change', toggleFields);
    toggleFields();

    // 구간별 배송비 관리
    const container = document.getElementById('priceRangesContainer');
    const hiddenInput = document.getElementById('priceRangesJson');
    let ranges = [];
    try { ranges = JSON.parse(hiddenInput.value) || []; } catch(e) { ranges = []; }

    function renderRanges() {
        container.innerHTML = '';
        ranges.forEach((range, idx) => {
            const row = document.createElement('div');
            row.className = 'input-group input-group-sm mb-1';
            row.innerHTML =
                '<input type="number" class="form-control" value="' + (range.min || 0) + '" placeholder="최소" data-idx="' + idx + '" data-field="min">' +
                '<span class="input-group-text">~</span>' +
                '<input type="number" class="form-control" value="' + (range.max || 0) + '" placeholder="최대" data-idx="' + idx + '" data-field="max">' +
                '<span class="input-group-text">원</span>' +
                '<input type="number" class="form-control" value="' + (range.cost || 0) + '" placeholder="배송비" data-idx="' + idx + '" data-field="cost">' +
                '<span class="input-group-text">원</span>' +
                '<button type="button" class="btn btn-outline-danger" data-idx="' + idx + '"><i class="bi bi-x-lg"></i></button>';
            container.appendChild(row);
        });

        container.querySelectorAll('input[data-idx]').forEach(input => {
            input.addEventListener('change', function() {
                ranges[this.dataset.idx][this.dataset.field] = parseInt(this.value) || 0;
                syncRanges();
            });
        });

        container.querySelectorAll('button[data-idx]').forEach(btn => {
            btn.addEventListener('click', function() {
                ranges.splice(parseInt(this.dataset.idx), 1);
                renderRanges();
            });
        });

        syncRanges();
    }

    function syncRanges() {
        hiddenInput.value = JSON.stringify(ranges);
    }

    document.getElementById('btnAddRange').addEventListener('click', function() {
        ranges.push({ min: 0, max: 0, cost: 0 });
        renderRanges();
    });

    renderRanges();
})();

MubloRequest.registerCallback('shippingDeleted', function(response) {
    if (response.result === 'success') {
        alert(response.message || '삭제되었습니다.');
        location.href = '/admin/shop/shipping';
    } else {
        alert(response.message || '삭제에 실패했습니다.');
    }
});
</script>
