<?php

namespace Mublo\Packages\Shop\Controller\Front;

use Mublo\Core\Context\Context;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Core\Response\ViewResponse;
use Mublo\Helper\Form\FormHelper;
use Mublo\Infrastructure\Storage\FileUploader;
use Mublo\Service\Auth\AuthService;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Service\ReviewService;

class ReviewController
{
    private ReviewService $reviewService;
    private OrderRepository $orderRepository;
    private AuthService $authService;
    private FileUploader $fileUploader;

    public function __construct(
        ReviewService $reviewService,
        OrderRepository $orderRepository,
        AuthService $authService,
        FileUploader $fileUploader
    ) {
        $this->reviewService = $reviewService;
        $this->orderRepository = $orderRepository;
        $this->authService = $authService;
        $this->fileUploader = $fileUploader;
    }

    /**
     * 상품별 리뷰 목록 (공개)
     */
    public function list(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $goodsId = (int) ($request->get('goods_id') ?? 0);
        $page = max(1, (int) ($request->get('page') ?? 1));

        $result = $this->reviewService->getByGoodsId($domainId, $goodsId, $page);
        $avgRating = $this->reviewService->getAverageRating($domainId, $goodsId);

        $pagination = $result['pagination'];
        $pagination['pageNums'] = 5;

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Review/List')
            ->withData([
                'items' => $result['items'],
                'pagination' => $pagination,
                'goodsId' => $goodsId,
                'avgRating' => $avgRating,
            ]);
    }

    /**
     * 내 리뷰 목록 (로그인 필수)
     */
    public function myReviews(array $params, Context $context): ViewResponse|RedirectResponse
    {
        if ($this->authService->user() === null) {
            return RedirectResponse::to('/login');
        }

        $domainId = $context->getDomainId() ?? 1;
        $memberId = $this->authService->id() ?? 0;
        $request = $context->getRequest();
        $page = max(1, (int) ($request->get('page') ?? 1));

        $result = $this->reviewService->getList(
            $domainId,
            ['member_id' => $memberId],
            $page,
            10
        );
        $pagination = $result['pagination'];
        $pagination['pageNums'] = 10;

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Review/MyReviews')
            ->withData([
                'items' => $result['items'],
                'pagination' => $pagination,
            ]);
    }

    /**
     * 리뷰 작성 폼 (로그인 필수, 구매확정 주문 검증)
     */
    public function form(array $params, Context $context): ViewResponse|RedirectResponse
    {
        if ($this->authService->user() === null) {
            return RedirectResponse::to('/login');
        }

        $request = $context->getRequest();
        $orderDetailId = (int) ($request->get('order_detail_id') ?? 0);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Review/Form')
            ->withData([
                'orderDetailId' => $orderDetailId,
            ]);
    }

    /**
     * 리뷰 작성 (AJAX, 로그인 필수)
     */
    public function store(array $params, Context $context): JsonResponse
    {
        if ($this->authService->user() === null) {
            return JsonResponse::error('로그인이 필요합니다.', 401);
        }

        $domainId = $context->getDomainId() ?? 1;
        $memberId = $this->authService->id() ?? 0;
        $request = $context->getRequest();

        $formData = $request->input('formData') ?? [];
        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());
        $data['member_id'] = $memberId;

        // 이미지 업로드 처리
        $files = $request->files();
        for ($i = 1; $i <= 3; $i++) {
            $fileKey = "fileData[image{$i}]";
            if (!empty($files[$fileKey]['tmp_name'])) {
                $uploadResult = $this->fileUploader->upload(
                    $files[$fileKey],
                    "shop/review/" . date('Y/m'),
                    ['image/jpeg', 'image/png', 'image/webp', 'image/gif']
                );
                if ($uploadResult) {
                    $data["image{$i}"] = $uploadResult['url'];
                    $data['review_type'] = 'PHOTO';
                }
            }
        }

        // 구매확정 후에만 작성 가능 검증
        $orderDetailId = (int) ($data['order_detail_id'] ?? 0);
        if ($orderDetailId > 0) {
            $existing = $this->reviewService->getByOrderDetailId($orderDetailId);
            if ($existing) {
                return JsonResponse::error('이미 작성된 후기가 있습니다.');
            }
        }

        $result = $this->reviewService->createReview($domainId, $data);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 리뷰 삭제 (본인만)
     */
    public function delete(array $params, Context $context): JsonResponse
    {
        if ($this->authService->user() === null) {
            return JsonResponse::error('로그인이 필요합니다.', 401);
        }

        $memberId = $this->authService->id() ?? 0;
        $request = $context->getRequest();
        $reviewId = (int) ($request->json('review_id') ?? 0);

        $review = $this->reviewService->getDetail($reviewId);
        if (!$review) {
            return JsonResponse::error('후기를 찾을 수 없습니다.');
        }

        if ((int) $review['member_id'] !== $memberId) {
            return JsonResponse::error('삭제 권한이 없습니다.', 403);
        }

        $result = $this->reviewService->deleteReview($reviewId);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    private function getFormSchema(): array
    {
        return [
            'numeric' => ['goods_id', 'order_detail_id', 'rating'],
            'html' => ['content'],
        ];
    }
}
