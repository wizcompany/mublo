<?php
/**
 * Admin Boardarticle - Form
 *
 * 게시글 작성/수정 폼
 *
 * @var string $pageTitle 페이지 제목
 * @var bool $isEdit 수정 모드 여부
 * @var array|null $article 게시글 데이터
 * @var array $boards 게시판 옵션
 * @var int $selectedBoardId 선택된 게시판 ID
 * @var array|null $selectedBoard 선택된 게시판 정보
 * @var array $categories 카테고리 옵션
 * @var array $attachments 첨부파일 목록 (수정 시)
 * @var array $statusOptions 상태 옵션
 */

$article = $article ?? [];
$isEdit = $isEdit ?? false;
$selectedBoardId = $selectedBoardId ?? 0;
?>

<?= editor_css() ?>

<form name="frm" id="frm">
<div class="page-container form-container">
    <!-- 고정 영역 START -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '게시글 작성') ?></h3>
                <p class="text-muted mb-0">
                    <?php if ($isEdit): ?>
                    게시글을 수정합니다.
                    <?php else: ?>
                    새로운 게시글을 작성합니다.
                    <?php endif; ?>
                </p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/board/article" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-1"></i>목록
                </a>
                <button type="button"
                    class="btn btn-outline-primary me-2 mublo-submit"
                    data-target="/admin/board/article/store"
                    data-callback="articleSaved"
                    onclick="setStatus('draft')">
                    <i class="bi bi-file-earmark me-1"></i>임시저장
                </button>
                <button type="button"
                    class="btn btn-primary mublo-submit"
                    data-target="/admin/board/article/store"
                    data-callback="articleSaved"
                    onclick="setStatus('published')">
                    <i class="bi bi-check-lg me-1"></i>발행
                </button>
            </div>
        </div>
    </div>
    <!-- 고정 영역 END -->

    <!-- 숨김 필드 -->
    <?php if ($isEdit): ?>
    <input type="hidden" name="formData[article_id]" value="<?= $article['article_id'] ?? '' ?>">
    <?php endif; ?>
    <input type="hidden" name="formData[status]" id="article_status" value="published">

    <div class="row mt-4">
        <!-- 메인 콘텐츠 영역 -->
        <div class="col-lg-9">
            <div class="card mb-4">
                <div class="card-body">
                    <!-- 제목 -->
                    <div class="mb-3">
                        <label class="form-label">제목 <span class="text-danger">*</span></label>
                        <input type="text"
                               class="form-control form-control-lg"
                               name="formData[title]"
                               value="<?= htmlspecialchars($article['title'] ?? '') ?>"
                               placeholder="게시글 제목을 입력하세요"
                               required>
                    </div>

                    <!-- 내용 -->
                    <div class="mb-3">
                        <label class="form-label">내용</label>
                        <?= editor_html('article_content', $article['content'] ?? '', [
                            'name' => 'formData[content]',
                            'height' => 400,
                            'toolbar' => 'full',
                            'placeholder' => '게시글 내용을 입력하세요',
                        ]) ?>
                    </div>
                </div>
            </div>

            <?php if ($isEdit && !empty($attachments ?? [])): ?>
            <!-- 첨부파일 목록 (수정 시) -->
            <div class="card mb-4">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-paperclip me-2 text-pastel-blue"></i>첨부파일
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <?php foreach ($attachments as $attachment): ?>
                        <div class="list-group-item d-flex justify-content-between align-items-center" id="attachment-<?= $attachment['attachment_id'] ?>">
                            <div>
                                <?php if ($attachment['is_image']): ?>
                                <i class="bi bi-image text-success me-2"></i>
                                <?php else: ?>
                                <i class="bi bi-file-earmark text-secondary me-2"></i>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($attachment['original_name']) ?></span>
                                <small class="text-muted ms-2">(<?= number_format($attachment['file_size'] / 1024, 1) ?> KB)</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteAttachment(<?= $attachment['attachment_id'] ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 사이드바 -->
        <div class="col-lg-3">
            <!-- 게시판 선택 -->
            <div class="card mb-4">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-folder me-2 text-pastel-green"></i>게시판
                </div>
                <div class="card-body">
                    <select class="form-select" name="formData[board_id]" id="board_select" required <?= $isEdit ? 'disabled' : '' ?>>
                        <option value="">게시판 선택</option>
                        <?php
                        $currentGroup = '';
                        foreach ($boards ?? [] as $board):
                            $boardConfig = $board['config'];
                            $groupName = $board['group_name'] ?: '미분류';
                            if ($currentGroup !== $groupName):
                                if ($currentGroup !== ''):
                                    echo '</optgroup>';
                                endif;
                                $currentGroup = $groupName;
                                echo '<optgroup label="' . htmlspecialchars($groupName) . '">';
                            endif;
                        ?>
                        <option value="<?= $boardConfig->getBoardId() ?>" <?= ($article['board_id'] ?? $selectedBoardId) == $boardConfig->getBoardId() ? 'selected' : '' ?>>
                            <?= htmlspecialchars($boardConfig->getBoardName()) ?>
                        </option>
                        <?php endforeach; ?>
                        <?php if ($currentGroup !== ''): ?>
                        </optgroup>
                        <?php endif; ?>
                    </select>
                    <?php if ($isEdit): ?>
                    <input type="hidden" name="formData[board_id]" value="<?= $article['board_id'] ?>">
                    <?php endif; ?>
                </div>
            </div>

            <!-- 카테고리 -->
            <div class="card mb-4" id="category_section" style="<?= empty($categories) ? 'display:none;' : '' ?>">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-tag me-2 text-pastel-purple"></i>카테고리
                </div>
                <div class="card-body">
                    <select class="form-select" name="formData[category_id]" id="category_select">
                        <option value="">카테고리 선택</option>
                        <?php foreach ($categories ?? [] as $category): ?>
                        <option value="<?= $category['category_id'] ?>" <?= ($article['category_id'] ?? '') == $category['category_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($category['category_name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- 옵션 -->
            <div class="card mb-4">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-gear me-2 text-pastel-sky"></i>옵션
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox"
                               class="form-check-input"
                               name="formData[is_notice]"
                               id="is_notice"
                               value="1"
                               <?= ($article['is_notice'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_notice">
                            <i class="bi bi-megaphone me-1"></i>공지사항
                        </label>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input type="checkbox"
                               class="form-check-input"
                               name="formData[is_secret]"
                               id="is_secret"
                               value="1"
                               <?= ($article['is_secret'] ?? false) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_secret">
                            <i class="bi bi-lock me-1"></i>비밀글
                        </label>
                    </div>
                </div>
            </div>

            <!-- 권한 설정 -->
            <div class="card mb-4">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-shield-lock me-2 text-pastel-orange"></i>권한 설정
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label small">읽기 레벨</label>
                        <select class="form-select form-select-sm" name="formData[read_level]">
                            <option value="">게시판 설정 사용</option>
                            <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= ($article['read_level'] ?? '') === $i ? 'selected' : '' ?>>
                                Lv.<?= $i ?><?= $i === 0 ? ' (전체)' : '' ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label small">다운로드 레벨</label>
                        <select class="form-select form-select-sm" name="formData[download_level]">
                            <option value="">게시판 설정 사용</option>
                            <?php for ($i = 0; $i <= 10; $i++): ?>
                            <option value="<?= $i ?>" <?= ($article['download_level'] ?? '') === $i ? 'selected' : '' ?>>
                                Lv.<?= $i ?><?= $i === 0 ? ' (전체)' : '' ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- 현재 상태 (수정 시) -->
            <?php if ($isEdit): ?>
            <div class="card">
                <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                    <i class="bi bi-info-circle me-2 text-pastel-blue"></i>정보
                </div>
                <div class="card-body small">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">상태</span>
                        <span>
                            <?php
                            $statusLabels = ['published' => '발행', 'draft' => '임시저장', 'deleted' => '삭제됨'];
                            echo $statusLabels[$article['status'] ?? 'published'] ?? '알 수 없음';
                            ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">조회수</span>
                        <span><?= number_format($article['view_count'] ?? 0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">댓글수</span>
                        <span><?= number_format($article['comment_count'] ?? 0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">작성일</span>
                        <span><?= substr($article['created_at'] ?? '', 0, 16) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">수정일</span>
                        <span><?= substr($article['updated_at'] ?? '', 0, 16) ?></span>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</form>

<script>
// 상태 설정
function setStatus(status) {
    document.getElementById('article_status').value = status;

    // 에디터 동기화 (폼 제출 전 명시적 호출)
    if (typeof MubloEditor !== 'undefined' && MubloEditor.syncAll) {
        MubloEditor.syncAll();
    }

    return true;
}

// 저장 완료 콜백
MubloRequest.registerCallback('articleSaved', function(response) {
    if (response.result === 'success') {
        alert(response.message || '저장되었습니다.');
        if (response.data && response.data.redirect) {
            location.href = response.data.redirect;
        } else {
            location.href = '/admin/board/article';
        }
    } else {
        alert(response.message || '저장에 실패했습니다.');
    }
});

// 게시판 변경 시 카테고리 로드
document.getElementById('board_select')?.addEventListener('change', function() {
    const boardId = this.value;
    const categorySection = document.getElementById('category_section');
    const categorySelect = document.getElementById('category_select');

    if (!boardId) {
        categorySection.style.display = 'none';
        categorySelect.innerHTML = '<option value="">카테고리 선택</option>';
        return;
    }

    // 카테고리 목록 로드
    fetch('/admin/board/article/categories?board_id=' + boardId)
        .then(response => response.json())
        .then(data => {
            if (data.result === 'success' && data.data.categories.length > 0) {
                let options = '<option value="">카테고리 선택</option>';
                data.data.categories.forEach(function(cat) {
                    options += '<option value="' + cat.category_id + '">' + cat.category_name + '</option>';
                });
                categorySelect.innerHTML = options;
                categorySection.style.display = 'block';
            } else {
                categorySection.style.display = 'none';
                categorySelect.innerHTML = '<option value="">카테고리 선택</option>';
            }
        })
        .catch(err => {
            console.error('카테고리 로드 실패:', err);
            categorySection.style.display = 'none';
        });
});

// 첨부파일 삭제
function deleteAttachment(attachmentId) {
    if (!confirm('이 첨부파일을 삭제하시겠습니까?')) {
        return;
    }

    MubloRequest.requestJson('/admin/board/article/attachment-delete', {
        attachment_id: attachmentId
    }).then(response => {
        if (response.result === 'success') {
            document.getElementById('attachment-' + attachmentId)?.remove();
        } else {
            alert(response.message || '삭제에 실패했습니다.');
        }
    }).catch(err => {
        alert('삭제 중 오류가 발생했습니다.');
        console.error(err);
    });
}
</script>

<?= editor_js() ?>
