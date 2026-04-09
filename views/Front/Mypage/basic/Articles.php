<?php
/**
 * Mypage - 내가 쓴 글
 *
 * @var array[] $articles    게시글 목록 (article_id, board_id, title, board_name, board_slug, view_count, comment_count, created_at)
 * @var array   $pagination  페이지네이션 (totalItems, perPage, currentPage, totalPages)
 * @var array[] $mypageMenus 사이드바 메뉴 목록
 * @var string  $currentSection
 */
?>

<?php ob_start(); ?>
<div class="mypage-list-summary">
    전체 <?= number_format($pagination['totalItems']) ?>개의 게시글
</div>

<table class="mypage-list-table">
    <thead>
        <tr>
            <th>게시판</th>
            <th>제목</th>
            <th>조회</th>
            <th>댓글</th>
            <th>작성일</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($articles)): ?>
            <tr class="empty-row">
                <td colspan="5">작성한 게시글이 없습니다.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($articles as $row): ?>
                <?php
                    $boardSlug  = $row['board_slug'] ?? '';
                    $articleId  = $row['article_id'] ?? 0;
                    $articleUrl = $boardSlug ? "/board/{$boardSlug}/view/{$articleId}" : "#";
                ?>
                <tr>
                    <td class="board-name"><?= htmlspecialchars($row['board_name'] ?? '') ?></td>
                    <td class="article-title">
                        <a href="<?= htmlspecialchars($articleUrl) ?>">
                            <?= htmlspecialchars($row['title'] ?? '') ?>
                        </a>
                    </td>
                    <td class="meta"><?= number_format($row['view_count'] ?? 0) ?></td>
                    <td class="meta"><?= number_format($row['comment_count'] ?? 0) ?></td>
                    <td class="meta"><?= htmlspecialchars(substr($row['created_at'] ?? '', 0, 10)) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- 페이지네이션 -->
<?= $this->pagination($pagination) ?>

<?php $content = ob_get_clean(); ?>

<?php include __DIR__ . '/_layout.php'; ?>
