<?php
/**
 * Mypage - 포인트 내역
 *
 * @var \Mublo\Entity\Balance\BalanceLog[] $logs       포인트 내역 목록
 * @var array                              $pagination  페이지네이션 (totalItems, perPage, currentPage, totalPages)
 * @var int                                $balance     현재 포인트 잔액
 * @var array[]                            $mypageMenus 사이드바 메뉴 목록
 * @var string                             $currentSection
 */
?>

<?php ob_start(); ?>
<!-- 잔액 카드 -->
<div class="balance-card">
    <div>
        <div class="label">현재 포인트 잔액</div>
        <div class="amount"><?= number_format($balance) ?><small>P</small></div>
    </div>
</div>

<!-- 내역 테이블 -->
<table class="balance-table">
    <thead>
        <tr>
            <th>일시</th>
            <th>내용</th>
            <th>구분</th>
            <th style="text-align:right;">변동</th>
            <th style="text-align:right;">잔액</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($logs)): ?>
            <tr class="empty-row">
                <td colspan="5">포인트 내역이 없습니다.</td>
            </tr>
        <?php else: ?>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars(substr($log->getCreatedAt()->format('Y-m-d H:i'), 0, 16)) ?></td>
                    <td><?= htmlspecialchars($log->getMessage() ?: $log->getAction()) ?></td>
                    <td><?= htmlspecialchars($log->getSourceName()) ?></td>
                    <td style="text-align:right;" class="<?= $log->isAddition() ? 'amount-plus' : 'amount-minus' ?>">
                        <?= $log->isAddition() ? '+' : '' ?><?= number_format($log->getAmount()) ?>P
                    </td>
                    <td style="text-align:right;"><?= number_format($log->getBalanceAfter()) ?>P</td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<!-- 페이지네이션 -->
<?php if ($pagination['totalPages'] > 1): ?>
    <div class="balance-pagination">
        <?php for ($p = 1; $p <= $pagination['totalPages']; $p++): ?>
            <?php if ($p === $pagination['currentPage']): ?>
                <span class="current"><?= $p ?></span>
            <?php else: ?>
                <a href="?page=<?= $p ?>"><?= $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
<?php endif; ?>

<?php $content = ob_get_clean(); ?>

<?php include __DIR__ . '/_layout.php'; ?>
