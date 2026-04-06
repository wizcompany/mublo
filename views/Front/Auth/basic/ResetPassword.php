<?php
/**
 * Auth ResetPassword View
 *
 * 비밀번호 재설정 폼 (이메일 링크 도착 후)
 *
 * @var string $token 비밀번호 재설정 토큰
 */
$this->layout(['header' => false, 'footer' => false]);
$this->assets->addCss('/serve/front/view/auth/basic/css/auth.css');
?>

<div class="reset-pw-wrapper">
    <div class="auth-logo">
        <a href="/">
            <?php if (!empty($siteImages['logo_pc'])): ?>
                <img src="<?= htmlspecialchars($siteImages['logo_pc']) ?>" alt="<?= htmlspecialchars($siteConfig['site_title'] ?? '') ?>">
            <?php else: ?>
                <span><?= htmlspecialchars($siteConfig['site_title'] ?? 'MUBLO') ?></span>
            <?php endif; ?>
        </a>
    </div>
    <h2>비밀번호 재설정</h2>
    <p class="subtitle">새로운 비밀번호를 입력해주세요.</p>

    <div id="reset-message" class="alert" style="display: none;"></div>

    <form id="reset-form" name="frmResetPassword">
        <input type="hidden" name="formData[token]" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
            <label for="new_password">새 비밀번호</label>
            <input
                type="password"
                id="new_password"
                name="formData[new_password]"
                class="form-control"
                placeholder="최소 6자 이상"
                required
            >
        </div>

        <div class="form-group">
            <label for="new_password_confirm">새 비밀번호 확인</label>
            <input
                type="password"
                id="new_password_confirm"
                name="formData[new_password_confirm]"
                class="form-control"
                placeholder="새 비밀번호를 다시 입력하세요"
                required
            >
        </div>

        <button type="button" class="btn" id="btn-reset">비밀번호 변경</button>
    </form>
</div>

<script>
(function() {
    var btnReset = document.getElementById('btn-reset');

    btnReset.addEventListener('click', function() {
        var messageDiv = document.getElementById('reset-message');
        var newPw = document.getElementById('new_password').value;
        var newPwConfirm = document.getElementById('new_password_confirm').value;

        if (!newPw || !newPwConfirm) {
            messageDiv.textContent = '비밀번호를 입력해주세요.';
            messageDiv.className = 'alert alert-danger';
            messageDiv.style.display = 'block';
            return;
        }

        if (newPw !== newPwConfirm) {
            messageDiv.textContent = '비밀번호가 일치하지 않습니다.';
            messageDiv.className = 'alert alert-danger';
            messageDiv.style.display = 'block';
            return;
        }

        messageDiv.style.display = 'none';
        btnReset.disabled = true;
        btnReset.textContent = '변경 중...';

        var form = document.getElementById('reset-form');

        MubloRequest.sendRequest({
            method: 'POST',
            url: '/find-account/reset-password',
            payloadType: MubloRequest.PayloadType.FORM,
            data: new FormData(form)
        })
        .then(function(data) {
            messageDiv.textContent = data.message;
            messageDiv.className = 'alert alert-success';
            messageDiv.style.display = 'block';
            form.reset();
            btnReset.style.display = 'none';
            setTimeout(function() {
                location.href = '/login';
            }, 2000);
        })
        .catch(function() {
            btnReset.disabled = false;
            btnReset.textContent = '비밀번호 변경';
        });
    });
})();
</script>
