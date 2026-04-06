<?php
/**
 * Front Index Page (basic skin)
 *
 * 메인 페이지 - 블록 렌더링
 *
 * @var string $blockHtml 블록 렌더링 HTML
 * @var string $pageTitle 페이지 제목
 */

$blockHtml = $blockHtml ?? '';
?>
<div class="page-index">
    <?php if (!empty($blockHtml)): ?>
        <?= $blockHtml ?>
    <?php else: ?>
        <div class="page-index__empty">
            <p>관리자에서 메인 페이지 블록을 설정해주세요.</p>
        </div>
    <?php endif; ?>
</div>
