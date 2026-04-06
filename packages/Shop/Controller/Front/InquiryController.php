<?php

namespace Mublo\Packages\Shop\Controller\Front;

use Mublo\Core\Context\Context;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Core\Response\ViewResponse;
use Mublo\Helper\Form\FormHelper;
use Mublo\Service\Auth\AuthService;
use Mublo\Packages\Shop\Service\InquiryService;

class InquiryController
{
    private InquiryService $inquiryService;
    private AuthService $authService;

    public function __construct(
        InquiryService $inquiryService,
        AuthService $authService
    ) {
        $this->inquiryService = $inquiryService;
        $this->authService = $authService;
    }

    /**
     * 상품별 문의 목록 (공개, 비밀글 처리 포함)
     */
    public function list(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $goodsId = (int) ($request->get('goods_id') ?? 0);
        $page = max(1, (int) ($request->get('page') ?? 1));
        $memberId = $this->authService->id() ?? 0;

        $result = $this->inquiryService->getList(
            $domainId,
            ['goods_id' => $goodsId, 'is_visible' => 1],
            $page,
            10
        );

        $pagination = $result['pagination'];
        $pagination['pageNums'] = 5;

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Inquiry/List')
            ->withData([
                'items' => $result['items'],
                'pagination' => $pagination,
                'goodsId' => $goodsId,
                'currentMemberId' => $memberId,
            ]);
    }

    /**
     * 내 문의 목록 (로그인 필수)
     */
    public function myInquiries(array $params, Context $context): ViewResponse|RedirectResponse
    {
        if ($this->authService->user() === null) {
            return RedirectResponse::to('/login');
        }

        $domainId = $context->getDomainId() ?? 1;
        $memberId = $this->authService->id() ?? 0;
        $request = $context->getRequest();
        $page = max(1, (int) ($request->get('page') ?? 1));

        $result = $this->inquiryService->getList(
            $domainId,
            ['member_id' => $memberId],
            $page,
            10
        );

        $pagination = $result['pagination'];
        $pagination['pageNums'] = 10;

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Inquiry/MyInquiries')
            ->withData([
                'items' => $result['items'],
                'pagination' => $pagination,
            ]);
    }

    /**
     * 문의 등록 (AJAX, 로그인 필수)
     */
    public function store(array $params, Context $context): JsonResponse
    {
        if ($this->authService->user() === null) {
            return JsonResponse::error('로그인이 필요합니다.', 401);
        }

        $domainId = $context->getDomainId() ?? 1;
        $memberId = $this->authService->id() ?? 0;
        $user = $this->authService->user();
        $request = $context->getRequest();

        $formData = $request->input('formData') ?? [];
        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());
        $data['member_id'] = $memberId;
        $data['author_name'] = $user['nickname'] ?? ($user['userid'] ?? '');

        $result = $this->inquiryService->createInquiry($domainId, $data);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 문의 삭제 (본인만, 답변 전)
     */
    public function delete(array $params, Context $context): JsonResponse
    {
        if ($this->authService->user() === null) {
            return JsonResponse::error('로그인이 필요합니다.', 401);
        }

        $memberId = $this->authService->id() ?? 0;
        $request = $context->getRequest();
        $inquiryId = (int) ($request->json('inquiry_id') ?? 0);

        $inquiry = $this->inquiryService->getDetail($inquiryId);
        if (!$inquiry) {
            return JsonResponse::error('문의를 찾을 수 없습니다.');
        }

        if ((int) $inquiry['member_id'] !== $memberId) {
            return JsonResponse::error('삭제 권한이 없습니다.', 403);
        }

        if ($inquiry['inquiry_status'] === 'REPLIED') {
            return JsonResponse::error('답변이 완료된 문의는 삭제할 수 없습니다.');
        }

        $result = $this->inquiryService->deleteInquiry($inquiryId);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    private function getFormSchema(): array
    {
        return [
            'numeric' => ['goods_id', 'is_secret'],
            'enum' => [
                'inquiry_type' => ['values' => ['PRODUCT', 'STOCK', 'DELIVERY', 'OTHER'], 'default' => 'PRODUCT'],
            ],
        ];
    }
}
