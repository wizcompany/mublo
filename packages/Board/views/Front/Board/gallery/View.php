<?php
/**
 * Board View (gallery skin)
 *
 * 게시글 상세 갤러리 스킨
 *
 * @var array $article 게시글 (ArticlePresenter 변환 완료)
 * @var array $board 게시판 설정
 * @var array|null $prev 이전 글
 * @var array|null $next 다음 글
 * @var bool $canModify 수정 권한
 * @var bool $canDelete 삭제 권한
 * @var bool $canComment 댓글 권한
 * @var bool $canReact 반응 권한
 * @var bool $canDownload 다운로드 권한
 * @var array $comments 댓글 목록
 * @var array|null $currentUser 현재 로그인 사용자
 */

$boardSlug = htmlspecialchars($board['board_slug'] ?? '');
$boardName = htmlspecialchars($board['board_name'] ?? '');
$useComment = !empty($board['use_comment']);
$useReaction = !empty($board['use_reaction']);
$reactionInfo = $reactionInfo ?? null;
$enabledReactions = $enabledReactions ?? [];

$articleId = (int) ($article['article_id'] ?? 0);
$content = $article['content'] ?? '';
$isNotice = in_array('notice', $article['badges']);
$isSecret = in_array('secret', $article['badges']);

$isLoggedIn = $currentUser !== null;
$currentMemberId = $currentUser['member_id'] ?? null;
$currentNickname = htmlspecialchars($currentUser['nickname'] ?? '');
?>

<link rel="stylesheet" href="/serve/package/Board/views/Front/Board/gallery/_assets/css/board.css">

<div class="board-view">
    <!-- 게시판 헤더 -->
    <div class="board-view__board-header">
        <h2 class="board-view__board-name">
            <a href="/board/<?= $boardSlug ?>"><?= $boardName ?></a>
        </h2>
    </div>

    <!-- 글 헤더 -->
    <div class="board-view__header">
        <h3 class="board-view__title">
            <?php if ($isNotice): ?>
                <span class="board-view__badge board-view__badge--notice">공지</span>
            <?php endif; ?>
            <?php if ($isSecret): ?>
                <span class="board-view__icon board-view__icon--secret">🔒</span>
            <?php endif; ?>
            <?= $article['title_safe'] ?>
        </h3>
        <div class="board-view__meta">
            <span class="board-view__author"><?= $article['author_name'] ?></span>
            <span class="board-view__date"><?= $article['date_full'] ?></span>
            <span class="board-view__views">조회 <?= $article['view_count_formatted'] ?></span>
            <?php if ((int) ($article['comment_count'] ?? 0) > 0): ?>
                <span class="board-view__comments">댓글 <?= $article['comment_count_formatted'] ?></span>
            <?php endif; ?>
            <?php if ($article['is_updated']): ?>
                <span class="board-view__updated">수정됨</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- 본문 -->
    <div class="board-view__content">
        <?= $content ?>
    </div>

    <!-- 첨부파일 -->
    <?php if (!empty($attachments)): ?>
    <div class="board-view__attachments">
        <h4 class="board-view__attachments-title">첨부파일 <span class="board-view__attachments-count"><?= count($attachments) ?></span></h4>
        <ul class="board-view__attachment-list">
            <?php foreach ($attachments as $att): ?>
            <li class="board-view__attachment-item">
                <?php if ($canDownload): ?>
                <a href="/board/<?= $boardSlug ?>/file/download/<?= $att['attachment_id'] ?>" class="board-view__attachment-link">
                    <span class="board-view__attachment-icon"><?= !empty($att['is_image']) ? '🖼️' : '📎' ?></span>
                    <span class="board-view__attachment-name"><?= htmlspecialchars($att['original_name']) ?></span>
                    <span class="board-view__attachment-size">(<?= number_format($att['file_size'] / 1024, 1) ?>KB)</span>
                </a>
                <?php else: ?>
                <span class="board-view__attachment-link board-view__attachment-link--disabled">
                    <span class="board-view__attachment-icon"><?= !empty($att['is_image']) ? '🖼️' : '📎' ?></span>
                    <span class="board-view__attachment-name"><?= htmlspecialchars($att['original_name']) ?></span>
                    <span class="board-view__attachment-size">(<?= number_format($att['file_size'] / 1024, 1) ?>KB)</span>
                </span>
                <?php endif; ?>
                <?php if ($att['download_count'] > 0): ?>
                <span class="board-view__attachment-downloads">다운 <?= $att['download_count'] ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- 링크 -->
    <?php if (!empty($links)): ?>
    <div class="board-view__links">
        <h4 class="board-view__links-title">관련 링크</h4>
        <ul class="board-view__link-list">
            <?php foreach ($links as $lnk): ?>
            <li class="board-view__link-item">
                <a href="<?= htmlspecialchars($lnk['link_url']) ?>" class="board-view__link-anchor" target="_blank" rel="noopener noreferrer">
                    <span class="board-view__link-icon">🔗</span>
                    <span class="board-view__link-text"><?= htmlspecialchars($lnk['link_title'] ?: $lnk['link_url']) ?></span>
                </a>
                <?php if ($lnk['click_count'] > 0): ?>
                <span class="board-view__link-clicks">클릭 <?= $lnk['click_count'] ?></span>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <!-- 반응 영역 -->
    <?php if ($useReaction && !empty($enabledReactions)): ?>
    <div class="board-reaction" id="board-reaction" data-article-id="<?= $articleId ?>">
        <div class="board-reaction__buttons">
            <?php foreach ($enabledReactions as $type => $config):
                $count = $reactionInfo['counts'][$type] ?? 0;
                $isActive = ($reactionInfo['my_reaction'] ?? null) === $type;
                $label = htmlspecialchars($config['label'] ?? $type);
                $icon = $config['icon'] ?? '';
                $color = htmlspecialchars($config['color'] ?? '#3B82F6');
            ?>
                <button type="button"
                        class="board-reaction__btn<?= $isActive ? ' board-reaction__btn--active' : '' ?>"
                        data-type="<?= htmlspecialchars($type) ?>"
                        data-color="<?= $color ?>"
                        style="<?= $isActive ? '--reaction-color: ' . $color : '' ?>"
                        <?= !$canReact ? 'disabled' : '' ?>>
                    <span class="board-reaction__icon"><?= $icon ?></span>
                    <span class="board-reaction__label"><?= $label ?></span>
                    <span class="board-reaction__count" id="reaction-count-<?= htmlspecialchars($type) ?>"><?= $count > 0 ? $count : '' ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <?php if (!$isLoggedIn): ?>
            <p class="board-reaction__login-hint">반응을 남기려면 <a href="/login">로그인</a>하세요.</p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- 버튼 영역 -->
    <div class="board-view__actions">
        <div class="board-view__actions-left">
            <a href="/board/<?= $boardSlug ?>" class="board-view__btn board-view__btn--list">목록</a>
        </div>
        <div class="board-view__actions-right">
            <?php if ($canModify): ?>
                <a href="<?= $article['edit_url'] ?>" class="board-view__btn board-view__btn--edit">수정</a>
            <?php endif; ?>
            <?php if ($canDelete): ?>
                <button type="button" class="board-view__btn board-view__btn--delete" data-article-id="<?= $articleId ?>">삭제</button>
            <?php endif; ?>
            <?php if ($canWrite): ?>
                <a href="/board/<?= $boardSlug ?>/write" class="board-view__btn board-view__btn--write">글쓰기</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 이전/다음 글 -->
    <?php if ($prev || $next): ?>
        <div class="board-view__adjacent">
            <?php if ($next): ?>
                <div class="board-view__adjacent-item board-view__adjacent-item--next">
                    <span class="board-view__adjacent-label">다음글</span>
                    <a href="<?= $next['url'] ?>" class="board-view__adjacent-link">
                        <?= $next['title_safe'] ?>
                    </a>
                </div>
            <?php endif; ?>
            <?php if ($prev): ?>
                <div class="board-view__adjacent-item board-view__adjacent-item--prev">
                    <span class="board-view__adjacent-label">이전글</span>
                    <a href="<?= $prev['url'] ?>" class="board-view__adjacent-link">
                        <?= $prev['title_safe'] ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- 댓글 영역 -->
    <?php if ($useComment): ?>
    <div class="board-comment" id="board-comment" data-board-slug="<?= $boardSlug ?>" data-article-id="<?= $articleId ?>">
        <h4 class="board-comment__title">댓글 <span class="board-comment__count" id="comment-count"><?= $article['comment_count_formatted'] ?></span></h4>

        <div class="board-comment__list" id="comment-list">
            <?php if (empty($comments)): ?>
                <p class="board-comment__empty">등록된 댓글이 없습니다.</p>
            <?php else: ?>
                <?php foreach ($comments as $c):
                    $cId = (int) $c['comment_id'];
                    $cDepth = (int) ($c['depth'] ?? 0);
                    $cContent = nl2br(htmlspecialchars($c['content'] ?? ''));
                    $cDate = $c['created_at'] ? date('Y-m-d H:i', strtotime($c['created_at'])) : '';
                    $cAuthor = htmlspecialchars($c['author_name'] ?? '익명');
                    $cIsSecret = !empty($c['is_secret']);
                    $cMemberId = $c['member_id'] ?? null;
                    $isOwn = $currentMemberId && $cMemberId && (int) $cMemberId === (int) $currentMemberId;
                ?>
                    <div class="board-comment__item board-comment__item--depth-<?= $cDepth ?>" data-comment-id="<?= $cId ?>" style="margin-left: <?= $cDepth * 30 ?>px;">
                        <div class="board-comment__item-header">
                            <span class="board-comment__item-author"><?= $cAuthor ?></span>
                            <span class="board-comment__item-date"><?= $cDate ?></span>
                        </div>
                        <div class="board-comment__item-content">
                            <?php if ($cIsSecret): ?>
                                <span class="board-comment__icon--secret">🔒</span>
                            <?php endif; ?>
                            <?= $cContent ?>
                        </div>
                        <div class="board-comment__item-actions">
                            <?php if ($canComment): ?>
                                <button type="button" class="board-comment__btn board-comment__btn--reply" data-parent-id="<?= $cId ?>">답글</button>
                            <?php endif; ?>
                            <?php if ($isOwn): ?>
                                <button type="button" class="board-comment__btn board-comment__btn--edit" data-comment-id="<?= $cId ?>">수정</button>
                                <button type="button" class="board-comment__btn board-comment__btn--delete" data-comment-id="<?= $cId ?>">삭제</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($canComment): ?>
        <div class="board-comment__form-wrap" id="comment-form-wrap">
            <div class="board-comment__form" id="comment-form">
                <?php if (!$isLoggedIn): ?>
                    <div class="board-comment__form-guest">
                        <input type="text" id="comment-guest-name" class="board-comment__input" placeholder="이름" maxlength="50">
                        <input type="password" id="comment-guest-password" class="board-comment__input" placeholder="비밀번호" maxlength="50">
                    </div>
                <?php endif; ?>
                <div class="board-comment__form-content">
                    <textarea id="comment-content" class="board-comment__textarea" rows="3" placeholder="댓글을 입력하세요."></textarea>
                </div>
                <div class="board-comment__form-actions">
                    <label class="board-comment__secret-label">
                        <input type="checkbox" id="comment-is-secret"> 비밀댓글
                    </label>
                    <button type="button" id="comment-submit-btn" class="board-comment__btn board-comment__btn--submit">등록</button>
                </div>
                <input type="hidden" id="comment-parent-id" value="">
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function() {
        const boardSlug = '<?= $boardSlug ?>';
        const articleId = <?= $articleId ?>;
        const isLoggedIn = <?= $isLoggedIn ? 'true' : 'false' ?>;

        // 댓글 등록
        const submitBtn = document.getElementById('comment-submit-btn');
        if (submitBtn) {
            submitBtn.addEventListener('click', function() {
                const content = document.getElementById('comment-content').value.trim();
                if (!content) {
                    alert('댓글 내용을 입력해주세요.');
                    return;
                }

                const payload = {
                    article_id: articleId,
                    content: content,
                    parent_id: document.getElementById('comment-parent-id').value || null,
                    is_secret: document.getElementById('comment-is-secret').checked
                };

                if (!isLoggedIn) {
                    payload.author_name = document.getElementById('comment-guest-name').value.trim();
                    payload.author_password = document.getElementById('comment-guest-password').value;
                    if (!payload.author_name) { alert('이름을 입력해주세요.'); return; }
                    if (!payload.author_password) { alert('비밀번호를 입력해주세요.'); return; }
                }

                MubloRequest.sendRequest({
                    url: '/board/' + boardSlug + '/comment',
                    method: 'POST',
                    data: payload,
                    payloadType: MubloRequest.PayloadType.JSON,
                    loading: true
                }).then(function(res) {
                    if (res.result === 'success') {
                        location.reload();
                    } else {
                        alert(res.message || '댓글 등록에 실패했습니다.');
                    }
                });
            });
        }

        // 답글 버튼
        document.querySelectorAll('.board-comment__btn--reply').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const parentId = this.dataset.parentId;
                const parentItem = this.closest('.board-comment__item');
                const formWrap = document.getElementById('comment-form-wrap');

                parentItem.after(formWrap);
                document.getElementById('comment-parent-id').value = parentId;
                document.getElementById('comment-content').focus();
                document.getElementById('comment-content').placeholder = '답글을 입력하세요.';
            });
        });

        // 댓글 삭제
        document.querySelectorAll('.board-comment__btn--delete').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!confirm('댓글을 삭제하시겠습니까?')) return;
                const commentId = this.dataset.commentId;

                MubloRequest.sendRequest({
                    url: '/board/' + boardSlug + '/comment/' + commentId + '/delete',
                    method: 'POST',
                    data: {},
                    payloadType: MubloRequest.PayloadType.JSON,
                    loading: true
                }).then(function(res) {
                    if (res.result === 'success') {
                        location.reload();
                    } else {
                        alert(res.message || '댓글 삭제에 실패했습니다.');
                    }
                });
            });
        });

        // 댓글 수정
        document.querySelectorAll('.board-comment__btn--edit').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const commentId = this.dataset.commentId;
                const item = this.closest('.board-comment__item');
                const contentEl = item.querySelector('.board-comment__item-content');
                const originalText = contentEl.innerText.trim();

                contentEl.innerHTML =
                    '<textarea class="board-comment__textarea board-comment__textarea--edit" rows="3">' +
                    MubloRequest.escapeHtml(originalText) +
                    '</textarea>' +
                    '<div class="board-comment__edit-actions">' +
                    '<button type="button" class="board-comment__btn board-comment__btn--save">저장</button>' +
                    '<button type="button" class="board-comment__btn board-comment__btn--cancel">취소</button>' +
                    '</div>';

                contentEl.querySelector('.board-comment__btn--save').addEventListener('click', function() {
                    const newContent = contentEl.querySelector('textarea').value.trim();
                    if (!newContent) { alert('댓글 내용을 입력해주세요.'); return; }

                    MubloRequest.sendRequest({
                        url: '/board/' + boardSlug + '/comment/' + commentId + '/update',
                        method: 'POST',
                        data: { content: newContent },
                        payloadType: MubloRequest.PayloadType.JSON,
                        loading: true
                    }).then(function(res) {
                        if (res.result === 'success') {
                            location.reload();
                        } else {
                            alert(res.message || '댓글 수정에 실패했습니다.');
                        }
                    });
                });

                contentEl.querySelector('.board-comment__btn--cancel').addEventListener('click', function() {
                    contentEl.innerHTML = originalText.replace(/\n/g, '<br>');
                });
            });
        });
    })();
    </script>
    <?php endif; ?>

    <?php if ($useReaction && !empty($enabledReactions) && $canReact): ?>
    <script>
    (function() {
        const boardSlug = '<?= $boardSlug ?>';
        const articleId = <?= $articleId ?>;

        document.querySelectorAll('.board-reaction__btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (this.disabled) return;

                const reactionType = this.dataset.type;

                MubloRequest.sendRequest({
                    url: '/board/' + boardSlug + '/reaction',
                    method: 'POST',
                    data: {
                        article_id: articleId,
                        reaction_type: reactionType
                    },
                    payloadType: MubloRequest.PayloadType.JSON,
                    loading: false
                }).then(function(res) {
                    if (res.result === 'success') {
                        const data = res.data;

                        document.querySelectorAll('.board-reaction__btn').forEach(function(b) {
                            b.classList.remove('board-reaction__btn--active');
                            b.style.removeProperty('--reaction-color');
                        });

                        document.querySelectorAll('.board-reaction__btn').forEach(function(b) {
                            const type = b.dataset.type;
                            const countEl = document.getElementById('reaction-count-' + type);
                            if (countEl) {
                                const c = (data.counts && data.counts[type]) ? data.counts[type] : 0;
                                countEl.textContent = c > 0 ? c : '';
                            }
                        });

                        if (data.my_reaction) {
                            const activeBtn = document.querySelector('.board-reaction__btn[data-type="' + data.my_reaction + '"]');
                            if (activeBtn) {
                                activeBtn.classList.add('board-reaction__btn--active');
                                activeBtn.style.setProperty('--reaction-color', activeBtn.dataset.color);
                            }
                        }
                    } else {
                        alert(res.message || '처리에 실패했습니다.');
                    }
                });
            });
        });
    })();
    </script>
    <?php endif; ?>
</div>
