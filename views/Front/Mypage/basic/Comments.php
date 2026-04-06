<?php
/**
 * Mypage - 내가 쓴 댓글
 *
 * @var array[] $comments    댓글 목록 (comment_id, article_id, board_id, content, article_title, article_slug, board_name, board_slug, created_at)
 * @var array   $pagination  페이지네이션 (totalItems, perPage, currentPage, totalPages)
 * @var array[] $mypageMenus 사이드바 메뉴 목록
 * @var string  $currentSection
 */
?>

<?php ob_start(); ?>
<div class="mypage-list-summary">
    전체 <?= number_format($pagination['totalItems']) ?>개의 댓글
</div>

<?php if (empty($comments)): ?>
    <div class="empty-state">작성한 댓글이 없습니다.</div>
<?php else: ?>
    <div class="mypage-comments-list">
        <?php foreach ($comments as $row): ?>
            <?php
                $boardSlug  = $row['board_slug'] ?? '';
                $articleId  = $row['article_id'] ?? 0;
                $articleUrl = $boardSlug ? "/board/{$boardSlug}/view/{$articleId}" : "#";
                $excerpt    = mb_substr(strip_tags($row['content'] ?? ''), 0, 120);
                if (mb_strlen($row['content'] ?? '') > 120) {
                    $excerpt .= '...';
                }
            ?>
            <div class="comment-item">
                <div class="comment-article">
                    <span class="board-badge"><?= htmlspecialchars($row['board_name'] ?? '') ?></span>
                    원글:
                    <a href="<?= htmlspecialchars($articleUrl) ?>">
                        <?= htmlspecialchars($row['article_title'] ?? '(삭제된 게시글)') ?>
                    </a>
                </div>
                <div class="comment-content"><?= htmlspecialchars($excerpt) ?></div>
                <div class="comment-date"><?= htmlspecialchars(substr($row['created_at'] ?? '', 0, 16)) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- 페이지네이션 -->
<?= $this->pagination($pagination) ?>

<?php $content = ob_get_clean(); ?>

<?php include __DIR__ . '/_layout.php'; ?>
