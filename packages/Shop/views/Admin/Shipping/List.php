<?php
/**
 * 배송 템플릿 목록
 * @var array $templates 배송 템플릿 목록
 * @var array $shippingMethodOptions 배송 방법 옵션
 */
?>

<div class="content-header d-flex justify-content-between align-items-center">
    <h2>배송 템플릿</h2>
    <a href="/admin/shop/shipping/create" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> 템플릿 등록
    </a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th style="width:60px">ID</th>
                    <th>템플릿명</th>
                    <th class="text-center" style="width:120px">배송 방법</th>
                    <th class="text-center" style="width:100px">기본 배송비</th>
                    <th class="text-center" style="width:100px">반품비</th>
                    <th class="text-center" style="width:80px">상태</th>
                    <th style="width:100px">관리</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($templates)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">등록된 배송 템플릿이 없습니다.</td></tr>
                <?php else: ?>
                    <?php foreach ($templates as $tpl): ?>
                        <tr>
                            <td><?= $tpl['shipping_id'] ?></td>
                            <td>
                                <a href="/admin/shop/shipping/<?= $tpl['shipping_id'] ?>/edit">
                                    <?= htmlspecialchars($tpl['name'] ?? '') ?>
                                </a>
                            </td>
                            <td class="text-center">
                                <?= htmlspecialchars($shippingMethodOptions[$tpl['shipping_method'] ?? ''] ?? $tpl['shipping_method'] ?? '-') ?>
                            </td>
                            <td class="text-center"><?= number_format($tpl['basic_cost'] ?? 0) ?>원</td>
                            <td class="text-center"><?= number_format($tpl['return_cost'] ?? 0) ?>원</td>
                            <td class="text-center">
                                <?php if ($tpl['is_active'] ?? false): ?>
                                    <span class="badge bg-success">활성</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">비활성</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/admin/shop/shipping/<?= $tpl['shipping_id'] ?>/edit" class="btn btn-sm btn-outline-primary">수정</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
