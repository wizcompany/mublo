<?php
/**
 * MemberPoint Plugin - 관리자 포인트 상세
 *
 * @var string $pageTitle
 * @var int $id
 * @var array|null $item
 */
?>

<div class="container-fluid">
    <div class="card">
        <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem">
            <i class="bi bi-coin me-2 text-pastel-blue"></i><?= htmlspecialchars($pageTitle) ?> #<?= $id ?>
        </div>
        <div class="card-body">
            <?php if ($item === null): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    포인트 내역을 찾을 수 없습니다.
                </div>
                <p class="text-muted">
                    이 페이지는 MemberPoint 플러그인의 라우트 테스트용 페이지입니다.<br>
                    URL: <code>/admin/member-point/history/<?= $id ?></code>
                </p>
            <?php else: ?>
                <dl class="row">
                    <dt class="col-sm-3">회원</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($item['member_name'] ?? '') ?></dd>

                    <dt class="col-sm-3">포인트</dt>
                    <dd class="col-sm-9"><?= number_format($item['point'] ?? 0) ?></dd>

                    <dt class="col-sm-3">내용</dt>
                    <dd class="col-sm-9"><?= htmlspecialchars($item['content'] ?? '') ?></dd>

                    <dt class="col-sm-3">일시</dt>
                    <dd class="col-sm-9"><?= $item['created_at'] ?? '' ?></dd>
                </dl>
            <?php endif; ?>

            <a href="/admin/member-point/history" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i>목록으로
            </a>
        </div>
    </div>
</div>
