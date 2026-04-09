<?php
/**
 * 쇼핑몰 설정 - 결제 설정
 *
 * @var array $config 쇼핑몰 설정
 * @var array $paymentMethods 허용 결제 수단 배열
 * @var array $paymentGateways ContractRegistry에서 조회한 PG사 메타 [key => [label, ...]]
 * @var \Mublo\Entity\Member\MemberLevel[] $memberLevels 회원 레벨 목록
 */

use Mublo\Packages\Shop\Enum\PaymentMethod;

$paymentGateways = $paymentGateways ?? [];
$paymentPgKeys = $paymentPgKeys ?? [];
$memberLevels = $memberLevels ?? [];

// 레벨별 포인트 설정 파싱
$pointLevelSettings = [];
if (!empty($config['point_level_settings'])) {
    $decoded = is_string($config['point_level_settings']) ? json_decode($config['point_level_settings'], true) : $config['point_level_settings'];
    $pointLevelSettings = is_array($decoded) ? $decoded : [];
}
?>
<!-- PG 연동 -->
<div class="card mb-4">
    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
        <i class="bi bi-credit-card me-2 text-pastel-blue"></i>PG 연동
    </div>
    <div class="card-body">
        <div class="row gy-3 gy-md-0 mb-3">
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label">사용 PG사</label>
                <?php if (!empty($paymentGateways)): ?>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($paymentGateways as $key => $meta): ?>
                    <div class="form-check">
                        <input type="checkbox" name="formData[payment_pg_keys_arr][]" class="form-check-input"
                               id="pgk_<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($key) ?>"
                               <?= in_array($key, $paymentPgKeys) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pgk_<?= htmlspecialchars($key) ?>">
                            <?= htmlspecialchars($meta['label'] ?? $key) ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="form-text">결제 플러그인을 설치하면 PG사를 선택할 수 있습니다.</div>
                <?php endif; ?>
            </div>
            <div class="col-12 col-sm-6 col-md-8">
                <label class="form-label">허용 결제 수단</label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach (PaymentMethod::options() as $value => $label): ?>
                    <div class="form-check">
                        <input type="checkbox" name="formData[payment_methods_arr][]" class="form-check-input"
                               id="pm_<?= $value ?>" value="<?= $value ?>"
                               <?= in_array($value, $paymentMethods) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pm_<?= $value ?>"><?= $label ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12 col-md-8">
                <label for="payment_bank_info" class="form-label">무통장 입금 안내</label>
                <textarea name="formData[payment_bank_info]" id="payment_bank_info" class="form-control" rows="3"
                          placeholder="은행명 / 계좌번호 / 예금주"><?= htmlspecialchars($config['payment_bank_info'] ?? '') ?></textarea>
            </div>
        </div>
    </div>
</div>

<!-- 포인트 결제 -->
<div class="card mb-4">
    <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
        <i class="bi bi-coin me-2 text-pastel-green"></i>포인트 결제
    </div>
    <div class="card-body">
        <div class="row gy-3 gy-md-0 mb-3">
            <div class="col-12">
                <div class="form-check form-switch">
                    <input type="hidden" name="formData[use_point_payment]" value="0">
                    <input type="checkbox" name="formData[use_point_payment]" id="use_point_payment" class="form-check-input"
                           value="1" <?= !empty($config['use_point_payment']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="use_point_payment">포인트 결제 허용</label>
                </div>
            </div>
        </div>
        <div class="row gy-3 gy-md-0 mb-3">
            <div class="col-12 col-sm-4">
                <label for="point_unit" class="form-label">사용 단위</label>
                <div class="input-group">
                    <input type="number" name="formData[point_unit]" id="point_unit" class="form-control"
                           value="<?= (int)($config['point_unit'] ?? 100) ?>" min="1">
                    <span class="input-group-text">P</span>
                </div>
                <div class="form-text">포인트 사용 최소 단위</div>
            </div>
        </div>

        <?php if (!empty($memberLevels)): ?>
        <label class="form-label fw-semibold">레벨별 최소/최대 사용</label>
        <div class="d-flex flex-wrap gap-2 mt-1">
            <?php foreach ($memberLevels as $level):
                $lid = $level->getLevelId();
                $ls = $pointLevelSettings[$lid] ?? [];
            ?>
            <div class="level-card level-card--wide">
                <div class="level-card__name"><?= htmlspecialchars($level->getLevelName()) ?></div>
                <div class="level-card__row">
                    <div class="level-card__label">최소</div>
                    <input type="number" name="formData[point_level_settings][<?= $lid ?>][min]"
                           class="form-control form-control-sm"
                           value="<?= (int)($ls['min'] ?? ($config['point_min'] ?? 100)) ?>" min="0">
                </div>
                <div class="level-card__row">
                    <div class="level-card__label">최대</div>
                    <input type="number" name="formData[point_level_settings][<?= $lid ?>][max]"
                           class="form-control form-control-sm"
                           value="<?= (int)($ls['max'] ?? ($config['point_max'] ?? 30000)) ?>" min="0">
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="row gy-3 gy-md-0">
            <div class="col-12 col-sm-4">
                <label for="point_min" class="form-label">최소 사용</label>
                <div class="input-group">
                    <input type="number" name="formData[point_min]" id="point_min" class="form-control"
                           value="<?= (int)($config['point_min'] ?? 100) ?>" min="0">
                    <span class="input-group-text">P</span>
                </div>
            </div>
            <div class="col-12 col-sm-4">
                <label for="point_max" class="form-label">최대 사용</label>
                <div class="input-group">
                    <input type="number" name="formData[point_max]" id="point_max" class="form-control"
                           value="<?= (int)($config['point_max'] ?? 30000) ?>" min="0">
                    <span class="input-group-text">P</span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
