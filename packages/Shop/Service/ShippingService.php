<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Packages\Shop\Repository\ShippingRepository;

/**
 * ShippingService
 *
 * 배송 템플릿 비즈니스 로직
 *
 * 책임:
 * - 배송 템플릿 CRUD
 * - 택배사 목록 조회
 *
 * 금지:
 * - Request/Response 직접 처리 (Controller 담당)
 * - DB 직접 접근 (Repository 담당)
 */
class ShippingService
{
    private ShippingRepository $shippingRepository;

    public function __construct(
        ShippingRepository $shippingRepository
    ) {
        $this->shippingRepository = $shippingRepository;
    }

    /**
     * 배송 템플릿 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @return Result 성공 시 items 배열 포함
     */
    public function getList(int $domainId): Result
    {
        $templates = $this->shippingRepository->getList($domainId);

        return Result::success('', [
            'items' => array_map(fn($tpl) => $tpl->toArray(), $templates),
        ]);
    }

    /**
     * 배송 템플릿 상세 조회
     *
     * @param int $shippingId 배송 템플릿 ID
     * @return Result 성공 시 template 데이터 포함
     */
    public function getTemplate(int $shippingId): Result
    {
        $template = $this->shippingRepository->find($shippingId);
        if (!$template) {
            return Result::failure('배송 템플릿을 찾을 수 없습니다.');
        }

        return Result::success('', ['template' => $template->toArray()]);
    }

    /**
     * 배송 템플릿 생성
     *
     * @param int $domainId 도메인 ID
     * @param array $data 배송 템플릿 데이터
     * @return Result 성공 시 shipping_id 포함
     */
    public function create(int $domainId, array $data): Result
    {
        // 필수 필드 검증
        if (empty($data['name'])) {
            return Result::failure('배송 템플릿 이름을 입력해주세요.');
        }

        // 데이터 정규화
        $insertData = $this->normalizeData($data);
        $insertData['domain_id'] = $domainId;

        $shippingId = $this->shippingRepository->create($insertData);
        if (!$shippingId) {
            return Result::failure('배송 템플릿 생성에 실패했습니다.');
        }

        return Result::success('배송 템플릿이 생성되었습니다.', ['shipping_id' => $shippingId]);
    }

    /**
     * 배송 템플릿 수정
     *
     * @param int $shippingId 배송 템플릿 ID
     * @param array $data 수정할 데이터
     * @return Result
     */
    public function update(int $shippingId, array $data): Result
    {
        $template = $this->shippingRepository->find($shippingId);
        if (!$template) {
            return Result::failure('배송 템플릿을 찾을 수 없습니다.');
        }

        $updateData = $this->normalizeData($data);

        $affected = $this->shippingRepository->update($shippingId, $updateData);
        if ($affected === false) {
            return Result::failure('배송 템플릿 수정에 실패했습니다.');
        }

        return Result::success('배송 템플릿이 수정되었습니다.');
    }

    /**
     * 배송 템플릿 삭제
     *
     * @param int $shippingId 배송 템플릿 ID
     * @return Result
     */
    public function delete(int $shippingId): Result
    {
        $template = $this->shippingRepository->find($shippingId);
        if (!$template) {
            return Result::failure('배송 템플릿을 찾을 수 없습니다.');
        }

        $deleted = $this->shippingRepository->delete($shippingId);
        if (!$deleted) {
            return Result::failure('배송 템플릿 삭제에 실패했습니다.');
        }

        return Result::success('배송 템플릿이 삭제되었습니다.');
    }

    /**
     * 택배사 목록 조회
     *
     * @return Result 성공 시 companies 배열 포함
     */
    public function getDeliveryCompanies(): Result
    {
        $companies = $this->shippingRepository->getDeliveryCompanies();

        return Result::success('', ['companies' => $companies]);
    }

    /**
     * 배송 템플릿 데이터 정규화
     *
     * @param array $data 원본 데이터
     * @return array 정규화된 데이터
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        $allowedFields = [
            'name', 'category', 'shipping_method', 'basic_cost',
            'price_ranges', 'free_threshold', 'goods_per_unit', 'extra_cost_enabled',
            'return_cost', 'exchange_cost',
            'shipping_guide', 'delivery_method', 'delivery_company_id',
            'origin_zipcode', 'origin_address1', 'origin_address2',
            'return_zipcode', 'return_address1', 'return_address2',
            'is_active',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                // price_ranges는 JSON 인코딩
                if ($field === 'price_ranges' && is_array($value)) {
                    $value = json_encode($value);
                }

                // 정수 필드
                if (in_array($field, ['basic_cost', 'free_threshold', 'goods_per_unit', 'return_cost', 'exchange_cost', 'delivery_company_id'], true)) {
                    $value = (int) $value;
                }

                // 불린 필드
                if (in_array($field, ['extra_cost_enabled', 'is_active'], true)) {
                    $value = (bool) $value ? 1 : 0;
                }

                $normalized[$field] = $value;
            }
        }

        return $normalized;
    }
}
