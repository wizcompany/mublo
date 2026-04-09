<?php
/**
 * 옵션 프리셋 목록
 * @var array $presets 프리셋 목록
 */
?>

<div class="content-header d-flex justify-content-between align-items-center">
    <h2>옵션 프리셋</h2>
    <a href="/admin/shop/options/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> 프리셋 추가
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>프리셋명</th>
                    <th>설명</th>
                    <th>등록일</th>
                    <th style="width:150px">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($presets)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-muted">등록된 프리셋이 없습니다.</td></tr>
                <?php else: ?>
                    <?php foreach ($presets as $preset): ?>
                        <tr>
                            <td><?= $preset['preset_id'] ?></td>
                            <td><a href="/admin/shop/options/<?= $preset['preset_id'] ?>/edit"><?= htmlspecialchars($preset['name']) ?></a></td>
                            <td><?= htmlspecialchars($preset['description'] ?? '-') ?></td>
                            <td><?= $preset['created_at'] ?? '' ?></td>
                            <td>
                                <a href="/admin/shop/options/<?= $preset['preset_id'] ?>/edit" class="btn btn-sm btn-outline-primary">수정</a>
                                <button class="btn btn-sm btn-outline-danger" onclick="deletePreset(<?= $preset['preset_id'] ?>)">삭제</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function deletePreset(id) {
    if (!confirm('이 프리셋을 삭제하시겠습니까?')) return;
    MubloRequest.requestJson('/admin/shop/options/' + id + '/delete', { preset_id: id })
        .then(() => location.reload());
}
</script>
