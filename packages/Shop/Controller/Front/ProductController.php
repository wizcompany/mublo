<?php

namespace Mublo\Packages\Shop\Controller\Front;

use Mublo\Core\Context\Context;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Registry\CategoryProviderRegistry;
use Mublo\Packages\Shop\Service\ProductService;
use Mublo\Packages\Shop\Service\ShopConfigService;
use Mublo\Packages\Shop\Repository\ReviewRepository;
use Mublo\Packages\Shop\Helper\ProductPresenter;

/**
 * Front 상품 컨트롤러
 *
 * 라우팅:
 * - GET /shop/products        → index (상품 목록)
 * - GET /shop/products/{id}   → view (상품 상세)
 */
class ProductController
{
    private ProductService $productService;
    private CategoryProviderRegistry $categoryRegistry;
    private ShopConfigService $shopConfigService;
    private ReviewRepository $reviewRepository;

    public function __construct(
        ProductService $productService,
        CategoryProviderRegistry $categoryRegistry,
        ShopConfigService $shopConfigService,
        ReviewRepository $reviewRepository
    ) {
        $this->productService = $productService;
        $this->categoryRegistry = $categoryRegistry;
        $this->shopConfigService = $shopConfigService;
        $this->reviewRepository = $reviewRepository;
    }

    /**
     * 상품 목록
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        // 필터 파라미터
        $categoryCode = trim($request->get('category_code') ?? '');
        $keyword = trim($request->get('keyword') ?? '');
        $sort = trim($request->get('sort') ?? 'newest');
        $page = max(1, (int) ($request->get('page') ?? 1));
        $perPage = 12;

        $filters = ['is_active' => true];
        if ($categoryCode !== '') {
            $filters['category_code'] = $categoryCode;
        }
        if ($keyword !== '') {
            $filters['keyword'] = $keyword;
        }
        if ($sort !== '') {
            $filters['sort'] = $sort;
        }

        // 상품 목록 조회
        $result = $this->productService->getList($domainId, $filters, $page, $perPage);

        $items = $result->get('items', []);
        $mainImages = $result->get('mainImages', []);
        $pagination = [
            'totalItems'  => $result->get('totalItems', 0),
            'perPage'     => $result->get('perPage', $perPage),
            'currentPage' => $result->get('currentPage', $page),
            'totalPages'  => $result->get('totalPages', 1),
            'pageNums'    => 10,
        ];

        // 리뷰 통계 배치 조회
        $goodsIds = array_column($items, 'goods_id');
        $reviewStats = !empty($goodsIds)
            ? $this->reviewRepository->getStatsByGoodsIds($domainId, $goodsIds)
            : [];

        // ProductPresenter 적용
        $shopConfig = $this->shopConfigService->getConfig($domainId)->get('config', []);
        $presenter = new ProductPresenter($shopConfig);
        $products = $presenter->toList($items, $mainImages, $reviewStats);

        // 카테고리 트리 (표준 규격: code, label, link, children)
        $categoryTree = $this->categoryRegistry->getTree('shop', $domainId);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Product/List')
            ->withData([
                'products'     => $products,
                'pagination'   => $pagination,
                'categoryTree' => $categoryTree,
                'filters'      => [
                    'category_code' => $categoryCode,
                    'keyword'       => $keyword,
                    'sort'          => $sort,
                ],
            ]);
    }

    /**
     * 상품 목록 AJAX (필터/페이지 변경 시)
     */
    public function list(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $categoryCode = trim($request->get('category_code') ?? '');
        $keyword = trim($request->get('keyword') ?? '');
        $sort = trim($request->get('sort') ?? 'newest');
        $page = max(1, (int) ($request->get('page') ?? 1));
        $perPage = (int) ($request->get('per_page') ?? 12);
        if ($perPage <= 0 || $perPage > 100) {
            $perPage = 12;
        }

        $filters = ['is_active' => true];
        if ($categoryCode !== '') {
            $filters['category_code'] = $categoryCode;
        }
        if ($keyword !== '') {
            $filters['keyword'] = $keyword;
        }
        if ($sort !== '') {
            $filters['sort'] = $sort;
        }

        $result = $this->productService->getList($domainId, $filters, $page, $perPage);

        $items = $result->get('items', []);
        $mainImages = $result->get('mainImages', []);

        $goodsIds = array_column($items, 'goods_id');
        $reviewStats = !empty($goodsIds)
            ? $this->reviewRepository->getStatsByGoodsIds($domainId, $goodsIds)
            : [];

        $shopConfig = $this->shopConfigService->getConfig($domainId)->get('config', []);
        $presenter = new ProductPresenter($shopConfig);
        $products = $presenter->toList($items, $mainImages, $reviewStats);

        return JsonResponse::success([
            'products'   => $products,
            'pagination' => [
                'totalItems'  => $result->get('totalItems', 0),
                'perPage'     => $result->get('perPage', $perPage),
                'currentPage' => $result->get('currentPage', $page),
                'totalPages'  => $result->get('totalPages', 1),
            ],
        ]);
    }

    /**
     * 상품 상세
     */
    public function view(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $goodsId = (int) ($params['id'] ?? $params[0] ?? 0);

        if ($goodsId <= 0) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Product/View')
                ->withStatusCode(404)
                ->withData(['product' => null, 'message' => '상품을 찾을 수 없습니다.']);
        }

        $result = $this->productService->getDetail($goodsId);

        if ($result->isFailure()) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Product/View')
                ->withStatusCode(404)
                ->withData(['product' => null, 'message' => $result->getMessage()]);
        }

        $productData = $result->get('product', []);

        // 조회수 증가
        $this->productService->incrementHit($goodsId);

        // 리뷰 통계
        $reviewStats = $this->reviewRepository->getStatsByGoodsIds($domainId, [$goodsId]);
        $reviewStat = $reviewStats[$goodsId] ?? [];

        // ProductPresenter 적용
        $shopConfig = $this->shopConfigService->getConfig($domainId)->get('config', []);
        $presenter = new ProductPresenter($shopConfig);
        $product = $presenter->toView($productData, $reviewStat);

        $viewTabs = array_filter(array_map('trim', explode(',', $shopConfig['goods_view_tab'] ?? '')));

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Product/View')
            ->withData([
                'product' => $product,
                'viewTabs' => $viewTabs,
            ]);
    }
}
