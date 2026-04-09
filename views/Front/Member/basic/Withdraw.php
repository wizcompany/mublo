<?php
/**
 * Member Withdraw View
 *
 * 회원 탈퇴 기본 스킨
 */
$this->assets->addCss('/serve/front/view/member/basic/css/member.css');
?>

<div class="member-withdraw-wrapper">
    <h2>회원 탈퇴</h2>

    <div id="withdraw-message" class="alert alert-danger" style="display: none;"></div>

    <div class="warning-box">
        <h4>회원 탈퇴 시 유의사항</h4>
        <ul>
            <li>탈퇴 시 모든 회원 정보가 삭제됩니다.</li>
            <li>작성한 게시글 및 댓글은 삭제되지 않습니다.</li>
            <li>탈퇴 후 동일한 아이디로 재가입이 불가능할 수 있습니다.</li>
            <li>탈퇴 후 데이터 복구가 불가능합니다.</li>
        </ul>
    </div>

    <form id="withdraw-form" name="frm">
        <div class="form-group">
            <label for="password">비밀번호 확인</label>
            <input
                type="password"
                id="password"
                name="formData[password]"
                class="form-control"
                placeholder="현재 비밀번호를 입력하세요"
                required
            >
        </div>

        <div class="checkbox-group">
            <input type="checkbox" id="confirm" name="confirm">
            <label for="confirm">위 내용을 확인했으며, 회원 탈퇴에 동의합니다.</label>
        </div>

        <div class="btn-group">
            <a href="/mypage" class="btn btn-secondary">취소</a>
            <button type="button" class="btn btn-danger" id="btn-withdraw">탈퇴하기</button>
        </div>
    </form>
</div>

<script>
// 탈퇴 완료 콜백 (MubloRequest의 executeCallback이 window[name] 폴백으로 호출)
window.withdrawComplete = function(response) {
    var messageDiv = document.getElementById('withdraw-message');
    if (response.result === 'success') {
        alert(response.message || '회원 탈퇴가 완료되었습니다.');
        location.href = (response.data && response.data.redirect) || '/';
    } else {
        messageDiv.textContent = response.message || '탈퇴 처리에 실패했습니다.';
        messageDiv.style.display = 'block';
    }
};

// 탈퇴 버튼 클릭: 검증 후 mublo-submit 실행
document.getElementById('btn-withdraw').addEventListener('click', function() {
    var messageDiv = document.getElementById('withdraw-message');
    var password = document.getElementById('password').value;

    if (!password) {
        messageDiv.textContent = '비밀번호를 입력해주세요.';
        messageDiv.style.display = 'block';
        return;
    }

    if (!document.getElementById('confirm').checked) {
        messageDiv.textContent = '회원 탈퇴에 동의해주세요.';
        messageDiv.style.display = 'block';
        return;
    }

    if (!confirm('정말로 탈퇴하시겠습니까? 이 작업은 되돌릴 수 없습니다.')) {
        return;
    }

    messageDiv.style.display = 'none';

    var form = document.getElementById('withdraw-form');

    MubloRequest.sendRequest({
        method: 'POST',
        url: '/member/withdraw',
        payloadType: MubloRequest.PayloadType.FORM,
        data: new FormData(form)
    })
    .then(function(data) { window.withdrawComplete(data); });
});
</script>
