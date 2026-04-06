<?php
/**
 * 주문 목록 (FSM 기반)
 *
 * @var array $orders 주문 목록
 * @var array $pagination 페이지네이션
 * @var array $filters 필터
 * @var array $orderStatusOptions FSM 상태 옵션 [id => label]
 */

$orderStatusOptions = $orderStatusOptions ?? [];
?>

<div class="content-header">
    <h2>주문 관리</h2>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-auto">
                <select name="order_status" class="form-select form-select-sm">
                    <option value="">전체 상태</option>
                    <?php foreach ($orderStatusOptions as $val => $lbl): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= ($filters['order_status'] ?? '') === $val ? 'selected' : '' ?>><?= htmlspecialchars($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <input type="date" name="date_from" class="form-control form-control-sm" value="<?= $filters['date_from'] ?? '' ?>">
            </div>
            <div class="col-auto">~</div>
            <div class="col-auto">
                <input type="date" name="date_to" class="form-control form-control-sm" value="<?= $filters['date_to'] ?? '' ?>">
            </div>
            <div class="col-auto">
                <input type="text" name="keyword" class="form-control form-control-sm" placeholder="주문번호/수령인" value="<?= htmlspecialchars($filters['keyword'] ?? '') ?>">
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">검색</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>주문번호</th>
                    <th>주문자</th>
                    <th>수령인</th>
                    <th class="text-end">결제금액</th>
                    <th class="text-center">결제수단</th>
                    <th class="text-center">상태</th>
                    <th>주문일</th>
                    <th style="width:80px">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8" class="text-center py-4 text-muted">주문 내역이 없습니다.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                            $rawStatus = $order['order_status'] ?? '';
                            $statusLabel = $orderStatusOptions[$rawStatus] ?? $rawStatus ?: '-';
                        ?>
                        <tr>
                            <td><a href="/admin/shop/orders/<?= htmlspecialchars($order['order_no']) ?>"><?= htmlspecialchars($order['order_no']) ?></a></td>
                            <td><?= $order['member_id'] ?: '비회원' ?></td>
                            <td><?= htmlspecialchars($order['recipient_name'] ?? '-') ?></td>
                            <td class="text-end"><?= number_format($order['total_price'] ?? 0) ?>원</td>
                            <td class="text-center"><?= htmlspecialchars($order['payment_method'] ?? '-') ?></td>
                            <td class="text-center">
                                <span class="badge bg-primary">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            </td>
                            <td><?= $order['created_at'] ?? '' ?></td>
                            <td><a href="/admin/shop/orders/<?= htmlspecialchars($order['order_no']) ?>" class="btn btn-sm btn-outline-primary">상세</a></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($pagination) && $pagination['totalPages'] > 1): ?>
    <?php $this->component('pagination', $pagination) ?>
<?php endif; ?>
