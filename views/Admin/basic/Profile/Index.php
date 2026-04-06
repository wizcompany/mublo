<?php
/**
 * Admin Profile - 내 정보
 *
 * @var string $pageTitle
 * @var array  $user 로그인한 관리자 정보
 */
?>
<div class="page-container">
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle ?? '내 정보') ?></h3>
                <p class="text-muted mb-0">관리자 계정 정보를 확인하고 수정합니다.</p>
            </div>
        </div>
    </div>

    <!-- 기본 정보 -->
    <div class="card mt-4">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
            <i class="bi bi-person-circle me-2 text-pastel-blue"></i>계정 정보
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label text-muted">아이디</label>
                    <p class="form-control-plaintext fw-semibold"><?= htmlspecialchars($user['user_id'] ?? '') ?></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted">회원등급</label>
                    <p class="form-control-plaintext"><?= htmlspecialchars($user['level_name'] ?? '관리자') ?></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted">가입일</label>
                    <p class="form-control-plaintext"><?= htmlspecialchars(substr($user['created_at'] ?? '', 0, 10)) ?></p>
                </div>
                <div class="col-md-6">
                    <label class="form-label text-muted">최근 로그인</label>
                    <p class="form-control-plaintext"><?= htmlspecialchars(substr($user['last_login_at'] ?? '', 0, 16)) ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- 수정 폼 -->
    <form method="post">
        <div class="card mt-4">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
                <i class="bi bi-pencil-square me-2 text-pastel-green"></i>정보 수정
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">닉네임</label>
                        <input type="text" name="formData[nickname]" class="form-control"
                               value="<?= htmlspecialchars($user['nickname'] ?? '') ?>" placeholder="2~20자">
                    </div>
                </div>

                <hr class="my-4">
                <h6 class="mb-3">비밀번호 변경</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">새 비밀번호</label>
                        <input type="password" name="formData[new_password]" class="form-control"
                               placeholder="변경할 경우에만 입력">
                        <small class="text-muted">최소 6자 이상</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">새 비밀번호 확인</label>
                        <input type="password" name="formData[new_password_confirm]" class="form-control"
                               placeholder="새 비밀번호를 다시 입력">
                    </div>
                </div>
            </div>
        </div>

        <div class="sticky-act mt-3 sticky-status">
            <button type="button" class="btn btn-primary mublo-submit"
                    data-target="/admin/profile/update"
                    data-callback="profileSaved">
                <i class="bi bi-check-lg me-1"></i>저장
            </button>
        </div>
    </form>
</div>

<script>
MubloRequest.registerCallback('profileSaved', function(response) {
    MubloRequest.showToast(response.message || '저장되었습니다.', 'success');
});
</script>
