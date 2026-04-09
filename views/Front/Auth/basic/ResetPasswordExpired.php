<?php
/**
 * Auth ResetPasswordExpired View
 *
 * 비밀번호 재설정 토큰 만료/무효 안내
 *
 * @var string $message 안내 메시지
 */
$this->layout(['header' => false, 'footer' => false]);
$this->assets->addCss('/serve/front/view/auth/basic/css/auth.css');
?>

<div class="expired-wrapper">
    <div class="auth-logo">
        <a href="/">
            <?php if (!empty($siteImages['logo_pc'])): ?>
                <img src="<?= htmlspecialchars($siteImages['logo_pc']) ?>" alt="<?= htmlspecialchars($siteConfig['site_title'] ?? '') ?>">
            <?php else: ?>
                <span><?= htmlspecialchars($siteConfig['site_title'] ?? 'MUBLO') ?></span>
            <?php endif; ?>
        </a>
    </div>
    <div class="icon">&#10060;</div>
    <h2>링크가 유효하지 않습니다</h2>
    <p><?= htmlspecialchars($message) ?></p>
    <a href="/find-account" class="btn">비밀번호 재설정 다시 요청</a>
</div>
