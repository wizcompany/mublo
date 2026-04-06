<?php
/**
 * @var string $pageTitle
 * @var string $installUrl
 */
?>
<div class="page-container">
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0"><?= htmlspecialchars($pageTitle) ?></h3>
            </div>
        </div>
    </div>

    <div class="mt-5 text-center">
        <i class="bi bi-clipboard-check fs-1 text-primary d-block mb-3"></i>
        <h5>설문조사 플러그인 설치가 필요합니다.</h5>
        <p class="text-muted">아래 버튼을 클릭하면 필요한 테이블이 생성됩니다.</p>
        <button type="button" class="btn btn-primary" id="btn-install">
            <i class="bi bi-download me-1"></i>지금 설치하기
        </button>
    </div>
</div>
<script>
document.getElementById('btn-install').addEventListener('click', function () {
    this.disabled = true;
    this.textContent = '설치 중...';
    MubloRequest.requestJson('<?= $installUrl ?>', {}, { method: 'POST' })
        .then(() => { location.reload(); });
});
</script>
