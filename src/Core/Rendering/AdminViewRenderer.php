<?php
namespace Mublo\Core\Rendering;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Context\Context;
use Mublo\Service\Admin\AdminMenuService;
use Mublo\Service\Auth\AuthService;
use Mublo\Infrastructure\Security\CsrfManager;
use Mublo\Core\Rendering\AssetManager;
use Mublo\Helper\List\ListRenderHelper;

/**
 * Class AdminViewRenderer
 *
 * ============================================================
 * AdminViewRenderer – Admin 영역 화면 조립자
 * ============================================================
 *
 * 이 클래스는 Admin 영역에서
 * 하나의 요청이 "어떤 구조와 규칙으로 화면에 출력되는지"를
 * 결정하고 조립하는 최상위 렌더러이다.
 *
 * ------------------------------------------------------------
 * [스킨 구조]
 * ------------------------------------------------------------
 *
 * views/Admin/{skin}/
 * ├── _partial/           # 공통 파일 (Head, Foot, Sidebar 등)
 * ├── _assets/            # CSS/JS (ServeController 제공)
 * │   ├── css/
 * │   └── js/
 * ├── Dashboard/          # 대시보드 뷰
 * ├── Auth/               # 인증 뷰
 * ├── Settings/           # 설정 뷰
 * └── ...
 *
 * views/Components/            # 공유 컴포넌트 (Admin/Front 공용)
 * ├── pagination.php
 * ├── breadcrumb.php
 * └── ...
 *
 * 스킨 배포 시 {skin}/ 폴더 하나만 복사하면 됨
 *
 * ------------------------------------------------------------
 * [역할 요약]
 * ------------------------------------------------------------
 *
 * - Controller 가 반환한 ViewResponse 를 해석한다.
 * - Admin 영역에 특화된 출력 흐름을 적용한다.
 * - Admin Header / Sidebar / Content / Footer 를 조립한다.
 *
 * ------------------------------------------------------------
 * [View Context]
 * ------------------------------------------------------------
 *
 * View 파일에서 $this로 접근 가능한 기능:
 * - $this->listRenderHelper : 리스트 렌더링 헬퍼
 * - $this->columns() : ListColumnBuilder 팩토리 (매번 새 인스턴스)
 * - $this->pagination($data) : 페이지네이션 렌더링
 * - $this->component($name, $data) : 컴포넌트 렌더링
 *
 * ------------------------------------------------------------
 * [책임]
 * ------------------------------------------------------------
 *
 * - Admin 화면 조립 책임
 * - 출력 순서 및 구조 제어
 * - Admin 전용 UI 규칙 적용
 *
 * ------------------------------------------------------------
 * [금지 사항]
 * ------------------------------------------------------------
 *
 * - Front 영역 출력 규칙 재사용
 * - Layout 내부 HTML 구조 정의
 * - 컴포넌트 내부 구현
 * - 비즈니스 로직 처리
 *
 * ------------------------------------------------------------
 */
class AdminViewRenderer implements ViewRendererInterface
{
    protected AdminMenuService $adminMenuService;
    protected AuthService $authService;
    protected CsrfManager $csrfManager;
    protected AssetManager $assetManager;

    /**
     * Admin 스킨명 (기본값: basic)
     */
    protected string $skin = 'basic';

    /**
     * View Context (View에서 $this로 접근)
     */
    protected ?ViewContext $viewContext = null;

    public function __construct(AdminMenuService $adminMenuService, AuthService $authService, CsrfManager $csrfManager, AssetManager $assetManager)
    {
        $this->adminMenuService = $adminMenuService;
        $this->authService = $authService;
        $this->csrfManager = $csrfManager;
        $this->assetManager = $assetManager;
    }

    /**
     * Admin 화면 렌더링 진입점
     */
    public function render(ViewResponse $response, Context $context): void
    {
        /* =====================================================
         * 스킨 결정 (Context에서 가져오기)
         * ===================================================== */

        $this->skin = $context->getAdminSkin();

        /* =====================================================
         * ViewContext 초기화 + Admin 전용 Helper 주입
         * ===================================================== */

        $this->viewContext = new ViewContext($this->skin);
        $this->viewContext->setQueryString(http_build_query($context->getRequest()->getQuery()));
        $this->viewContext->setHelper('listRenderHelper', new ListRenderHelper());
        $this->viewContext->setHelper('assets', $this->assetManager);

        // CSRF 토큰을 모든 뷰 데이터에 자동 주입
        $response->withData(['csrfToken' => $this->csrfManager->getToken()]);

        /* =====================================================
         * 로그인 페이지 등 Partial 출력 (단독 View)
         * ===================================================== */
        if ($response->isFullPageHint()) {
            $fullPageData = array_merge($response->getViewData(), [
                'siteTitle' => $context->getSiteLogoText(),
                'favicon'   => $context->getSiteImageUrl('favicon'),
                'appIcon'   => $context->getSiteImageUrl('app_icon'),
            ]);
            $response->withData($fullPageData);

            // 1. Head
            $this->includePartial('Head.php', $fullPageData);

            // 2. Content
            $this->renderContent($response, $context);

            // 3. Foot
            $this->includePartial('Foot.php', $fullPageData);

            return;
        }

        /* =====================================================
         * FULL PAGE 출력 (Admin 레이아웃)
         * ===================================================== */

        // 공통 데이터 준비 (activeCode, proxyLogin 등)
        $request = $context->getRequest();
        $currentPath = $request->getPath();
        $activeCode = $request->query('activeCode')
            ?: $this->adminMenuService->getActiveCode($currentPath);

        $viewData = array_merge($response->getViewData(), [
            'activeCode' => $activeCode,
            'proxyLogin' => $this->authService->getProxyLogin(),
            'siteTitle'  => $context->getSiteLogoText(),
            'favicon'    => $context->getSiteImageUrl('favicon'),
            'appIcon'    => $context->getSiteImageUrl('app_icon'),
        ]);

        // Sidebar용 메뉴 데이터 추가 (권한 필터링 적용)
        $user = $this->authService->user();
        $sidebarData = array_merge($viewData, [
            'menu' => $this->adminMenuService->getFilteredMenus(
                $context->getDomainId(),
                $user['level_value'] ?? 0,
                $this->authService->isSuper(),
                $context->getDomainGroup()
            ),
        ]);

        // 1. Partial Head (CSS, meta tags)
        $this->includePartial('Head.php', $viewData);

        // 2. Layout Open (app wrapper, backdrop)
        $this->includePartial('LayoutOpen.php', $viewData);

        // 3. Sidebar
        $this->includePartial('Sidebar.php', $sidebarData);

        // 4. Header (main-content wrapper, top-header, page-content open)
        $this->includePartial('Header.php', $viewData);

        // 5. Main Content
        $this->renderContent($response, $context);

        // 6. Footer (closing tags, sidebar scripts)
        $this->includePartial('Footer.php', $viewData);

        // 7. Partial Foot (closing body, html) - activeCode 포함
        $this->includePartial('Foot.php', $viewData);
    }

    /**
     * Content View 렌더링 (Admin 전용)
     *
     * View 파일은 AdminViewContext의 render() 메서드 내에서 include되므로
     * View에서 $this로 ViewContext에 접근 가능:
     * - $this->listRenderHelper
     * - $this->columns()
     * - $this->renderPagination()
     * - $this->renderComponent()
     *
     * 경로 예시:
     * - 상대 경로: auth/login → views/Admin/{skin}/Auth/Login.php
     * - 절대 경로: /path/to/Plugin/views/History → /path/to/Plugin/views/History.php
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
                error_log("[AdminViewRenderer] Absolute view not found: {$path}");
                $this->renderFallbackError('요청하신 페이지를 찾을 수 없습니다.', $response->getViewData());
                return;
            }

            // ViewContext의 render()를 통해 include (View에서 $this 접근 가능)
            $this->viewContext->render($path, $response->getViewData());
            return;
        }

        // 상대 경로인 경우 (Core용, 기존 로직)
        $logicalPath = $viewPath;

        // 경로 조작 방지
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

        // 새 구조: views/Admin/{skin}/{Group}/{File}.php
        $path = MUBLO_VIEW_PATH
            . "/Admin/{$this->skin}/{$group}/{$file}.php";

        if (!file_exists($path)) {
            error_log("[AdminViewRenderer] View not found: Admin/{$this->skin}/{$group}/{$file}.php");
            $this->renderFallbackError('요청하신 페이지를 찾을 수 없습니다.', $response->getViewData());
            return;
        }

        // ViewContext의 render()를 통해 include (View에서 $this 접근 가능)
        $this->viewContext->render($path, $response->getViewData());
    }

    /**
     * _partial 파일 렌더링
     *
     * ViewContext를 통해 렌더링하여 $this 접근 가능
     * 경로: views/Admin/{skin}/_partial/{filename}
     */
    protected function includePartial(string $filename, array $data = []): void
    {
        $fullPath = MUBLO_VIEW_PATH . "/Admin/{$this->skin}/_partial/{$filename}";

        if (!file_exists($fullPath)) {
            if (($_ENV['APP_DEBUG'] ?? 'false') === 'true') {
                error_log("[AdminViewRenderer] Partial not found: Admin/{$this->skin}/_partial/{$filename}");
            }
            return;
        }

        // ViewContext의 render()를 통해 include (View에서 $this 접근 가능)
        $this->viewContext->render($fullPath, $data);
    }

    /**
     * 뷰 파일 미발견 시 fallback 에러 화면 렌더링
     *
     * Core의 Admin 에러 뷰가 있으면 사용, 없으면 인라인 출력
     */
    protected function renderFallbackError(string $defaultMessage, array $viewData = []): void
    {
        $message = $viewData['message'] ?? $defaultMessage;

        // Core Admin 스킨의 Error/404 뷰 시도
        $corePath = MUBLO_VIEW_PATH . "/Admin/{$this->skin}/Error/404.php";
        if (file_exists($corePath)) {
            $this->viewContext->render($corePath, array_merge($viewData, ['message' => $message]));
            return;
        }

        // Core 에러 뷰도 없으면 인라인 렌더링
        echo '<div class="container-fluid py-4">';
        echo '<div class="card"><div class="card-body text-center py-5">';
        echo '<i class="bi bi-exclamation-triangle text-warning" style="font-size:3rem"></i>';
        echo '<h4 class="mt-3">페이지를 찾을 수 없습니다</h4>';
        echo '<p class="text-muted">' . htmlspecialchars($message) . '</p>';
        echo '<button type="button" class="btn btn-secondary" onclick="history.back()">뒤로 가기</button>';
        echo '</div></div></div>';
    }
}
