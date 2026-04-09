<?php
/**
 * SNS 로그인 설정
 *
 * @var array  $config          현재 설정
 * @var string $callbackBaseUrl 콜백 URL 베이스
 * @var array  $levelOptions    [level_value => level_name] 일반 회원 레벨 목록
 */
$providers = [
    'naver' => [
        'label'        => '네이버',
        'icon'         => 'bi-chat-fill',
        'color'        => '#03C75A',
        'docs'         => 'https://developers.naver.com',
        'fields'       => [
            'client_id'     => ['label' => 'Client ID',     'type' => 'text',     'placeholder' => 'Client ID'],
            'client_secret' => ['label' => 'Client Secret', 'type' => 'text', 'placeholder' => 'Client Secret'],
        ],
    ],
    'kakao' => [
        'label'        => '카카오',
        'icon'         => 'bi-chat-square-fill',
        'color'        => '#FEE500',
        'docs'         => 'https://developers.kakao.com',
        'fields'       => [
            'client_id'     => ['label' => 'REST API 키',    'type' => 'text', 'placeholder' => 'REST API 키'],
            'admin_key'     => ['label' => 'Admin 키',       'type' => 'text', 'placeholder' => 'Admin 키'],
            'javascript_key'=> ['label' => 'JavaScript 키', 'type' => 'text', 'placeholder' => 'JavaScript 키'],
        ],
    ],
    'google' => [
        'label'        => 'Google',
        'icon'         => 'bi-google',
        'color'        => '#4285F4',
        'docs'         => 'https://console.cloud.google.com',
        'fields'       => [
            'client_id'     => ['label' => 'Client ID',     'type' => 'text',     'placeholder' => 'Client ID'],
            'client_secret' => ['label' => 'Client Secret', 'type' => 'text', 'placeholder' => 'Client Secret'],
        ],
    ],
];
$currentLevel = (int)($config['register_level'] ?? 1);
?>
<form name="frm" id="frm">
<div class="page-container form-container">

    <!-- 고정 헤더 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0">SNS 로그인 설정</h3>
                <p class="text-muted mb-0">소셜 로그인 제공자별 API 키를 설정합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0 d-flex gap-2">
                <a href="/admin/sns-login/accounts" class="btn btn-outline-secondary">
                    <i class="bi bi-list-ul me-1"></i>연동 내역
                </a>
                <button type="button"
                        class="btn btn-primary mublo-submit"
                        data-target="/admin/sns-login/settings"
                        data-callback="snsSettingsSaved">
                    <i class="bi bi-check-lg me-1"></i>저장
                </button>
            </div>
        </div>
    </div>

    <div class="mt-4">

        <!-- 공통 설정 -->
        <div class="card mb-4">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-sliders me-2 text-pastel-blue"></i>공통 설정
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-start">
                    <div class="col-md-6">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox"
                                   name="formData[auto_register]" value="1"
                                   id="autoRegister"
                                   <?= !empty($config['auto_register']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="autoRegister">신규 회원 자동 가입</label>
                        </div>
                        <div class="form-text">OFF 시 SNS 최초 로그인 후 닉네임 입력 페이지로 이동합니다.</div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">자동 가입 회원 레벨</label>
                        <select name="formData[register_level]" class="form-select">
                            <?php foreach ($levelOptions as $val => $name): ?>
                            <option value="<?= (int)$val ?>" <?= $currentLevel === (int)$val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                            <?php endforeach; ?>
                            <?php if (empty($levelOptions)): ?>
                            <option value="1">레벨 1</option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">SNS 가입 시 부여할 레벨</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 제공자 카드 (3열) -->
        <div class="row g-4">
            <?php foreach ($providers as $name => $info):
                $pc          = $config[$name] ?? [];
                $isEnabled   = !empty($pc['enabled']);
                $callbackUrl = $callbackBaseUrl . '/sns-login/callback/' . $name;
            ?>
            <div class="col-12 col-lg-4">
                <div class="card h-100" style="border-top: 3px solid <?= $info['color'] ?>;">
                    <div class="px-3 pt-3 pb-0 fw-semibold d-flex align-items-center gap-2" style="font-size:0.9rem">
                        <i class="<?= $info['icon'] ?>" style="color:<?= $info['color'] ?>; font-size:1.1rem;"></i>
                        <span><?= $info['label'] ?></span>
                        <div class="form-check form-switch ms-auto mb-0">
                            <input class="form-check-input" type="checkbox"
                                   name="formData[<?= $name ?>][enabled]" value="1"
                                   id="<?= $name ?>Enabled"
                                   <?= $isEnabled ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= $name ?>Enabled">
                                <small class="text-muted">사용</small>
                            </label>
                        </div>
                    </div>
                    <div class="card-body d-flex flex-column gap-3">
                        <?php foreach ($info['fields'] as $fieldKey => $fieldInfo): ?>
                        <div>
                            <label class="form-label"><?= $fieldInfo['label'] ?></label>
                            <input type="text"
                                   name="formData[<?= $name ?>][<?= $fieldKey ?>]"
                                   class="form-control form-control-sm"
                                   value="<?= htmlspecialchars($pc[$fieldKey] ?? '') ?>"
                                   placeholder="<?= htmlspecialchars($fieldInfo['placeholder']) ?>"
                                   autocomplete="off">
                        </div>
                        <?php endforeach; ?>
                        <div>
                            <label class="form-label">
                                Callback URL
                                <span class="text-muted fw-normal" style="font-size:.8em;">(개발자 센터 등록 필요)</span>
                            </label>
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control"
                                       id="callbackUrl_<?= $name ?>"
                                       value="<?= htmlspecialchars($callbackUrl) ?>"
                                       readonly>
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="copyCallback('<?= $name ?>')">
                                    <i class="bi bi-clipboard" id="clipIcon_<?= $name ?>"></i>
                                </button>
                            </div>
                        </div>
                        <div class="mt-auto pt-1">
                            <a href="<?= $info['docs'] ?>" target="_blank" rel="noopener"
                               class="text-decoration-none" style="font-size:.85rem;">
                                <i class="bi bi-box-arrow-up-right me-1"></i><?= $info['label'] ?> 개발자 센터
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

    </div>
</div>
</form>

<script>
function copyCallback(name) {
    var input = document.getElementById('callbackUrl_' + name);
    var icon  = document.getElementById('clipIcon_' + name);
    navigator.clipboard.writeText(input.value).then(function() {
        icon.className = 'bi bi-clipboard-check text-success';
        setTimeout(function() { icon.className = 'bi bi-clipboard'; }, 2000);
    });
}

MubloRequest.registerCallback('snsSettingsSaved', function(response) {
    alert(response.message || '저장되었습니다.');
    if (response.result === 'success') {
        location.reload();
    }
});
</script>
