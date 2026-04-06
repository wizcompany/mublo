<?php
/**
 * Mypage 공통 레이아웃
 *
 * 사이드바 + 콘텐츠 영역.
 * 각 Mypage View에서 ob_start() 후 $content를 설정하고 include 합니다.
 *
 * @var array[] $mypageMenus     사이드바 메뉴 목록 (buildMenus()가 반환)
 * @var string  $currentSection  현재 활성 섹션 키
 * @var string  $content         렌더링할 콘텐츠 HTML
 */
$this->assets->addCss('/serve/front/view/mypage/basic/css/mypage.css');
?>

<div class="mypage-wrapper">
    <!-- 사이드바 -->
    <aside class="mypage-sidebar">
        <?php if (!empty($user)): ?>
            <div class="mypage-user-card">
                <div class="user-name"><?= htmlspecialchars($user['nickname'] ?? $user['user_id'] ?? '') ?></div>
                <div class="user-level"><?= htmlspecialchars($user['level_name'] ?? '일반회원') ?></div>
            </div>
        <?php endif; ?>

        <nav class="mypage-nav">
            <?php
            foreach ($mypageMenus as $i => $menu):
                // 탈퇴 메뉴 앞에 구분선
                if ($menu['section'] === 'withdraw' && $i > 0):
            ?>
                <div class="mypage-nav-divider"></div>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($menu['url']) ?>"
               class="mypage-nav-item<?= $menu['active'] ? ' active' : '' ?>">
                <?= htmlspecialchars($menu['label']) ?>
            </a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <!-- 콘텐츠 -->
    <main class="mypage-content">
        <?= $content ?>
    </main>
</div>
