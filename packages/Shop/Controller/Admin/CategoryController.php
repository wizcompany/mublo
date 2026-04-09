<?php
namespace Mublo\Packages\Shop\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Packages\Shop\Service\CategoryService;
use Mublo\Service\Member\MemberLevelService;

/**
 * Admin CategoryController
 *
 * Core 메뉴 관리와 동일한 패턴:
 * - 탭1: 카테고리 아이템 CRUD (shop_category_items)
 * - 탭2: 카테고리 트리 빌더 (shop_category_tree)
 *
 * 라우팅:
 * - GET  /admin/shop/categories             → index
 * - POST /admin/shop/categories/item-store  → itemStore
 * - GET  /admin/shop/categories/item-view   → itemView
 * - POST /admin/shop/categories/item-delete → itemDelete
 * - POST /admin/shop/categories/tree-update → treeUpdate
 */
class CategoryController
{
    private CategoryService $categoryService;
    private MemberLevelService $levelService;

    public function __construct(CategoryService $categoryService, MemberLevelService $levelService)
    {
        $this->categoryService = $categoryService;
        $this->levelService = $levelService;
    }

    /**
     * 카테고리 관리 메인 (탭 기반)
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $tab = $request->query('tab', 'items');

        // 탭1: 아이템 목록
        $itemsResult = $this->categoryService->getItems($domainId);
        $items = $itemsResult->get('items', []);

        // 탭2: 트리 (계층형)
        $treeHierarchy = $this->categoryService->getTreeHierarchy($domainId);

        // 트리 flat (아이템 정보 포함)
        $treeResult = $this->categoryService->getTree($domainId);
        $flatTree = $treeResult->get('items', []);

        // 트리에 있는 카테고리 코드 목록 (아이템 풀에서 제외 표시용)
        $usedCodes = array_column($flatTree, 'category_code');

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Admin/Category/List')
            ->withData([
                'pageTitle' => '카테고리 관리',
                'activeTab' => $tab,
                'items' => $items,
                'tree' => $treeHierarchy,
                'flatTree' => $flatTree,
                'usedCodes' => $usedCodes,
                'levelOptions' => $this->levelService->getOptionsForSelect(),
            ]);
    }

    /**
     * 카테고리 아이템 저장 (생성/수정)
     */
    public function itemStore(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $categoryId = (int) $request->json('category_id', 0);
        $data = [
            'name' => $request->json('name', ''),
            'description' => $request->json('description', ''),
            'allow_member_level' => (int) $request->json('allow_member_level', 0),
            'allow_coupon' => $request->json('allow_coupon', 1),
            'is_adult' => $request->json('is_adult', 0),
            'is_active' => $request->json('is_active', 1),
        ];

        if ($categoryId > 0) {
            $result = $this->categoryService->updateItem($categoryId, $data);
        } else {
            $result = $this->categoryService->createItem($domainId, $data);
        }

        if ($result->isSuccess()) {
            return JsonResponse::success($result->getData(), $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 카테고리 아이템 조회 (AJAX)
     */
    public function itemView(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $categoryId = (int) $request->query('category_id', 0);

        if ($categoryId <= 0) {
            return JsonResponse::error('카테고리 ID가 필요합니다.');
        }

        $item = $this->categoryService->getItem($categoryId);

        if (!$item) {
            return JsonResponse::error('카테고리를 찾을 수 없습니다.');
        }

        return JsonResponse::success($item);
    }

    /**
     * 카테고리 아이템 삭제
     */
    public function itemDelete(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $categoryId = (int) $request->json('category_id', 0);

        if ($categoryId <= 0) {
            return JsonResponse::error('카테고리 ID가 필요합니다.');
        }

        $result = $this->categoryService->deleteItem($categoryId);

        if ($result->isSuccess()) {
            return JsonResponse::success(null, $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 카테고리 트리 저장
     */
    public function treeUpdate(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $treeData = $request->json('tree', []);

        if (!is_array($treeData)) {
            return JsonResponse::error('잘못된 트리 데이터입니다.');
        }

        $result = $this->categoryService->saveTree($domainId, $treeData);

        if ($result->isSuccess()) {
            return JsonResponse::success(null, $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }
}
