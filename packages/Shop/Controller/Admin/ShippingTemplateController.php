<?php
namespace Mublo\Packages\Shop\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Packages\Shop\Service\ShippingService;
use Mublo\Packages\Shop\Enum\ShippingMethod;
use Mublo\Helper\Form\FormHelper;

/**
 * Admin ShippingTemplateController
 *
 * 배송 템플릿 관리 컨트롤러
 *
 * 라우팅:
 * - GET  /admin/shop/shipping              → index (목록)
 * - GET  /admin/shop/shipping/create       → create (생성 폼)
 * - GET  /admin/shop/shipping/{id}/edit    → edit (수정 폼)
 * - POST /admin/shop/shipping/store        → store (생성/수정)
 * - POST /admin/shop/shipping/{id}/delete  → delete (삭제)
 */
class ShippingTemplateController
{
    private ShippingService $shippingService;

    public function __construct(ShippingService $shippingService)
    {
        $this->shippingService = $shippingService;
    }

    /**
     * 배송 템플릿 목록
     *
     * GET /admin/shop/shipping
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;

        $result = $this->shippingService->getList($domainId);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Shipping/List')
            ->withData([
                'pageTitle' => '배송 템플릿',
                'templates' => $result->get('items', []),
                'shippingMethodOptions' => ShippingMethod::options(),
            ]);
    }

    /**
     * 배송 템플릿 생성 폼
     *
     * GET /admin/shop/shipping/create
     */
    public function create(array $params, Context $context): ViewResponse
    {
        $companiesResult = $this->shippingService->getDeliveryCompanies();

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Shipping/Form')
            ->withData([
                'pageTitle' => '배송 템플릿 등록',
                'isEdit' => false,
                'template' => null,
                'shippingMethodOptions' => ShippingMethod::options(),
                'deliveryCompanies' => $companiesResult->get('companies', []),
            ]);
    }

    /**
     * 배송 템플릿 수정 폼
     *
     * GET /admin/shop/shipping/{id}/edit
     */
    public function edit(array $params, Context $context): ViewResponse
    {
        $request = $context->getRequest();

        $shippingId = (int) ($params['id'] ?? $params[0] ?? $request->query('id', 0));

        if ($shippingId <= 0) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Error/404')
                ->withData(['message' => '배송 템플릿을 찾을 수 없습니다.']);
        }

        $result = $this->shippingService->getTemplate($shippingId);

        if ($result->isFailure()) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Error/404')
                ->withData(['message' => $result->getMessage()]);
        }

        $companiesResult = $this->shippingService->getDeliveryCompanies();

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Shipping/Form')
            ->withData([
                'pageTitle' => '배송 템플릿 수정',
                'isEdit' => true,
                'template' => $result->get('template', []),
                'shippingMethodOptions' => ShippingMethod::options(),
                'deliveryCompanies' => $companiesResult->get('companies', []),
            ]);
    }

    /**
     * 배송 템플릿 저장 (생성/수정)
     *
     * POST /admin/shop/shipping/store
     */
    public function store(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $formData = $request->input('formData') ?? [];
        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        // price_ranges JSON 파싱
        if (!empty($data['price_ranges']) && is_string($data['price_ranges'])) {
            $decoded = json_decode($data['price_ranges'], true);
            $data['price_ranges'] = is_array($decoded) ? $decoded : [];
        }

        $shippingId = (int) ($data['shipping_id'] ?? 0);
        unset($data['shipping_id']);

        if ($shippingId > 0) {
            $result = $this->shippingService->update($shippingId, $data);
        } else {
            $result = $this->shippingService->create($domainId, $data);
        }

        if ($result->isSuccess()) {
            return JsonResponse::success(
                ['redirect' => '/admin/shop/shipping'],
                $result->getMessage()
            );
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 배송 템플릿 삭제
     *
     * POST /admin/shop/shipping/{id}/delete
     */
    public function delete(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();

        $shippingId = (int) ($params['id'] ?? $params[0] ?? $request->json('shipping_id', 0));

        if ($shippingId <= 0) {
            return JsonResponse::error('배송 템플릿 ID가 필요합니다.');
        }

        $result = $this->shippingService->delete($shippingId);

        if ($result->isSuccess()) {
            return JsonResponse::success(null, $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 폼 데이터 스키마
     */
    private function getFormSchema(): array
    {
        return [
            'numeric' => [
                'shipping_id', 'basic_cost', 'free_threshold',
                'goods_per_unit', 'return_cost', 'exchange_cost',
                'delivery_company_id',
            ],
            'enum' => [
                'shipping_method' => [
                    'values' => ['FREE', 'COND', 'PAID', 'QUANTITY', 'AMOUNT'],
                    'default' => 'PAID',
                ],
                'delivery_method' => [
                    'values' => ['COURIER', 'POSTAL', 'PICKUP', 'OWN', 'ETC'],
                    'default' => 'COURIER',
                ],
            ],
            'bool' => [
                'is_active', 'extra_cost_enabled',
            ],
            'required_string' => ['name'],
        ];
    }
}
