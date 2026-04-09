<?php
/**
 * Member Register - 가입 신청 완료 (관리자 승인 대기)
 *
 * 회원가입 승인 대기 기본 스킨
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
    <div class="complete-icon">&#9993;</div>
    <h2>가입 신청이 완료되었습니다</h2>
    <p class="complete-message">
        가입 신청을 받았습니다.<br>
        관리자 승인 후 로그인이 가능합니다.
    </p>
    <div>
        <a href="/" class="btn">메인으로</a>
    </div>
</div>
