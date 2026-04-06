<?php
/**
 * Member Register - Step 2: 정보 입력
 *
 * 회원가입 정보 입력 기본 스킨
 *
 * @var array $fields 추가 필드 정의 (is_visible_signup=1)
 * @var bool $useEmailAsUserId 이메일=아이디 모드 여부
 */
$this->layout(['header' => false, 'footer' => false]);
$this->assets->addCss('/serve/front/view/member/basic/css/member.css');
?>

<div class="member-register-wrapper">
    <div class="auth-logo">
        <a href="/">
            <?php if (!empty($siteImages['logo_pc'])): ?>
                <img src="<?= htmlspecialchars($siteImages['logo_pc']) ?>" alt="<?= htmlspecialchars($siteConfig['site_title'] ?? '') ?>">
            <?php else: ?>
                <span><?= htmlspecialchars($siteConfig['site_title'] ?? 'MUBLO') ?></span>
            <?php endif; ?>
        </a>
    </div>
    <h2>회원가입</h2>
    <div class="step-indicator">
        1. 약관동의 &gt; <span class="current">2. 정보입력</span> &gt; 3. 가입완료
    </div>

    <div id="register-message" class="alert" style="display: none;"></div>

    <form name="frm" id="frm">
        <!-- 아이디 -->
        <div class="form-group">
            <label for="user_id"><?= ($useEmailAsUserId ?? false) ? '이메일' : '아이디' ?> <span class="required">*</span></label>
            <div class="input-group">
                <input
                    type="<?= ($useEmailAsUserId ?? false) ? 'email' : 'text' ?>"
                    id="user_id"
                    name="formData[user_id]"
                    class="form-control"
                    placeholder="<?= ($useEmailAsUserId ?? false) ? '이메일을 입력하세요' : '영문, 숫자 4~20자' ?>"
                    required
                >
                <button type="button" class="btn btn-check" id="check-user-id">중복확인</button>
            </div>
            <div id="user-id-message" class="form-help"></div>
        </div>

        <!-- 비밀번호 -->
        <div class="form-group">
            <label for="password">비밀번호 <span class="required">*</span></label>
            <input
                type="password"
                id="password"
                name="formData[password]"
                class="form-control"
                placeholder="최소 6자 이상"
                required
            >
        </div>

        <!-- 비밀번호 확인 -->
        <div class="form-group">
            <label for="password_confirm">비밀번호 확인 <span class="required">*</span></label>
            <input
                type="password"
                id="password_confirm"
                name="formData[password_confirm]"
                class="form-control"
                placeholder="비밀번호를 다시 입력하세요"
                required
            >
        </div>

        <!-- 닉네임 -->
        <div class="form-group">
            <label for="nickname">닉네임 <span class="required">*</span></label>
            <div class="input-group">
                <input
                    type="text"
                    id="nickname"
                    name="formData[nickname]"
                    class="form-control"
                    placeholder="2~20자"
                    required
                >
                <button type="button" class="btn btn-check" id="check-nickname">중복확인</button>
            </div>
            <div id="nickname-message" class="form-help"></div>
        </div>

        <!-- 추가 필드 (CustomFieldRenderer 사용) -->
        <?php if (!empty($fields)): ?>
            <?php foreach ($fields as $field): ?>
                <div class="form-group">
                    <label for="field_<?= $field['field_id'] ?>">
                        <?= htmlspecialchars($field['field_label']) ?>
                        <?php if ($field['is_required']): ?>
                            <span class="required">*</span>
                        <?php endif; ?>
                    </label>

                    <?= \Mublo\Service\CustomField\CustomFieldRenderer::render($field, null, [
                        'namePrefix' => 'formData[fields]',
                        'idPrefix' => 'field_',
                    ]) ?>

                    <?php if (!empty($field['field_description'])): ?>
                        <div class="form-help"><?= htmlspecialchars($field['field_description']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- 플러그인 확장 필드 -->
        <?php if (!empty($registerFormExtras)): ?>
            <?php foreach ($registerFormExtras as $html): ?>
                <?= $html ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <button type="button" class="btn mublo-submit" data-target="/member/register/form" data-callback="registerComplete">회원가입</button>
    </form>

    <div class="auth-links">
        이미 회원이신가요? <a href="/login">로그인</a>
    </div>
</div>

<script>
// 아이디 중복 확인
document.getElementById('check-user-id').addEventListener('click', function() {
    var userId = document.getElementById('user_id').value;
    var messageDiv = document.getElementById('user-id-message');

    if (!userId) {
        messageDiv.textContent = '아이디를 입력해주세요.';
        messageDiv.style.color = '#e74c3c';
        return;
    }

    MubloRequest.requestJson('/member/check-userid', { user_id: userId })
        .then(function(res) {
            messageDiv.textContent = res.message;
            messageDiv.style.color = '#27ae60';
        })
        .catch(function() {
            messageDiv.textContent = '이미 사용 중인 아이디입니다.';
            messageDiv.style.color = '#e74c3c';
        });
});

// 닉네임 중복 확인
document.getElementById('check-nickname').addEventListener('click', function() {
    var nickname = document.getElementById('nickname').value;
    var messageDiv = document.getElementById('nickname-message');

    if (!nickname) {
        messageDiv.textContent = '닉네임을 입력해주세요.';
        messageDiv.style.color = '#e74c3c';
        return;
    }

    MubloRequest.requestJson('/member/check-nickname', { nickname: nickname })
        .then(function(res) {
            messageDiv.textContent = res.message;
            messageDiv.style.color = '#27ae60';
        })
        .catch(function() {
            messageDiv.textContent = '이미 사용 중인 닉네임입니다.';
            messageDiv.style.color = '#e74c3c';
        });
});

// 가입 완료 콜백 (MubloRequest의 executeCallback이 window[name] 폴백으로 호출)
window.registerComplete = function(response) {
    var messageDiv = document.getElementById('register-message');
    if (response.result === 'success') {
        // 전체를 로딩 화면으로 교체
        var wrapper = document.querySelector('.member-register-wrapper');
        if (wrapper) {
            wrapper.innerHTML = '<div style="text-align:center; padding:80px 20px;">'
                + '<div style="width:36px; height:36px; border:3px solid #e2e8f0; border-top-color:#6366f1; border-radius:50%; animation:mublo-spin 0.7s linear infinite; margin:0 auto;"></div>'
                + '<div style="margin-top:16px; color:#64748b; font-size:0.95rem;">처리중입니다...</div>'
                + '<style>@keyframes mublo-spin { to { transform:rotate(360deg); } }</style>'
                + '</div>';
        }

        setTimeout(function() {
            location.href = response.data.redirect || '/member/register/complete';
        }, 1500);
    } else {
        messageDiv.textContent = response.message || '회원가입에 실패했습니다.';
        messageDiv.className = 'alert alert-danger';
        messageDiv.style.display = 'block';
    }
};


</script>
<?= \Mublo\Service\CustomField\CustomFieldRenderer::renderFileScript('/member/upload-field-file') ?>

<?php if (!empty($registerFormScripts)): ?>
<script>
<?php foreach ($registerFormScripts as $script): ?>
<?= $script ?>

<?php endforeach; ?>
</script>
<?php endif; ?>
