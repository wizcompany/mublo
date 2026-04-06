<?php
namespace Mublo\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Service\Block\BlockPageService;
use Mublo\Service\Block\BlockRowService;
use Mublo\Service\Member\MemberLevelService;
use Mublo\Enum\Block\LayoutType;
use Mublo\Helper\Form\FormHelper;

/**
 * Admin BlockPageController
 *
 * 블록 페이지 관리 컨트롤러
 *
 * 자동 라우팅:
 * - GET  /admin/block-page              → index
 * - GET  /admin/block-page/create       → create
 * - GET  /admin/block-page/edit         → edit (쿼리: ?id=123)
 * - POST /admin/block-page/store        → store
 * - POST /admin/block-page/delete       → delete
 */
class BlockPageController
{
    private BlockPageService $pageService;
    private BlockRowService $rowService;
    private MemberLevelService $levelService;

    public function __construct(
        BlockPageService $pageService,
        BlockRowService $rowService,
        MemberLevelService $levelService
    ) {
        $this->pageService = $pageService;
        $this->rowService = $rowService;
        $this->levelService = $levelService;
    }

    /**
     * 페이지 목록
     *
     * GET /admin/block-page
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        // 페이지네이션
        $page = (int) $request->query('page', 1);
        $perPage = (int) ($context->getDomainInfo()?->getSiteConfig()['per_page'] ?? 20);

        $pagination = $this->pageService->paginate($domainId, $page, $perPage);
        $pages = $pagination['data'];

        // 페이지 데이터 가공
        $pagesData = [];
        foreach ($pages as $pageItem) {
            $pageData = $pageItem->toArray();
            $pageData['row_count'] = count($this->rowService->getRowsByPage($pageItem->getPageId()));
            $pagesData[] = $pageData;
        }

        return ViewResponse::view('blockpage/index')
            ->withData([
                'pageTitle' => '블록 페이지 관리',
                'pages' => $pagesData,
                'pagination' => $pagination,
            ]);
    }

    /**
     * 페이지 생성 폼
     *
     * GET /admin/block-page/create
     */
    public function create(array $params, Context $context): ViewResponse
    {
        return ViewResponse::view('blockpage/form')
            ->withData([
                'pageTitle' => '블록 페이지 추가',
                'isEdit' => false,
                'page' => null,
                'levelOptions' => $this->levelService->getOptionsForSelect(),
                'layoutOptions' => LayoutType::options(),
            ]);
    }

    /**
     * 페이지 수정 폼
     *
     * GET /admin/block-page/edit?id=123
     */
    public function edit(array $params, Context $context): ViewResponse
    {
        $request = $context->getRequest();
        $pageId = (int) $request->query('id', 0);

        if ($pageId === 0 && isset($params[0])) {
            $pageId = (int) $params[0];
        }

        $page = $this->pageService->getPage($pageId);

        if (!$page) {
            return ViewResponse::view('Error/404')
                ->withData(['message' => '페이지를 찾을 수 없습니다.']);
        }

        // 연결된 행 수
        $rowCount = count($this->rowService->getRowsByPage($pageId));

        return ViewResponse::view('blockpage/form')
            ->withData([
                'pageTitle' => '블록 페이지 수정',
                'isEdit' => true,
                'page' => $page->toArray(),
                'rowCount' => $rowCount,
                'levelOptions' => $this->levelService->getOptionsForSelect(),
                'layoutOptions' => LayoutType::options(),
            ]);
    }

    /**
     * 페이지 저장 (생성/수정)
     *
     * POST /admin/block-page/store
     */
    public function store(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $formData = $request->input('formData') ?? [];
        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        $pageId = (int) ($data['page_id'] ?? 0);

        if ($pageId > 0) {
            // 수정
            $result = $this->pageService->updatePage($pageId, $data);
        } else {
            // 생성
            $result = $this->pageService->createPage($domainId, $data);
        }

        if ($result->isSuccess()) {
            $newPageId = $result->get('page_id', $pageId);
            return JsonResponse::success(
                ['redirect' => '/admin/block-page/edit?id=' . $newPageId],
                $result->getMessage()
            );
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 페이지 삭제
     *
     * POST /admin/block-page/delete
     */
    public function delete(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $pageId = (int) $request->json('page_id', 0);

        if ($pageId <= 0) {
            return JsonResponse::error('페이지 ID가 필요합니다.');
        }

        $result = $this->pageService->deletePage($pageId);

        if ($result->isSuccess()) {
            return JsonResponse::success(null, $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 목록 일괄 삭제
     *
     * POST /admin/block-page/list-delete
     */
    public function listDelete(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $chk = $request->input('chk') ?? [];

        if (empty($chk)) {
            return JsonResponse::error('삭제할 항목을 선택해주세요.');
        }

        $deleted = 0;
        $failed = 0;

        foreach ($chk as $pageId) {
            $result = $this->pageService->deletePage((int) $pageId);
            if ($result->isSuccess()) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        if ($deleted > 0) {
            $message = "{$deleted}개 항목이 삭제되었습니다.";
            if ($failed > 0) {
                $message .= " ({$failed}개는 연결된 행이 있어 삭제 불가)";
            }
            return JsonResponse::success(['deleted' => $deleted], $message);
        }

        return JsonResponse::error('삭제할 수 있는 항목이 없습니다.');
    }

    /**
     * 코드 중복 확인
     *
     * POST /admin/block-page/check-code
     */
    public function checkCode(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $code = $request->json('code', '');
        $excludeId = (int) $request->json('exclude_id', 0);

        // 코드 유효성 + 중복 검사 (Service 사용)
        $result = $this->pageService->checkCodeAvailability(
            $domainId,
            $code,
            $excludeId > 0 ? $excludeId : null
        );

        if (!$result['available']) {
            return JsonResponse::error($result['message']);
        }

        return JsonResponse::success(null, $result['message']);
    }

    /**
     * 폼 데이터 스키마
     */
    private function getFormSchema(): array
    {
        return [
            'numeric' => ['page_id', 'layout_type', 'allow_level', 'use_fullpage', 'custom_width', 'sidebar_left_width', 'sidebar_right_width'],
            'bool' => ['use_header', 'use_footer', 'is_active', 'sidebar_left_mobile', 'sidebar_right_mobile'],
            'required_string' => ['page_code', 'page_title'],
        ];
    }
}
