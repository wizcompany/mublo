<?php
/**
 * Member Profile View
 *
 * 회원 프로필/마이페이지 기본 스킨
 *
 * @var array $user 로그인한 사용자 정보
 * @var array $fieldDefinitions 추가 필드 정의
 * @var array $fieldValues 필드 값 (field_id => field_value)
 */
$this->assets->addCss('/serve/front/view/member/basic/css/member.css');
?>

<div class="member-profile-wrapper">
    <h2>마이페이지</h2>

    <div id="profile-message" class="alert" style="display: none;"></div>

    <div class="profile-info">
        <p><strong>아이디</strong> <?= htmlspecialchars($user['user_id'] ?? '') ?></p>
        <p><strong>닉네임</strong> <?= htmlspecialchars($user['nickname'] ?? '') ?></p>
        <p><strong>회원등급</strong> <?= htmlspecialchars($user['level_name'] ?? '일반회원') ?></p>
        <p><strong>가입일</strong> <?= htmlspecialchars($user['created_at'] ?? '') ?></p>
        <?php if (!empty($user['last_login_at'])): ?>
            <p><strong>최근 로그인</strong> <?= htmlspecialchars($user['last_login_at']) ?></p>
        <?php endif; ?>
    </div>

    <form id="profile-form" method="POST" action="/mypage">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
        <h3 class="section-title">기본 정보</h3>

        <div class="form-group">
            <label for="nickname">닉네임</label>
            <input
                type="text"
                id="nickname"
                name="nickname"
                class="form-control"
                value="<?= htmlspecialchars($user['nickname'] ?? '') ?>"
                placeholder="2~20자"
            >
        </div>

        <h3 class="section-title">비밀번호 변경</h3>

        <div class="form-group">
            <label for="new_password">새 비밀번호</label>
            <input
                type="password"
                id="new_password"
                name="new_password"
                class="form-control"
                placeholder="변경할 경우에만 입력하세요"
            >
            <div class="form-help">최소 6자 이상 입력해주세요</div>
        </div>

        <div class="form-group">
            <label for="new_password_confirm">새 비밀번호 확인</label>
            <input
                type="password"
                id="new_password_confirm"
                name="new_password_confirm"
                class="form-control"
                placeholder="새 비밀번호를 다시 입력하세요"
            >
        </div>

        <?php if (!empty($fieldDefinitions)): ?>
            <h3 class="section-title">추가 정보</h3>

            <?php foreach ($fieldDefinitions as $field): ?>
                <div class="form-group">
                    <label for="pf_field_<?= $field['field_id'] ?>">
                        <?= htmlspecialchars($field['field_label']) ?>
                    </label>

                    <?= \Mublo\Service\CustomField\CustomFieldRenderer::render($field, $fieldValues[$field['field_id']] ?? '', [
                        'namePrefix' => 'fields',
                        'idPrefix' => 'pf_field_',
                        'showExisting' => true,
                    ]) ?>

                    <?php if (!empty($field['field_description'])): ?>
                        <div class="form-help"><?= htmlspecialchars($field['field_description']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="btn-group">
            <button type="button" class="btn mublo-submit" data-target="/mypage" data-callback="profileComplete">저장</button>
            <a href="/member/withdraw" class="btn btn-danger">회원탈퇴</a>
        </div>
    </form>
</div>

<script>
// 프로필 수정 완료 콜백 (MubloRequest의 executeCallback이 window[name] 폴백으로 호출)
window.profileComplete = function(response) {
    var messageDiv = document.getElementById('profile-message');
    if (response.result === 'success') {
        messageDiv.textContent = response.message || '저장되었습니다.';
        messageDiv.className = 'alert alert-success';
        messageDiv.style.display = 'block';
        // 비밀번호 필드 초기화
        document.getElementById('new_password').value = '';
        document.getElementById('new_password_confirm').value = '';
    } else {
        messageDiv.textContent = response.message || '저장에 실패했습니다.';
        messageDiv.className = 'alert alert-danger';
        messageDiv.style.display = 'block';
    }
    window.scrollTo(0, 0);
};


</script>
<?= \Mublo\Service\CustomField\CustomFieldRenderer::renderFileScript('/member/upload-field-file') ?>
