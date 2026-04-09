<?php
namespace Mublo\Core\App;

use FastRoute\RouteCollector;
use FastRoute\Dispatcher as FastRouteDispatcher;
use Mublo\Core\Http\Request;
use Mublo\Core\Context\Context;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Service\Extension\ExtensionService;
use function FastRoute\simpleDispatcher;
use function FastRoute\cachedDispatcher;

/**
 * Class Router
 *
 * ============================================================
 * Router – FastRoute 기반 URL 라우팅 시스템
 * ============================================================
 *
 * 이 클래스는 HTTP 요청의 URL을 분석하여
 * 적절한 Controller와 Method를 결정하는 역할을 담당한다.
 *
 * ------------------------------------------------------------
 * [핵심 기능]
 * ------------------------------------------------------------
 *
 * 1. 명시적 라우트 매칭 (FastRoute 기반)
 * 2. Plugin/Package 라우트 자동 로드
 * 3. 미매칭 시 자동 Controller/Method 매핑 (autoResolve)
 * 4. 도메인별 라우트 캐싱 (프로덕션 환경 최적화)
 *
 * ------------------------------------------------------------
 * [도메인별 라우트 캐싱 시스템]
 * ------------------------------------------------------------
 *
 * 멀티 도메인 환경에서 각 도메인은 서로 다른 Plugin/Package를
 * 활성화할 수 있으므로, 캐시 파일을 도메인별로 분리한다.
 *
 * 캐시 파일 위치:
 * - storage/cache/routes/{domain}.cache.php
 * - 예: storage/cache/routes/shop.example.com.cache.php
 *
 * 캐시 활성화 조건:
 * - APP_DEBUG=false (프로덕션 모드)
 *
 * 캐시 무효화 방법:
 * - 특정 도메인: Router::clearRouteCache('shop.example.com')
 * - 전체: Router::clearAllRouteCache()
 * - 인스턴스: $router->clearCache()
 *
 * ------------------------------------------------------------
 * [책임]
 * ------------------------------------------------------------
 *
 * - URL → Controller/Method 매핑
 * - 라우트 파라미터 추출
 * - 미들웨어 정보 전달
 *
 * ------------------------------------------------------------
 * [금지 사항]
 * ------------------------------------------------------------
 *
 * - Controller 실행 (Dispatcher의 역할)
 * - 인증/권한 검사 (Middleware의 역할)
 * - 비즈니스 로직
 * - HTML 출력
 *
 * ------------------------------------------------------------
 */
class Router
{
    /**
     * 라우트 캐시 디렉토리 경로
     *
     * 도메인별 캐시 파일이 저장될 디렉토리
     * 자동 생성됨
     */
    private const CACHE_DIR = MUBLO_STORAGE_PATH . '/cache/routes';

    /**
     * 캐시 사용 여부
     *
     * true: 프로덕션 모드 (캐시 활성화)
     *       - 라우트 정보를 파일에 캐시하여 성능 향상
     *       - Plugin/Package 변경 시 수동 캐시 클리어 필요
     *
     * false: 개발 모드 (캐시 비활성화)
     *        - 매 요청마다 라우트 재구성
     *        - routes.php 변경 즉시 반영
     */
    private bool $useCache;

    /**
     * 현재 요청의 도메인
     *
     * 캐시 파일 경로 결정에 사용
     * dispatch() 호출 시 Context에서 설정됨
     */
    private ?string $currentDomain = null;

    /**
     * DI 컨테이너
     *
     * ExtensionService 등 서비스 접근에 사용
     */
    private ?DependencyContainer $container = null;

    /**
     * Router 생성자
     *
     * 환경 변수를 확인하여 캐시 사용 여부를 결정한다.
     * APP_DEBUG=true이면 개발 모드로 캐시를 사용하지 않는다.
     *
     * @param DependencyContainer|null $container DI 컨테이너 (활성화된 확장 필터링용)
     */
    public function __construct(?DependencyContainer $container = null)
    {
        $this->container = $container;

        // ------------------------------------------------
        // APP_DEBUG 환경 변수 확인
        //
        // 'true' 문자열이면 개발 모드 → 캐시 비활성화
        // 그 외 (false, 미설정 등)면 프로덕션 → 캐시 활성화
        // ------------------------------------------------
        $isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';

        $this->useCache = !$isDebug;
    }

    /**
     * 라우트 디스패치 (메인 진입점)
     *
     * HTTP 요청을 분석하여 실행할 Controller/Method 정보를 반환한다.
     *
     * ----------------------------------------------------
     * [처리 순서]
     * ----------------------------------------------------
     *
     * 1. Context에서 도메인 정보 추출
     * 2. FastRoute Dispatcher 생성 (도메인별 캐시 또는 실시간)
     * 3. 명시적 라우트 매칭 시도
     * 4. 매칭 성공 → Controller/Method/Params 반환
     * 5. 매칭 실패 → autoResolve로 자동 매핑
     * 6. Method Not Allowed → 예외 발생
     *
     * ----------------------------------------------------
     *
     * @param Request $request HTTP 요청 객체
     * @param Context $context 애플리케이션 컨텍스트
     * @return array{
     *   controller: class-string,  // Controller 클래스명 (FQCN)
     *   method: string,            // 실행할 메서드명
     *   params: array,             // URL 파라미터 (예: ['id' => 123])
     *   middleware: array          // 적용할 미들웨어 목록
     * }
     * @throws \RuntimeException 라우팅 실패 시
     */
    public function dispatch(Request $request, Context $context): array
    {
        // ==================================================
        // 1. 현재 도메인 설정
        //
        // Context에서 도메인 정보를 가져와 캐시 파일 경로 결정에 사용
        // 도메인이 없으면 'default'를 사용
        // ==================================================
        $this->currentDomain = $context->getDomain() ?? 'default';

        // ==================================================
        // 2. FastRoute Dispatcher 생성
        //
        // 프로덕션: cachedDispatcher 사용 (도메인별 캐시)
        //          - 캐시 파일이 있으면 로드
        //          - 없으면 생성 후 캐시
        //
        // 개발: simpleDispatcher 사용
        //       - 매번 라우트 테이블 재구성
        //       - routes.php 변경 즉시 반영
        // ==================================================
        $dispatcher = $this->createDispatcher($context);

        // ==================================================
        // 3. FastRoute 라우트 매칭 실행
        //
        // dispatch()는 다음 중 하나를 반환:
        // - [FOUND, handler, params]
        // - [NOT_FOUND]
        // - [METHOD_NOT_ALLOWED, allowedMethods]
        // ==================================================
        // Trailing slash 정규화: /products/ → /products (루트 '/' 제외)
        $path = $request->getPath();
        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        $routeInfo = $dispatcher->dispatch(
            $request->getMethod(),
            $path
        );

        // ==================================================
        // 4. FastRoute 매칭 성공
        //
        // 명시적 라우트에서 찾음
        // handler 배열에서 controller, method, middleware 추출
        // ==================================================
        if ($routeInfo[0] === FastRouteDispatcher::FOUND) {
            return [
                'controller' => $routeInfo[1]['controller'],
                'method'     => $routeInfo[1]['method'],
                'params'     => array_merge($routeInfo[1]['defaults'] ?? [], $routeInfo[2] ?? []),
                'middleware' => $routeInfo[1]['middleware'] ?? [],
            ];
        }

        // ==================================================
        // 5. FastRoute 미매칭 → 자동 매핑
        //
        // 명시적 라우트에 없는 URL은
        // URL 패턴을 분석하여 Controller/Method 자동 결정
        //
        // 예: /board/list → BoardController@list
        // ==================================================
        if ($routeInfo[0] === FastRouteDispatcher::NOT_FOUND) {
            // 정적 에셋 경로는 PHP가 처리하지 않음
            // ErrorDocument 404 /index.php 설정으로 유입된 경우 조용히 404 반환
            $path = $request->getPath();
            if (str_starts_with($path, '/assets/') || str_starts_with($path, '/serve/')) {
                throw new \RuntimeException('404 Not Found', 404);
            }
            return $this->autoResolve($path, $context);
        }

        // ==================================================
        // 6. Method Not Allowed
        //
        // URL은 존재하지만 HTTP Method가 맞지 않음
        // 예: POST /login 라우트만 있는데 GET /login 요청
        // ==================================================
        if ($routeInfo[0] === FastRouteDispatcher::METHOD_NOT_ALLOWED) {
            throw new \RuntimeException('405 Method Not Allowed');
        }

        // ==================================================
        // 7. 예상치 못한 라우팅 결과
        // ==================================================
        throw new \RuntimeException('Routing error');
    }

    /**
     * FastRoute Dispatcher 생성
     *
     * 환경과 도메인에 따라 적절한 Dispatcher를 생성하여 반환한다.
     *
     * ----------------------------------------------------
     * [프로덕션 모드 (useCache = true)]
     * ----------------------------------------------------
     *
     * cachedDispatcher를 사용하여 라우트 정보를 도메인별로 캐시한다.
     *
     * 캐시 파일: storage/cache/routes/{domain}.cache.php
     *
     * 1. 캐시 파일 존재 시:
     *    - 파일에서 라우트 데이터 로드 (매우 빠름)
     *    - routes.php 파싱 없음
     *    - 정규식 컴파일 없음
     *
     * 2. 캐시 파일 미존재 시:
     *    - 모든 라우트 정의 실행
     *    - 결과를 캐시 파일에 저장
     *    - 다음 요청부터 캐시 사용
     *
     * ----------------------------------------------------
     * [개발 모드 (useCache = false)]
     * ----------------------------------------------------
     *
     * simpleDispatcher를 사용하여 매번 라우트를 구성한다.
     *
     * - routes.php 변경 즉시 반영
     * - 디버깅 용이
     * - 성능은 다소 떨어짐
     *
     * ----------------------------------------------------
     *
     * @param Context $context 애플리케이션 컨텍스트
     * @return FastRouteDispatcher
     */
    private function createDispatcher(Context $context): FastRouteDispatcher
    {
        // ------------------------------------------------
        // 라우트 정의 콜백
        //
        // 이 콜백 안에서 모든 라우트가 정의된다:
        // - Core 라우트 (Front, Admin)
        // - Plugin 라우트 (도메인별 활성화된 것만)
        // - Package 라우트 (도메인별 활성화된 것만)
        // ------------------------------------------------
        $routeDefinitionCallback = function (RouteCollector $r) use ($context) {
            $this->registerCoreRoutes($r);
            $this->loadPluginRoutes($r, $context);
            $this->loadPackageRoutes($r, $context);
        };

        // ------------------------------------------------
        // 프로덕션 모드: cachedDispatcher 사용 (도메인별)
        // ------------------------------------------------
        if ($this->useCache) {
            // 캐시 디렉토리 확인 및 생성
            $this->ensureCacheDirectoryExists();

            // 도메인별 캐시 파일 경로
            $cacheFile = $this->getCacheFilePath();

            // routes.php가 캐시보다 새로우면 캐시 무효화
            $this->invalidateStaleCacheFile($cacheFile);

            return cachedDispatcher($routeDefinitionCallback, [
                'cacheFile' => $cacheFile,
                'cacheDisabled' => false,
            ]);
        }

        // ------------------------------------------------
        // 개발 모드: simpleDispatcher 사용
        //
        // 매 요청마다 라우트 테이블을 새로 구성
        // routes.php 수정 시 즉시 반영됨
        // ------------------------------------------------
        return simpleDispatcher($routeDefinitionCallback);
    }

    /**
     * 도메인별 캐시 파일 경로 반환
     *
     * 도메인명에서 파일시스템에 안전한 이름을 생성한다.
     * 특수문자는 유지하되, 경로 구분자가 될 수 있는 문자만 치환
     *
     * @return string 캐시 파일 전체 경로
     */
    private function getCacheFilePath(): string
    {
        // ------------------------------------------------
        // 도메인명을 파일명으로 변환
        //
        // shop.example.com → shop.example.com.cache.php
        // localhost:8080 → localhost_8080.cache.php
        //
        // 파일시스템에서 문제가 될 수 있는 문자 치환:
        // - : (포트 구분자) → _
        // - / (경로 구분자) → _ (혹시 모를 상황 대비)
        // ------------------------------------------------
        $safeDomain = str_replace([':', '/'], '_', $this->currentDomain);

        return self::CACHE_DIR . '/' . $safeDomain . '.cache.php';
    }

    /**
     * Core 라우트 등록
     *
     * 프레임워크 기본 라우트를 정의한다.
     * Plugin/Package 라우트보다 먼저 등록되어 우선순위가 높다.
     *
     * ----------------------------------------------------
     * [라우트 그룹]
     * ----------------------------------------------------
     *
     * 1. Front 라우트: 사용자 페이지
     *    - 메인 페이지, 게시판 등
     *
     * 2. Admin 라우트: 관리자 페이지
     *    - 로그인, 대시보드 등
     *    - AdminMiddleware 적용
     *
     * 3. API 라우트: 시스템 API
     *    - CSRF 토큰, 정적 파일 서빙 등
     *
     * ----------------------------------------------------
     *
     * @param RouteCollector $r FastRoute RouteCollector
     */
    private function registerCoreRoutes(RouteCollector $r): void
    {
        // =============================================
        // Front 라우트 - 사용자 영역
        // =============================================

        $authMiddleware = [\Mublo\Core\Middleware\AuthMiddleware::class];

        // robots.txt (도메인 설정 우선, 없으면 public/robots.txt 폴백)
        $r->addRoute('GET', '/robots.txt', [
            'controller' => \Mublo\Controller\Front\RobotsController::class,
            'method'     => 'index',
            'middleware' => [],
        ]);

        // 메인 페이지
        $r->addRoute('GET', '/', [
            'controller' => \Mublo\Controller\Front\IndexController::class,
            'method'     => 'index',
            'middleware' => [],
        ]);

        // 전체 검색
        $r->addRoute('GET', '/search', [
            'controller' => \Mublo\Controller\Front\SearchController::class,
            'method'     => 'index',
            'middleware' => [],
        ]);

        // --------------------------------------------
        // 블록 페이지 라우트
        //
        // {code}: 페이지 코드 (영소문자, 숫자, 하이픈)
        // --------------------------------------------
        $r->addRoute('GET', '/p/{code}', [
            'controller' => \Mublo\Controller\Front\PageController::class,
            'method'     => 'view',
            'middleware' => [],
        ]);

        // --------------------------------------------
        // 약관/정책 열람 라우트
        // --------------------------------------------
        $r->addRoute('GET', '/policy/view/{slug}', [
            'controller' => \Mublo\Controller\Front\PolicyController::class,
            'method'     => 'view',
            'middleware' => [],
        ]);
        $r->addRoute('GET', '/terms', [
            'controller' => \Mublo\Controller\Front\PolicyController::class,
            'method'     => 'view',
            'defaults'   => ['slug' => 'terms'],
            'middleware' => [],
        ]);
        $r->addRoute('GET', '/privacy', [
            'controller' => \Mublo\Controller\Front\PolicyController::class,
            'method'     => 'view',
            'defaults'   => ['slug' => 'privacy'],
            'middleware' => [],
        ]);

        // --------------------------------------------
        // 회원 라우트
        //
        // 회원가입 3단계: 약관동의 → 정보입력 → 가입완료
        // --------------------------------------------
        $r->addRoute('GET', '/member/register', [
            'controller' => \Mublo\Controller\Front\MemberController::class,
            'method'     => 'registerAgree',
            'middleware' => [],
        ]);
        $r->addRoute('POST', '/member/register/agree', [
            'controller' => \Mublo\Controller\Front\MemberController::class,
            'method'     => 'registerAgreeProcess',
            'middleware' => [],
        ]);
        $r->addRoute('GET', '/member/register/form', [
            'controller' => \Mublo\Controller\Front\MemberController::class,
            'method'     => 'registerForm',
            'middleware' => [],
        ]);
        $r->addRoute('POST', '/member/register/form', [
            'controller' => \Mublo\Controller\Front\MemberController::class,
            'method'     => 'register',
            'middleware' => [],
        ]);
        $r->addRoute('GET', '/member/register/complete', [
            'controller' => \Mublo\Controller\Front\MemberController::class,
            'method'     => 'registerComplete',
            'middleware' => [],
        ]);
        $r->addRoute('GET', '/member/register/pending', [
            'controller' => \Mublo\Controller\Front\MemberController::class,
            'method'     => 'registerPending',
            'middleware' => [],
        ]);
        $r->addRoute('POST', '/member/check-userid', [
            'controller' => \Mublo\Controller\Front\MemberController::class,
            'method'     => 'checkUserId',
            'middleware' => [],
        ]);
        $r->addRoute('POST', '/member/check-nickname', [
            'controller' => \Mublo\Controller\Front\MemberController::class,
            'method'     => 'checkNickname',
            'middleware' => [],
        ]);
        $r->addRoute('POST', '/member/upload-field-file', [
            'controller' => \Mublo\Controller\Front\MemberController::class,
            'method'     => 'uploadFieldFile',
            'middleware' => [],
        ]);

        // --------------------------------------------
        // 보안 파일 다운로드
        // --------------------------------------------
        $r->addRoute('GET', '/download/{token}', [
            'controller' => \Mublo\Controller\Api\DownloadController::class,
            'method'     => 'download',
            'middleware' => [],
        ]);

        // --------------------------------------------
        // 프론트 마이페이지 라우트 (로그인 필수)
        // --------------------------------------------
        $r->addRoute('GET', '/mypage', [
            'controller' => \Mublo\Controller\Front\MypageController::class,
            'method'     => 'index',
            'middleware' => $authMiddleware,
        ]);
        $r->addRoute('GET', '/mypage/profile', [
            'controller' => \Mublo\Controller\Front\MypageController::class,
            'method'     => 'profile',
            'middleware' => $authMiddleware,
        ]);
        $r->addRoute('POST', '/mypage/profile', [
            'controller' => \Mublo\Controller\Front\MypageController::class,
            'method'     => 'updateProfile',
            'middleware' => $authMiddleware,
        ]);
        $r->addRoute('GET', '/mypage/balance', [
            'controller' => \Mublo\Controller\Front\MypageController::class,
            'method'     => 'balance',
            'middleware' => $authMiddleware,
        ]);
        $r->addRoute('GET', '/mypage/articles', [
            'controller' => \Mublo\Controller\Front\MypageController::class,
            'method'     => 'articles',
            'middleware' => $authMiddleware,
        ]);
        $r->addRoute('GET', '/mypage/comments', [
            'controller' => \Mublo\Controller\Front\MypageController::class,
            'method'     => 'comments',
            'middleware' => $authMiddleware,
        ]);
        $r->addRoute('GET', '/mypage/withdraw', [
            'controller' => \Mublo\Controller\Front\MypageController::class,
            'method'     => 'withdraw',
            'middleware' => $authMiddleware,
        ]);
        $r->addRoute('POST', '/mypage/withdraw', [
            'controller' => \Mublo\Controller\Front\MypageController::class,
            'method'     => 'withdraw',
            'middleware' => $authMiddleware,
        ]);

        // --------------------------------------------
        // 프론트 인증 라우트
        //
        // 로그인/로그아웃 (인증 불필요)
        // --------------------------------------------
        $r->addRoute('GET', '/login', [
            'controller' => \Mublo\Controller\Front\AuthController::class,
            'method'     => 'loginForm',
            'middleware' => [],
        ]);
        $r->addRoute('POST', '/login', [
            'controller' => \Mublo\Controller\Front\AuthController::class,
            'method'     => 'login',
            'middleware' => [],
        ]);
        $r->addRoute(['GET', 'POST'], '/logout', [
            'controller' => \Mublo\Controller\Front\AuthController::class,
            'method'     => 'logout',
            'middleware' => [],
        ]);
        $r->addRoute('GET', '/find-account', [
            'controller' => \Mublo\Controller\Front\AuthController::class,
            'method'     => 'findAccountForm',
            'middleware' => [],
        ]);
        $r->addRoute('POST', '/find-account/find-userid', [
            'controller' => \Mublo\Controller\Front\AuthController::class,
            'method'     => 'findUserId',
            'middleware' => [],
        ]);
        $r->addRoute('POST', '/find-account/request-reset', [
            'controller' => \Mublo\Controller\Front\AuthController::class,
            'method'     => 'requestReset',
            'middleware' => [],
        ]);
        $r->addRoute('GET', '/find-account/reset-password', [
            'controller' => \Mublo\Controller\Front\AuthController::class,
            'method'     => 'resetPasswordForm',
            'middleware' => [],
        ]);
        $r->addRoute('POST', '/find-account/reset-password', [
            'controller' => \Mublo\Controller\Front\AuthController::class,
            'method'     => 'resetPassword',
            'middleware' => [],
        ]);

        // =============================================
        // Admin 라우트 - 관리자 영역
        // =============================================

        // --------------------------------------------
        // 관리자 인증 라우트
        //
        // 로그인/로그아웃은 인증 미들웨어 적용 안 함
        // (비로그인 상태에서 접근해야 하므로)
        // --------------------------------------------
        $r->addRoute('GET', '/admin/login', [
            'controller' => \Mublo\Controller\Admin\AuthController::class,
            'method'     => 'loginForm',
            'middleware' => [],  // 인증 불필요
        ]);

        $r->addRoute('POST', '/admin/login', [
            'controller' => \Mublo\Controller\Admin\AuthController::class,
            'method'     => 'login',
            'middleware' => [],  // 인증 불필요
        ]);

        $r->addRoute('POST', '/admin/logout', [
            'controller' => \Mublo\Controller\Admin\AuthController::class,
            'method'     => 'logout',
            'middleware' => [],  // 인증 불필요 (로그아웃 처리)
        ]);

        // 대리 로그인 (상위 관리자 → 하위 도메인)
        $r->addRoute('GET', '/admin/proxy-login', [
            'controller' => \Mublo\Controller\Admin\AuthController::class,
            'method'     => 'proxyLoginVerify',
            'middleware' => [],  // 인증 불필요 (토큰 기반 인증)
        ]);

        // --------------------------------------------
        // 관리자 대시보드 (루트 경로)
        //
        // /admin → /admin/dashboard 와 동일하게 처리
        // 이 외 Admin 라우트는 autoResolve에서 자동 처리
        // --------------------------------------------
        $r->addRoute('GET', '/admin', [
            'controller' => \Mublo\Controller\Admin\DashboardController::class,
            'method'     => 'index',
            'middleware' => [\Mublo\Core\Middleware\AdminMiddleware::class],
        ]);

        // --------------------------------------------
        // 관리자 대시보드 API
        // --------------------------------------------
        $adminMiddleware = [\Mublo\Core\Middleware\AdminMiddleware::class];

        $r->addRoute('POST', '/admin/dashboard/widget/hide', [
            'controller' => \Mublo\Controller\Admin\DashboardController::class,
            'method'     => 'hideWidget',
            'middleware' => $adminMiddleware,
        ]);

        $r->addRoute('POST', '/admin/dashboard/widget/show', [
            'controller' => \Mublo\Controller\Admin\DashboardController::class,
            'method'     => 'showWidget',
            'middleware' => $adminMiddleware,
        ]);

        $r->addRoute('POST', '/admin/dashboard/widget/move', [
            'controller' => \Mublo\Controller\Admin\DashboardController::class,
            'method'     => 'moveWidget',
            'middleware' => $adminMiddleware,
        ]);

        $r->addRoute('POST', '/admin/dashboard/layout/reset', [
            'controller' => \Mublo\Controller\Admin\DashboardController::class,
            'method'     => 'resetLayout',
            'middleware' => $adminMiddleware,
        ]);

        $r->addRoute('POST', '/admin/dashboard/layout/reorder', [
            'controller' => \Mublo\Controller\Admin\DashboardController::class,
            'method'     => 'reorderWidgets',
            'middleware' => $adminMiddleware,
        ]);

        // --------------------------------------------
        // 관리자 리포트 API
        // --------------------------------------------

        $r->addRoute('POST', '/admin/report/{reportName}/download', [
            'controller' => \Mublo\Controller\Admin\ReportController::class,
            'method'     => 'download',
            'middleware' => $adminMiddleware,
        ]);

        $r->addRoute('POST', '/admin/report/{reportName}/chunks', [
            'controller' => \Mublo\Controller\Admin\ReportController::class,
            'method'     => 'chunks',
            'middleware' => $adminMiddleware,
        ]);

        $r->addRoute('POST', '/admin/report/{reportName}/merge', [
            'controller' => \Mublo\Controller\Admin\ReportController::class,
            'method'     => 'merge',
            'middleware' => $adminMiddleware,
        ]);

        $r->addRoute('GET', '/admin/report/files/{fileId}', [
            'controller' => \Mublo\Controller\Admin\ReportController::class,
            'method'     => 'file',
            'middleware' => $adminMiddleware,
        ]);

        // =============================================
        // API 라우트 - 시스템 API
        // =============================================

        // --------------------------------------------
        // CSRF 토큰 API (v1)
        //
        // MubloRequest.js에서 AJAX 요청 시 사용
        // API 버전 관리: /api/v1/csrf/...
        // --------------------------------------------
        $r->addRoute('GET', '/api/v1/csrf/token', [
            'controller' => \Mublo\Controller\Api\CsrfController::class,
            'method'     => 'token',
            'middleware' => [],
        ]);

        $r->addRoute('POST', '/api/v1/csrf/regenerate', [
            'controller' => \Mublo\Controller\Api\CsrfController::class,
            'method'     => 'regenerate',
            'middleware' => [],
        ]);

        // --------------------------------------------
        // 정적 파일 서빙 (ServeController)
        //
        // Plugin, Package, Views의 정적 파일 제공
        // {name}: Plugin/Package 이름
        // {path:.+}: 파일 경로 (슬래시 포함 허용)
        // --------------------------------------------
        $r->addRoute('GET', '/serve/package/{name}/{path:.+}', [
            'controller' => \Mublo\Controller\Api\ServeController::class,
            'method'     => 'package',
            'middleware' => [],
        ]);

        $r->addRoute('GET', '/serve/plugin/{name}/{path:.+}', [
            'controller' => \Mublo\Controller\Api\ServeController::class,
            'method'     => 'plugin',
            'middleware' => [],
        ]);

        // Block 스킨 에셋 서빙
        // /serve/block/{type}/{skin}/{path} → views/Block/{type}/{skin}/{path}
        $r->addRoute('GET', '/serve/block/{type}/{skin}/{path:.+}', [
            'controller' => \Mublo\Controller\Api\ServeController::class,
            'method'     => 'blockSkinAsset',
            'middleware' => [],
        ]);

        $r->addRoute('GET', '/serve/views/admin/{path:.+}', [
            'controller' => \Mublo\Controller\Api\ServeController::class,
            'method'     => 'viewsAdmin',
            'middleware' => [],
        ]);

        // Admin 스킨 에셋 서빙
        // /serve/admin/{skin}/{path} → views/Admin/{skin}/_assets/{path}
        $r->addRoute('GET', '/serve/admin/{skin}/{path:.+}', [
            'controller' => \Mublo\Controller\Api\ServeController::class,
            'method'     => 'adminSkinAsset',
            'middleware' => [],
        ]);

        // Front Content View 스킨 에셋 서빙
        // /serve/front/view/{group}/{skin}/{path} → views/Front/{Group}/{skin}/_assets/{path}
        $r->addRoute('GET', '/serve/front/view/{group}/{skin}/{path:.+}', [
            'controller' => \Mublo\Controller\Api\ServeController::class,
            'method'     => 'frontViewSkinAsset',
            'middleware' => [],
        ]);

        // Front 프레임 스킨 에셋 서빙
        // /serve/front/{skin}/{path} → views/Front/frame/{skin}/_assets/{path}
        $r->addRoute('GET', '/serve/front/{skin}/{path:.+}', [
            'controller' => \Mublo\Controller\Api\ServeController::class,
            'method'     => 'frontSkinAsset',
            'middleware' => [],
        ]);

        $r->addRoute('GET', '/serve/views/front/{path:.+}', [
            'controller' => \Mublo\Controller\Api\ServeController::class,
            'method'     => 'viewsFront',
            'middleware' => [],
        ]);
    }

    /**
     * Plugin routes.php 로드
     *
     * 각 Plugin 디렉토리의 routes.php를 찾아 로드한다.
     * routes.php는 PrefixedRouteCollector를 받는 콜백을 반환해야 한다.
     *
     * ----------------------------------------------------
     * [도메인별 활성화 체크]
     * ----------------------------------------------------
     *
     * ExtensionService를 통해 도메인별 활성화된 Plugin만 로드한다.
     * 비활성화된 Plugin의 라우트는 등록되지 않는다.
     *
     * ----------------------------------------------------
     * [URL 접두사 자동 적용]
     * ----------------------------------------------------
     *
     * PrefixedRouteCollector를 통해 URL 접두사가 자동 적용된다.
     *
     * - Front: /{plugin_name}/...
     * - Admin: /admin/{plugin_name}/...
     *
     * 예) MemberPoint 플러그인:
     *     /history      → /memberpoint/history
     *     /admin/list   → /admin/memberpoint/list
     *
     * ----------------------------------------------------
     * [routes.php 형식]
     * ----------------------------------------------------
     *
     * ```php
     * return function (PrefixedRouteCollector $r): void {
     *     $r->addRoute('GET', '/history', [...]);
     *     $r->addRoute('GET', '/admin/list', [...]);
     * };
     * ```
     *
     * ----------------------------------------------------
     *
     * @param RouteCollector $r FastRoute RouteCollector
     * @param Context $context 애플리케이션 컨텍스트
     */
    private function loadPluginRoutes(RouteCollector $r, Context $context): void
    {
        $pluginDir = MUBLO_PLUGIN_PATH;

        // Plugin 디렉토리가 없으면 스킵
        if (!is_dir($pluginDir)) {
            return;
        }

        // ------------------------------------------------
        // 도메인별 활성화된 Plugin 목록 조회
        // ------------------------------------------------
        $enabledPlugins = $this->getEnabledPluginNames($context);

        // 모든 Plugin 디렉토리 탐색
        $plugins = glob($pluginDir . '/*', GLOB_ONLYDIR);

        foreach ($plugins as $pluginPath) {
            $pluginName = basename($pluginPath);

            // ----------------------------------------
            // Plugin 활성화 체크
            //
            // 비활성화된 Plugin은 스킵
            // (enabledPlugins가 null이면 전체 로드 - 컨테이너 없는 경우)
            // ----------------------------------------
            if ($enabledPlugins !== null && !$this->isExtensionEnabled($pluginName, $enabledPlugins)) {
                continue;
            }

            // super_only 플러그인: 하위 도메인에서 라우트 차단
            if ($this->isSuperOnlyPluginOnSubSite($pluginPath, $context)) {
                continue;
            }

            $routesFile = $pluginPath . '/routes.php';

            // routes.php가 있는 Plugin만 처리
            if (!file_exists($routesFile)) {
                continue;
            }

            // ----------------------------------------
            // Plugin 이름에서 URL 접두사 생성
            //
            // 디렉토리명: MemberPoint
            // 접두사: memberpoint (소문자)
            // ----------------------------------------
            $prefix = $this->buildRoutePrefix($pluginName);

            // ----------------------------------------
            // routes.php 로드
            //
            // 콜백 함수를 반환해야 함
            // function(PrefixedRouteCollector $r): void
            // ----------------------------------------
            $callback = require $routesFile;

            if (is_callable($callback)) {
                // PrefixedRouteCollector로 래핑하여 접두사 강제 적용
                $prefixedCollector = new PrefixedRouteCollector($r, $prefix, 'plugin');
                $callback($prefixedCollector);
            }
        }
    }

    /**
     * super_only 플러그인이 하위 도메인에서 접근되는지 확인
     *
     * super_only 플러그인은 루트 도메인에서만 라우트 접근 허용
     */
    private function isSuperOnlyPluginOnSubSite(string $pluginPath, Context $context): bool
    {
        $manifestFile = $pluginPath . '/manifest.json';
        if (!file_exists($manifestFile)) {
            return false;
        }

        $manifest = json_decode(file_get_contents($manifestFile), true);
        if (empty($manifest['super_only'])) {
            return false;
        }

        // 루트 도메인이면 허용
        $domainId = $context->getDomainId();
        $group = $context->getDomainGroup();

        if ($domainId === null || $group === null || $group === '') {
            return false;
        }

        $rootId = (int) explode('/', $group)[0];
        return $rootId > 0 && $rootId !== $domainId;
    }

    /**
     * 활성화된 Plugin 이름 목록 조회
     *
     * @param Context $context
     * @return array|null 활성화된 플러그인 이름 배열, 컨테이너가 없으면 null
     */
    private function getEnabledPluginNames(Context $context): ?array
    {
        if ($this->container === null) {
            return null; // 컨테이너 없으면 전체 로드 (하위 호환성)
        }

        $domainId = $context->getDomainId();
        if (!$domainId) {
            return []; // 도메인 없으면 빈 배열 (아무것도 로드 안 함)
        }

        try {
            $extensionService = $this->container->get(ExtensionService::class);
            return $extensionService->getEnabledPlugins($domainId);
        } catch (\Throwable $e) {
            error_log("Failed to get enabled plugins: " . $e->getMessage());
            return null; // 에러 시 전체 로드 (null = 필터링 안 함 시그널)
        }
    }

    /**
     * Package routes.php 로드
     *
     * 각 Package 디렉토리의 routes.php를 찾아 로드한다.
     * Plugin과 동일한 방식으로 PrefixedRouteCollector를 통해 접두사가 적용된다.
     *
     * ----------------------------------------------------
     * [도메인별 활성화 체크]
     * ----------------------------------------------------
     *
     * ExtensionService를 통해 도메인별 활성화된 Package만 로드한다.
     * 비활성화된 Package의 라우트는 등록되지 않는다.
     *
     * ----------------------------------------------------
     * [Package vs Plugin]
     * ----------------------------------------------------
     *
     * - Plugin: 단일 기능 확장 (포인트, 배너 등)
     * - Package: 복합 기능 확장 (쇼핑몰, 예약 시스템 등)
     *
     * 라우팅 방식은 동일하나, Package는 더 복잡한 MVC 구조를 가짐
     *
     * ----------------------------------------------------
     * [URL 접두사 자동 적용]
     * ----------------------------------------------------
     *
     * - Front: /{package_name}/...
     * - Admin: /admin/{package_name}/...
     *
     * 예) Shop 패키지:
     *     /goods        → /shop/goods
     *     /admin/order  → /admin/shop/order
     *
     * ----------------------------------------------------
     *
     * @param RouteCollector $r FastRoute RouteCollector
     * @param Context $context 애플리케이션 컨텍스트
     */
    private function loadPackageRoutes(RouteCollector $r, Context $context): void
    {
        $packageDir = MUBLO_PACKAGE_PATH;

        // Packages 디렉토리가 없으면 스킵
        if (!is_dir($packageDir)) {
            return;
        }

        // ------------------------------------------------
        // 도메인별 활성화된 Package 목록 조회
        // ------------------------------------------------
        $enabledPackages = $this->getEnabledPackageNames($context);

        // 모든 Package 디렉토리 탐색
        $packages = glob($packageDir . '/*', GLOB_ONLYDIR);

        foreach ($packages as $packagePath) {
            $packageName = basename($packagePath);

            // ----------------------------------------
            // Package 활성화 체크
            //
            // 비활성화된 Package는 스킵
            // (enabledPackages가 null이면 전체 로드 - 컨테이너 없는 경우)
            // ----------------------------------------
            if ($enabledPackages !== null && !$this->isExtensionEnabled($packageName, $enabledPackages)) {
                continue;
            }

            $routesFile = $packagePath . '/routes.php';

            // routes.php가 있는 Package만 처리
            if (!file_exists($routesFile)) {
                continue;
            }

            // ----------------------------------------
            // Package 이름에서 URL 접두사 생성
            //
            // 디렉토리명: Shop
            // 접두사: shop (소문자)
            // ----------------------------------------
            $prefix = $this->buildRoutePrefix($packageName);

            // routes.php 로드 및 콜백 실행
            $callback = require $routesFile;

            if (is_callable($callback)) {
                $prefixedCollector = new PrefixedRouteCollector($r, $prefix, 'package');
                $callback($prefixedCollector);
            }
        }
    }

    /**
     * 활성화된 Package 이름 목록 조회
     *
     * @param Context $context
     * @return array|null 활성화된 패키지 이름 배열, 컨테이너가 없으면 null
     */
    private function getEnabledPackageNames(Context $context): ?array
    {
        if ($this->container === null) {
            return null; // 컨테이너 없으면 전체 로드 (하위 호환성)
        }

        $domainId = $context->getDomainId();
        if (!$domainId) {
            return []; // 도메인 없으면 빈 배열 (아무것도 로드 안 함)
        }

        try {
            $extensionService = $this->container->get(ExtensionService::class);
            return $extensionService->getEnabledPackages($domainId);
        } catch (\Throwable $e) {
            error_log("Failed to get enabled packages: " . $e->getMessage());
            return null; // 에러 시 전체 로드 (null = 필터링 안 함 시그널)
        }
    }

    /**
     * 확장 이름이 활성화 목록에 포함되는지 확인
     *
     * 이름 비교 시 아래 표기를 모두 동등하게 처리한다.
     * - AutoForm
     * - auto-form
     * - auto_form
     * - autoform
     */
    private function isExtensionEnabled(string $name, array $enabledList): bool
    {
        $normalizedName = $this->normalizeExtensionName($name);
        foreach ($enabledList as $enabledName) {
            if (!is_string($enabledName)) {
                continue;
            }
            if ($this->normalizeExtensionName($enabledName) === $normalizedName) {
                return true;
            }
        }

        return false;
    }

    /**
     * URL 접두사 생성
     *
     * 예:
     * - AutoForm  -> auto-form
     * - auto_form -> auto-form
     * - auto-form -> auto-form
     */
    private function buildRoutePrefix(string $name): string
    {
        $prefix = preg_replace('/([a-z0-9])([A-Z])/', '$1-$2', $name);
        $prefix = str_replace('_', '-', (string) $prefix);
        $prefix = strtolower($prefix);
        $prefix = preg_replace('/-+/', '-', $prefix);
        return trim((string) $prefix, '-');
    }

    /**
     * 확장 이름 정규화 (매칭 비교용)
     */
    private function normalizeExtensionName(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name)) ?? '';
    }

    /**
     * 자동 Controller / Method 매핑
     *
     * 명시적 라우트에서 매칭되지 않은 URL을
     * 규칙 기반으로 Controller/Method에 매핑한다.
     *
     * ----------------------------------------------------
     * [매핑 규칙]
     * ----------------------------------------------------
     *
     * URL 패턴                    → Controller@Method, params
     * /                           → IndexController@index
     * /main                       → MainController@index
     * /board/list                 → BoardController@list
     * /admin/member/edit          → (Admin) MemberController@edit
     * /admin/member/edit/123      → (Admin) MemberController@edit, ['123']
     * /admin/board/view/notice/5  → (Admin) BoardController@view, ['notice', '5']
     *
     * ----------------------------------------------------
     * [URL 파라미터]
     * ----------------------------------------------------
     *
     * 세 번째 세그먼트부터 params 배열로 Controller에 전달된다.
     * Controller 메서드 시그니처: function method(array $params, Context $context)
     *
     * 예: /admin/member-field/edit/42
     *     → MemberFieldController@edit
     *     → $params = ['42']
     *
     * ----------------------------------------------------
     * [Admin 영역 판단]
     * ----------------------------------------------------
     *
     * Context.isAdmin()이 true이면:
     * - 네임스페이스: Mublo\Controller\Admin\
     *
     * Context.isAdmin()이 false이면:
     * - 네임스페이스: Mublo\Controller\Front\
     *
     * ----------------------------------------------------
     * [미들웨어]
     * ----------------------------------------------------
     *
     * - Admin 영역: AdminMiddleware 자동 적용
     * - Front 영역: 미들웨어 없음 (필요시 명시적 라우트 정의)
     *
     * ----------------------------------------------------
     *
     * @param string $path URL 경로
     * @param Context $context 애플리케이션 컨텍스트
     * @return array{
     *   controller: class-string,
     *   method: string,
     *   params: array,
     *   middleware: array
     * }
     */
    private function autoResolve(string $path, Context $context): array
    {
        // ------------------------------------------------
        // "/" 또는 빈 경로 → IndexController@index
        // ------------------------------------------------
        if ($path === '/' || $path === '') {
            return [
                'controller' => \Mublo\Controller\Front\IndexController::class,
                'method'     => 'index',
                'params'     => [],
                'middleware' => [],
            ];
        }

        // ------------------------------------------------
        // URL 세그먼트 분리
        //
        // /board/list → ['board', 'list']
        // /admin/member/edit → ['admin', 'member', 'edit']
        //
        // array_filter: 빈 문자열 제거 (양끝 슬래시)
        // array_values: 인덱스 재정렬
        // ------------------------------------------------
        $segments = array_values(
            array_filter(explode('/', $path))
        );

        // ------------------------------------------------
        // Admin 영역일 경우 'admin' 세그먼트 제거
        //
        // /admin/settings → ['admin', 'settings']
        // Admin 영역에서는 'admin'을 건너뛰고 처리
        // ['settings'] → SettingsController@index
        //
        // /admin/member/edit → ['admin', 'member', 'edit']
        // Admin 영역에서는 ['member', 'edit']로 처리
        // → MemberController@edit
        // ------------------------------------------------
        if ($context->isAdmin() && !empty($segments) && $segments[0] === 'admin') {
            array_shift($segments);
        }

        // ------------------------------------------------
        // 세그먼트가 비어있으면 (Admin 루트: /admin)
        // DashboardController@index로 처리
        // ------------------------------------------------
        if (empty($segments)) {
            $controllerName = 'DashboardController';
            $method = 'index';
            $params = [];
        } else {
            // ------------------------------------------------
            // Controller 이름 결정
            //
            // 첫 번째 세그먼트를 PascalCase로 변환
            // settings → SettingsController
            // my-page → MyPageController (kebab-case 지원)
            // ------------------------------------------------
            $controllerName = str_replace(' ', '', ucwords(str_replace('-', ' ', $segments[0]))) . 'Controller';

            // ------------------------------------------------
            // Method 이름 결정
            //
            // 두 번째 세그먼트가 있으면 사용
            // 없으면 'index'가 기본값
            // view-detail → viewDetail (camelCase 변환)
            // ------------------------------------------------
            $rawMethod = $segments[1] ?? 'index';
            $method = lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $rawMethod))));

            // ------------------------------------------------
            // URL 파라미터 추출
            //
            // 세 번째 세그먼트부터 params 배열로 전달
            // /admin/member/edit/123     → ['123']
            // /admin/board/view/notice/5 → ['notice', '5']
            // ------------------------------------------------
            $params = array_slice($segments, 2);
        }

        // ------------------------------------------------
        // 네임스페이스 결정
        //
        // Context.isAdmin()에 따라 분기
        // - Admin: Mublo\Controller\Admin\
        // - Front: Mublo\Controller\Front\
        // ------------------------------------------------
        $namespace = $context->isAdmin()
            ? 'Mublo\\Controller\\Admin\\'
            : 'Mublo\\Controller\\Front\\';

        $controllerClass = $namespace . $controllerName;

        // ------------------------------------------------
        // 클래스/메서드 존재 검증
        //
        // 존재하지 않는 Controller나 public 메서드로의 접근을 차단
        // 내부 메서드가 의도치 않게 엔드포인트로 노출되는 것을 방지
        // ------------------------------------------------
        if (!class_exists($controllerClass)) {
            throw new \RuntimeException('404 Not Found');
        }

        if (!method_exists($controllerClass, $method)) {
            throw new \RuntimeException('404 Not Found');
        }

        $reflMethod = new \ReflectionMethod($controllerClass, $method);
        if (!$reflMethod->isPublic() || $reflMethod->isStatic()) {
            throw new \RuntimeException('404 Not Found');
        }

        // ------------------------------------------------
        // 미들웨어 결정
        //
        // Admin 영역은 자동으로 AdminMiddleware 적용
        // Front 영역은 미들웨어 없음
        // ------------------------------------------------
        $middleware = $context->isAdmin()
            ? [\Mublo\Core\Middleware\AdminMiddleware::class]
            : [];

        return [
            'controller' => $controllerClass,
            'method'     => $method,
            'params'     => $params,
            'middleware' => $middleware,
        ];
    }

    /**
     * 캐시 디렉토리 존재 확인 및 생성
     *
     * 캐시 파일을 저장할 디렉토리가 없으면 생성한다.
     * 재귀적으로 상위 디렉토리도 함께 생성된다.
     *
     * 경로: storage/cache/routes/
     */
    private function ensureCacheDirectoryExists(): void
    {
        if (!is_dir(self::CACHE_DIR)) {
            // 0755: 소유자 rwx, 그룹 rx, 기타 rx
            // true: 재귀적 생성 (중간 디렉토리도 생성)
            mkdir(self::CACHE_DIR, 0755, true);
        }
    }

    /**
     * 캐시 파일이 TTL을 초과했으면 삭제
     *
     * 캐시 파일 생성 후 일정 시간(1시간)이 지나면 삭제하여
     * 다음 요청에서 라우트를 재구성하도록 한다.
     */
    private function invalidateStaleCacheFile(string $cacheFile): void
    {
        if (!file_exists($cacheFile)) {
            return;
        }

        if (time() - filemtime($cacheFile) > 3600) {
            unlink($cacheFile);
        }
    }

    /**
     * 현재 도메인의 라우트 캐시 클리어
     *
     * 현재 설정된 도메인의 캐시 파일을 삭제하여
     * 다음 요청 시 라우트가 재구성되도록 한다.
     *
     * ----------------------------------------------------
     * [사용 시점]
     * ----------------------------------------------------
     *
     * - 해당 도메인의 Plugin/Package 활성화 상태 변경 후
     * - routes.php 파일 수정 후
     *
     * ----------------------------------------------------
     *
     * @return bool 삭제 성공 여부
     */
    public function clearCache(): bool
    {
        if ($this->currentDomain === null) {
            return false;
        }

        $cacheFile = $this->getCacheFilePath();

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return true;  // 파일이 없으면 이미 클리어된 상태
    }

    /**
     * 특정 도메인의 라우트 캐시 클리어 (정적 메서드)
     *
     * 인스턴스 생성 없이 특정 도메인의 캐시를 클리어할 수 있는 편의 메서드
     *
     * @param string $domain 도메인명 (예: 'shop.example.com')
     * @return bool 삭제 성공 여부
     */
    public static function clearRouteCache(string $domain): bool
    {
        $safeDomain = str_replace([':', '/'], '_', $domain);
        $cacheFile = MUBLO_STORAGE_PATH . '/cache/routes/' . $safeDomain . '.cache.php';

        if (file_exists($cacheFile)) {
            return unlink($cacheFile);
        }

        return true;
    }

    /**
     * 모든 도메인의 라우트 캐시 클리어 (정적 메서드)
     *
     * routes 캐시 디렉토리의 모든 캐시 파일을 삭제한다.
     *
     * ----------------------------------------------------
     * [사용 시점]
     * ----------------------------------------------------
     *
     * - 전역 라우트 변경 후 (Core routes 수정)
     * - Plugin/Package 추가/삭제 후
     * - 배포 스크립트에서 자동 호출
     *
     * ----------------------------------------------------
     *
     * @return int 삭제된 파일 수
     */
    public static function clearAllRouteCache(): int
    {
        $cacheDir = MUBLO_STORAGE_PATH . '/cache/routes';
        $deletedCount = 0;

        if (!is_dir($cacheDir)) {
            return 0;
        }

        $cacheFiles = glob($cacheDir . '/*.cache.php');

        foreach ($cacheFiles as $file) {
            if (unlink($file)) {
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * 캐시 사용 여부 확인
     *
     * 현재 Router가 캐시를 사용하도록 설정되어 있는지 반환한다.
     * 디버깅/테스트 용도
     *
     * @return bool 캐시 사용 여부
     */
    public function isCacheEnabled(): bool
    {
        return $this->useCache;
    }

    /**
     * 현재 도메인의 캐시 파일 존재 여부 확인
     *
     * 라우트 캐시 파일이 실제로 존재하는지 확인한다.
     * 디버깅/모니터링 용도
     *
     * @return bool 캐시 파일 존재 여부
     */
    public function cacheFileExists(): bool
    {
        if ($this->currentDomain === null) {
            return false;
        }

        return file_exists($this->getCacheFilePath());
    }

    /**
     * 현재 도메인 반환
     *
     * dispatch() 호출 후 설정된 현재 도메인을 반환한다.
     *
     * @return string|null 현재 도메인
     */
    public function getCurrentDomain(): ?string
    {
        return $this->currentDomain;
    }

    /**
     * 캐시된 도메인 목록 반환
     *
     * 현재 캐시되어 있는 모든 도메인 목록을 반환한다.
     * 관리/모니터링 용도
     *
     * @return array 도메인 목록
     */
    public static function getCachedDomains(): array
    {
        $cacheDir = MUBLO_STORAGE_PATH . '/cache/routes';
        $domains = [];

        if (!is_dir($cacheDir)) {
            return [];
        }

        $cacheFiles = glob($cacheDir . '/*.cache.php');

        foreach ($cacheFiles as $file) {
            $filename = basename($file, '.cache.php');
            // 파일명에서 도메인 복원 (_ → :)
            $domain = str_replace('_', ':', $filename);
            $domains[] = $domain;
        }

        return $domains;
    }
}
