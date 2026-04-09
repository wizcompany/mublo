<?php

namespace Mublo\Packages\Shop\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Helper\Form\FormHelper;
use Mublo\Packages\Shop\Service\ExhibitionService;
use Mublo\Packages\Shop\Service\ProductService;

class ExhibitionController
{
    private ExhibitionService $exhibitionService;
    private ProductService $productService;

    public function __construct(
        ExhibitionService $exhibitionService,
        ProductService $productService
    ) {
        $this->exhibitionService = $exhibitionService;
        $this->productService    = $productService;
    }

    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request  = $context->getRequest();
        $page     = (int) ($request->query('page') ?? 1);
        $filters  = array_filter([
            'is_active' => $request->query('is_active') ?? '',
            'keyword'   => $request->query('keyword') ?? '',
        ], fn($v) => $v !== '');

        $result = $this->exhibitionService->getList($domainId, $filters, $page);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Exhibition/List')
            ->withData([
                'pageTitle'  => '기획전 관리',
                'items'      => $result['items'],
                'pagination' => $result['pagination'],
                'filters'    => $filters,
            ]);
    }

    public function create(array $params, Context $context): ViewResponse
    {
        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Exhibition/Form')
            ->withData([
                'pageTitle'  => '기획전 등록',
                'exhibition' => null,
                'items'      => [],
            ]);
    }

    public function edit(array $params, Context $context): ViewResponse
    {
        $request = $context->getRequest();
        $id      = (int) ($params['id'] ?? $params[0] ?? $request->query('id', 0));

        $exhibition = $this->exhibitionService->getDetail($id);
        if (!$exhibition) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Error/404')
                ->withData(['message' => '기획전을 찾을 수 없습니다.']);
        }

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Exhibition/Form')
            ->withData([
                'pageTitle'  => '기획전 수정',
                'exhibition' => $exhibition,
                'items'      => $exhibition['items'] ?? [],
            ]);
    }

    public function store(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request  = $context->getRequest();

        $formData = $request->input('formData') ?? [];
        $data     = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        $exhibitionId = (int) ($data['exhibition_id'] ?? 0);
        $items        = $request->input('items') ?? [];
        unset($data['exhibition_id']);

        if ($exhibitionId) {
            $result = $this->exhibitionService->update($exhibitionId, $data);
        } else {
            $result = $this->exhibitionService->create($domainId, $data);
            if ($result->isSuccess()) {
                $exhibitionId = (int) $result->get('exhibition_id', 0);
            }
        }

        // 상품 연결 동기화
        if ($result->isSuccess() && $exhibitionId && !empty($items)) {
            $this->exhibitionService->syncItems($exhibitionId, $items);
        }

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    public function delete(array $params, Context $context): JsonResponse
    {
        $request      = $context->getRequest();
        $exhibitionId = (int) ($request->json('exhibition_id') ?? 0);

        $result = $this->exhibitionService->delete($exhibitionId);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    public function addItem(array $params, Context $context): JsonResponse
    {
        $request      = $context->getRequest();
        $data         = $request->json();
        $exhibitionId = (int) ($data['exhibition_id'] ?? 0);

        $result = $this->exhibitionService->addItem($exhibitionId, $data);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    public function deleteItem(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $itemId  = (int) ($request->json('item_id') ?? 0);

        $result = $this->exhibitionService->deleteItem($itemId);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    public function syncItems(array $params, Context $context): JsonResponse
    {
        $request      = $context->getRequest();
        $data         = $request->json();
        $exhibitionId = (int) ($data['exhibition_id'] ?? 0);
        $items        = $data['items'] ?? [];

        $result = $this->exhibitionService->syncItems($exhibitionId, $items);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    private function getFormSchema(): array
    {
        return [
            'numeric' => ['exhibition_id', 'is_active', 'sort_order'],
            'date'    => ['start_date', 'end_date'],
        ];
    }
}
