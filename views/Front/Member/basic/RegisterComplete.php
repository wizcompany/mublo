<?php
/**
 * Member Register - Step 3: 가입 완료
 *
 * 회원가입 완료 기본 스킨
 */
$this->layout(['header' => false, 'footer' => false]);
$this->assets->addCss('/serve/front/view/member/basic/css/member.css');
?>

<div class="member-complete-wrapper">
    <div class="auth-logo">
        <a href="/">
            <?php if (!empty($siteImages['logo_pc'])): ?>
                <img src="<?= htmlspecialchars($siteImages['logo_pc']) ?>" alt="<?= htmlspecialchars($siteConfig['site_title'] ?? '') ?>">
            <?php else: ?>
                <span><?= htmlspecialchars($siteConfig['site_title'] ?? 'MUBLO') ?></span>
            <?php endif; ?>
        </a>
    </div>
    <div class="complete-icon">&#10003;</div>
    <h2>회원가입이 완료되었습니다</h2>
    <p class="complete-message">
        회원가입을 축하합니다!<br>
        로그인 후 서비스를 이용하실 수 있습니다.
    </p>
    <div>
        <a href="/login" class="btn">로그인</a>
        <a href="/" class="btn btn-outline">메인으로</a>
    </div>
</div>
