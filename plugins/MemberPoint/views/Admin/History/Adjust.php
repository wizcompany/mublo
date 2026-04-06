<?php
/**
 * MemberPoint Plugin - 관리자 포인트 수동 조정
 *
 * @var string $pageTitle
 * @var array|null $member 회원 정보 (미리 선택된 경우)
 * @var int $currentBalance 현재 잔액
 */
?>
<div class="page-container">
    <!-- 헤더 영역 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><i class="bi bi-plus-circle me-2"></i><?= htmlspecialchars($pageTitle ?? '포인트 수동 조정') ?></h3>
                <p class="text-muted mb-0">회원에게 포인트를 지급하거나 차감합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/member-point/history" class="btn btn-default">
                    <i class="bi bi-arrow-left me-1"></i>목록으로
                </a>
            </div>
        </div>
    </div>

    <!-- 조정 폼 -->
    <div class="card mt-4">
        <div class="card-body">
            <form id="adjustForm">
                <!-- 회원 선택 -->
                <div class="mb-4">
                    <label for="memberSearch" class="form-label fw-bold">회원 선택 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="memberSearch"
                               placeholder="회원 아이디를 입력하세요"
                               value="<?= htmlspecialchars($member['user_id'] ?? '') ?>"
                               autocomplete="off">
                        <input type="hidden" name="formData[member_id]" id="memberId"
                               value="<?= $member['member_id'] ?? '' ?>">
                        <button type="button" class="btn btn-outline-secondary" id="searchMemberBtn">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                    <div id="memberSearchResults" class="list-group position-absolute" style="z-index:1000; max-height:200px; overflow-y:auto; display:none;"></div>
                    <div id="memberInfo" class="mt-2 <?= $member ? '' : 'd-none' ?>">
                        <?php if ($member): ?>
                        <div class="alert alert-info mb-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>
                                    <strong><?= htmlspecialchars($member['user_id']) ?></strong>
                                    (ID: <?= $member['member_id'] ?>)
                                </span>
                                <span>현재 잔액: <strong id="currentBalanceDisplay"><?= number_format($currentBalance) ?></strong> P</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 조정 타입 -->
                <div class="mb-4">
                    <label class="form-label fw-bold">조정 유형 <span class="text-danger">*</span></label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="formData[adjust_type]" id="typeAdd" value="add" checked>
                        <label class="btn btn-outline-primary" for="typeAdd">
                            <i class="bi bi-plus-circle me-1"></i>지급
                        </label>

                        <input type="radio" class="btn-check" name="formData[adjust_type]" id="typeSubtract" value="subtract">
                        <label class="btn btn-outline-danger" for="typeSubtract">
                            <i class="bi bi-dash-circle me-1"></i>차감
                        </label>
                    </div>
                </div>

                <!-- 포인트 금액 -->
                <div class="mb-4">
                    <label for="amount" class="form-label fw-bold">포인트 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" class="form-control form-control-lg" id="amount" name="formData[amount]"
                               min="1" placeholder="0" required>
                        <span class="input-group-text">P</span>
                    </div>
                    <div id="previewBalance" class="form-text"></div>
                </div>

                <!-- 조정 사유 -->
                <div class="mb-4">
                    <label for="message" class="form-label fw-bold">조정 사유 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="message" name="formData[message]"
                           placeholder="예: 이벤트 당첨, 관리자 보정, 잘못된 지급 취소 등" required>
                    <div class="form-text">회원에게 표시되는 메시지입니다.</div>
                </div>

                <!-- 관리자 메모 -->
                <div class="mb-4">
                    <label for="memo" class="form-label fw-bold">관리자 메모</label>
                    <textarea class="form-control" id="memo" name="formData[memo]" rows="2"
                              placeholder="내부 관리용 메모 (선택사항)"></textarea>
                </div>

                <!-- 제출 버튼 -->
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary btn-lg mublo-submit"
                            data-target="/admin/member-point/adjust"
                            data-callback="MubloFormSuccess">
                        <i class="bi bi-check-lg me-1"></i>포인트 조정
                    </button>
                    <a href="/admin/member-point/history" class="btn btn-default btn-lg">취소</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const memberSearch = document.getElementById('memberSearch');
    const memberIdInput = document.getElementById('memberId');
    const searchResults = document.getElementById('memberSearchResults');
    const memberInfo = document.getElementById('memberInfo');
    const amountInput = document.getElementById('amount');
    const previewBalance = document.getElementById('previewBalance');
    let currentBalance = <?= $currentBalance ?>;
    let searchTimeout = null;

    // 회원 검색
    memberSearch.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const keyword = this.value.trim();

        if (keyword.length < 2) {
            searchResults.style.display = 'none';
            return;
        }

        searchTimeout = setTimeout(function() {
            MubloRequest.requestJson('/admin/member-point/search-member?keyword=' + encodeURIComponent(keyword))
                .then(function(response) {
                    if (response.result === 'success' && response.data.members.length > 0) {
                        showSearchResults(response.data.members);
                    } else {
                        searchResults.innerHTML = '<div class="list-group-item text-muted">검색 결과가 없습니다.</div>';
                        searchResults.style.display = 'block';
                    }
                });
        }, 300);
    });

    // 검색 결과 표시
    function showSearchResults(members) {
        searchResults.innerHTML = members.map(function(m) {
            return '<a href="#" class="list-group-item list-group-item-action" data-member-id="' + m.member_id + '" data-user-id="' + m.user_id + '" data-balance="' + m.balance + '">' +
                '<div class="d-flex justify-content-between">' +
                '<span><strong>' + m.user_id + '</strong></span>' +
                '<span class="text-muted">' + Number(m.balance).toLocaleString() + ' P</span>' +
                '</div>' +
                '</a>';
        }).join('');
        searchResults.style.display = 'block';
    }

    // 회원 선택
    searchResults.addEventListener('click', function(e) {
        e.preventDefault();
        const item = e.target.closest('.list-group-item');
        if (!item || !item.dataset.memberId) return;

        memberIdInput.value = item.dataset.memberId;
        memberSearch.value = item.dataset.userId;
        currentBalance = parseInt(item.dataset.balance) || 0;

        memberInfo.innerHTML = '<div class="alert alert-info mb-0 py-2">' +
            '<div class="d-flex justify-content-between align-items-center">' +
            '<span><strong>' + item.dataset.userId + '</strong> (ID: ' + item.dataset.memberId + ')</span>' +
            '<span>현재 잔액: <strong id="currentBalanceDisplay">' + currentBalance.toLocaleString() + '</strong> P</span>' +
            '</div></div>';
        memberInfo.classList.remove('d-none');

        searchResults.style.display = 'none';
        updatePreview();
    });

    // 외부 클릭 시 검색 결과 닫기
    document.addEventListener('click', function(e) {
        if (!searchResults.contains(e.target) && e.target !== memberSearch) {
            searchResults.style.display = 'none';
        }
    });

    // 금액 입력 시 미리보기
    amountInput.addEventListener('input', updatePreview);
    document.querySelectorAll('input[name="formData[adjust_type]"]').forEach(function(radio) {
        radio.addEventListener('change', updatePreview);
    });

    function updatePreview() {
        if (!memberIdInput.value || !amountInput.value) {
            previewBalance.textContent = '';
            return;
        }

        const amount = parseInt(amountInput.value) || 0;
        const isAdd = document.getElementById('typeAdd').checked;
        const newBalance = isAdd ? (currentBalance + amount) : (currentBalance - amount);

        if (!isAdd && newBalance < 0) {
            previewBalance.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-triangle me-1"></i>잔액이 부족합니다. (변경 후: ' + newBalance.toLocaleString() + ' P)</span>';
        } else {
            const changeText = isAdd ? '+' + amount.toLocaleString() : '-' + amount.toLocaleString();
            previewBalance.innerHTML = '<span class="text-' + (isAdd ? 'primary' : 'danger') + '">' + changeText + '</span> &rarr; ' +
                '변경 후 잔액: <strong>' + newBalance.toLocaleString() + ' P</strong>';
        }
    }

    // 폼 제출 성공 콜백
    window.MubloFormSuccess = function(response) {
        if (response.data && response.data.redirect) {
            window.location.href = response.data.redirect;
        }
    };
});
</script>
