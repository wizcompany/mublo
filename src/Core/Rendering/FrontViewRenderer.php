<?php
namespace Mublo\Core\Rendering;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Context\Context;
use Mublo\Service\Auth\AuthService;
use Mublo\Service\Menu\MenuService;
use Mublo\Service\Block\BlockRenderService;
use Mublo\Infrastructure\Security\CsrfManager;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\Rendering\ViewContextCreatedEvent;
use Mublo\Core\Event\Tracking\PageViewedEvent;
use Mublo\Core\Registry\CategoryProviderRegistry;
use Mublo\Core\Rendering\AssetManager;
use Mublo\Helper\View\ViewFormatHelper;
use Mublo\Helper\View\ViewContentHelper;

/**
 * Class FrontViewRenderer
 *
 * ============================================================
 * FrontViewRenderer – Front 영역 화면 조립자
 * ============================================================
 *
 * 이 클래스는 Front 영역에서
 * 하나의 요청이 "어떤 순서와 구성으로 화면에 출력되는지"를
 * 결정하고 조립하는 최상위 렌더러이다.
 *
 * ------------------------------------------------------------
 * [역할 요약]
 * ------------------------------------------------------------
 *
 * - Controller 가 반환한 ViewResponse 를 해석한다.
 * - Front 영역에 맞는 출력 흐름을 결정한다.
 * - Header / Layout / Content / Footer 를 조립한다.
 * - 공통 데이터(메뉴, 회원, 사이트설정)를 모든 View에 주입한다.
 *
 * LayoutManager 는 이 클래스가 사용하는 "도구"일 뿐,
 * 출력의 주도권은 FrontViewRenderer 에 있다.
 *
 * ------------------------------------------------------------
 * [책임]
 * ------------------------------------------------------------
 *
 * - Front 영역 화면 조립 책임
 * - 출력 순서 제어
 * - Front 전용 규칙 적용
 *
 * ------------------------------------------------------------
 * [View Context]
 * ------------------------------------------------------------
 *
 * View 파일에서 $this로 접근 가능한 기능:
 * - $this->pagination($data) : 페이지네이션 렌더링
 * - $this->component($name, $data) : 컴포넌트 렌더링
 * - $this->format->method() : 포맷팅 헬퍼 (ViewFormatHelper)
 * - $this->content->method() : 콘텐츠 파싱 헬퍼 (ViewContentHelper)
 *
 * ------------------------------------------------------------
 * [LayoutManager 와의 관계]
 * ------------------------------------------------------------
 *
 * - LayoutManager 는 body 영역의 레이아웃과 스킨을
 *   결정하는 역할만 담당한다.
 * - LayoutManager 는 페이지 전체를 조립하지 않는다.
 * - FrontViewRenderer 는 LayoutManager 를 호출하여
 *   body 레이아웃 HTML 을 얻는다.
 *
 * ------------------------------------------------------------
 * [금지 사항]
 * ------------------------------------------------------------
 *
 * - Layout 내부 구조 정의
 * - 컴포넌트 내부 구현
 * - 비즈니스 로직 처리
 * - Admin 영역 출력 규칙 침범
 *
 * ------------------------------------------------------------
 * 이 클래스는
 * "Front 화면이 어떻게 만들어지는지"를
 * 가장 먼저 봐야 할 기준점(anchor)이다.
 * ------------------------------------------------------------
 */
class FrontViewRenderer implements ViewRendererInterface
{
    protected LayoutManager $layoutManager;
    protected AuthService $authService;
    protected MenuService $menuService;
    protected BlockRenderService $blockRenderService;
    protected CsrfManager $csrfManager;
    protected EventDispatcher $eventDispatcher;
    protected AssetManager $assetManager;
    protected CategoryProviderRegistry $categoryRegistry;

    /**
     * View Context (View에서 $this로 접근)
     */
    protected ?ViewContext $viewContext = null;

    /**
     * Frame 스킨명
     */
    protected string $frameSkin = 'basic';

    /**
     * 공통 데이터 (모든 View에 주입)
     */
    protected array $commonData = [];

    public function __construct(
        LayoutManager $layoutManager,
        AuthService $authService,
        MenuService $menuService,
        BlockRenderService $blockRenderService,
        CsrfManager $csrfManager,
        EventDispatcher $eventDispatcher,
        AssetManager $assetManager,
        CategoryProviderRegistry $categoryRegistry
    ) {
        $this->layoutManager = $layoutManager;
        $this->authService = $authService;
        $this->menuService = $menuService;
        $this->blockRenderService = $blockRenderService;
        $this->csrfManager = $csrfManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->assetManager = $assetManager;
        $this->categoryRegistry = $categoryRegistry;
    }

    /**
     * Front 화면 렌더링 진입점
     */
    public function render(ViewResponse $response, Context $context): void
    {
        /* =====================================================
         * 스킨 결정 (Context에서 가져오기)
         * ===================================================== */

        $this->frameSkin = $context->getFrameSkin();

        /* =====================================================
         * ViewContext 초기화 (Front용, Helper 없이 기본 기능만)
         * ===================================================== */

        $this->viewContext = new ViewContext('front');
        $this->viewContext->setQueryString(http_build_query($context->getRequest()->getQuery()));
        $this->viewContext->setHelper('format', new ViewFormatHelper());
        $this->viewContext->setHelper('content', new ViewContentHelper());
        $this->viewContext->setHelper('assets', $this->assetManager);
        $this->viewContext->setCategoryRegistry($this->categoryRegistry, $context->getDomainId() ?? 1);

        // Plugin/Package가 자체 ViewHelper를 등록할 수 있는 확장점
        $this->eventDispatcher->dispatch(
            new ViewContextCreatedEvent($this->viewContext)
        );

        /* =====================================================
         * 공통 데이터 수집
         * ===================================================== */

        $this->commonData = $this->collectCommonData($context);

        /* =====================================================
         * 페이지 설정 (_pageConfig) 추출
         *
         * BlockPage 등에서 전달하는 레이아웃 오버라이드
         * ===================================================== */

        $viewData = $response->getViewData();
        $pageConfig = $viewData['_pageConfig'] ?? [];
        $useHeader = (bool) ($pageConfig['use_header'] ?? true);
        $useFooter = (bool) ($pageConfig['use_footer'] ?? true);

        /* =====================================================
         * 출력 버퍼링 시작
         *
         * 블록/플러그인이 렌더링 중 addCss/addJs를 호출하면
         * 버퍼링 완료 후 플레이스홀더 치환으로
         * CSS → <head>, JS → </body> 앞에 삽입
         *
         * try-finally로 감싸서 에러 발생 시에도 버퍼가 반드시 출력되도록 보장
         * ===================================================== */
        ob_start();

        try {

        /* =====================================================
         * PARTIAL 출력 (단독 View)
         * ===================================================== */
        $frameSkin = $this->frameSkin;

        if ($response->isFullPageHint()) {
            // 1. Head
            $this->includeViewSafely(
                "frame/{$frameSkin}/Head.php",
                $viewData
            );

            // 2. Content
            $this->renderContent($response, $context);

            // 3. Foot
            $this->includeViewSafely(
                "frame/{$frameSkin}/Foot.php",
                $viewData
            );

            return;
        }

        /* =====================================================
         * FULL PAGE 출력 (2-pass 렌더링)
         *
         * 1차: Content를 버퍼에 먼저 렌더링
         *      → 스킨이 $this->layout() 으로 header/footer 힌트 선언
         * 2차: 힌트를 반영하여 Head → Header? → Layout → Content → Footer? → Foot 조립
         * ===================================================== */

        // --- 1차: Content 버퍼링 ---
        ob_start();
        $this->renderContent($response, $context);
        $contentHtml = ob_get_clean();

        // 스킨에서 선언한 레이아웃 힌트 반영 (스킨 > _pageConfig > 기본값)
        $useHeader = $this->viewContext->getLayoutOption('header', $useHeader);
        $useFooter = $this->viewContext->getLayoutOption('footer', $useFooter);

        // --- 2차: 페이지 조립 ---
        $domainId = $context->getDomainId() ?? 1;
        $menuCode = $context->getCurrentMenuCode();

        // 1. Head (html / head / body 시작)
        $this->includeViewSafely(
            "frame/{$frameSkin}/Head.php",
            $viewData
        );

        // 2. Header (Layout 바깥, 전역 UI)
        if ($useHeader) {
            $this->includeViewSafely(
                "frame/{$frameSkin}/Header.php",
                $viewData
            );
        }

        // 2-1. subhead 블록 (Header 아래, Layout 바깥)
        if ($useHeader) {
            echo $this->blockRenderService->renderPosition($domainId, 'subhead', $menuCode);
        }

        // 3. Layout Open (본문 래퍼 시작)
        $layout = $this->layoutManager->resolve($context, $pageConfig);
        $layoutData = $layout['data'] ?? [];

        $this->includeViewSafely(
            "frame/{$frameSkin}/LayoutOpen.php",
            $layoutData
        );

        // 3-1. 좌측 사이드바
        $layoutType = (int) ($layoutData['layoutType'] ?? 1);
        if ($layoutType === 2 || $layoutType === 4) {
            $leftMobileClass = empty($layoutData['sidebarLeftMobile']) ? ' mublo-layout__sidebar--mobile-hidden' : '';
            $leftWidthStyle = !empty($layoutData['sidebarLeftWidth'])
                ? ' style="width:' . (int) $layoutData['sidebarLeftWidth'] . 'px"' : '';
            echo '<aside class="mublo-layout__sidebar mublo-layout__sidebar--left' . $leftMobileClass . '"' . $leftWidthStyle . '>';
            echo $this->blockRenderService->renderPosition($domainId, 'left', $menuCode);
            echo '</aside>';
        }

        // 4. 메인 콘텐츠 영역 (버퍼링된 Content 출력)
        echo '<div class="mublo-layout__content">';
        echo $this->blockRenderService->renderPosition($domainId, 'contenthead', $menuCode);
        echo $contentHtml;
        echo $this->blockRenderService->renderPosition($domainId, 'contentfoot', $menuCode);
        echo '</div>';

        // 4-1. 우측 사이드바
        if ($layoutType === 3 || $layoutType === 4) {
            $rightMobileClass = empty($layoutData['sidebarRightMobile']) ? ' mublo-layout__sidebar--mobile-hidden' : '';
            $rightWidthStyle = !empty($layoutData['sidebarRightWidth'])
                ? ' style="width:' . (int) $layoutData['sidebarRightWidth'] . 'px"' : '';
            echo '<aside class="mublo-layout__sidebar mublo-layout__sidebar--right' . $rightMobileClass . '"' . $rightWidthStyle . '>';
            echo $this->blockRenderService->renderPosition($domainId, 'right', $menuCode);
            echo '</aside>';
        }

        // 5. Layout Close
        $this->includeViewSafely(
            "frame/{$frameSkin}/LayoutClose.php",
            $layoutData
        );

        // 5-1. subfoot 블록 (Layout 아래, Footer 바깥)
        if ($useFooter) {
            echo $this->blockRenderService->renderPosition($domainId, 'subfoot', $menuCode);
        }

        // 6. Footer (Layout 바깥, 전역 UI)
        if ($useFooter) {
            $this->includeViewSafely(
                "frame/{$frameSkin}/Footer.php",
                $viewData
            );
        }

        // 6-1. 플러그인 프론트 렌더링 슬롯 (팝업, 위젯 등)
        $frontFootEvent = $this->eventDispatcher->dispatch(
            new \Mublo\Core\Event\Rendering\FrontFootRenderEvent($domainId)
        );
        $pluginFootHtml = $frontFootEvent->getHtml();
        if ($pluginFootHtml !== '') {
            echo $pluginFootHtml;
        }

        // 7. Foot (script / body 종료)
        $this->includeViewSafely(
            "frame/{$frameSkin}/Foot.php",
            $viewData
        );

        // PageView 이벤트 발행 (방문통계 등 플러그인이 구독)
        $request = $context->getRequest();
        $this->eventDispatcher->dispatch(new PageViewedEvent(
            domainId: $context->getDomainId() ?? 0,
            url: $request->getUri(),
            pageType: $this->resolvePageType($response, $pageConfig),
            memberId: $this->authService->user()['member_id'] ?? null,
            ipAddress: $request->getClientIp(),
            userAgent: $request->header('User-Agent') ?? '',
            referer: $request->header('Referer') ?? ''
        ));

        } finally {
            $this->flushWithAssets();
        }
    }

    /**
     * 페이지 유형 판별
     */
    private function resolvePageType(ViewResponse $response, array $pageConfig): string
    {
        if (!empty($pageConfig['page_id'])) {
            return 'page';
        }

        $viewPath = $response->getViewPath();

        // Core 고유 페이지 타입
        if (str_starts_with($viewPath, 'Index/')) return 'index';
        if (str_starts_with($viewPath, 'Auth/')) return 'auth';
        if (str_starts_with($viewPath, 'Member/')) return 'member';
        if (str_starts_with($viewPath, 'Search/')) return 'search';

        // Package/Plugin에 위임
        $event = $this->eventDispatcher->dispatch(
            new \Mublo\Core\Event\Rendering\PageTypeResolveEvent($viewPath)
        );

        return $event->getPageType() ?? 'other';
    }

    /**
     * 공통 데이터 수집
     *
     * 모든 View에 주입되는 공통 데이터:
     * - currentMember: 현재 로그인 회원 정보 (null이면 비로그인)
     * - menuTree: 메뉴 트리 (계층형)
     * - siteConfig: 사이트 설정 (도메인별)
     * - companyConfig: 회사 정보 (Footer에서 활용)
     * - seoConfig: SEO/로고/SNS 설정 (Footer에서 활용)
     * - footerMenus: 푸터 메뉴 목록
     * - siteImages: 사이트 이미지 URLs (logo_pc, logo_mobile, favicon, app_icon, og_image)
     * - csInfo: 고객센터 정보 (tel, time, email) — 패키지가 siteOverrides로 덮어씌움
     */
    protected function collectCommonData(Context $context): array
    {
        $domainId = $context->getDomainId() ?? 1;
        $domainInfo = $context->getDomainInfo();

        // 메뉴 트리 (에러 시 빈 배열)
        try {
            $menuTree = $this->menuService->getTreeHierarchy($domainId);
        } catch (\Throwable $e) {
            $menuTree = [];
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                error_log("[FrontViewRenderer] Menu tree error: " . $e->getMessage());
            }
        }

        // 유틸리티 메뉴 (에러 시 빈 배열)
        try {
            $utilityMenus = $this->menuService->getUtilityMenus($domainId);
        } catch (\Throwable $e) {
            $utilityMenus = [];
        }

        // 푸터 메뉴 (에러 시 빈 배열)
        try {
            $footerMenus = $this->menuService->getFooterMenus($domainId);
        } catch (\Throwable $e) {
            $footerMenus = [];
        }

        // 고객센터 정보: 패키지 오버라이드(siteOverrides) → 사이트 고객센터 설정(cs_*) → 대표 정보
        $companyConfig = $domainInfo?->getCompanyConfig() ?? [];
        $csInfo = [
            'tel'      => $context->getSiteOverride('cs_tel', $companyConfig['cs_tel'] ?? $companyConfig['tel'] ?? ''),
            'time'     => $context->getSiteOverride('cs_time', $companyConfig['cs_time'] ?? ''),
            'email'    => $context->getSiteOverride('cs_email', $companyConfig['email'] ?? ''),
            'ict_mark' => $context->getSiteOverride('ict_mark', ''),
        ];

        $request = $context->getRequest();
        $currentUrl = $request->getScheme() . '://' . $request->getHost() . $request->getPath();

        return [
            'currentMember' => $this->authService->user(),
            'menuTree' => $menuTree,
            'siteConfig' => $domainInfo?->getSiteConfig() ?? [],
            'companyConfig' => $companyConfig,
            'seoConfig' => $domainInfo?->getSeoConfig() ?? [],
            'utilityMenus' => $utilityMenus,
            'footerMenus' => $footerMenus,
            'siteImages' => $context->getSiteImageUrls(),
            'csrfToken' => $this->csrfManager->getToken(),
            'currentMenuCode' => $context->getCurrentMenuCode(),
            'csInfo' => $csInfo,
            'frameSkin' => $this->frameSkin,
            'currentUrl' => $currentUrl,
        ];
    }

    /**
     * View 데이터에 공통 데이터 병합
     *
     * Controller 데이터가 공통 데이터보다 우선 (덮어쓰기 가능)
     */
    protected function mergeData(array $viewData): array
    {
        return array_merge($this->commonData, $viewData);
    }

    /**
     * --------------------------------------------------------
     * Content View 렌더링 (Front 전용, 방어 포함)
     *
     * View 파일은 ViewContext의 render() 메서드 내에서 include되므로
     * View에서 $this로 ViewContext에 접근 가능:
     * - $this->pagination()
     * - $this->component()
     *
     * 경로 예시:
     * - 상대 경로: board/list → views/Front/Board/{skin}/List.php
     * - 절대 경로: /path/to/Plugin/views/Front/Product → /path/to/Plugin/views/Front/Product.php
     * --------------------------------------------------------
     */
    protected function renderContent(ViewResponse $response, Context $context): void
    {
        $viewPath = $response->getViewPath();

        // 절대 경로인 경우 (Plugin/Package용)
        if ($response->isAbsolutePath()) {
            $path = $viewPath . '.php';

            // 경로 조작 방지 (.. 포함 여부만 체크)
            if (str_contains($viewPath, '..')) {
                if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                    error_log("[Security] Path traversal attempt detected: {$viewPath}");
                }
                return;
            }

            if (!file_exists($path)) {
                error_log("[FrontViewRenderer] Absolute view not found: {$path}");
                $this->renderFallbackError($response->getViewData());
                return;
            }

            // ViewContext의 render()를 통해 include (View에서 $this 접근 가능)
            $this->viewContext->render($path, $this->mergeData($response->getViewData()));
            return;
        }

        // 상대 경로인 경우 (Core용, 기존 로직)
        $logicalPath = $viewPath;

        // [보완 1] 경로 조작 방지 (Path Traversal)
        if (str_contains($logicalPath, '..') ||
            str_contains($logicalPath, '\\') ||
            str_starts_with($logicalPath, '/')) {
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                error_log("[Security] Path traversal attempt detected: {$logicalPath}");
            }
            return;
        }

        $parts = explode('/', $logicalPath);

        if (count($parts) !== 2) {
            return;
        }

        [$group, $file] = array_map(
            fn ($v) => ucfirst($v),
            $parts
        );

        // ViewGroup 별 Front 스킨 선택
        $skin = $context->getFrontSkin($group) ?? 'basic';

        $path = MUBLO_VIEW_PATH
            . "/Front/{$group}/{$skin}/{$file}.php";

        // 디버그: Content 렌더링 시도 로그
        if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
            echo "<!-- renderContent: logicalPath={$logicalPath}, group={$group}, skin={$skin}, file={$file} -->\n";
            echo "<!-- Full path: {$path} -->\n";
        }

        if (!file_exists($path)) {
            error_log("[FrontViewRenderer] View not found: Front/{$group}/{$skin}/{$file}.php");
            $this->renderFallbackError($response->getViewData());
            return;
        }

        // ViewContext의 render()를 통해 include (View에서 $this 접근 가능)
        $this->viewContext->render($path, $this->mergeData($response->getViewData()));
    }

    /**
     * 출력 버퍼 수집 + 에셋 플레이스홀더 치환 후 출력
     *
     * 버퍼가 없거나 치환 실패 시에도 안전하게 출력
     */
    protected function flushWithAssets(): void
    {
        $html = ob_get_clean();

        if ($html === false) {
            return;
        }

        $html = str_replace('<!-- MUBLO_CSS -->', $this->assetManager->renderCss(), $html);
        $html = str_replace('<!-- MUBLO_JS -->', $this->assetManager->renderJs(), $html);
        echo $html;
    }

    /**
     * --------------------------------------------------------
     * 안전한 View 렌더링
     * (Partial / Layout / Header / Footer 공용)
     *
     * ViewContext를 통해 렌더링하여 $this 접근 가능
     * --------------------------------------------------------
     */
    protected function includeViewSafely(string $relativePath, array $data = []): void
    {
        $fullPath = MUBLO_VIEW_PATH . '/Front/' . $relativePath;

        if (!file_exists($fullPath)) {
            // 디버그 모드에서 파일 미발견 로그
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                echo "<!-- View not found: {$fullPath} -->\n";
            }
            return;
        }

        // ViewContext의 render()를 통해 include (View에서 $this 접근 가능)
        $this->viewContext->render($fullPath, $this->mergeData($data));
    }

    /**
     * 뷰 파일 미발견 시 fallback 에러 화면 렌더링
     *
     * Core의 Error/NotFound 뷰 사용
     */
    protected function renderFallbackError(array $viewData = []): void
    {
        $corePath = MUBLO_VIEW_PATH . '/Error/NotFound.php';
        if (file_exists($corePath)) {
            extract($viewData);
            include $corePath;
            return;
        }

        // Core 에러 뷰도 없으면 인라인 렌더링
        echo '<div style="text-align:center;padding:60px 20px">';
        echo '<h2>페이지를 찾을 수 없습니다</h2>';
        echo '<p>요청하신 페이지가 존재하지 않거나 이동되었습니다.</p>';
        echo '<a href="/">홈으로</a>';
        echo '</div>';
    }
}
