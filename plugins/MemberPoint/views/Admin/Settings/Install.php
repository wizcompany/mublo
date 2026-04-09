<?php
/**
 * MemberPoint 플러그인 설치 페이지
 *
 * @var array $pending 대기 중인 마이그레이션 목록
 */
?>
<div class="admin-content">
    <div class="admin-page-header">
        <h1 class="admin-page-title">포인트 플러그인 설치</h1>
    </div>

    <div class="admin-card">
        <div class="admin-card-body">
            <p>다음 마이그레이션이 실행됩니다:</p>
            <ul>
                <?php foreach ($pending as $migration): ?>
                <li><code><?= htmlspecialchars($migration) ?></code></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn btn-primary" id="installBtn">설치 실행</button>
        </div>
    </div>
</div>
<script>
document.getElementById('installBtn').addEventListener('click', function() {
    MubloRequest.requestJson('/admin/member-point/install', {}, { method: 'POST' })
        .then(function() { location.reload(); });
});
</script>
