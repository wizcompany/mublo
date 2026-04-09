<?php
namespace Mublo\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Core\Block\BlockRegistry;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Enum\Block\BlockPosition;
use Mublo\Service\Block\BlockRowService;
use Mublo\Service\Block\BlockColumnService;
use Mublo\Service\Block\BlockPageService;
use Mublo\Service\Block\BlockSkinService;
use Mublo\Service\Block\BlockPreviewService;
use Mublo\Service\Menu\MenuService;
use Mublo\Service\Auth\AuthService;
use Mublo\Infrastructure\Storage\UploadedFile;
use Mublo\Infrastructure\Storage\FileUploader;
use Mublo\Helper\Form\FormHelper;

/**
 * Admin BlockRowController
 *
 * 블록 행 관리 컨트롤러
 *
 * 자동 라우팅:
 * - GET  /admin/block-row              → index
 * - GET  /admin/block-row/create       → create
 * - GET  /admin/block-row/edit         → edit (쿼리: ?id=123)
 * - POST /admin/block-row/store        → store
 * - POST /admin/block-row/delete       → delete
 * - POST /admin/block-row/order-update → orderUpdate
 * - GET  /admin/block-row/get-columns  → getColumns (AJAX)
 */
class BlockRowController
{
    private BlockRowService $rowService;
    private BlockColumnService $columnService;
    private BlockPageService $pageService;
    private BlockSkinService $skinService;
    private BlockPreviewService $previewService;
    private MenuService $menuService;
    private \Mublo\Infrastructure\Database\Database $db;
    private DependencyContainer $container;

    public function __construct(
        BlockRowService $rowService,
        BlockColumnService $columnService,
        BlockPageService $pageService,
        BlockSkinService $skinService,
        BlockPreviewService $previewService,
        MenuService $menuService,
        \Mublo\Infrastructure\Database\Database $db,
        DependencyContainer $container
    ) {
        $this->rowService = $rowService;
        $this->columnService = $columnService;
        $this->pageService = $pageService;
        $this->skinService = $skinService;
        $this->previewService = $previewService;
        $this->menuService = $menuService;
        $this->db = $db;
        $this->container = $container;
    }

    /**
     * 행 목록
     *
     * GET /admin/block-row
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        // 필터
        $position = $request->query('position');
        $pageId = (int) $request->query('page_id', 0);

        // 페이지네이션
        $page = (int) $request->query('page', 1);
        $perPage = (int) ($context->getDomainInfo()?->getSiteConfig()['per_page'] ?? 20);

        if ($pageId > 0) {
            // 페이지 기반 행 조회
            $rows = $this->rowService->getRowsByPage($pageId);
            $pagination = null;
        } else {
            // 위치 기반 행 조회
            $pagination = $this->rowService->paginateByPosition(
                $domainId,
                $position ?: null,
                $page,
                $perPage
            );
            $rows = $pagination['data'];
        }

        // 행 데이터 가공
        $rowsData = [];
        foreach ($rows as $row) {
            $rowData = $row->toArray();
            $columns = $this->columnService->getColumnsByRow($row->getRowId());
            $rowData['column_count_actual'] = count($columns);
            // 각 칸의 콘텐츠 유무 배열 (true: 콘텐츠 있음, false: 없음)
            $rowData['column_has_content'] = array_map(
                fn($col) => $col->hasContent(),
                $columns
            );
            $rowsData[] = $rowData;
        }

        return ViewResponse::view('blockrow/index')
            ->withData([
                'pageTitle' => '블록 행 관리',
                'rows' => $rowsData,
                'pagination' => $pagination,
                'positions' => $this->rowService->getValidPositions(),
                'pages' => $this->pageService->getSelectOptions($domainId),
                'currentPosition' => $position,
                'currentPageId' => $pageId,
            ]);
    }

    /**
     * 행 생성 폼
     *
     * GET /admin/block-row/create
     */
    public function create(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        // 기본 위치 또는 페이지 ID
        $position = $request->query('position', BlockPosition::INDEX->value);
        $pageId = (int) $request->query('page_id', 0);
        $pages = $this->pageService->getSelectOptions($domainId);

        $isPageBased = $pageId > 0;
        $currentPageLabel = $this->findPageLabel($pages, $pageId);

        return ViewResponse::view('blockrow/form')
            ->withData([
                'pageTitle' => '블록 행 추가',
                'isEdit' => false,
                'rowData' => [],
                'rowId' => 0,
                'position' => $position,
                'pageId' => $pageId,
                'columnCount' => 1,
                'isPageBased' => $isPageBased,
                'currentPageLabel' => $currentPageLabel,
                'bgConfig' => [],
                'columns' => [],
                'positions' => $this->rowService->getValidPositions(),
                'pages' => $pages,
                'contentTypes' => BlockRegistry::getContentTypeOptions(),
                'contentTypeGroups' => BlockRegistry::getContentTypesGroupedByKind(),
                'skinLists' => $this->skinService->getAllSkinLists(),
                'menuOptions' => $this->buildMenuOptions($domainId),
                'domainId' => $domainId,
            ]);
    }

    /**
     * 행 수정 폼
     *
     * GET /admin/block-row/edit?id=123
     */
    public function edit(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $rowId = (int) $request->query('id', 0);

        if ($rowId === 0 && isset($params[0])) {
            $rowId = (int) $params[0];
        }

        $row = $this->rowService->getRow($rowId);

        if (!$row) {
            return ViewResponse::view('Error/404')
                ->withData(['message' => '행을 찾을 수 없습니다.']);
        }

        // 도메인 소유권 검증
        if ($row->getDomainId() !== $domainId) {
            return ViewResponse::view('Error/404')
                ->withData(['message' => '행을 찾을 수 없습니다.']);
        }

        // 칸 목록
        $columns = $this->columnService->getColumnsByRow($rowId);
        $columnsData = array_map(fn($col) => $col->toArray(), $columns);

        // 폼 데이터 준비
        $data = $row->toArray();
        $position = $data['position'] ?? $row->getPosition();
        $pageId = (int) ($data['page_id'] ?? $row->getPageId());
        $columnCount = (int) ($data['column_count'] ?? 1);
        $pages = $this->pageService->getSelectOptions($domainId);

        $isPageBased = $pageId > 0;
        $currentPageLabel = $this->findPageLabel($pages, $pageId);

        // 배경 설정 파싱
        $bgConfig = $data['background_config'] ?? [];
        if (is_string($bgConfig)) {
            $bgConfig = json_decode($bgConfig, true) ?? [];
        }

        return ViewResponse::view('blockrow/form')
            ->withData([
                'pageTitle' => '블록 행 수정',
                'isEdit' => true,
                'rowData' => $data,
                'rowId' => $rowId,
                'position' => $position,
                'pageId' => $pageId,
                'columnCount' => $columnCount,
                'isPageBased' => $isPageBased,
                'currentPageLabel' => $currentPageLabel,
                'bgConfig' => $bgConfig,
                'columns' => $columnsData,
                'positions' => $this->rowService->getValidPositions(),
                'pages' => $pages,
                'contentTypes' => BlockRegistry::getContentTypeOptions(),
                'contentTypeGroups' => BlockRegistry::getContentTypesGroupedByKind(),
                'skinLists' => $this->skinService->getAllSkinLists(),
                'menuOptions' => $this->buildMenuOptions($domainId),
                'domainId' => $domainId,
            ]);
    }

    /**
     * 행 저장 (생성/수정)
     *
     * POST /admin/block-row/store
     */
    public function store(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $formData = $request->input('formData') ?? [];

        // 배경 설정 필드를 background_config JSON으로 통합
        $formData = $this->processBackgroundConfig($formData, $domainId, $request);

        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        // 칸 데이터 처리
        $columnsData = $this->parseColumnsData($request->input('columns') ?? [], $domainId, $request);

        // Include 블록 슈퍼관리자 전용 제한
        $includeError = $this->validateIncludePermission($columnsData, $context);
        if ($includeError) {
            return $includeError;
        }

        $rowId = (int) ($data['row_id'] ?? 0);

        if ($rowId > 0) {
            // 수정 (도메인 소유권 검증 포함)
            $result = $this->rowService->updateRow($rowId, $data, $columnsData, $domainId);
        } else {
            // 생성
            $result = $this->rowService->createRow($domainId, $data, $columnsData);
        }

        if ($result->isSuccess()) {
            // 페이지 기반이면 해당 페이지의 행 목록으로, 아니면 전체 목록으로
            $pageId = (int) ($data['page_id'] ?? 0);
            $redirect = $pageId > 0
                ? "/admin/block-row?page_id={$pageId}"
                : '/admin/block-row';


            return JsonResponse::success(
                ['redirect' => $redirect],
                $result->getMessage()
            );
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 행 삭제
     *
     * POST /admin/block-row/delete
     */
    public function delete(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $rowId = (int) $request->json('row_id', 0);

        if ($rowId <= 0) {
            return JsonResponse::error('행 ID가 필요합니다.');
        }

        $result = $this->rowService->deleteRow($rowId, $domainId);

        if ($result->isSuccess()) {
            return JsonResponse::success(null, $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 사용 여부 토글
     *
     * POST /admin/block-row/toggle-active
     */
    public function toggleActive(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $rowId = (int) $request->json('row_id', 0);

        if ($rowId <= 0) {
            return JsonResponse::error('행 ID가 필요합니다.');
        }

        $row = $this->rowService->getRow($rowId);

        if (!$row || $row->getDomainId() !== $domainId) {
            return JsonResponse::error('행을 찾을 수 없습니다.');
        }

        $newActive = !$row->isActive();
        $result = $this->rowService->updateRow($rowId, ['is_active' => $newActive], [], $domainId);

        if ($result->isSuccess()) {
            return JsonResponse::success(
                ['is_active' => $newActive],
                $newActive ? '사용으로 변경되었습니다.' : '미사용으로 변경되었습니다.'
            );
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 정렬 순서 변경
     *
     * POST /admin/block-row/order-update
     */
    public function orderUpdate(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $rowIds = $request->json('row_ids', []);

        if (empty($rowIds) || !is_array($rowIds)) {
            return JsonResponse::error('정렬할 행 목록이 필요합니다.');
        }

        $result = $this->rowService->updateOrder($rowIds, $domainId);

        if ($result->isSuccess()) {
            return JsonResponse::success(null, $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 정렬 순서 직접 설정
     *
     * POST /admin/block-row/order-set
     */
    public function orderSet(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $orders = $request->json('orders', []);

        if (empty($orders) || !is_array($orders)) {
            return JsonResponse::error('순서 정보가 필요합니다.');
        }

        $result = $this->rowService->setOrder($orders, $domainId);

        if ($result->isSuccess()) {
            return JsonResponse::success(null, $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 칸 목록 조회 (AJAX)
     *
     * GET /admin/block-row/get-columns?row_id=123
     */
    public function getColumns(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $rowId = (int) $request->query('row_id', 0);

        if ($rowId <= 0) {
            return JsonResponse::error('행 ID가 필요합니다.');
        }

        $columns = $this->columnService->getColumnsByRow($rowId);
        $columnsData = array_map(fn($col) => $col->toArray(), $columns);

        return JsonResponse::success([
            'columns' => $columnsData,
        ]);
    }

    /**
     * 콘텐츠 타입별 아이템 목록 조회 (AJAX)
     *
     * GET /admin/block-row/get-content-items?content_type=board
     *
     * @return JsonResponse { items: [{id: string, label: string}, ...] }
     */
    public function getContentItems(array $params, Context $context): JsonResponse
    {
        $request = $context->getRequest();
        $domainId = $context->getDomainId() ?? 1;
        $contentType = $request->query('content_type', '');

        if (empty($contentType)) {
            return JsonResponse::error('콘텐츠 타입이 필요합니다.');
        }

        $items = $this->fetchContentItems($domainId, $contentType);

        return JsonResponse::success([
            'items' => $items,
            'content_type' => $contentType,
        ]);
    }

    /**
     * 콘텐츠 타입별 아이템 가져오기
     *
     * @return array [{id: string, label: string}, ...]
     */
    private function fetchContentItems(int $domainId, string $contentType): array
    {
        // Core 타입: menu
        if ($contentType === 'menu') {
            $menuRepo = new \Mublo\Repository\Menu\MenuItemRepository($this->db);
            $menus = $menuRepo->findByDomain($domainId, true);
            return array_map(fn($m) => [
                'id' => $m['menu_code'],
                'label' => $m['label'],
            ], $menus);
        }

        // 이벤트로 Package/Plugin에 위임
        $event = $this->container->get(\Mublo\Core\Event\EventDispatcher::class)
            ->dispatch(new \Mublo\Core\Event\Block\BlockContentItemsCollectEvent($domainId, $contentType));

        if ($event->hasItems()) {
            return $event->getItems();
        }

        // Fallback: BlockRegistry의 itemsProvider
        $providerClass = BlockRegistry::getItemsProviderClass($contentType);
        if ($providerClass) {
            try {
                $provider = $this->container->canResolve($providerClass)
                    ? $this->container->get($providerClass)
                    : new $providerClass();
                return $provider->getItems($domainId);
            } catch (\Throwable $e) {
                error_log("BlockItemsProvider failed for {$contentType}: " . $e->getMessage());
                return [];
            }
        }

        return [];
    }

    /**
     * 목록 일괄 삭제
     *
     * POST /admin/block-row/list-delete
     */
    public function listDelete(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $chk = $request->input('chk') ?? [];

        if (empty($chk)) {
            return JsonResponse::error('삭제할 항목을 선택해주세요.');
        }

        $deleted = 0;

        foreach ($chk as $rowId) {
            $result = $this->rowService->deleteRow((int) $rowId, $domainId);
            if ($result->isSuccess()) {
                $deleted++;
            }
        }

        if ($deleted > 0) {
            return JsonResponse::success(
                ['deleted' => $deleted],
                "{$deleted}개 항목이 삭제되었습니다."
            );
        }

        return JsonResponse::error('삭제할 수 있는 항목이 없습니다.');
    }

    /**
     * 행 복사
     *
     * POST /admin/block-row/copy
     */
    public function copy(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $rowId = (int) $request->json('row_id', 0);
        $targetPosition = $request->json('position');
        $targetPageId = $request->json('page_id') ? (int) $request->json('page_id') : null;

        if ($rowId <= 0) {
            return JsonResponse::error('복사할 행 ID가 필요합니다.');
        }

        // 동일 도메인 내 복사만 허용 (교차 도메인 복사 차단)
        $result = $this->rowService->copyRow($rowId, $targetPosition, $targetPageId, $domainId);

        if ($result->isSuccess()) {
            return JsonResponse::success(
                ['row_id' => $result->get('row_id')],
                $result->getMessage()
            );
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 행 이동
     *
     * POST /admin/block-row/move
     */
    public function move(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $rowId = (int) $request->json('row_id', 0);
        $targetPosition = $request->json('position');
        $targetPageId = $request->json('page_id') ? (int) $request->json('page_id') : null;

        if ($rowId <= 0) {
            return JsonResponse::error('이동할 행 ID가 필요합니다.');
        }

        $result = $this->rowService->moveRow($rowId, $targetPosition, $targetPageId, $domainId);

        if ($result->isSuccess()) {
            return JsonResponse::success(null, $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 저장된 행 미리보기 (목록에서 사용)
     *
     * GET /admin/block-row/preview-row?row_id=123
     */
    public function previewRow(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $rowId = (int) ($request->query('row_id') ?? $request->json('row_id') ?? 0);

        if ($rowId <= 0) {
            return JsonResponse::error('행 ID가 필요합니다.');
        }

        $row = $this->rowService->getRow($rowId);
        if (!$row || $row->getDomainId() !== $domainId) {
            return JsonResponse::error('행을 찾을 수 없습니다.');
        }

        // 렌더링 전 에셋 스냅샷
        $assets = $this->container->canResolve(\Mublo\Core\Rendering\AssetManager::class)
            ? $this->container->get(\Mublo\Core\Rendering\AssetManager::class)
            : null;
        $cssBefore = $assets ? $assets->getCssPaths() : [];

        $html = $this->previewService->renderExistingRowPreview($rowId, [], []);

        if ($html === null) {
            return JsonResponse::error('미리보기를 생성할 수 없습니다.');
        }

        $skinCss = $assets ? array_values(array_diff($assets->getCssPaths(), $cssBefore)) : [];

        return JsonResponse::success([
            'html' => $html,
            'skinCss' => $skinCss,
        ]);
    }

    /**
     * 미리보기 생성
     *
     * POST /admin/block-row/preview
     */
    public function preview(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $formData = $request->json('formData') ?? [];

        // 배경 설정 필드를 background_config JSON으로 통합
        $formData = $this->processBackgroundConfig($formData, $domainId, $request);

        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());
        $columnsData = $this->parseColumnsData($request->json('columns') ?? [], $domainId, $request);

        // 도메인 ID 추가
        $data['domain_id'] = $domainId;

        // 렌더링 전 에셋 스냅샷 (스킨 CSS 캡처용)
        $assets = $this->container->canResolve(\Mublo\Core\Rendering\AssetManager::class)
            ? $this->container->get(\Mublo\Core\Rendering\AssetManager::class)
            : null;
        $cssBefore = $assets ? $assets->getCssPaths() : [];

        // 미리보기 서비스
        $previewService = $this->previewService;

        $rowId = (int) ($data['row_id'] ?? 0);

        if ($rowId > 0) {
            // 기존 행 미리보기
            $html = $previewService->renderExistingRowPreview($rowId, $data, $columnsData);
        } else {
            // 새 행 미리보기
            $token = $previewService->createPreview($data, $columnsData);
            $html = $previewService->renderPreview($token);
        }

        if ($html === null) {
            return JsonResponse::error('미리보기를 생성할 수 없습니다.');
        }

        // 렌더링 중 추가된 스킨 CSS 추출
        $skinCss = $assets ? array_values(array_diff($assets->getCssPaths(), $cssBefore)) : [];

        return JsonResponse::success([
            'html' => $html,
            'skinCss' => $skinCss,
        ], '미리보기가 생성되었습니다.');
    }

    /**
     * 칸 데이터 파싱
     *
     * DB 필드: column_index, width, pc_padding, mobile_padding,
     *          content_type, content_kind, content_skin,
     *          background_config, border_config, title_config,
     *          content_config, content_items, is_active
     *
     * 삭제된 필드: content_count, content_style (content_config로 통합)
     */
    private function parseColumnsData(array $rawColumns, int $domainId, \Mublo\Core\Http\Request $request): array
    {
        $columns = [];

        foreach ($rawColumns as $index => $colData) {
            $column = [
                'column_index' => (int) $index,
                'width' => $colData['width'] ?? null,
                'pc_padding' => $colData['pc_padding'] ?? null,
                'mobile_padding' => $colData['mobile_padding'] ?? null,
                'content_type' => $colData['content_type'] ?? null,
                'content_kind' => $colData['content_kind'] ?? 'CORE',
                'content_skin' => $colData['content_skin'] ?? null,
                'is_active' => (int) ($colData['is_active'] ?? 1),
            ];

            // JSON 필드
            if (!empty($colData['background_config'])) {
                $column['background_config'] = is_array($colData['background_config'])
                    ? $colData['background_config']
                    : json_decode($colData['background_config'], true);
            }

            if (!empty($colData['border_config'])) {
                $column['border_config'] = is_array($colData['border_config'])
                    ? $colData['border_config']
                    : json_decode($colData['border_config'], true);
            }

            if (!empty($colData['title_config'])) {
                $column['title_config'] = is_array($colData['title_config'])
                    ? $colData['title_config']
                    : json_decode($colData['title_config'], true);
            }

            if (!empty($colData['content_config'])) {
                $column['content_config'] = is_array($colData['content_config'])
                    ? $colData['content_config']
                    : json_decode($colData['content_config'], true);
            }

            if (!empty($colData['content_items'])) {
                $column['content_items'] = is_array($colData['content_items'])
                    ? $colData['content_items']
                    : json_decode($colData['content_items'], true);
            }

            // image 타입인 경우 파일 업로드 처리 (content_items에 이미지 배열)
            if (($column['content_type'] ?? '') === 'image') {
                $column['content_items'] = $this->processColumnImages(
                    (int) $index,
                    $column['content_items'] ?? [],
                    $domainId,
                    $request
                );
            }

            // 제목 이미지 파일 업로드 처리
            if (!empty($column['title_config'])) {
                $column['title_config'] = $this->processTitleImages(
                    (int) $index,
                    $column['title_config'],
                    $domainId,
                    $request
                );
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * 칸 이미지 파일 업로드 처리
     *
     * @param int $columnIndex 칸 인덱스
     * @param array $contentItems 이미지 배열 (content_items)
     * @param int $domainId 도메인 ID
     * @return array 업데이트된 이미지 배열
     */
    private function processColumnImages(int $columnIndex, array $contentItems, int $domainId, \Mublo\Core\Http\Request $request): array
    {
        // contentItems가 이미지 배열
        $images = $contentItems;

        // column_images[칸인덱스][이미지인덱스][pc|mo] 형식의 파일 처리
        $files = $request->getRawFile('column_images');
        if ($files === null) {
            return $images;
        }
        $uploader = $this->getBlockImageUploader();

        // 각 이미지 아이템 처리
        foreach ($images as $imgIndex => &$image) {
            // PC 이미지
            if (!empty($image['pc_has_file'])) {
                $pcFile = $this->getNestedFile($files, $columnIndex, $imgIndex, 'pc');
                if ($pcFile && $pcFile->isValid()) {
                    // 기존 이미지 삭제
                    $this->deleteBlockImage($image['pc_image'] ?? '');

                    // 새 이미지 업로드
                    $result = $uploader->upload($pcFile, $domainId, [
                        'subdirectory' => 'block/images',
                        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                    ]);

                    if ($result->isSuccess()) {
                        $image['pc_image'] = '/storage/' . $result->getRelativePath() . '/' . $result->getStoredName();
                    }
                }
                unset($image['pc_has_file']);
            }

            // MO 이미지
            if (!empty($image['mo_has_file'])) {
                $moFile = $this->getNestedFile($files, $columnIndex, $imgIndex, 'mo');
                if ($moFile && $moFile->isValid()) {
                    // 기존 이미지 삭제
                    $this->deleteBlockImage($image['mo_image'] ?? '');

                    // 새 이미지 업로드
                    $result = $uploader->upload($moFile, $domainId, [
                        'subdirectory' => 'block/images',
                        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
                    ]);

                    if ($result->isSuccess()) {
                        $image['mo_image'] = '/storage/' . $result->getRelativePath() . '/' . $result->getStoredName();
                    }
                }
                unset($image['mo_has_file']);
            }

            // 삭제 처리
            if (!empty($image['pc_del'])) {
                $this->deleteBlockImage($image['pc_image'] ?? '');
                $image['pc_image'] = '';
                unset($image['pc_del']);
            }

            if (!empty($image['mo_del'])) {
                $this->deleteBlockImage($image['mo_image'] ?? '');
                $image['mo_image'] = '';
                unset($image['mo_del']);
            }
        }

        return $images;
    }

    /**
     * 제목 이미지 파일 업로드 처리
     *
     * @param int $columnIndex 칸 인덱스
     * @param array $titleConfig 제목 설정
     * @param int $domainId 도메인 ID
     * @param \Mublo\Core\Http\Request $request 요청
     * @return array 업데이트된 제목 설정
     */
    private function processTitleImages(int $columnIndex, array $titleConfig, int $domainId, \Mublo\Core\Http\Request $request): array
    {
        $files = $request->getRawFile('column_title_image');
        $uploader = $this->getBlockImageUploader();

        foreach (['pc', 'mo'] as $type) {
            $key = "{$type}_image";
            $hasFileKey = "{$type}_image_has_file";
            $delKey = "{$type}_image_del";

            // 삭제 처리
            if (!empty($titleConfig[$delKey])) {
                $this->deleteBlockImage($titleConfig[$key] ?? '');
                $titleConfig[$key] = '';
                unset($titleConfig[$delKey]);
                continue;
            }

            // 새 파일 업로드
            if (!empty($titleConfig[$hasFileKey]) && $files !== null) {
                $file = $this->getTitleImageFile($files, $columnIndex, $type);
                if ($file && $file->isValid()) {
                    // 기존 이미지 삭제
                    $this->deleteBlockImage($titleConfig[$key] ?? '');

                    $result = $uploader->upload($file, $domainId, [
                        'subdirectory' => 'block/titles',
                        'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'],
                    ]);

                    if ($result->isSuccess()) {
                        $titleConfig[$key] = '/storage/' . $result->getRelativePath() . '/' . $result->getStoredName();
                    }
                }
                unset($titleConfig[$hasFileKey]);
            }
        }

        return $titleConfig;
    }

    /**
     * 제목 이미지 파일 추출 (column_title_image[colIdx][type] 구조)
     */
    private function getTitleImageFile(array $files, int $colIdx, string $type): ?UploadedFile
    {
        $name = $files['name'][$colIdx][$type] ?? null;
        $tmpName = $files['tmp_name'][$colIdx][$type] ?? null;
        $error = $files['error'][$colIdx][$type] ?? UPLOAD_ERR_NO_FILE;
        $size = $files['size'][$colIdx][$type] ?? 0;
        $fileType = $files['type'][$colIdx][$type] ?? '';

        if ($error === UPLOAD_ERR_NO_FILE || !$tmpName) {
            return null;
        }

        return new UploadedFile([
            'name' => $name,
            'type' => $fileType,
            'tmp_name' => $tmpName,
            'error' => $error,
            'size' => $size,
        ]);
    }

    /**
     * 블록 이미지용 FileUploader 인스턴스
     */
    private function getBlockImageUploader(): FileUploader
    {
        return new FileUploader(MUBLO_PUBLIC_STORAGE_PATH);
    }

    /**
     * 블록 이미지 삭제
     *
     * 보안: storage 디렉토리 내부 파일만 삭제 허용
     */
    private function deleteBlockImage(string $imageUrl): void
    {
        if (empty($imageUrl) || $imageUrl === '__pending__') {
            return;
        }

        // 경로 조작 시도 차단 (../ 패턴)
        if (str_contains($imageUrl, '..')) {
            return;
        }

        // /storage/ 경로로 시작하는 파일만 허용
        if (!str_starts_with($imageUrl, '/storage/')) {
            return;
        }

        $fullPath = MUBLO_PUBLIC_PATH . $imageUrl;

        // realpath로 실제 경로 확인
        $realPath = realpath($fullPath);
        if ($realPath === false) {
            return;
        }

        // storage 디렉토리 내부인지 검증
        $storageBase = realpath(MUBLO_PUBLIC_STORAGE_PATH);
        if ($storageBase === false || !str_starts_with($realPath, $storageBase)) {
            return;
        }

        @unlink($realPath);
    }

    /**
     * 중첩된 $_FILES 배열에서 UploadedFile 객체 가져오기
     */
    private function getNestedFile(array $files, int $colIdx, int $imgIdx, string $type): ?UploadedFile
    {
        $name = $files['name'][$colIdx][$imgIdx][$type] ?? null;
        $tmpName = $files['tmp_name'][$colIdx][$imgIdx][$type] ?? null;
        $error = $files['error'][$colIdx][$imgIdx][$type] ?? UPLOAD_ERR_NO_FILE;
        $size = $files['size'][$colIdx][$imgIdx][$type] ?? 0;
        $fileType = $files['type'][$colIdx][$imgIdx][$type] ?? '';

        if ($error === UPLOAD_ERR_NO_FILE || !$tmpName) {
            return null;
        }

        return new UploadedFile([
            'name' => $name,
            'type' => $fileType,
            'tmp_name' => $tmpName,
            'error' => $error,
            'size' => $size,
        ]);
    }

    /**
     * 배경 설정 필드를 background_config JSON으로 통합
     *
     * 개별 필드(bg_color, bg_image_old, bg_image_del)를 background_config로 변환
     */
    private function processBackgroundConfig(array $formData, int $domainId, \Mublo\Core\Http\Request $request): array
    {
        $bgConfig = [];

        // 배경 색상
        if (!empty($formData['bg_color'])) {
            $bgConfig['color'] = $formData['bg_color'];
        }

        // 배경 그라데이션
        if (!empty($formData['bg_gradient'])) {
            $bgConfig['gradient'] = $formData['bg_gradient'];
        }

        // 배경 이미지 처리
        $existingImage = $formData['bg_image_old'] ?? '';
        $deleteImage = !empty($formData['bg_image_del']);

        // 새 이미지 업로드 확인
        $rawFile = $request->getRawFile('bg_image');
        $uploadedFile = ($rawFile && ($rawFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE)
            ? new UploadedFile($rawFile)
            : null;

        if ($uploadedFile && $uploadedFile->isValid()) {
            // 기존 이미지 삭제
            $this->deleteBlockImage($existingImage);

            // 새 이미지 업로드
            $uploader = $this->getBlockImageUploader();
            $result = $uploader->upload($uploadedFile, $domainId, [
                'subdirectory' => 'block/bg',
                'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            ]);

            if ($result->isSuccess()) {
                $bgConfig['image'] = '/storage/' . $result->getRelativePath() . '/' . $result->getStoredName();
            }
        } elseif (!$deleteImage && $existingImage) {
            // 기존 이미지 유지
            $bgConfig['image'] = $existingImage;
        } elseif ($deleteImage && $existingImage) {
            // 기존 이미지 삭제
            $this->deleteBlockImage($existingImage);
        }

        // 배경 이미지 옵션 (이미지가 있을 때만 저장)
        if (!empty($bgConfig['image'])) {
            $bgConfig['size'] = $formData['bg_size'] ?? 'cover';
            $bgConfig['position'] = $formData['bg_position'] ?? 'center center';
            $bgConfig['repeat'] = $formData['bg_repeat'] ?? 'no-repeat';
            $bgConfig['attachment'] = $formData['bg_attachment'] ?? 'scroll';
        }

        // background_config로 통합
        $formData['background_config'] = $bgConfig;

        // 개별 필드 제거
        unset($formData['bg_color']);
        unset($formData['bg_gradient']);
        unset($formData['bg_image_old']);
        unset($formData['bg_image_del']);
        unset($formData['bg_size']);
        unset($formData['bg_position']);
        unset($formData['bg_repeat']);
        unset($formData['bg_attachment']);

        return $formData;
    }

    /**
     * 폼 데이터 스키마
     */
    // ── 시각 에디터 ──

    /**
     * 행 시�� 에디터
     *
     * GET /admin/block-row/editor?id=123
     */
    public function editor(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $rowId = (int) $request->query('id', 0);

        if ($rowId === 0 && isset($params[0])) {
            $rowId = (int) $params[0];
        }

        if ($rowId === 0) {
            return ViewResponse::view('Error/404')
                ->withData(['message' => '행을 찾을 수 없습니다.']);
        }

        $row = $this->rowService->getRow($rowId);

        if (!$row || $row->getDomainId() !== $domainId) {
            return ViewResponse::view('Error/404')
                ->withData(['message' => '행을 찾을 수 없습니다.']);
        }

        $pageId = (int) ($row->getPageId() ?? 0);
        $pages = $this->pageService->getSelectOptions($domainId);

        return ViewResponse::view('blockrow/editor')
            ->withData([
                'pageTitle' => '블록 행 에디터',
                'rowId' => $rowId,
                'isPageBased' => $pageId > 0,
                'pageId' => $pageId,
                'currentPageLabel' => $this->findPageLabel($pages, $pageId),
                'contentTypes' => BlockRegistry::getContentTypeOptions(),
                'contentTypeGroups' => BlockRegistry::getContentTypesGroupedByKind(),
                'skinLists' => $this->skinService->getAllSkinLists(),
                'positions' => $this->rowService->getValidPositions(),
                'menuOptions' => $this->buildMenuOptions($domainId),
                'pages' => $pages,
                'domainId' => $domainId,
            ]);
    }

    /**
     * 에디터 데이터 로드 (JSON)
     *
     * GET /admin/block-row/editor-load?id=123
     */
    public function editorLoad(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $rowId = (int) $request->query('id', 0);

        $row = $this->rowService->getRow($rowId);

        if (!$row || $row->getDomainId() !== $domainId) {
            return JsonResponse::error('행을 찾을 수 없습니다.');
        }

        $columns = $this->columnService->getColumnsByRow($rowId);
        $columnsData = array_map(fn($col) => $col->toArray(), $columns);

        // 같은 위치의 다른 행들 렌더링 (미리보기 컨텍스트용)
        $siblingRows = [];
        $rawPosition = $row->getPosition();
        $position = $rawPosition instanceof \BackedEnum ? $rawPosition->value : (string) ($rawPosition ?? '');
        $pageId = (int) ($row->getPageId() ?? 0);

        try {
            if ($pageId > 0) {
                $allRows = $this->rowService->getActiveRowsByPage($domainId, $pageId);
            } elseif ($position !== '') {
                $allRows = $this->rowService->getActiveRowsByPosition($domainId, $position);
            } else {
                $allRows = [];
            }

            // 현재 행이 목록에 없으면 (비활성 등) 직접 추가
            $foundCurrent = false;
            foreach ($allRows as $sibRow) {
                if ($sibRow->getRowId() === $rowId) {
                    $foundCurrent = true;
                    break;
                }
            }
            if (!$foundCurrent) {
                $allRows[] = $row;
                usort($allRows, fn($a, $b) => $a->getSortOrder() - $b->getSortOrder());
            }

            foreach ($allRows as $sibRow) {
                $sibId = $sibRow->getRowId();
                if ($sibId === $rowId) {
                    $siblingRows[] = ['row_id' => $sibId, 'html' => '__CURRENT__', 'sort_order' => $sibRow->getSortOrder()];
                    continue;
                }
                $html = $this->previewService->renderExistingRowPreview($sibId, [], []);
                $siblingRows[] = ['row_id' => $sibId, 'html' => $html ?? '', 'sort_order' => $sibRow->getSortOrder()];
            }
        } catch (\Throwable $e) {
            $siblingRows = [['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]];
        }

        // 현재 행의 서버 렌더링 HTML (배너 등 Plugin 콘텐츠용)
        $currentRowHtml = '';
        $currentRowCss = [];
        try {
            $assets = $this->container->canResolve(\Mublo\Core\Rendering\AssetManager::class)
                ? $this->container->get(\Mublo\Core\Rendering\AssetManager::class)
                : null;
            $cssBefore = $assets ? $assets->getCssPaths() : [];

            $currentRowHtml = $this->previewService->renderExistingRowPreview($rowId, [], []) ?? '';

            $currentRowCss = $assets ? array_values(array_diff($assets->getCssPaths(), $cssBefore)) : [];
        } catch (\Throwable $e) {
            // 렌더링 실패 시 빈 문자열
        }

        return JsonResponse::success([
            'row' => $row->toArray(),
            'columns' => $columnsData,
            'siblingRows' => $siblingRows,
            'currentRowHtml' => $currentRowHtml,
            'currentRowCss' => $currentRowCss,
        ]);
    }

    /**
     * 에디터 저장 (JSON)
     *
     * POST /admin/block-row/editor-save
     *
     * 기존 store()와 동일한 저장 로직이지만:
     * - JSON 요청 수신 ($request->json)
     * - redirect 대신 JSON 응답만 반환
     * - Adapter가 background_config를 개별 필드(bg_color 등)로 분해하여 전송
     */
    public function editorSave(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $formData = $request->json('formData') ?? [];

        // 배경 설정 필드를 background_config JSON으로 통합
        $formData = $this->processBackgroundConfig($formData, $domainId, $request);

        $data = FormHelper::normalizeFormData($formData, $this->getFormSchema());

        // 칸 데이터 처리
        $columnsData = $this->parseColumnsData($request->json('columns') ?? [], $domainId, $request);

        // Include 블록 슈퍼관리자 전용 제한
        $includeError = $this->validateIncludePermission($columnsData, $context);
        if ($includeError) {
            return $includeError;
        }

        $rowId = (int) ($data['row_id'] ?? 0);

        if ($rowId > 0) {
            $result = $this->rowService->updateRow($rowId, $data, $columnsData, $domainId);
        } else {
            $result = $this->rowService->createRow($domainId, $data, $columnsData);
        }

        if ($result->isSuccess()) {
            return JsonResponse::success([], $result->getMessage());
        }

        return JsonResponse::error($result->getMessage());
    }

    /**
     * 에디터 이미지 업로드 (AJAX)
     *
     * POST /admin/block-row/editor-upload
     * 파일 필드: file
     * 추가 파라미터: target (bg|title_pc|title_mo|content)
     */
    public function editorUpload(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();

        $rawFile = $request->getRawFile('file');
        if (!$rawFile || ($rawFile['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return JsonResponse::error('파일이 없습니다.');
        }

        $uploadedFile = new UploadedFile($rawFile);
        if (!$uploadedFile->isValid()) {
            return JsonResponse::error('유효하지 않은 파일입니다.');
        }

        $target = $request->input('target') ?? 'bg';
        $subdirectory = match ($target) {
            'bg' => 'block/bg',
            'title_pc', 'title_mo' => 'block/title',
            'content' => 'block/content',
            default => 'block/bg',
        };

        $uploader = $this->getBlockImageUploader();
        $result = $uploader->upload($uploadedFile, $domainId, [
            'subdirectory' => $subdirectory,
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        ]);

        if ($result->isSuccess()) {
            $url = '/storage/' . $result->getRelativePath() . '/' . $result->getStoredName();
            return JsonResponse::success(['url' => $url]);
        }

        return JsonResponse::error($result->getMessage() ?: '업로드에 실패했습니다.');
    }

    private function getFormSchema(): array
    {
        return [
            'numeric' => [
                'row_id',
                'page_id',
                'width_type',
                'column_count',
                'column_margin',
                'column_width_unit',
                'sort_order',
            ],
            'bool' => ['is_active'],
            'json' => ['background_config'],
        ];
    }

    /**
     * 페이지 라벨 찾기
     */
    private function findPageLabel(array $pages, int $pageId): string
    {
        if ($pageId <= 0) {
            return '';
        }

        foreach ($pages as $page) {
            if ($page['value'] == $pageId) {
                return $page['label'] . ' (' . $page['code'] . ')';
            }
        }

        return '';
    }

    /**
     * Include 블록 사용 권한 검증
     *
     * Include 블록은 PHP 파일을 포함하는 강한 권한 기능이므로
     * 최고관리자(super admin)만 사용 가능
     */
    private function validateIncludePermission(array $columnsData, Context $context): ?JsonResponse
    {
        $hasInclude = false;
        foreach ($columnsData as $col) {
            if (($col['content_type'] ?? '') === 'include') {
                $hasInclude = true;
                break;
            }
        }

        if (!$hasInclude) {
            return null;
        }

        /** @var AuthService $authService */
        $authService = $this->container->get(AuthService::class);
        if (!$authService->isSuper()) {
            return JsonResponse::error('Include 블록은 최고관리자만 사용할 수 있습니다.');
        }

        return null;
    }

    /**
     * 메뉴 옵션 목록 생성 (position_menu 셀렉트용)
     *
     * 메뉴 트리 항목과 트리 미배치 항목을 optgroup으로 분류
     *
     * @return array [['group' => string, 'items' => [['value' => string, 'label' => string, 'depth' => int], ...]], ...]
     */
    private function buildMenuOptions(int $domainId): array
    {
        $options = [];

        // 메뉴 트리 (depth 정보 포함)
        $treeItems = $this->menuService->getTree($domainId, true);
        $treeMenuCodes = [];

        if (!empty($treeItems)) {
            $treeGroup = ['group' => '메뉴 트리', 'items' => []];
            foreach ($treeItems as $node) {
                $treeGroup['items'][] = [
                    'value' => $node['menu_code'],
                    'label' => $node['label'] ?? $node['menu_code'],
                    'depth' => (int) ($node['depth'] ?? 0),
                ];
                $treeMenuCodes[] = $node['menu_code'];
            }
            $options[] = $treeGroup;
        }

        // 트리 미배치 아이템
        $allItems = $this->menuService->getItems($domainId, true);
        $unplacedItems = [];
        foreach ($allItems as $item) {
            $code = $item['menu_code'] ?? '';
            if (!in_array($code, $treeMenuCodes, true)) {
                $unplacedItems[] = [
                    'value' => $code,
                    'label' => $item['label'] ?? $code,
                    'depth' => 0,
                ];
            }
        }

        if (!empty($unplacedItems)) {
            $options[] = ['group' => '트리 미배치', 'items' => $unplacedItems];
        }

        return $options;
    }
}
