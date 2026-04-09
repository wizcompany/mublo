<?php
/**
 * 쇼핑몰 설정 - 기본 설정
 *
 * @var array $config 쇼핑몰 설정
 */
?>
<div class="card mb-4">
    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
        <i class="bi bi-shop me-2 text-pastel-blue"></i>기본 설정
    </div>
    <div class="card-body">
        <div class="row gy-3 gy-md-0 mb-3">
            <div class="col-12 col-sm-6 col-md-4">
                <label for="shop_title" class="form-label">쇼핑몰 제목</label>
                <input type="text" name="formData[title]" id="shop_title" class="form-control"
                       value="<?= htmlspecialchars($config['title'] ?? '') ?>" placeholder="쇼핑몰 이름">
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label for="skin_name" class="form-label">사용 스킨</label>
                <select name="formData[skin_name]" id="skin_name" class="form-select">
                    <?php foreach ($skinOptions ?? ['basic' => 'basic'] as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= ($config['skin_name'] ?? 'basic') === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">쇼핑몰 프론트 스킨</div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label for="default_shipping_template" class="form-label">기본 배송 템플릿</label>
                <select name="formData[default_shipping_template_id]" id="default_shipping_template" class="form-select">
                    <option value="0">선택 안함</option>
                    <?php foreach ($shippingTemplates ?? [] as $tmpl): ?>
                    <option value="<?= (int) $tmpl['shipping_id'] ?>"
                        <?= ((int)($config['default_shipping_template_id'] ?? 0)) === (int) $tmpl['shipping_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tmpl['name'] ?? '템플릿 #' . $tmpl['shipping_id']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">상품 등록 시 자동 선택되는 배송 템플릿</div>
            </div>
        </div>
        <div class="row gy-3 gy-md-0">
            <div class="col-12 col-sm-6 col-md-4">
                <label for="cart_keep_days" class="form-label">장바구니 보관 기간 (회원)</label>
                <div class="input-group">
                    <input type="number" name="formData[cart_keep_days]" id="cart_keep_days" class="form-control"
                           value="<?= (int)($config['cart_keep_days'] ?? 15) ?>" min="1">
                    <span class="input-group-text">일</span>
                </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4">
                <label for="guest_cart_keep_days" class="form-label">장바구니 보관 기간 (비회원)</label>
                <div class="input-group">
                    <input type="number" name="formData[guest_cart_keep_days]" id="guest_cart_keep_days" class="form-control"
                           value="<?= (int)($config['guest_cart_keep_days'] ?? 7) ?>" min="1">
                    <span class="input-group-text">일</span>
                </div>
            </div>
        </div>
    </div>
</div>
