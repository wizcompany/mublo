<?php
/**
 * 쿠폰 목록
 * @var array $coupons 쿠폰 정책 목록
 * @var array $pagination 페이지네이션
 */
?>

<div class="content-header d-flex justify-content-between align-items-center">
    <h2>쿠폰 관리</h2>
    <a href="/admin/shop/coupons/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> 쿠폰 등록
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>쿠폰명</th>
                    <th class="text-center">유형</th>
                    <th class="text-center">할인</th>
                    <th class="text-center">발행기간</th>
                    <th class="text-center">상태</th>
                    <th style="width:100px">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($coupons)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">등록된 쿠폰이 없습니다.</td></tr>
                <?php else: ?>
                    <?php foreach ($coupons as $coupon): ?>
                        <tr>
                            <td><?= $coupon['coupon_group_id'] ?></td>
                            <td><a href="/admin/shop/coupons/edit/<?= $coupon['coupon_group_id'] ?>"><?= htmlspecialchars($coupon['name']) ?></a></td>
                            <td class="text-center"><?= htmlspecialchars($coupon['coupon_type'] ?? '-') ?></td>
                            <td class="text-center">
                                <?php if (($coupon['discount_type'] ?? '') === 'PERCENTAGE'): ?>
                                    <?= $coupon['discount_value'] ?>%
                                <?php else: ?>
                                    <?= number_format($coupon['discount_value'] ?? 0) ?>원
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?= $coupon['issue_start'] ?? '' ?> ~ <?= $coupon['issue_end'] ?? '' ?>
                            </td>
                            <td class="text-center">
                                <?php if ($coupon['is_active'] ?? false): ?>
                                    <span class="badge bg-success">활성</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">비활성</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/admin/shop/coupons/edit/<?= $coupon['coupon_group_id'] ?>" class="btn btn-sm btn-outline-primary">수정</a>
                            </td>
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
