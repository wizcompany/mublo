<?php
/**
 * 게시판 패키지 설치 안내
 *
 * @var string $pageTitle 페이지 제목
 * @var array $pending 미실행 마이그레이션 파일 목록
 */
$pending = $pending ?? [];
?>

<div class="content-header">
    <h2><?= htmlspecialchars($pageTitle) ?></h2>
</div>

<div class="card">
    <div class="card-body text-center py-5">
        <i class="bi bi-clipboard2-data" style="font-size:3rem; color:#6c757d;"></i>
        <h4 class="mt-3">게시판 패키지 업데이트가 필요합니다</h4>
        <p class="text-muted mb-4">데이터베이스 테이블을 업데이트해야 게시판 기능을 정상적으로 사용할 수 있습니다.</p>

        <?php if (!empty($pending)): ?>
        <div class="mb-4" style="max-width:400px; margin:0 auto;">
            <h6 class="text-start">실행할 마이그레이션:</h6>
            <ul class="list-group list-group-flush text-start">
                <?php foreach ($pending as $file): ?>
                <li class="list-group-item py-1">
                    <i class="bi bi-file-earmark-code me-1 text-primary"></i>
                    <code><?= htmlspecialchars($file) ?></code>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <button type="button" class="btn btn-primary btn-lg" id="btn-install-board">
            <i class="bi bi-download me-2"></i>설치
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var btn = document.getElementById('btn-install-board');
    if (!btn) return;

    btn.addEventListener('click', function () {
        if (!confirm('게시판 패키지를 업데이트하시겠습니까?\n데이터베이스 테이블이 생성/변경됩니다.')) {
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>설치 중...';

        MubloRequest.requestJson('/admin/board/install', {}, { loading: true })
            .then(function () {
                location.href = '/admin/board/config';
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-download me-2"></i>설치';
            });
    });
});
</script>
