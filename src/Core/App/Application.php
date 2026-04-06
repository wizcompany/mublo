<?php
namespace Mublo\Core\App;

use Mublo\Core\Http\Request;
use Mublo\Core\Context\Context;
use Mublo\Core\Context\ContextBuilder;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Provider\ServiceProvider;
use Mublo\Core\Extension\ExtensionManager;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\RequestInterceptEvent;
use Mublo\Core\Env\Env;
use Mublo\Service\Extension\ExtensionService;
use Mublo\Repository\Domain\DomainRepository;
use Mublo\Infrastructure\Database\Database;
use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Response\RedirectResponse;
use Mublo\Core\Response\FileResponse;
use Mublo\Core\Rendering\FrontViewRenderer;
use Mublo\Core\Rendering\AdminViewRenderer;
use Mublo\Core\Rendering\ErrorRenderer;
use Mublo\Core\Event\Rendering\RendererResolveEvent;
use Mublo\Core\Error\ErrorHandler;
use Mublo\Core\Middleware\MiddlewarePipeline;
use Mublo\Core\Middleware\SessionMiddleware;
use Mublo\Core\Middleware\CsrfMiddleware;
use Mublo\Infrastructure\Log\Logger;
use Mublo\Infrastructure\Cache\CacheInterface;
use Mublo\Helper\Editor\EditorHelper;
use Mublo\Core\Event\Rendering\SiteContextReadyEvent;

/**
 * Class Application
 *
 * 프레임워크의 진입점이자 실행 흐름의 중심
 *
 * 책임:
 * - Request 생성
 * - Context 생성
 * - Router / Dispatcher 실행 흐름 제어
 * - Response 타입에 따라 최종 출력 위임
 *
 * 금지:
 * - 비즈니스 로직
 * - DB 직접 접근
 * - Controller 판단 로직
 * - 인증 / 권한 판단
 */
class Application
{
    /**
     * 전역 DI 컨테이너
     */
    protected DependencyContainer $container;

    /**
     * 현재 요청(Request)
     */
    protected Request $request;

    /**
     * 요청 해석 결과(Context)
     */
    protected Context $context;

    /**
     * 에러 핸들러
     */
    protected ErrorHandler $errorHandler;

    public function __construct()
    {
        /**
         * 컨테이너는 Application 생성 시점에 준비만 한다.
         * (서비스 등록은 boot 단계에서)
         */
        $this->container = DependencyContainer::getInstance();
    }

    /**
     * 애플리케이션 부트 단계
     *
     * - 환경 변수 로드
     * - 에러 핸들러 초기화
     * - 서비스 등록
     * - 이벤트 시스템 초기화
     */
    public function boot(): void
    {
        // 0. 환경 변수 로드 (.env 파일)
        $this->loadEnv();

        // 0.5. 에러 핸들러 초기화 및 등록
        $this->initErrorHandler();

        // 1. ServiceProvider 등록 (Core 서비스 등록)
        $provider = new ServiceProvider();
        $provider->register($this->container);

        // 2. EventDispatcher 초기화
        // (ServiceProvider에서 등록되지 않았을 경우를 대비해 체크 후 등록)
        if (!$this->container->has(EventDispatcher::class)) {
            $this->container->singleton(EventDispatcher::class, function () {
                return new EventDispatcher();
            });
        }

        // 3. Core 이벤트 구독자 등록
        $provider->bootSubscribers($this->container);

        // Note: 확장(Plugin/Package)의 이벤트 구독자 등록은
        // loadEnabledExtensions()에서 ExtensionManager가 담당
    }

    /**
     * 에러 핸들러 초기화
     */
    protected function initErrorHandler(): void
    {
        // Logger 생성 (도메인 ID는 나중에 설정)
        $logger = new Logger(0, 'app');

        // 디버그 모드 확인 (Env::get은 'true'를 boolean true로 변환)
        $debug = Env::get('APP_DEBUG', false) === true;

        // ErrorHandler 생성 및 등록
        $this->errorHandler = new ErrorHandler($logger, null, $debug);
        $this->errorHandler->register();

        // 컨테이너에 등록
        $this->container->singleton(ErrorHandler::class, fn() => $this->errorHandler);
        $this->container->singleton(Logger::class, fn() => $logger);
    }

    /**
     * 환경 변수 로드
     *
     * bootstrap.php의 Dotenv과 역할이 다름 (중복 아님):
     * - Dotenv: .env/.env.local/.env.{환경} 변형 파일 → $_ENV 로드
     * - Env::load(): .env → putenv() + Env 내부 캐시 로드
     *
     * getenv() 및 Env::get() 캐시를 위해 반드시 필요
     */
    protected function loadEnv(): void
    {
        $envPath = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env';

        if (file_exists($envPath)) {
            Env::load($envPath);
        }
    }

    /**
     * 애플리케이션 실행
     *
     * 전체 실행 흐름:
     * Request
     *  → Context
     *    → Router
     *      → Dispatcher
     *        → Response
     *          → Rendering / Output
     */
    public function run(): void
    {
        // ==================================================
        // 1. Request 생성
        // ==================================================
        $this->request = $this->createRequest();

        // ==================================================
        // 2. Context 생성
        // - Request를 해석한 "애플리케이션 상태"
        // ==================================================
        $this->context = $this->createContext($this->request);

        // Context를 Container에 등록 (다른 서비스에서 주입받을 수 있도록)
        $this->container->set(Context::class, $this->context);

        // 도메인 ID 설정 (도메인별 캐시/로그/에디터 경로 분리)
        if ($domainId = $this->context->getDomainId()) {
            $this->container->get(CacheInterface::class)->setDomainId($domainId);
            $this->errorHandler->setDomainId($domainId);
            EditorHelper::configureForDomain($domainId);
        }

        // ==================================================
        // 3. 도메인 유효성 검증
        // - 등록된 도메인인지, 접근 가능한지 확인
        // ==================================================
        if (!$this->validateDomain()) {
            return; // 에러 페이지가 이미 출력됨
        }

        // ==================================================
        // 3.5. 활성화된 확장(Plugin/Package) 로딩
        // - 도메인의 extension_config에 따라 활성화된 것만 로딩
        // ==================================================
        $this->loadEnabledExtensions();

        // 3.6. 확장 로드 후 Core 이벤트 구독자 등록
        // - PolicyService, ExtensionService 등 Package 의존 서비스 필요
        ServiceProvider::bootPostExtensionSubscribers($this->container);

        // 확장 속성 잠금 (boot() 이후 setAttribute() 차단)
        $this->context->lockAttributes();

        // ==================================================
        // 4. 전역 Middleware 실행
        // - Session 시작 등 모든 요청에 공통 적용
        // ==================================================
        $globalPipeline = new MiddlewarePipeline($this->container);
        $globalPipeline->through([
            SessionMiddleware::class,
            CsrfMiddleware::class,
        ]);

        try {
            $response = $globalPipeline->run(
                $this->request,
                $this->context,
                function ($request, $context) {
                    // ==================================================
                    // 4.5. SiteContextReadyEvent 발행
                    // - Session 시작 이후이므로 세션 값(파트너 코드 등) 접근 가능
                    // - Plugin/Package가 로고/이미지 URL을 override할 수 있는 확장점
                    // ==================================================
                    $this->container->get(EventDispatcher::class)
                        ->dispatch(new SiteContextReadyEvent($context, $request));

                    // ==================================================
                    // 4.6. RequestInterceptEvent 발행
                    // - Plugin/Package가 요청을 가로채고 Response를 반환할 수 있는 확장점
                    // - 비공개 사이트 접근 제어 등에 사용
                    // ==================================================
                    $interceptEvent = $this->container->get(EventDispatcher::class)
                        ->dispatch(new RequestInterceptEvent($context, $request));

                    if ($interceptEvent->hasResponse()) {
                        return $interceptEvent->getResponse();
                    }

                    // ==================================================
                    // 5. Router 실행
                    // - 어떤 Controller / Method를 호출할지 결정
                    // - Container 전달로 활성화된 확장만 라우트 로드
                    // ==================================================
                    $router = new Router($this->container);
                    $route  = $router->dispatch($request, $context);

                    // ==================================================
                    // 6. Dispatcher 실행
                    // - Controller 실행
                    // - Response 객체 반환
                    // ==================================================
                    $dispatcher = new Dispatcher($this->container);
                    return $dispatcher->dispatch($route, $context);
                }
            );

            // ==================================================
            // 7. Response 처리
            // - 타입에 따라 Rendering 또는 출력
            // ==================================================
            $this->handleResponse($response);

        } catch (\Throwable $e) {
            // ==================================================
            // ErrorHandler가 로깅 + 에러 페이지 처리
            // - 404, 403, 500 자동 분기
            // - 개발 모드: 상세 에러 표시
            // - 운영 모드: 일반 에러 페이지
            // ==================================================
            $this->errorHandler->handle($e);
        }
    }

    /**
     * Request 객체 생성
     *
     * ⚠️ 전역 변수($_SERVER, $_GET, $_POST) 접근은
     * 반드시 이 메서드 안에서만 한다.
     */
    protected function createRequest(): Request
    {
        // 신뢰 프록시 설정 로드
        $this->configureTrustedProxies();

        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        $request = new Request(
            $method,
            rawurldecode($uri),
            $_GET ?? [],
            $_POST ?? [],
            $_SERVER ?? [],
            $_FILES ?? [],
            $_COOKIE ?? []
        );

        // JSON 요청 시 php://input 파싱
        $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            if ($rawInput) {
                $jsonData = json_decode($rawInput, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $request->setJsonInput($jsonData);
                }
            }
        }

        return $request;
    }

    /**
     * 신뢰 프록시 설정 로드
     *
     * config/security.php의 trusted_proxies 설정을 Request 클래스에 적용
     */
    protected function configureTrustedProxies(): void
    {
        $securityConfig = [];
        $configPath = MUBLO_CONFIG_PATH . '/security.php';

        if (file_exists($configPath)) {
            $securityConfig = require $configPath;
        }

        $trustedProxies = $securityConfig['trusted_proxies'] ?? [];

        if (!empty($trustedProxies)) {
            Request::setTrustedProxies($trustedProxies);
        }
    }

    /**
     * Context 객체 생성
     *
     * Request를 기반으로
     * 애플리케이션 관점의 상태를 구성한다.
     */
    protected function createContext(Request $request): Context
    {
        $builder = new ContextBuilder();
        return $builder->build($request);
    }

    /**
     * 도메인 유효성 검증
     *
     * - 등록된 도메인인지 확인
     * - 활성 상태인지 확인
     * - 계약 만료 여부 확인
     *
     * @return bool 유효하면 true, 에러 출력 후 false
     */
    protected function validateDomain(): bool
    {
        // 설치 페이지는 검증 스킵
        if ($this->isInstallRequest()) {
            return true;
        }

        // API 중 일부는 도메인 없이 접근 가능 (예: CSRF 토큰)
        if ($this->isPublicApiRequest()) {
            return true;
        }

        $domainInfo = $this->context->getDomainInfo();
        $domainName = $this->context->getDomain() ?? 'unknown';

        // 1. 도메인 미등록
        if ($domainInfo === null) {
            $this->renderDomainError('not_found', $domainName);
            return false;
        }

        // 2. 도메인 차단됨
        if ($domainInfo->isBlocked()) {
            $this->renderDomainError('blocked', $domainName);
            return false;
        }

        // 3. 계약 만료
        if ($domainInfo->isContractExpired()) {
            $this->renderDomainError('expired', $domainName, [
                'expireDate' => $domainInfo->getContractEndDate()
            ]);
            return false;
        }

        // 4. 비활성 상태
        if (!$domainInfo->isActive()) {
            $this->renderDomainError('inactive', $domainName);
            return false;
        }

        return true;
    }

    /**
     * 설치 페이지 요청인지 확인
     */
    protected function isInstallRequest(): bool
    {
        $path = $this->request->getPath();
        return str_starts_with($path, '/install');
    }

    /**
     * 도메인 없이 접근 가능한 공개 API 요청인지 확인
     */
    protected function isPublicApiRequest(): bool
    {
        $path = $this->request->getPath();

        // CSRF 토큰 API는 도메인 없이 접근 가능
        if (str_starts_with($path, '/csrf/') || str_starts_with($path, '/api/v1/csrf/')) {
            return true;
        }

        // 정적 파일 서빙은 도메인 없이 접근 가능
        if (str_starts_with($path, '/serve/')) {
            return true;
        }

        return false;
    }

    /**
     * 활성화된 확장(Plugin/Package) 로딩
     *
     * 도메인의 extension_config를 확인하여
     * 활성화된 플러그인과 패키지만 로딩합니다.
     */
    protected function loadEnabledExtensions(): void
    {
        // 설치 페이지나 공개 API는 확장 로딩 스킵
        if ($this->isInstallRequest() || $this->isPublicApiRequest()) {
            return;
        }

        $domainId = $this->context->getDomainId();
        if (!$domainId) {
            return;
        }

        try {
            // ExtensionService를 통해 활성화된 확장 목록 조회
            $extensionService = $this->container->get(ExtensionService::class);
            $enabledPlugins = $extensionService->getEnabledPlugins($domainId);
            $enabledPackages = $extensionService->getEnabledPackages($domainId);

            // ExtensionManager를 통해 확장 로딩 (Provider 등록/boot)
            $extensionManager = new ExtensionManager($this->container);
            $extensionManager->loadExtensions(
                $this->context,
                $enabledPlugins,
                $enabledPackages
            );

            // 컨테이너에 ExtensionManager 등록 (다른 곳에서 참조 가능하도록)
            $this->container->singleton(ExtensionManager::class, fn() => $extensionManager);

            // Note: 각 Plugin/Package Provider의 boot()에서 모든 이벤트 구독자 등록
            // (AdminMenuSubscriber, MemberEventSubscriber 등 모두 포함)

        } catch (\Throwable $e) {
            // 확장 로딩 실패해도 코어 기능은 계속 동작
            // Logger로 기록
            if ($this->container->has(Logger::class)) {
                $logger = $this->container->get(Logger::class);
                $logger->warning('Extension loading failed', [
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * 도메인 관련 에러 페이지 렌더링
     *
     * @param string $type 에러 유형 (not_found, blocked, expired, inactive)
     * @param string $domainName 도메인명
     * @param array $extra 추가 데이터
     */
    protected function renderDomainError(string $type, string $domainName, array $extra = []): void
    {
        // 도메인 에러 로깅
        $this->logDomainError($type, $domainName, $extra);

        $errorRenderer = new ErrorRenderer();

        switch ($type) {
            case 'not_found':
                $errorRenderer->renderDomainNotFound($domainName);
                break;

            case 'blocked':
                $errorRenderer->renderDomainBlocked();
                break;

            case 'expired':
                $errorRenderer->renderDomainExpired($extra['expireDate'] ?? null);
                break;

            case 'inactive':
            default:
                $errorRenderer->renderDomainBlocked();
                break;
        }
    }

    /**
     * 도메인 에러 로깅
     */
    protected function logDomainError(string $type, string $domainName, array $extra = []): void
    {
        if (!$this->container->has(Logger::class)) {
            return;
        }

        $logger = $this->container->get(Logger::class);

        $context = [
            'domain' => $domainName,
            'type' => $type,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'uri' => $_SERVER['REQUEST_URI'] ?? '',
        ];

        if (!empty($extra)) {
            $context = array_merge($context, $extra);
        }

        $messages = [
            'not_found' => "Domain not found: {$domainName}",
            'blocked' => "Domain blocked: {$domainName}",
            'expired' => "Domain expired: {$domainName}",
            'inactive' => "Domain inactive: {$domainName}",
        ];

        $message = $messages[$type] ?? "Domain error ({$type}): {$domainName}";

        // 도메인 에러는 warning 레벨로 기록
        $logger->channel('error')->warning($message, $context);
    }

    /**
     * Response 처리
     *
     * ⚠️ Application은 "출력 방식 선택"까지만 책임지고,
     * 실제 HTML 조합은 Renderer에게 위임한다.
     */
    protected function handleResponse($response): void
    {
        // ------------------------------
        // ViewResponse (HTML)
        // ------------------------------
        if ($response instanceof ViewResponse) {
            http_response_code($response->getStatusCode());

            // 렌더러 결정 이벤트 — Package/Plugin이 커스텀 렌더러 지정 가능
            $renderer = null;
            $eventDispatcher = $this->container->get(EventDispatcher::class);

            $event = $eventDispatcher->dispatch(
                new RendererResolveEvent($response, $this->context)
            );
            $renderer = $event->getRenderer();

            // fallback: 기존 Admin / Front 분기
            if ($renderer === null) {
                $renderer = $this->context->isAdmin()
                    ? $this->container->get(AdminViewRenderer::class)
                    : $this->container->get(FrontViewRenderer::class);
            }

            $renderer->render($response, $this->context);
            return;
        }

        // ------------------------------
        // JsonResponse
        // ------------------------------
        if ($response instanceof JsonResponse) {
            http_response_code($response->getStatusCode());

            foreach ($response->getHeaders() as $key => $value) {
                header("{$key}: {$value}");
            }

            echo $response->toJson();
            return;
        }

        // ------------------------------
        // RedirectResponse
        // ------------------------------
        if ($response instanceof RedirectResponse) {
            http_response_code($response->getStatusCode());

            foreach ($response->getHeaders() as $key => $value) {
                header("{$key}: {$value}");
            }
            return;
        }

        // ------------------------------
        // FileResponse (정적 파일)
        // ------------------------------
        if ($response instanceof FileResponse) {
            $response->send();
            return;
        }

        // ------------------------------
        // send() 메서드를 가진 커스텀 Response (Package 확장용)
        // ------------------------------
        if (method_exists($response, 'send')) {
            $response->send();
            return;
        }

        throw new \RuntimeException('Unknown Response type');
    }
}
