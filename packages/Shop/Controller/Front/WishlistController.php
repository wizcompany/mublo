<?php

namespace Mublo\Packages\Shop\Controller\Front;

use Mublo\Core\Context\Context;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Core\Response\ViewResponse;
use Mublo\Service\Auth\AuthService;
use Mublo\Packages\Shop\Service\WishlistService;

class WishlistController
{
    private WishlistService $wishlistService;
    private AuthService $authService;

    public function __construct(
        WishlistService $wishlistService,
        AuthService $authService
    ) {
        $this->wishlistService = $wishlistService;
        $this->authService = $authService;
    }

    /**
     * 찜 토글 (AJAX)
     */
    public function toggle(array $params, Context $context): JsonResponse
    {
        if ($this->authService->user() === null) {
            return JsonResponse::error('로그인이 필요합니다.', 401);
        }

        $request = $context->getRequest();
        $memberId = $this->authService->id() ?? 0;
        $goodsId = (int) ($request->json('goods_id') ?? 0);

        if ($goodsId <= 0) {
            return JsonResponse::error('상품 정보가 없습니다.');
        }

        $result = $this->wishlistService->toggle($memberId, $goodsId);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 찜 목록 페이지
     */
    public function index(array $params, Context $context): ViewResponse|RedirectResponse
    {
        if ($this->authService->user() === null) {
            return RedirectResponse::to('/login');
        }

        $memberId = $this->authService->id() ?? 0;
        $request = $context->getRequest();
        $page = max(1, (int) ($request->get('page') ?? 1));

        $data = $this->wishlistService->getMemberWishlist($memberId, $page);
        $pagination = $data['pagination'];
        $pagination['pageNums'] = 10;

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Wishlist/Index')
            ->withData([
                'items' => $data['items'],
                'pagination' => $pagination,
            ]);
    }
}
