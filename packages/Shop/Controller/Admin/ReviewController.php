<?php

namespace Mublo\Packages\Shop\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Helper\Form\FormHelper;
use Mublo\Helper\Editor\EditorHelper;
use Mublo\Packages\Shop\Service\ReviewService;

class ReviewController
{
    private ReviewService $reviewService;

    public function __construct(ReviewService $reviewService)
    {
        $this->reviewService = $reviewService;
    }

    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $page = (int) ($request->query('page') ?? 1);
        $filters = array_filter([
            'is_visible' => $request->query('is_visible') ?? '',
            'keyword' => $request->query('keyword') ?? '',
        ], fn($v) => $v !== '');

        $result = $this->reviewService->getList($domainId, $filters, $page);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Review/Index')
            ->withData([
                'pageTitle' => '구매후기 관리',
                'items' => $result['items'],
                'pagination' => $result['pagination'],
                'filters' => $filters,
            ]);
    }

    public function create(array $params, Context $context): ViewResponse
    {
        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Review/Form')
            ->withData([
                'pageTitle' => '구매후기 등록',
                'review' => null,
            ]);
    }

    public function edit(array $params, Context $context): ViewResponse
    {
        $request = $context->getRequest();
        $id = (int) ($params['id'] ?? $params[0] ?? $request->query('id', 0));

        $review = $this->reviewService->getDetail($id);
        if (!$review) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Error/404')
                ->withData(['message' => '후기를 찾을 수 없습니다.']);
        }

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Review/Form')
            ->withData([
                'pageTitle' => '구매후기 수정',
                'review' => $review,
            ]);
    }

    public function store(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $formData = $request->input('formData') ?? [];
        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        if (!empty($data['content'])) {
            $storagePath = defined('MUBLO_PUBLIC_STORAGE_PATH') ? MUBLO_PUBLIC_STORAGE_PATH : 'public/storage';
            $data['content'] = EditorHelper::processImages(
                $data['content'],
                'shop/review/' . date('Y/m'),
                $storagePath . '/D' . $domainId,
                '/storage/D' . $domainId
            );
        }

        $reviewId = (int) ($data['review_id'] ?? 0);
        unset($data['review_id']);

        if ($reviewId) {
            $result = $this->reviewService->updateReview($reviewId, $data);
        } else {
            $result = $this->reviewService->createReview($domainId, $data);
        }

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    public function reply(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $data = $request->json();
        $reviewId = (int) ($data['review_id'] ?? 0);
        $replyContent = trim($data['admin_reply'] ?? '');

        if (!$reviewId) {
            return JsonResponse::error('후기 ID가 필요합니다.');
        }

        $result = $this->reviewService->replyToReview($reviewId, $replyContent);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    public function toggleVisibility(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $reviewId = (int) ($request->json('review_id') ?? 0);

        $result = $this->reviewService->toggleVisibility($reviewId);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    public function delete(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $reviewId = (int) ($request->json('review_id') ?? 0);

        $result = $this->reviewService->deleteReview($reviewId);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    public function listModify(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $items = $request->json('items') ?? [];

        if (empty($items)) {
            return JsonResponse::error('수정할 항목이 없습니다.');
        }

        $result = $this->reviewService->batchUpdate($items);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    public function listDelete(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $reviewIds = $request->json('review_ids') ?? [];

        if (empty($reviewIds)) {
            return JsonResponse::error('삭제할 항목이 없습니다.');
        }

        $result = $this->reviewService->batchDelete($reviewIds);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    private function getFormSchema(): array
    {
        return [
            'numeric' => ['review_id', 'rating', 'is_visible', 'is_best'],
            'html' => ['content'],
        ];
    }
}
