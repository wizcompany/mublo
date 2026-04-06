<?php
/**
 * Admin Boardarticle - View
 *
 * 게시글 상세 보기
 *
 * @var string $pageTitle 페이지 제목
 * @var array $article 게시글 정보
 * @var array $author 작성자 정보
 * @var array $board 게시판 정보
 * @var array $comments 댓글 목록
 * @var array $attachments 첨부파일 목록
 * @var array $links 링크 목록
 */
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item"><a href="/admin/board/article">게시글 관리</a></li>
                        <li class="breadcrumb-item active"><?= htmlspecialchars(mb_substr($article['title'] ?? '', 0, 20)) ?><?= mb_strlen($article['title'] ?? '') > 20 ? '...' : '' ?></li>
                    </ol>
                </nav>
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '게시글 상세') ?></h3>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/board/article" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-1"></i>목록
                </a>
                <a href="/admin/board/article/edit?id=<?= $article['article_id'] ?>" class="btn btn-outline-primary me-2">
                    <i class="bi bi-pencil me-1"></i>수정
                </a>
                <button type="button" class="btn btn-outline-danger" onclick="deleteArticle()">
                    <i class="bi bi-trash me-1"></i>삭제
                </button>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <!-- 본문 영역 -->
        <div class="col-lg-8">
            <!-- 게시글 정보 -->
            <div class="card">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <div>
                        <?php if ($article['is_notice']): ?>
                        <span class="badge bg-danger me-1">공지</span>
                        <?php endif; ?>
                        <?php if ($article['is_secret']): ?>
                        <span class="badge bg-warning text-dark me-1">비밀글</span>
                        <?php endif; ?>
                        <i class="bi bi-file-text me-2 text-pastel-blue"></i><?= htmlspecialchars($article['title'] ?? '') ?>
                    </div>
                    <div class="text-muted small mt-2">
                        <span class="badge bg-secondary me-2"><?= htmlspecialchars($board['board_name'] ?? '-') ?></span>
                        <?php
                        $authorName = $article['author_name'] ?? '익명';
                        ?>
                        <span class="me-3"><i class="bi bi-person me-1"></i><?= htmlspecialchars($authorName) ?></span>
                        <span class="me-3"><i class="bi bi-clock me-1"></i><?= date('Y-m-d H:i', strtotime($article['created_at'])) ?></span>
                        <span class="me-3"><i class="bi bi-eye me-1"></i><?= number_format($article['view_count'] ?? 0) ?></span>
                        <span><i class="bi bi-chat me-1"></i><?= number_format($article['comment_count'] ?? 0) ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="article-content">
                        <?= $article['content'] ?? '' ?>
                    </div>
                </div>
            </div>

            <!-- 첨부파일 -->
            <?php if (!empty($attachments)): ?>
            <div class="card mt-4">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-paperclip me-2 text-pastel-green"></i>첨부파일 (<?= count($attachments) ?>개)
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>파일명</th>
                                <th style="width: 100px;">크기</th>
                                <th style="width: 80px;">다운로드</th>
                                <th style="width: 60px;">관리</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attachments as $file): ?>
                            <tr>
                                <td>
                                    <?php if ($file['is_image']): ?>
                                    <i class="bi bi-image text-success me-1"></i>
                                    <?php else: ?>
                                    <i class="bi bi-file-earmark me-1"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($file['original_name']) ?>
                                </td>
                                <td>
                                    <?php
                                    $size = $file['file_size'];
                                    if ($size >= 1048576) {
                                        echo number_format($size / 1048576, 2) . ' MB';
                                    } elseif ($size >= 1024) {
                                        echo number_format($size / 1024, 2) . ' KB';
                                    } else {
                                        echo $size . ' B';
                                    }
                                    ?>
                                </td>
                                <td class="text-center">
                                    <?= number_format($file['download_count'] ?? 0) ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            onclick="deleteAttachment(<?= $file['attachment_id'] ?>)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <!-- 링크 -->
            <?php if (!empty($links)): ?>
            <div class="card mt-4">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-link-45deg me-2 text-pastel-purple"></i>링크 (<?= count($links) ?>개)
                </div>
                <ul class="list-group list-group-flush">
                    <?php foreach ($links as $link): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <a href="<?= htmlspecialchars($link['link_url']) ?>" target="_blank">
                                <?= htmlspecialchars($link['link_title'] ?: $link['link_url']) ?>
                            </a>
                            <small class="text-muted ms-2">(클릭: <?= number_format($link['click_count'] ?? 0) ?>)</small>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <!-- 댓글 -->
            <div class="card mt-4">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-chat-dots me-2 text-pastel-sky"></i>댓글 (<?= count($comments) ?>개)
                </div>
                <div class="card-body p-0">
                    <?php if (empty($comments)): ?>
                    <div class="text-center py-4 text-muted">
                        댓글이 없습니다.
                    </div>
                    <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($comments as $comment): ?>
                        <li class="list-group-item <?= $comment->getDepth() > 0 ? 'ps-5' : '' ?>">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <?php if ($comment->getDepth() > 0): ?>
                                    <i class="bi bi-arrow-return-right text-muted me-1"></i>
                                    <?php endif; ?>
                                    <?php if ($comment->isSecret()): ?>
                                    <span class="badge bg-warning text-dark me-1">비밀</span>
                                    <?php endif; ?>
                                    <?php if ($comment->isDeleted()): ?>
                                    <span class="text-muted text-decoration-line-through">삭제된 댓글입니다.</span>
                                    <?php else: ?>
                                    <span><?= nl2br(htmlspecialchars($comment->getContent())) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$comment->isDeleted()): ?>
                                <button type="button" class="btn btn-sm btn-outline-danger"
                                        onclick="deleteComment(<?= $comment->getCommentId() ?>)">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted small mt-1">
                                <?php $commentAuthor = $comment->getAuthorDisplayName(); ?>
                                <span class="me-2"><?= htmlspecialchars($commentAuthor) ?></span>
                                <span><?= date('Y-m-d H:i', strtotime($comment->getCreatedAt()->format('Y-m-d H:i:s'))) ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- 사이드바 -->
        <div class="col-lg-4">
            <!-- 게시글 정보 -->
            <div class="card">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-info-circle me-2 text-pastel-orange"></i>게시글 정보
                </div>
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">ID</span>
                        <span>#<?= $article['article_id'] ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">게시판</span>
                        <span><?= htmlspecialchars($board['board_name'] ?? '-') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">상태</span>
                        <?php
                        $statusClass = match ($article['status']) {
                            'published' => 'success',
                            'draft' => 'warning',
                            'deleted' => 'secondary',
                            default => 'secondary',
                        };
                        $statusLabel = match ($article['status']) {
                            'published' => '발행',
                            'draft' => '임시저장',
                            'deleted' => '삭제됨',
                            default => $article['status'],
                        };
                        ?>
                        <span class="badge bg-<?= $statusClass ?>"><?= $statusLabel ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">조회수</span>
                        <span><?= number_format($article['view_count'] ?? 0) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">댓글수</span>
                        <span><?= number_format($article['comment_count'] ?? 0) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">반응수</span>
                        <span><?= number_format($article['reaction_count'] ?? 0) ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">작성일</span>
                        <span><?= date('Y-m-d H:i', strtotime($article['created_at'])) ?></span>
                    </li>
                    <?php if ($article['updated_at'] !== $article['created_at']): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">수정일</span>
                        <span><?= date('Y-m-d H:i', strtotime($article['updated_at'])) ?></span>
                    </li>
                    <?php endif; ?>
                    <?php if ($article['ip_address']): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">IP</span>
                        <span><code><?= htmlspecialchars($article['ip_address']) ?></code></span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- 작성자 정보 -->
            <div class="card mt-4">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-person me-2 text-pastel-blue"></i>작성자 정보
                </div>
                <ul class="list-group list-group-flush">
                    <?php if ($article['member_id']): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">회원 ID</span>
                        <span><?= htmlspecialchars($author['userid'] ?? '-') ?></span>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">닉네임</span>
                        <span><?= htmlspecialchars($author['nickname'] ?? '-') ?></span>
                    </li>
                    <li class="list-group-item">
                        <a href="/admin/member/edit?id=<?= $article['member_id'] ?>" class="btn btn-sm btn-outline-primary w-100">
                            <i class="bi bi-person-gear me-1"></i>회원 정보 보기
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span class="text-muted">이름</span>
                        <span><?= htmlspecialchars($article['author_name'] ?? '익명') ?></span>
                    </li>
                    <li class="list-group-item">
                        <span class="badge bg-secondary">비회원</span>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
const articleId = <?= $article['article_id'] ?>;

function deleteArticle() {
    if (!confirm('게시글을 삭제하시겠습니까?\n삭제된 게시글은 복구할 수 없습니다.')) {
        return;
    }

    MubloRequest.requestJson('/admin/board/article/delete', {
        article_id: articleId
    }).then(response => {
        if (response.result === 'success') {
            alert(response.message || '게시글이 삭제되었습니다.');
            location.href = '/admin/board/article';
        } else {
            alert(response.message || '삭제에 실패했습니다.');
        }
    }).catch(err => {
        alert('오류가 발생했습니다.');
        console.error(err);
    });
}

function deleteComment(commentId) {
    if (!confirm('댓글을 삭제하시겠습니까?')) {
        return;
    }

    MubloRequest.requestJson('/admin/board/article/comment-delete', {
        comment_id: commentId
    }).then(response => {
        if (response.result === 'success') {
            alert(response.message || '댓글이 삭제되었습니다.');
            location.reload();
        } else {
            alert(response.message || '댓글 삭제에 실패했습니다.');
        }
    }).catch(err => {
        alert('오류가 발생했습니다.');
        console.error(err);
    });
}

function deleteAttachment(attachmentId) {
    if (!confirm('첨부파일을 삭제하시겠습니까?')) {
        return;
    }

    MubloRequest.requestJson('/admin/board/article/attachment-delete', {
        attachment_id: attachmentId
    }).then(response => {
        if (response.result === 'success') {
            alert(response.message || '첨부파일이 삭제되었습니다.');
            location.reload();
        } else {
            alert(response.message || '첨부파일 삭제에 실패했습니다.');
        }
    }).catch(err => {
        alert('오류가 발생했습니다.');
        console.error(err);
    });
}
</script>
