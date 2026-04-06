<?php
/**
 * Mypage - 회원정보수정
 *
 * @var array  $user             로그인한 사용자 정보
 * @var array  $fieldDefinitions 추가 필드 정의
 * @var array  $fieldValues      필드 값 (field_id => field_value)
 * @var array[] $mypageMenus     사이드바 메뉴 목록
 * @var string $currentSection   현재 활성 섹션
 */
?>

<?php ob_start(); ?>
<div class="mypage-profile">
    <div id="profile-message" class="alert" style="display: none;"></div>

    <!-- 기본 정보 -->
    <div class="profile-info">
        <p><strong>아이디</strong> <?= htmlspecialchars($user['user_id'] ?? '') ?></p>
        <p><strong>닉네임</strong> <?= htmlspecialchars($user['nickname'] ?? '') ?></p>
        <p><strong>회원등급</strong> <?= htmlspecialchars($user['level_name'] ?? '일반회원') ?></p>
        <p><strong>가입일</strong> <?= htmlspecialchars(substr($user['created_at'] ?? '', 0, 10)) ?></p>
        <?php if (!empty($user['last_login_at'])): ?>
            <p><strong>최근 로그인</strong> <?= htmlspecialchars(substr($user['last_login_at'], 0, 16)) ?></p>
        <?php endif; ?>
    </div>

    <form id="profile-form" method="POST" action="/mypage/profile">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">

        <h3 class="section-title">기본 정보</h3>
        <div class="form-group">
            <label for="nickname">닉네임</label>
            <input type="text" id="nickname" name="nickname" class="form-control"
                   value="<?= htmlspecialchars($user['nickname'] ?? '') ?>" placeholder="2~20자">
        </div>

        <h3 class="section-title">비밀번호 변경</h3>
        <div class="form-group">
            <label for="new_password">새 비밀번호</label>
            <input type="password" id="new_password" name="new_password" class="form-control"
                   placeholder="변경할 경우에만 입력하세요">
            <div class="form-help">최소 6자 이상</div>
        </div>
        <div class="form-group">
            <label for="new_password_confirm">새 비밀번호 확인</label>
            <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control"
                   placeholder="새 비밀번호를 다시 입력하세요">
        </div>

        <?php if (!empty($fieldDefinitions)): ?>
            <h3 class="section-title">추가 정보</h3>
            <?php foreach ($fieldDefinitions as $field): ?>
                <div class="form-group">
                    <label for="pf_field_<?= $field['field_id'] ?>">
                        <?= htmlspecialchars($field['field_label']) ?>
                    </label>
                    <?= \Mublo\Service\CustomField\CustomFieldRenderer::render($field, $fieldValues[$field['field_id']] ?? '', [
                        'namePrefix'   => 'fields',
                        'idPrefix'     => 'pf_field_',
                        'showExisting' => true,
                    ]) ?>
                    <?php if (!empty($field['field_description'])): ?>
                        <div class="form-help"><?= htmlspecialchars($field['field_description']) ?></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div style="margin-top: 24px;">
            <button type="button" class="btn-primary mublo-submit"
                    data-target="/mypage/profile" data-callback="profileComplete">저장</button>
        </div>
    </form>
</div>

<script>
window.profileComplete = function(response) {
    var msg = document.getElementById('profile-message');
    msg.textContent = response.message || '저장되었습니다.';
    msg.className = 'alert alert-success';
    document.getElementById('new_password').value = '';
    document.getElementById('new_password_confirm').value = '';
    msg.style.display = 'block';
    window.scrollTo(0, 0);
};
</script>
<?= \Mublo\Service\CustomField\CustomFieldRenderer::renderFileScript('/member/upload-field-file') ?>
<?php $content = ob_get_clean(); ?>

<?php include __DIR__ . '/_layout.php'; ?>
