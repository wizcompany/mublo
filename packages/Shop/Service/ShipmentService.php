<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Packages\Shop\Repository\ShipmentRepository;
use Mublo\Packages\Shop\Repository\OrderRepository;

/**
 * ShipmentService
 *
 * 배송 추적 비즈니스 로직
 *
 * shop_shipments 테이블은 주문의 실제 택배 배송 정보를 저장합니다.
 * 주문 상태(order_status)와는 별개로 택배사/송장번호/배송 상태를 관리하며,
 * 택배 추적 URL 생성, 배송 상태 업데이트 등을 담당합니다.
 *
 * 책임:
 * - 송장 등록 및 수정
 * - 배송 상태 업데이트 (READY → PICKED_UP → IN_TRANSIT → DELIVERED)
 * - 주문별 배송 정보 조회
 * - 택배 추적 URL 생성
 */
class ShipmentService
{
    private ShipmentRepository $shipmentRepository;
    private ?OrderRepository $orderRepository;

    /** 허용 배송 상태 전이 맵 */
    private const ALLOWED_TRANSITIONS = [
        'READY'      => ['PICKED_UP', 'FAILED'],
        'PICKED_UP'  => ['IN_TRANSIT', 'FAILED'],
        'IN_TRANSIT' => ['DELIVERED', 'FAILED'],
        'DELIVERED'  => [],
        'FAILED'     => ['READY'],
    ];

    public function __construct(
        ShipmentRepository $shipmentRepository,
        ?OrderRepository $orderRepository = null
    ) {
        $this->shipmentRepository = $shipmentRepository;
        $this->orderRepository    = $orderRepository;
    }

    /**
     * 주문에 송장 등록
     */
    public function registerShipment(string $orderNo, array $data): Result
    {
        if (empty($data['invoice_no'])) {
            return Result::failure('송장번호를 입력해주세요.');
        }

        $insertData = [
            'order_no'         => $orderNo,
            'order_detail_id'  => isset($data['order_detail_id']) ? (int) $data['order_detail_id'] : null,
            'company_id'       => isset($data['company_id']) ? (int) $data['company_id'] : null,
            'invoice_no'       => trim($data['invoice_no']),
            'shipment_status'  => 'READY',
            'admin_memo'       => $data['admin_memo'] ?? null,
        ];

        $shipmentId = $this->shipmentRepository->create($insertData);
        if (!$shipmentId) {
            return Result::failure('송장 등록에 실패했습니다.');
        }

        return Result::success('송장이 등록되었습니다.', ['shipment_id' => $shipmentId]);
    }

    /**
     * 주문별 배송 정보 조회
     */
    public function getByOrderNo(string $orderNo): array
    {
        $shipments = $this->shipmentRepository->getByOrderNo($orderNo);

        return array_map(function (array $s) {
            $s['tracking_url'] = $this->buildTrackingUrl(
                $s['tracking_url_template'] ?? null,
                $s['invoice_no'] ?? ''
            );
            return $s;
        }, $shipments);
    }

    /**
     * 배송 상태 업데이트
     */
    public function updateStatus(int $shipmentId, string $newStatus): Result
    {
        $allowedStatuses = ['READY', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED', 'FAILED'];
        if (!in_array($newStatus, $allowedStatuses, true)) {
            return Result::failure('유효하지 않은 배송 상태입니다.');
        }

        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) {
            return Result::failure('배송 정보를 찾을 수 없습니다.');
        }

        $currentStatus = $shipment['shipment_status'];
        $allowed       = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];
        if (!in_array($newStatus, $allowed, true)) {
            return Result::failure("'{$currentStatus}' 상태에서 '{$newStatus}'으로 변경할 수 없습니다.");
        }

        $this->shipmentRepository->updateStatus($shipmentId, $newStatus);
        return Result::success('배송 상태가 업데이트되었습니다.');
    }

    /**
     * 송장 정보 수정 (송장번호, 택배사, 메모)
     */
    public function updateShipment(int $shipmentId, array $data): Result
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) {
            return Result::failure('배송 정보를 찾을 수 없습니다.');
        }

        $allowed   = ['company_id', 'invoice_no', 'admin_memo'];
        $updateData = array_intersect_key($data, array_flip($allowed));

        if (isset($updateData['invoice_no'])) {
            $updateData['invoice_no'] = trim($updateData['invoice_no']);
            if (empty($updateData['invoice_no'])) {
                return Result::failure('송장번호를 입력해주세요.');
            }
        }

        $this->shipmentRepository->update($shipmentId, $updateData);
        return Result::success('배송 정보가 수정되었습니다.');
    }

    /**
     * 송장 삭제
     */
    public function deleteShipment(int $shipmentId): Result
    {
        $shipment = $this->shipmentRepository->find($shipmentId);
        if (!$shipment) {
            return Result::failure('배송 정보를 찾을 수 없습니다.');
        }

        $this->shipmentRepository->delete($shipmentId);
        return Result::success('배송 정보가 삭제되었습니다.');
    }

    /**
     * 택배 추적 URL 생성
     *
     * tracking_url_template 예시:
     * "https://trace.carrier.com/tracking/{invoice_no}"
     */
    private function buildTrackingUrl(?string $template, string $invoiceNo): ?string
    {
        if (!$template || !$invoiceNo) {
            return null;
        }
        return str_replace('{invoice_no}', urlencode($invoiceNo), $template);
    }
}
