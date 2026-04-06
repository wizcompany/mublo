<?php
/**
 * Admin Login View
 *
 * 관리자 로그인 페이지
 */
?>
<style>
    .login-wrapper {
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f0f2f5;
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    }

    .login-card {
        width: 100%;
        max-width: 420px;
        margin: 20px;
    }

    .login-brand {
        text-align: center;
        margin-bottom: 32px;
    }

    .login-brand-icon {
        width: 48px;
        height: 48px;
        background: #1a1a2e;
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
    }

    .login-brand-icon svg {
        width: 24px;
        height: 24px;
        fill: #fff;
    }

    .login-brand h1 {
        font-size: 22px;
        font-weight: 700;
        color: #1a1a2e;
        margin: 0 0 6px;
        letter-spacing: -0.3px;
    }

    .login-brand p {
        font-size: 14px;
        color: #8c8c9a;
        margin: 0;
    }

    .login-box {
        background: #fff;
        border-radius: 16px;
        padding: 36px 32px 32px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.06), 0 4px 16px rgba(0,0,0,0.06);
        border: 1px solid rgba(0,0,0,0.04);
    }

    .login-box .form-group {
        margin-bottom: 20px;
    }

    .login-box .form-group label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #3a3a4a;
        margin-bottom: 6px;
    }

    .login-box .form-control {
        width: 100%;
        padding: 11px 14px;
        font-size: 14px;
        border: 1px solid #d9dbe3;
        border-radius: 10px;
        background: #fafbfc;
        color: #1a1a2e;
        transition: border-color 0.15s, box-shadow 0.15s, background 0.15s;
        outline: none;
        box-sizing: border-box;
    }

    .login-box .form-control::placeholder {
        color: #b0b3c0;
    }

    .login-box .form-control:focus {
        border-color: #4a6cf7;
        box-shadow: 0 0 0 3px rgba(74,108,247,0.1);
        background: #fff;
    }

    .login-box .btn-login {
        width: 100%;
        padding: 12px;
        font-size: 15px;
        font-weight: 600;
        margin-top: 8px;
        border: none;
        border-radius: 10px;
        background: #1a1a2e;
        color: #fff;
        cursor: pointer;
        transition: background 0.15s, transform 0.1s;
        letter-spacing: 0.2px;
    }

    .login-box .btn-login:hover {
        background: #2d2d44;
    }

    .login-box .btn-login:active {
        transform: scale(0.99);
    }

    .login-box .alert {
        padding: 12px 14px;
        font-size: 13px;
        border-radius: 10px;
        margin-bottom: 20px;
        background: #fef2f2;
        color: #b91c1c;
        border: 1px solid #fecaca;
    }

    .login-footer {
        text-align: center;
        margin-top: 24px;
        color: #b0b3c0;
        font-size: 12px;
    }
</style>

<div class="login-wrapper">
    <div class="login-card">
        <div class="login-brand">
            <div class="login-brand-icon">
                <svg viewBox="0 0 24 24"><path d="M12 2L2 7v10l10 5 10-5V7L12 2zm0 2.18L19.18 7 12 9.82 4.82 7 12 4.18zM4 8.64l7 3.5V19.5l-7-3.5V8.64zm9 10.86v-7.36l7-3.5v7.36l-7 3.5z"/></svg>
            </div>
            <h1><?= htmlspecialchars($pageTitle ?? '관리자') ?></h1>
            <p>계정 정보를 입력하여 로그인하세요</p>
        </div>

        <div class="login-box">
            <?php if (!empty($error)): ?>
                <div class="alert">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="/admin/login" autocomplete="off">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <div class="form-group">
                    <label for="user_id">아이디</label>
                    <input
                        type="text"
                        id="user_id"
                        name="formData[user_id]"
                        class="form-control"
                        value="<?= htmlspecialchars($user_id ?? '') ?>"
                        placeholder="아이디를 입력하세요"
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
                        name="formData[password]"
                        class="form-control"
                        placeholder="비밀번호를 입력하세요"
                        required
                    >
                </div>

                <button type="submit" class="btn-login">로그인</button>
            </form>
        </div>

        <div class="login-footer">
            &copy; <?= date('Y') ?> <?= htmlspecialchars($siteTitle ?? 'Mublo', ENT_QUOTES, 'UTF-8') ?>
        </div>
    </div>
</div>

<?php
$host = $_SERVER['HTTP_HOST'] ?? '';
$isDemo = str_starts_with(explode('.', $host)[0], 'demo');
if ($isDemo): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var uid = document.getElementById('user_id');
    var pwd = document.getElementById('password');
    if (uid && !uid.value) uid.value = 'demo';
    if (pwd && !pwd.value) pwd.value = '123456';
});
</script>
<?php endif; ?>
