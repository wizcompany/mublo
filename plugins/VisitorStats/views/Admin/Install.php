<?php
$pending = $pending ?? [];
?>

<div class="content-header">
    <h2><?= htmlspecialchars($pageTitle ?? '플러그인 설치', ENT_QUOTES, 'UTF-8') ?></h2>
</div>

<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-bar-chart-line" style="font-size:3rem; color:#6c757d;"></i>
        <h4 class="mt-3">방문자 통계 플러그인 설치가 필요합니다</h4>
        <p class="text-muted mb-4">통계 데이터 저장을 위한 테이블을 먼저 생성해야 합니다.</p>

        <?php if (!empty($pending)): ?>
        <div class="mb-4" style="max-width:500px; margin:0 auto;">
            <h6 class="text-start">실행할 마이그레이션:</h6>
            <ul class="list-group list-group-flush text-start">
                <?php foreach ($pending as $file): ?>
                <li class="list-group-item py-1">
                    <i class="bi bi-file-earmark-code me-1 text-primary"></i>
                    <code><?= htmlspecialchars($file, ENT_QUOTES, 'UTF-8') ?></code>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <button type="button" class="btn btn-primary btn-lg" id="btn-install-visitorstats">
            <i class="bi bi-download me-2"></i>설치
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btn-install-visitorstats');
    if (!btn) return;

    btn.addEventListener('click', function () {
        if (!confirm('방문자 통계 플러그인을 설치하시겠습니까?\n데이터베이스 테이블이 생성됩니다.')) {
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>설치 중...';

        MubloRequest.requestJson('/admin/visitor-stats/install', {}, { method: 'POST', loading: true })
            .then(function () {
                location.href = '/admin/visitor-stats/dashboard';
            })
            .catch(function (err) {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-download me-2"></i>설치';
            });
    });
});
</script>
