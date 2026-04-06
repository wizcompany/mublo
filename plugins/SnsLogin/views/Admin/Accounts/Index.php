<?php
/**
 * SNS 연동 내역
 *
 * @var array  $accounts   연동 계정 목록 (sa.* + nickname, user_id)
 * @var string $provider   현재 필터 provider
 * @var array  $pagination totalItems, perPage, currentPage, totalPages
 */
$providerMeta = [
    'naver'  => ['label' => '네이버',  'icon' => 'bi-chat-fill',       'color' => '#03C75A'],
    'kakao'  => ['label' => '카카오',  'icon' => 'bi-chat-square-fill', 'color' => '#FEE500'],
    'google' => ['label' => 'Google', 'icon' => 'bi-google',           'color' => '#4285F4'],
];

function buildUrl(string $base, array $params): string {
    $q = http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
    return $q ? $base . '?' . $q : $base;
}

$baseUrl     = '/admin/sns-login/accounts';
$currentPage = $pagination['currentPage'];
$totalPages  = $pagination['totalPages'];
?>
<div class="page-container">

    <!-- 고정 헤더 -->
    <div class="sticky-header">
        <div class="row align-items-end page-navigation">
            <div class="col-sm">
                <h3 class="fs-4 mb-0">SNS 연동 내역</h3>
                <p class="text-muted mb-0">회원의 SNS 연동 현황을 관리합니다.</p>
            </div>
            <div class="col-sm-auto my-2 my-sm-0">
                <a href="/admin/sns-login/settings" class="btn btn-outline-secondary">
                    <i class="bi bi-gear me-1"></i>설정
                </a>
            </div>
        </div>
    </div>

    <!-- 제공자 필터 탭 -->
    <ul class="nav nav-tabs mt-4 mb-3">
        <li class="nav-item">
            <a class="nav-link <?= $provider === '' ? 'active' : '' ?>"
               href="<?= buildUrl($baseUrl, ['provider' => '']) ?>">
                전체
                <?php if ($provider === ''): ?>
                <span class="badge bg-secondary ms-1"><?= number_format($pagination['totalItems']) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php foreach ($providerMeta as $key => $meta): ?>
        <li class="nav-item">
            <a class="nav-link <?= $provider === $key ? 'active' : '' ?>"
               href="<?= buildUrl($baseUrl, ['provider' => $key]) ?>">
                <i class="<?= $meta['icon'] ?>" style="color:<?= $meta['color'] ?>;"></i>
                <?= $meta['label'] ?>
                <?php if ($provider === $key): ?>
                <span class="badge bg-secondary ms-1"><?= number_format($pagination['totalItems']) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endforeach; ?>
    </ul>

    <!-- 목록 테이블 -->
    <div class="card">
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width:100px;">제공자</th>
                        <th>닉네임</th>
                        <th>아이디</th>
                        <th>제공자 이메일</th>
                        <th style="width:160px;">연동일시</th>
                        <th style="width:80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($accounts)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-person-x fs-2 d-block mb-2"></i>
                            연동 내역이 없습니다.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($accounts as $row):
                        $meta = $providerMeta[$row['provider']] ?? ['label' => $row['provider'], 'icon' => 'bi-person-badge', 'color' => '#6c757d'];
                    ?>
                    <tr>
                        <td>
                            <span class="d-flex align-items-center gap-1">
                                <i class="<?= $meta['icon'] ?>" style="color:<?= $meta['color'] ?>;"></i>
                                <small><?= $meta['label'] ?></small>
                            </span>
                        </td>
                        <td>
                            <?php if ($row['nickname']): ?>
                            <a href="/admin/member/edit/<?= (int)$row['member_id'] ?>">
                                <?= htmlspecialchars($row['nickname']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">(탈퇴회원)</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted small"><?= htmlspecialchars($row['user_id'] ?? '') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars($row['provider_email'] ?? '-') ?></td>
                        <td class="text-muted small"><?= htmlspecialchars(substr($row['linked_at'] ?? '', 0, 16)) ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-danger"
                                    onclick="unlinkAccount(<?= (int)$row['id'] ?>)">
                                해제
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 페이지네이션 -->
    <?php if ($totalPages > 1): ?>
    <nav class="mt-3 d-flex justify-content-center">
        <ul class="pagination">
            <?php if ($currentPage > 1): ?>
            <li class="page-item">
                <a class="page-link" href="<?= buildUrl($baseUrl, ['provider' => $provider, 'page' => $currentPage - 1]) ?>">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
            <?php endif; ?>

            <?php
            $start = max(1, $currentPage - 2);
            $end   = min($totalPages, $currentPage + 2);
            for ($p = $start; $p <= $end; $p++):
            ?>
            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                <a class="page-link" href="<?= buildUrl($baseUrl, ['provider' => $provider, 'page' => $p]) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
            <li class="page-item">
                <a class="page-link" href="<?= buildUrl($baseUrl, ['provider' => $provider, 'page' => $currentPage + 1]) ?>">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    <?php endif; ?>

</div>

<script>
function unlinkAccount(id) {
    if (!confirm('이 SNS 연동을 해제하시겠습니까?\n해당 회원은 SNS 로그인을 사용할 수 없게 됩니다.')) return;

    MubloRequest.requestJson('/admin/sns-login/accounts/' + id, {}, { method: 'DELETE' })
        .then(function() { location.reload(); });
}
</script>
