<?php
/**
 * 주문 상세 (FSM 기반 + 아이템별 관리)
 *
 * @var string $pageTitle 페이지 제목
 * @var array $order 주문 정보
 * @var array $orderItems 주문 상품 목록
 * @var array $orderStatusOptions FSM 상태 옵션 [id => label]
 * @var array $availableTransitions 현재 상태에서 이동 가능한 상태 배열
 * @var string $currentStatusLabel 현재 상태 라벨
 * @var array $orderLogs 상태 변경 이력
 * @var array $orderFieldValues 주문 추가 필드 값
 * @var array $orderReturns 반품 정보
 * @var array $refundInfo 환불 정보 [total_paid, total_refunded, refundable]
 * @var array $paymentTransactions 결제/환불 트랜잭션 이력
 * @var array $orderMemos 관리자 메모 목록
 * @var array $memoTypeLabels 메모 유형 라벨
 * @var int $domainId 도메인 ID
 */

$order = $order ?? [];
$orderItems = $orderItems ?? [];
$orderFieldValues = $orderFieldValues ?? [];
$orderLogs = $orderLogs ?? [];
$orderReturns = $orderReturns ?? [];
$availableTransitions = $availableTransitions ?? [];
$orderStatusOptions = $orderStatusOptions ?? [];
$currentStatusLabel = $currentStatusLabel ?? '-';
$refundInfo = $refundInfo ?? [];
$paymentTransactions = $paymentTransactions ?? [];
$orderMemos = $orderMemos ?? [];
$memoTypeLabels = $memoTypeLabels ?? [];
$orderNo = $order['order_no'] ?? '';

// 반품 정보를 detail_id 기준으로 그룹핑
$returnsByDetail = [];
foreach ($orderReturns as $ret) {
    $did = $ret['order_detail_id'] ?? 0;
    $returnsByDetail[$did] = $ret;
}
?>

<div class="content-header d-flex align-items-center justify-content-between mb-3">
    <div>
        <h2 class="mb-0"><?= htmlspecialchars($pageTitle ?? '주문 상세') ?></h2>
        <small class="text-muted">주문일: <?= htmlspecialchars($order['created_at'] ?? '') ?></small>
    </div>
    <a href="/admin/shop/orders" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>목록
    </a>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- 주문 정보 -->
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-receipt me-2 text-pastel-blue"></i>주문 정보</div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-3 text-muted">주문번호</div>
                    <div class="col-9"><strong><?= htmlspecialchars($orderNo) ?></strong></div>
                </div>
                <div class="row mb-2">
                    <div class="col-3 text-muted">주문자</div>
                    <div class="col-9"><?= htmlspecialchars($order['orderer_name'] ?? '') ?> (<?= htmlspecialchars($order['orderer_phone'] ?? '') ?>)</div>
                </div>
                <div class="row mb-2">
                    <div class="col-3 text-muted">결제 수단</div>
                    <div class="col-9"><?= htmlspecialchars($order['payment_gateway'] ?? '') ?> / <?= htmlspecialchars($order['payment_method'] ?? '') ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-3 text-muted">주문 상태</div>
                    <div class="col-9">
                        <span class="badge bg-primary"><?= htmlspecialchars($currentStatusLabel) ?></span>
                    </div>
                </div>
                <?php if (!empty($order['order_memo'])): ?>
                <div class="row mb-2">
                    <div class="col-3 text-muted">주문 메모</div>
                    <div class="col-9"><?= nl2br(htmlspecialchars($order['order_memo'])) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 배송 정보 -->
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-truck me-2 text-pastel-green"></i>배송 정보</div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-3 text-muted">수령인</div>
                    <div class="col-9"><?= htmlspecialchars($order['recipient_name'] ?? '') ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-3 text-muted">연락처</div>
                    <div class="col-9"><?= htmlspecialchars($order['recipient_phone'] ?? '') ?></div>
                </div>
                <div class="row mb-2">
                    <div class="col-3 text-muted">주소</div>
                    <div class="col-9">
                        [<?= htmlspecialchars($order['shipping_zip'] ?? '') ?>]
                        <?= htmlspecialchars($order['shipping_address1'] ?? '') ?>
                        <?= htmlspecialchars($order['shipping_address2'] ?? '') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 주문 상품 (아이템별 관리) -->
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-box-seam me-2 text-pastel-purple"></i>주문 상품</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>상품명</th>
                            <th>옵션</th>
                            <th class="text-end">단가</th>
                            <th class="text-center">수량</th>
                            <th class="text-end">합계</th>
                            <th class="text-center">상태</th>
                            <th class="text-center">반품</th>
                            <th style="width:100px">관리</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderItems as $item): ?>
                        <?php
                            $detailId = (int) ($item['order_detail_id'] ?? 0);
                            $itemStatus = $item['status'] ?? '';
                            $itemStatusLabel = $orderStatusOptions[$itemStatus] ?? $itemStatus ?: '-';
                            $returnType = $item['return_type'] ?? 'NONE';
                            $returnStatus = $item['return_status'] ?? 'NONE';
                            $hasReturn = $returnType !== 'NONE' && $returnType !== '';
                            $isPendingReturn = $returnStatus === 'REQUESTED';
                        ?>
                        <tr>
                            <td>
                                <?= htmlspecialchars($item['goods_name'] ?? '') ?>
                                <?php if (!empty($item['goods_image'])): ?>
                                    <br><img src="<?= htmlspecialchars($item['goods_image']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;margin-top:4px">
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['option_name'] ?? '-') ?></td>
                            <td class="text-end"><?= number_format((int) ($item['goods_price'] ?? 0)) ?>원</td>
                            <td class="text-center"><?= (int) ($item['quantity'] ?? 0) ?></td>
                            <td class="text-end"><strong><?= number_format((int) ($item['total_price'] ?? 0)) ?>원</strong></td>
                            <td class="text-center">
                                <span class="badge bg-primary"><?= htmlspecialchars($itemStatusLabel) ?></span>
                            </td>
                            <td class="text-center">
                                <?php if ($hasReturn): ?>
                                    <?php
                                        $rtLabel = $returnType === 'RETURN' ? '반품' : ($returnType === 'EXCHANGE' ? '교환' : $returnType);
                                        $rsLabel = match($returnStatus) {
                                            'REQUESTED' => '요청',
                                            'COMPLETED' => '완료',
                                            'REFUSED' => '거절',
                                            default => $returnStatus,
                                        };
                                        $rsBg = match($returnStatus) {
                                            'REQUESTED' => 'warning',
                                            'COMPLETED' => 'success',
                                            'REFUSED' => 'danger',
                                            default => 'secondary',
                                        };
                                    ?>
                                    <span class="badge bg-<?= $rsBg ?>"><?= $rtLabel ?>/<?= $rsLabel ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        관리
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="openItemStatusModal(<?= $detailId ?>, '<?= htmlspecialchars($item['goods_name'] ?? '') ?>'); return false;">
                                                상태 변경
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="openCancelModal(<?= $detailId ?>, '<?= htmlspecialchars($item['goods_name'] ?? '') ?>'); return false;">
                                                취소
                                            </a>
                                        </li>
                                        <?php if (!$hasReturn): ?>
                                        <li>
                                            <a class="dropdown-item" href="#" onclick="openReturnModal(<?= $detailId ?>, '<?= htmlspecialchars($item['goods_name'] ?? '') ?>'); return false;">
                                                반품/교환
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($isPendingReturn): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-success" href="#" onclick="processReturn(<?= $detailId ?>, true); return false;">
                                                반품 승인
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="#" onclick="openReturnRefuseModal(<?= $detailId ?>); return false;">
                                                반품 거절
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 주문 추가 정보 (커스텀 필드) -->
        <?php if (!empty($orderFieldValues)): ?>
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-info-circle me-2 text-pastel-sky"></i>주문 추가 정보</div>
            <div class="card-body">
                <?php foreach ($orderFieldValues as $fv): ?>
                <div class="row mb-2">
                    <div class="col-3 text-muted"><?= htmlspecialchars($fv['field_label']) ?></div>
                    <div class="col-9">
                        <?php if ($fv['field_type'] === 'file' && !empty($fv['download_url'])): ?>
                            <a href="<?= htmlspecialchars($fv['download_url']) ?>" target="_blank">
                                <i class="bi bi-file-earmark me-1"></i><?= htmlspecialchars($fv['filename'] ?? '파일') ?>
                            </a>
                        <?php elseif ($fv['field_type'] === 'address'): ?>
                            <?= htmlspecialchars($fv['display_value'] ?? '') ?>
                        <?php elseif ($fv['field_type'] === 'textarea'): ?>
                            <?= nl2br(htmlspecialchars($fv['display_value'] ?? '')) ?>
                        <?php else: ?>
                            <?= htmlspecialchars($fv['display_value'] ?? '') ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- 반품 정보 -->
        <?php if (!empty($orderReturns)): ?>
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-arrow-return-left me-2 text-pastel-orange"></i>반품/교환 내역</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>유형</th>
                            <th>상태</th>
                            <th>사유</th>
                            <th class="text-end">환불금액</th>
                            <th>요청일</th>
                            <th>완료일</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orderReturns as $ret): ?>
                        <?php
                            $rtLabel = ($ret['return_type'] ?? '') === 'RETURN' ? '반품' : (($ret['return_type'] ?? '') === 'EXCHANGE' ? '교환' : ($ret['return_type'] ?? '취소'));
                            $rsLabel = match($ret['return_status'] ?? '') {
                                'REQUESTED' => '요청',
                                'COMPLETED' => '완료',
                                'REFUSED' => '거절',
                                default => $ret['return_status'] ?? '-',
                            };
                            $rsBg = match($ret['return_status'] ?? '') {
                                'REQUESTED' => 'warning',
                                'COMPLETED' => 'success',
                                'REFUSED' => 'danger',
                                default => 'secondary',
                            };
                        ?>
                        <tr>
                            <td><?= $rtLabel ?></td>
                            <td><span class="badge bg-<?= $rsBg ?>"><?= $rsLabel ?></span></td>
                            <td>
                                <?= htmlspecialchars($ret['reason_type'] ?? '') ?>
                                <?php if (!empty($ret['reason_detail'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($ret['reason_detail']) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($ret['refused_reason'])): ?>
                                    <br><small class="text-danger">거절: <?= htmlspecialchars($ret['refused_reason']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format((int) ($ret['refund_amount'] ?? 0)) ?>원</td>
                            <td><?= $ret['requested_at'] ?? $ret['created_at'] ?? '-' ?></td>
                            <td><?= $ret['completed_at'] ?? '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- 결제/환불 내역 -->
        <?php if (!empty($paymentTransactions)): ?>
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-credit-card me-2 text-pastel-blue"></i>결제/환불 내역</div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>유형</th>
                            <th>상태</th>
                            <th>PG</th>
                            <th class="text-end">금액</th>
                            <th>일시</th>
                            <th>비고</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentTransactions as $tx): ?>
                        <?php
                            $txType = $tx['transaction_type'] ?? '';
                            $txTypeLabel = match($txType) {
                                'PAYMENT' => '결제',
                                'CANCEL' => '전액취소',
                                'PARTIAL_CANCEL' => '부분취소',
                                default => $txType,
                            };
                            $txTypeBg = match($txType) {
                                'PAYMENT' => 'success',
                                'CANCEL', 'PARTIAL_CANCEL' => 'danger',
                                default => 'secondary',
                            };
                            $txStatus = $tx['transaction_status'] ?? '';
                            $txStatusLabel = match($txStatus) {
                                'SUCCESS' => '성공',
                                'FAILED' => '실패',
                                'PENDING' => '대기',
                                default => $txStatus,
                            };
                            $txStatusBg = match($txStatus) {
                                'SUCCESS' => 'success',
                                'FAILED' => 'danger',
                                'PENDING' => 'warning',
                                default => 'secondary',
                            };
                            $txAmount = ($txType === 'PAYMENT')
                                ? (int) ($tx['amount'] ?? 0)
                                : (int) ($tx['cancel_amount'] ?? 0);
                        ?>
                        <tr>
                            <td><span class="badge bg-<?= $txTypeBg ?>"><?= $txTypeLabel ?></span></td>
                            <td><span class="badge bg-<?= $txStatusBg ?>"><?= $txStatusLabel ?></span></td>
                            <td><?= htmlspecialchars($tx['pg_provider'] ?? '-') ?></td>
                            <td class="text-end"><?= $txType === 'PAYMENT' ? '' : '-' ?><?= number_format($txAmount) ?>원</td>
                            <td><small><?= $tx['created_at'] ?? '' ?></small></td>
                            <td><small class="text-muted"><?= htmlspecialchars($tx['cancel_reason'] ?? '') ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- 상태 변경 이력 -->
        <?php if (!empty($orderLogs)): ?>
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-clock-history me-2 text-pastel-green"></i>상태 변경 이력</div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($orderLogs as $log): ?>
                    <?php
                        $logType = $log['change_type'] ?? 'STATUS';
                        $logTypeBg = match($logType) {
                            'STATUS' => 'secondary',
                            'PAYMENT' => 'danger',
                            'RETURN' => 'warning',
                            'SHIPPING' => 'info',
                            'SYSTEM' => 'dark',
                            default => 'secondary',
                        };
                    ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-<?= $logTypeBg ?>"><?= htmlspecialchars($logType) ?></span>
                                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($log['prev_status_label'] ?? $log['prev_status'] ?? '') ?></span>
                                <i class="bi bi-arrow-right mx-1"></i>
                                <span class="badge bg-primary"><?= htmlspecialchars($log['new_status_label'] ?? $log['new_status'] ?? '') ?></span>
                                <?php if (!empty($log['reason'])): ?>
                                    <br><small class="text-muted mt-1"><?= htmlspecialchars($log['reason']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="text-end">
                                <small class="text-muted"><?= $log['created_at'] ?? '' ?></small>
                                <?php if (!empty($log['changed_by'])): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($log['changed_by']) ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <!-- 금액 요약 -->
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-currency-dollar me-2 text-pastel-purple"></i>결제 금액</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">상품 합계</span>
                    <span><?= number_format((int) ($order['total_price'] ?? 0)) ?>원</span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">배송비</span>
                    <span><?= number_format((int) ($order['shipping_fee'] ?? 0)) ?>원</span>
                </div>
                <?php if (($order['coupon_discount'] ?? 0) > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">쿠폰 할인</span>
                    <span class="text-danger">-<?= number_format((int) $order['coupon_discount']) ?>원</span>
                </div>
                <?php endif; ?>
                <?php if (($order['point_used'] ?? 0) > 0): ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">포인트 사용</span>
                    <span class="text-danger">-<?= number_format((int) $order['point_used']) ?>원</span>
                </div>
                <?php endif; ?>
                <hr>
                <div class="d-flex justify-content-between">
                    <strong>총 결제 금액</strong>
                    <strong class="text-primary fs-5">
                        <?= number_format(
                            ((int) ($order['total_price'] ?? 0))
                            + ((int) ($order['shipping_fee'] ?? 0))
                            + ((int) ($order['extra_price'] ?? 0))
                            - ((int) ($order['coupon_discount'] ?? 0))
                            - ((int) ($order['point_used'] ?? 0))
                        ) ?>원
                    </strong>
                </div>
            </div>
        </div>

        <!-- 환불 처리 -->
        <?php
            $totalPaid = (int) ($refundInfo['total_paid'] ?? 0);
            $totalRefunded = (int) ($refundInfo['total_refunded'] ?? 0);
            $refundable = (int) ($refundInfo['refundable'] ?? 0);
        ?>
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-cash-coin me-2 text-pastel-sky"></i>환불 처리</div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">총 결제 금액</span>
                    <span><?= number_format($totalPaid) ?>원</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">기환불 금액</span>
                    <span class="text-danger"><?= $totalRefunded > 0 ? '-' : '' ?><?= number_format($totalRefunded) ?>원</span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between mb-2">
                    <strong>환불 가능 금액</strong>
                    <strong class="text-primary"><?= number_format($refundable) ?>원</strong>
                </div>
                <?php if ($refundable > 0): ?>
                <button type="button" class="btn btn-danger w-100" onclick="openRefundModal()">
                    <i class="bi bi-cash-coin me-1"></i>환불 처리
                </button>
                <?php else: ?>
                <p class="text-muted mb-0 text-center"><small>환불 가능 금액이 없습니다.</small></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 주문 상태 변경 (FSM 기반) -->
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold" style="font-size:0.9rem"><i class="bi bi-arrow-repeat me-2 text-pastel-orange"></i>주문 상태 변경</div>
            <div class="card-body">
                <div class="mb-2">
                    <small class="text-muted">현재 상태</small>
                    <div><span class="badge bg-primary"><?= htmlspecialchars($currentStatusLabel) ?></span></div>
                </div>
                <?php if (!empty($availableTransitions)): ?>
                <select id="newOrderStatus" class="form-select mb-2">
                    <option value="">변경할 상태 선택</option>
                    <?php foreach ($availableTransitions as $trans): ?>
                    <option value="<?= htmlspecialchars($trans['id']) ?>"><?= htmlspecialchars($trans['label']) ?></option>
                    <?php endforeach; ?>
                </select>
                <textarea id="statusReason" class="form-control mb-2" rows="2" placeholder="변경 사유 (선택)"></textarea>
                <button type="button" class="btn btn-primary w-100" id="btnChangeStatus">상태 변경</button>
                <?php else: ?>
                <p class="text-muted mb-0">변경 가능한 상태가 없습니다.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- 관리자 메모 -->
        <div class="card mb-3">
            <div class="px-3 pt-3 pb-0 fw-semibold d-flex justify-content-between align-items-center" style="font-size:0.9rem">
                <span><i class="bi bi-journal-text me-2 text-pastel-blue"></i>관리자 메모</span>
                <span class="badge bg-secondary"><?= count($orderMemos) ?></span>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <select id="memoType" class="form-select form-select-sm mb-2">
                        <?php foreach ($memoTypeLabels as $typeKey => $typeLabel): ?>
                        <option value="<?= htmlspecialchars($typeKey) ?>"><?= htmlspecialchars($typeLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <textarea id="memoContent" class="form-control form-control-sm mb-2" rows="3" placeholder="메모 내용을 입력하세요."></textarea>
                    <button type="button" class="btn btn-sm btn-outline-primary w-100" onclick="submitMemo()">
                        <i class="bi bi-plus-lg me-1"></i>메모 추가
                    </button>
                </div>
                <?php if (!empty($orderMemos)): ?>
                <hr class="my-2">
                <div style="max-height:300px;overflow-y:auto">
                    <?php foreach ($orderMemos as $memo): ?>
                    <?php
                        $memoTypeBg = match($memo['memo_type'] ?? 'MEMO') {
                            'CS_CALL' => 'success',
                            'CS_CHAT' => 'info',
                            'CS_EMAIL' => 'primary',
                            'INTERNAL' => 'dark',
                            default => 'secondary',
                        };
                        $memoTypeLabel = $memoTypeLabels[$memo['memo_type'] ?? 'MEMO'] ?? $memo['memo_type'] ?? '메모';
                    ?>
                    <div class="border rounded p-2 mb-2 position-relative">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-<?= $memoTypeBg ?>"><?= htmlspecialchars($memoTypeLabel) ?></span>
                            <button type="button" class="btn btn-sm text-danger p-0" onclick="deleteMemo(<?= (int) ($memo['memo_id'] ?? 0) ?>)" title="삭제">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="small"><?= nl2br(htmlspecialchars($memo['content'] ?? '')) ?></div>
                        <div class="text-muted mt-1" style="font-size:0.75rem">
                            <?= $memo['created_at'] ?? '' ?>
                            <?php if (!empty($memo['staff_id'])): ?>
                                · 담당자 #<?= (int) $memo['staff_id'] ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- 모달 1: 아이템 상태 변경 -->
<div class="modal fade" id="itemStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">상품 상태 변경</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong id="itemStatusName"></strong></p>
                <input type="hidden" id="itemStatusDetailId">
                <div class="mb-3">
                    <label class="form-label">변경 상태</label>
                    <select id="itemStatusSelect" class="form-select">
                        <option value="">선택</option>
                        <?php foreach ($orderStatusOptions as $id => $label): ?>
                        <option value="<?= htmlspecialchars($id) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">변경 사유 (선택)</label>
                    <textarea id="itemStatusReason" class="form-control" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-primary" onclick="submitItemStatus()">변경</button>
            </div>
        </div>
    </div>
</div>

<!-- 모달 2: 아이템 취소 -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">상품 취소</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong id="cancelItemName"></strong></p>
                <input type="hidden" id="cancelDetailId">
                <div class="mb-3">
                    <label class="form-label">취소 사유 <span class="text-danger">*</span></label>
                    <textarea id="cancelReason" class="form-control" rows="3" placeholder="취소 사유를 입력해주세요."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-danger" onclick="submitCancel()">취소 처리</button>
            </div>
        </div>
    </div>
</div>

<!-- 모달 3: 반품/교환 -->
<div class="modal fade" id="returnModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">반품/교환 요청</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2"><strong id="returnItemName"></strong></p>
                <input type="hidden" id="returnDetailId">
                <div class="mb-3">
                    <label class="form-label">유형 <span class="text-danger">*</span></label>
                    <div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="returnType" id="rtReturn" value="RETURN" checked>
                            <label class="form-check-label" for="rtReturn">반품</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="returnType" id="rtExchange" value="EXCHANGE">
                            <label class="form-check-label" for="rtExchange">교환</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">사유 유형</label>
                    <select id="returnReasonType" class="form-select">
                        <option value="">선택</option>
                        <option value="단순변심">단순변심</option>
                        <option value="상품불량">상품불량</option>
                        <option value="오배송">오배송</option>
                        <option value="배송지연">배송지연</option>
                        <option value="기타">기타</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">상세 사유</label>
                    <textarea id="returnReasonDetail" class="form-control" rows="3" placeholder="상세 사유를 입력해주세요."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-warning" onclick="submitReturn()">요청 접수</button>
            </div>
        </div>
    </div>
</div>

<!-- 모달 4: 반품 거절 사유 -->
<div class="modal fade" id="returnRefuseModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">반품 거절</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="refuseDetailId">
                <div class="mb-3">
                    <label class="form-label">거절 사유 <span class="text-danger">*</span></label>
                    <textarea id="refuseReason" class="form-control" rows="3" placeholder="거절 사유를 입력해주세요."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-danger" onclick="submitReturnRefuse()">거절</button>
            </div>
        </div>
    </div>
</div>

<!-- 모달 5: 환불 처리 -->
<div class="modal fade" id="refundModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">환불 처리</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 mb-3">
                    <div class="d-flex justify-content-between">
                        <span>환불 가능 금액</span>
                        <strong id="refundableDisplay"><?= number_format($refundable) ?>원</strong>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">환불 금액 <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="number" id="refundAmount" class="form-control" min="1" max="<?= $refundable ?>" placeholder="0">
                        <span class="input-group-text">원</span>
                        <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('refundAmount').value='<?= $refundable ?>'">전액</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">환불 방법 <span class="text-danger">*</span></label>
                    <select id="refundMethod" class="form-select" onchange="toggleBankInfo()">
                        <option value="">선택</option>
                        <option value="PG_CANCEL">PG 결제 취소 (카드/간편결제)</option>
                        <option value="BANK">무통장 환불</option>
                        <option value="POINT">포인트 환불</option>
                    </select>
                </div>
                <div id="bankInfoArea" style="display:none">
                    <div class="mb-2">
                        <label class="form-label">은행명</label>
                        <input type="text" id="refundBank" class="form-control form-control-sm" placeholder="은행명">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">계좌번호</label>
                        <input type="text" id="refundAccount" class="form-control form-control-sm" placeholder="계좌번호">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">예금주</label>
                        <input type="text" id="refundHolder" class="form-control form-control-sm" placeholder="예금주">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">환불 사유 <span class="text-danger">*</span></label>
                    <textarea id="refundReason" class="form-control" rows="3" placeholder="환불 사유를 입력해주세요."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">닫기</button>
                <button type="button" class="btn btn-danger" onclick="submitRefund()">환불 처리</button>
            </div>
        </div>
    </div>
</div>

<script>
var ORDER_NO = '<?= htmlspecialchars($orderNo) ?>';

// ===== 주문 상태 변경 =====
document.getElementById('btnChangeStatus')?.addEventListener('click', function() {
    var status = document.getElementById('newOrderStatus').value;
    var reason = document.getElementById('statusReason').value;

    if (!status) {
        alert('변경할 상태를 선택해주세요.');
        return;
    }
    if (!confirm('주문 상태를 변경하시겠습니까?')) return;

    MubloRequest.requestJson('/admin/shop/orders/' + ORDER_NO + '/status', {
        order_status: status,
        reason: reason
    }).then(function() {
        location.reload();
    });
});

// ===== 아이템 상태 변경 =====
function openItemStatusModal(detailId, name) {
    document.getElementById('itemStatusDetailId').value = detailId;
    document.getElementById('itemStatusName').textContent = name;
    document.getElementById('itemStatusSelect').value = '';
    document.getElementById('itemStatusReason').value = '';
    new bootstrap.Modal(document.getElementById('itemStatusModal')).show();
}

function submitItemStatus() {
    var detailId = document.getElementById('itemStatusDetailId').value;
    var status = document.getElementById('itemStatusSelect').value;
    var reason = document.getElementById('itemStatusReason').value;

    if (!status) {
        alert('변경할 상태를 선택해주세요.');
        return;
    }

    MubloRequest.requestJson('/admin/shop/orders/' + ORDER_NO + '/items/' + detailId + '/status', {
        order_status: status,
        reason: reason
    }).then(function() {
        location.reload();
    });
}

// ===== 아이템 취소 =====
function openCancelModal(detailId, name) {
    document.getElementById('cancelDetailId').value = detailId;
    document.getElementById('cancelItemName').textContent = name;
    document.getElementById('cancelReason').value = '';
    new bootstrap.Modal(document.getElementById('cancelModal')).show();
}

function submitCancel() {
    var detailId = document.getElementById('cancelDetailId').value;
    var reason = document.getElementById('cancelReason').value;

    if (!reason.trim()) {
        alert('취소 사유를 입력해주세요.');
        return;
    }

    MubloRequest.requestJson('/admin/shop/orders/' + ORDER_NO + '/items/' + detailId + '/cancel', {
        reason: reason
    }).then(function() {
        location.reload();
    });
}

// ===== 반품/교환 =====
function openReturnModal(detailId, name) {
    document.getElementById('returnDetailId').value = detailId;
    document.getElementById('returnItemName').textContent = name;
    document.getElementById('rtReturn').checked = true;
    document.getElementById('returnReasonType').value = '';
    document.getElementById('returnReasonDetail').value = '';
    new bootstrap.Modal(document.getElementById('returnModal')).show();
}

function submitReturn() {
    var detailId = document.getElementById('returnDetailId').value;
    var returnType = document.querySelector('input[name="returnType"]:checked').value;
    var reasonType = document.getElementById('returnReasonType').value;
    var reasonDetail = document.getElementById('returnReasonDetail').value;

    MubloRequest.requestJson('/admin/shop/orders/' + ORDER_NO + '/items/' + detailId + '/return', {
        return_type: returnType,
        reason_type: reasonType,
        reason_detail: reasonDetail
    }).then(function() {
        location.reload();
    });
}

// ===== 반품 승인/거절 =====
function processReturn(detailId, accept) {
    if (!confirm(accept ? '반품을 승인하시겠습니까?' : '반품을 거절하시겠습니까?')) return;

    MubloRequest.requestJson('/admin/shop/orders/' + ORDER_NO + '/items/' + detailId + '/return-process', {
        accept: accept,
        reason: ''
    }).then(function() {
        location.reload();
    });
}

function openReturnRefuseModal(detailId) {
    document.getElementById('refuseDetailId').value = detailId;
    document.getElementById('refuseReason').value = '';
    new bootstrap.Modal(document.getElementById('returnRefuseModal')).show();
}

function submitReturnRefuse() {
    var detailId = document.getElementById('refuseDetailId').value;
    var reason = document.getElementById('refuseReason').value;

    if (!reason.trim()) {
        alert('거절 사유를 입력해주세요.');
        return;
    }

    MubloRequest.requestJson('/admin/shop/orders/' + ORDER_NO + '/items/' + detailId + '/return-process', {
        accept: false,
        reason: reason
    }).then(function() {
        location.reload();
    });
}

// ===== 환불 =====
function openRefundModal() {
    document.getElementById('refundAmount').value = '';
    document.getElementById('refundMethod').value = '';
    document.getElementById('refundReason').value = '';
    document.getElementById('refundBank').value = '';
    document.getElementById('refundAccount').value = '';
    document.getElementById('refundHolder').value = '';
    document.getElementById('bankInfoArea').style.display = 'none';
    new bootstrap.Modal(document.getElementById('refundModal')).show();
}

function toggleBankInfo() {
    var method = document.getElementById('refundMethod').value;
    document.getElementById('bankInfoArea').style.display = method === 'BANK' ? '' : 'none';
}

function submitRefund() {
    var amount = parseInt(document.getElementById('refundAmount').value) || 0;
    var method = document.getElementById('refundMethod').value;
    var reason = document.getElementById('refundReason').value;

    if (amount <= 0) {
        alert('환불 금액을 입력해주세요.');
        return;
    }
    if (!method) {
        alert('환불 방법을 선택해주세요.');
        return;
    }
    if (!reason.trim()) {
        alert('환불 사유를 입력해주세요.');
        return;
    }

    var data = {
        amount: amount,
        refund_method: method,
        reason: reason
    };

    if (method === 'BANK') {
        data.refund_bank = document.getElementById('refundBank').value;
        data.refund_account = document.getElementById('refundAccount').value;
        data.refund_holder = document.getElementById('refundHolder').value;
    }

    if (!confirm(amount.toLocaleString() + '원을 환불하시겠습니까?')) return;

    MubloRequest.requestJson('/admin/shop/orders/' + ORDER_NO + '/refund', data).then(function() {
        location.reload();
    });
}

// ===== 관리자 메모 =====
function submitMemo() {
    var content = document.getElementById('memoContent').value;
    var memoType = document.getElementById('memoType').value;

    if (!content.trim()) {
        alert('메모 내용을 입력해주세요.');
        return;
    }

    MubloRequest.requestJson('/admin/shop/orders/' + ORDER_NO + '/memos', {
        content: content,
        memo_type: memoType
    }).then(function() {
        location.reload();
    });
}

function deleteMemo(memoId) {
    if (!confirm('메모를 삭제하시겠습니까?')) return;

    MubloRequest.requestJson('/admin/shop/orders/' + ORDER_NO + '/memos/' + memoId + '/delete', {}).then(function() {
        location.reload();
    });
}
</script>
