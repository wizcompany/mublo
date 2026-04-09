<?php
/**
 * Auth Login View
 *
 * 로그인 기본 스킨
 *
 * @var string $redirect 로그인 후 리다이렉트 경로
 * @var bool $useEmailAsUserId 이메일=아이디 모드 여부
 */
$this->layout(['header' => false, 'footer' => false]);
$this->assets->addCss('/serve/front/view/auth/basic/css/auth.css');
?>

<div class="auth-login-wrapper">
    <div class="auth-logo">
        <a href="/">
            <?php if (!empty($siteImages['logo_pc'])): ?>
                <img src="<?= htmlspecialchars($siteImages['logo_pc']) ?>" alt="<?= htmlspecialchars($siteConfig['site_title'] ?? '') ?>">
            <?php else: ?>
                <span><?= htmlspecialchars($siteConfig['site_title'] ?? 'MUBLO') ?></span>
            <?php endif; ?>
        </a>
    </div>
    <h2>로그인</h2>

    <div id="login-error" class="alert alert-danger" style="display: none;"></div>

    <form id="login-form" name="frm" autocomplete="off">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect ?? '/') ?>">

        <div class="form-group">
            <label for="user_id"><?= ($useEmailAsUserId ?? false) ? '이메일' : '아이디' ?></label>
            <input
                type="<?= ($useEmailAsUserId ?? false) ? 'email' : 'text' ?>"
                id="user_id"
                name="user_id"
                class="form-control"
                placeholder="<?= ($useEmailAsUserId ?? false) ? '이메일을 입력하세요' : '아이디를 입력하세요' ?>"
                autocomplete="off"
                required
                autofocus
            >
        </div>

        <div class="form-group">
            <label for="password">비밀번호</label>
            <input
                type="password"
                id="password"
                name="password"
                class="form-control"
                placeholder="비밀번호를 입력하세요"
                required
            >
        </div>

        <button type="button" class="btn mublo-submit" data-target="/login" data-callback="loginComplete">로그인</button>
    </form>

    <?php if (!empty($loginFormExtras)): ?>
        <?php foreach ($loginFormExtras as $html): ?>
            <?= $html ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="auth-links">
        <a href="/find-account">아이디/비밀번호 찾기</a> | <a href="/member/register">회원가입</a>
    </div>
</div>

<script>
// 로그인 완료 콜백 (MubloRequest의 executeCallback이 window[name] 폴백으로 호출)
window.loginComplete = function(response) {
    var errorDiv = document.getElementById('login-error');
    if (response.result === 'success') {
        var redirect = (response.data && response.data.redirect) || '/';
        location.href = redirect;
    } else {
        errorDiv.textContent = response.message || '로그인에 실패했습니다.';
        errorDiv.style.display = 'block';
    }
};

// Enter 키로 로그인
document.getElementById('login-form').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        var btn = this.querySelector('.mublo-submit');
        if (btn) btn.click();
    }
});
</script>
