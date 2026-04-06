<?php

namespace Mublo\Core\Provider;

use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\App\Router;
use Mublo\Core\App\Dispatcher;
use Mublo\Core\Rendering\AssetManager;
use Mublo\Core\Rendering\LayoutManager;
use Mublo\Core\Rendering\FrontViewRenderer;
use Mublo\Core\Rendering\AdminViewRenderer;
use Mublo\Core\Session\SessionInterface;
use Mublo\Infrastructure\Session\SessionManager;
use Mublo\Core\Cookie\CookieInterface;
use Mublo\Infrastructure\Cookie\CookieManager;

use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Log\Logger;
use Mublo\Infrastructure\Cache\CacheInterface;
use Mublo\Infrastructure\Cache\CacheFactory;
use Mublo\Infrastructure\Cache\DomainCache;
use Mublo\Infrastructure\Storage\FileUploader;
use Mublo\Infrastructure\Storage\SecureFileService;
use Mublo\Core\Env\Env;

use Mublo\Core\Registry\ContractRegistry;
use Mublo\Core\Registry\CategoryProviderRegistry;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\Subscriber\MemberQuerySubscriber;
use Mublo\Core\Event\Domain\DomainEventSubscriber;
use Mublo\Service\Search\SearchService;
use Mublo\Service\Admin\AdminMenuService;
use Mublo\Service\Admin\AdminPermissionService;
use Mublo\Service\Auth\AuthService;
use Mublo\Service\Auth\LoginAttemptService;
use Mublo\Service\Menu\MenuService;
use Mublo\Service\Block\BlockRenderService;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Core\Middleware\AdminMiddleware;
use Mublo\Core\Middleware\AuthMiddleware;
use Mublo\Core\Middleware\CsrfMiddleware;
use Mublo\Infrastructure\Security\CsrfManager;
use Mublo\Repository\Balance\BalanceLogRepository;
use Mublo\Repository\Member\AdminPermissionRepository;
use Mublo\Repository\Member\MemberLevelRepository;
use Mublo\Service\Balance\BalanceManager;
use Mublo\Infrastructure\Code\CodeGenerator;
use Mublo\Core\Context\Context;
use Mublo\Core\Extension\MigrationRunner;
use Mublo\Service\Migration\CoreMigrationService;
use Mublo\Core\Report\Audit\ReportAuditLogger;
use Mublo\Core\Report\Contract\ReportRendererInterface;
use Mublo\Core\Report\Engine\ReportDefinitionRegistry;
use Mublo\Core\Report\Engine\ReportManager;
use Mublo\Core\Report\Engine\ReportRendererResolver;
use Mublo\Core\Report\Renderer\CsvReportRenderer;
use Mublo\Core\Report\Renderer\PdfReportRenderer;
use Mublo\Core\Report\Renderer\XlsxReportRenderer;
use Mublo\Core\Report\Security\AdminPermissionGate;
use Mublo\Core\Report\Store\ReportFileStore;

use Mublo\Core\Dashboard\DashboardWidgetRegistry;
use Mublo\Core\Dashboard\DashboardLayoutManager;
use Mublo\Core\Dashboard\LayoutSanitizer;
use Mublo\Core\Dashboard\SlotGridArranger;
use Mublo\Core\Dashboard\Widget\SystemInfoWidget;
use Mublo\Core\Dashboard\Widget\MemberStatsWidget;
use Mublo\Repository\DashboardLayoutRepository;

use Mublo\Infrastructure\Mail\Mailer;
use Mublo\Contract\Notification\NotificationGatewayInterface;
use Mublo\Core\Notification\EmailNotificationGateway;

/**
 * CoreServiceProvider
 * Class ServiceProvider
 *
 * 프레임워크 코어 구성요소 등록
 *
 * 책임:
 * - Router / Dispatcher
 * - Rendering 관련 객체 조립
 * - Database 연결
 *
 * 금지:
 * - 로직
 * - 조건 분기
 * 애플리케이션의 주요 서비스와 의존성을 컨테이너에 등록(Binding)하는 역할
 */
class ServiceProvider
{
    /**
     * 컨테이너에 서비스 등록
     *
     * @param DependencyContainer $container
     */
    public function register(DependencyContainer $container): void
    {
        // ====================================
        // 1. Infrastructure (기반)
        // ====================================

        // ------------------------------------
        // Database
        // ------------------------------------
        $container->singleton(
            DatabaseManager::class,
            fn () => DatabaseManager::getInstance()->loadFromConfig()
        );

        $container->singleton(Database::class, function (DependencyContainer $c) {
            $db = $c->get(DatabaseManager::class)->connect();

            // Logger 연결 (슬로우 쿼리 로깅)
            if ($c->has(Logger::class)) {
                $db->setLogger($c->get(Logger::class));
            }

            // 슬로우 쿼리 임계값 설정 (.env에서 읽기, 기본 1.0초)
            $threshold = (float) Env::get('DB_SLOW_QUERY_THRESHOLD', '1.0');
            $db->setSlowQueryThreshold($threshold);

            // 쿼리 로깅 활성화 (개발 모드에서만)
            $debug = Env::get('APP_DEBUG', false) === true;
            if ($debug) {
                $db->enableQueryLog(true);
            }

            return $db;
        });

        // ------------------------------------
        // Session
        // ------------------------------------
        $container->singleton(SessionManager::class, function () {
            return new SessionManager();
        });

        $container->singleton(SessionInterface::class, function (DependencyContainer $c) {
            return $c->get(SessionManager::class);
        });

        // ------------------------------------
        // Cache
        // ------------------------------------
        $container->singleton(CacheInterface::class, function () {
            return CacheFactory::getInstance();
        });

        // DomainCache는 공유 CacheInterface 싱글톤과 분리된 독립 인스턴스를 사용.
        // Application::run()의 setDomainId() 호출이 DomainCache 경로에 영향을 주지 않도록 한다.
        // storage/cache/data/domains/ 전용 경로 → global/ 과 격리.
        $container->singleton(DomainCache::class, function () {
            return new DomainCache();
        });

        // ------------------------------------
        // Cookie
        // ------------------------------------
        $container->singleton(CookieInterface::class, function () {
            return new CookieManager();
        });

        // ------------------------------------
        // File Uploader
        // ------------------------------------
        $container->singleton(FileUploader::class, function () {
            return new FileUploader();
        });

        // ------------------------------------
        // Secure File Service (보안 파일)
        // ------------------------------------
        $container->singleton(SecureFileService::class, function () {
            return new SecureFileService();
        });

        // ------------------------------------
        // Mailer (이메일 발송)
        // ------------------------------------
        $container->singleton(Mailer::class, function (DependencyContainer $c) {
            $logger = $c->has(Logger::class) ? $c->get(Logger::class) : null;
            return new Mailer(null, $logger);
        });

        // ------------------------------------
        // Contract Registry (범용 계약 레지스트리)
        // ------------------------------------
        $container->singleton(ContractRegistry::class, function () {
            $registry = new ContractRegistry();
            $registry->register(
                ReportRendererInterface::class,
                'csv',
                fn() => new CsvReportRenderer(),
                [
                    'label' => 'CSV',
                    'description' => '기본 CSV 렌더러',
                ]
            );
            $registry->register(
                ReportRendererInterface::class,
                'xlsx',
                fn() => new XlsxReportRenderer(),
                [
                    'label' => 'XLSX',
                    'description' => '기본 Excel 렌더러',
                ]
            );
            $registry->register(
                ReportRendererInterface::class,
                'pdf',
                fn() => new PdfReportRenderer(),
                [
                    'label' => 'PDF',
                    'description' => '기본 PDF 렌더러',
                ]
            );

            // 이메일 알림 게이트웨이 (Core 레벨)
            $registry->register(
                NotificationGatewayInterface::class,
                'core_email',
                fn() => new EmailNotificationGateway($container->get(Mailer::class)),
                [
                    'label'       => '이메일',
                    'icon'        => 'bi-envelope',
                    'description' => 'SMTP/mail() 이메일 발송',
                    'channels'    => ['email'],
                ]
            );

            return $registry;
        });

        // ------------------------------------
        // Event System
        // ------------------------------------
        $container->singleton(EventDispatcher::class, function () {
            return new EventDispatcher();
        });

        // ====================================
        // 2. Repository (데이터 접근)
        // ====================================

        $container->singleton(MemberRepository::class, function (DependencyContainer $c) {
            return new MemberRepository($c->get(Database::class));
        });

        $container->singleton(BalanceLogRepository::class, function (DependencyContainer $c) {
            return new BalanceLogRepository($c->get(Database::class));
        });

        $container->singleton(DashboardLayoutRepository::class, function (DependencyContainer $c) {
            return new DashboardLayoutRepository($c->get(Database::class));
        });

        // ====================================
        // 3. Service (비즈니스 로직)
        // ====================================

        // ------------------------------------
        // Auth
        // ------------------------------------
        $container->singleton(LoginAttemptService::class, function (DependencyContainer $c) {
            $securityConfig = require MUBLO_ROOT_PATH . '/config/security.php';
            return new LoginAttemptService(
                $c->get(Database::class),
                $securityConfig['login_rate_limiting'] ?? []
            );
        });

        $container->singleton(AuthService::class, function (DependencyContainer $c) {
            return new AuthService(
                $c->get(SessionInterface::class),
                $c->get(MemberRepository::class),
                $c->get(\Mublo\Core\Event\EventDispatcher::class),
                $c->get(LoginAttemptService::class)
            );
        });

        // ------------------------------------
        // Admin Menu
        // ------------------------------------
        $container->singleton(AdminMenuService::class, function (DependencyContainer $c) {
            return new AdminMenuService(
                $c->get(EventDispatcher::class),
                $c->get(AdminPermissionService::class)
            );
        });

        // ------------------------------------
        // Admin Permission
        // ------------------------------------
        $container->singleton(AdminPermissionService::class, function (DependencyContainer $c) {
            return new AdminPermissionService(
                $c->get(Database::class),
                $c->get(AdminPermissionRepository::class),
                $c->get(MemberLevelRepository::class)
            );
        });

        // ------------------------------------
        // Block Render
        // ------------------------------------
        $container->singleton(BlockRenderService::class, function (DependencyContainer $c) {
            return new BlockRenderService(
                $c->get(\Mublo\Repository\Block\BlockRowRepository::class),
                $c->get(\Mublo\Repository\Block\BlockColumnRepository::class),
                $c->get(CacheInterface::class),
                $c
            );
        });

        // ------------------------------------
        // Balance Manager (포인트/잔액 관리)
        // ------------------------------------
        $container->singleton(BalanceManager::class, function (DependencyContainer $c) {
            return new BalanceManager(
                $c->get(BalanceLogRepository::class),
                $c->get(MemberRepository::class),
                $c->get(Database::class),
                $c->get(EventDispatcher::class)
            );
        });

        // ------------------------------------
        // Migration (Core 마이그레이션 추적)
        // ------------------------------------
        $container->singleton(CoreMigrationService::class, function (DependencyContainer $c) {
            return new CoreMigrationService($c->get(Database::class));
        });

        $container->singleton(\Mublo\Service\System\SystemService::class, function (DependencyContainer $c) {
            return new \Mublo\Service\System\SystemService(
                $c->get(Database::class),
                $c->get(\Mublo\Infrastructure\Cache\DomainCache::class),
                $c->get(SecureFileService::class)
            );
        });

        // ------------------------------------
        // Dashboard
        // ------------------------------------
        $container->singleton(DashboardWidgetRegistry::class, function () {
            return new DashboardWidgetRegistry();
        });

        $container->singleton(LayoutSanitizer::class, function () {
            return new LayoutSanitizer();
        });

        $container->singleton(SlotGridArranger::class, function () {
            return new SlotGridArranger();
        });

        $container->singleton(DashboardLayoutManager::class, function (DependencyContainer $c) {
            return new DashboardLayoutManager(
                $c->get(DashboardLayoutRepository::class),
                $c->get(DashboardWidgetRegistry::class),
                $c->get(LayoutSanitizer::class)
            );
        });

        // ------------------------------------
        // Report
        // ------------------------------------
        $container->singleton(ReportDefinitionRegistry::class, function () {
            return new ReportDefinitionRegistry();
        });

        $container->singleton(AdminPermissionGate::class, function (DependencyContainer $c) {
            return new AdminPermissionGate(
                $c->get(AuthService::class),
                $c->get(AdminPermissionService::class)
            );
        });

        $container->singleton(ReportRendererResolver::class, function (DependencyContainer $c) {
            return new ReportRendererResolver(
                $c->get(ContractRegistry::class)
            );
        });

        $container->singleton(ReportFileStore::class, function () {
            return new ReportFileStore();
        });

        $container->singleton(ReportAuditLogger::class, function () {
            return new ReportAuditLogger();
        });

        $container->singleton(ReportManager::class, function (DependencyContainer $c) {
            return new ReportManager(
                $c->get(ReportDefinitionRegistry::class),
                $c->get(ReportRendererResolver::class),
                $c->get(AdminPermissionGate::class),
                $c->get(ReportFileStore::class),
                $c->get(ReportAuditLogger::class)
            );
        });

        // ====================================
        // 4. Middleware
        // ====================================

        $container->singleton(AdminMiddleware::class, function (DependencyContainer $c) {
            return new AdminMiddleware(
                $c->get(AuthService::class),
                $c->get(AdminMenuService::class),
                $c->get(AdminPermissionService::class),
                $c->has(Logger::class) ? $c->get(Logger::class) : null
            );
        });

        $container->singleton(AuthMiddleware::class, function (DependencyContainer $c) {
            return new AuthMiddleware(
                $c->get(AuthService::class)
            );
        });

        $container->singleton(CsrfManager::class, function () {
            return new CsrfManager();
        });

        $container->singleton(CsrfMiddleware::class, function (DependencyContainer $c) {
            $csrf = new CsrfMiddleware($c->get(CsrfManager::class));
            // Core CSRF 예외 경로
            $csrf->addExcludePath('/api/track/');
            return $csrf;
        });

        // ====================================
        // 5. Rendering
        // ====================================

        $container->factory(
            LayoutManager::class,
            fn () => new LayoutManager()
        );

        $container->singleton(AssetManager::class, fn () => new AssetManager());
        $container->singleton(CategoryProviderRegistry::class, fn () => new CategoryProviderRegistry());

        $container->factory(
            FrontViewRenderer::class,
            fn (DependencyContainer $c) => new FrontViewRenderer(
                $c->get(LayoutManager::class),
                $c->get(AuthService::class),
                $c->get(MenuService::class),
                $c->get(BlockRenderService::class),
                $c->get(CsrfManager::class),
                $c->get(EventDispatcher::class),
                $c->get(AssetManager::class),
                $c->get(CategoryProviderRegistry::class)
            )
        );

        $container->factory(
            AdminViewRenderer::class,
            fn (DependencyContainer $c) => new AdminViewRenderer(
                $c->get(AdminMenuService::class),
                $c->get(\Mublo\Service\Auth\AuthService::class),
                $c->get(CsrfManager::class),
                $c->get(AssetManager::class)
            )
        );

        // ====================================
        // 6. Router / Dispatcher
        // ====================================

        $container->factory(
            Router::class,
            fn () => new Router()
        );

        $container->factory(
            Dispatcher::class,
            fn (DependencyContainer $c) => new Dispatcher($c)
        );

        // ====================================
        // 7. Utility
        // ====================================

        // Code Generator (Context 의존)
        $container->factory(CodeGenerator::class, function (DependencyContainer $c) {
            return new CodeGenerator(
                $c->get(Database::class),
                $c->get(Context::class)
            );
        });

        // Migration Runner (Core / Plugin / Package DB 마이그레이션 통합)
        $container->singleton(MigrationRunner::class, function (DependencyContainer $c) {
            return new MigrationRunner($c->get(Database::class));
        });

        // ------------------------------------
        // Search (전체 검색)
        // ------------------------------------
        $container->singleton(SearchService::class, function (DependencyContainer $c) {
            return new SearchService($c->get(EventDispatcher::class));
        });

    }

    /**
     * Core 이벤트 구독자 등록
     *
     * Application.boot()에서 ServiceProvider.register() 이후 호출.
     * EventDispatcher가 준비된 후에 Core 구독자를 등록한다.
     */
    public function bootSubscribers(DependencyContainer $container): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);

        // 회원 조회 이벤트 구독자 (Package/Plugin → Core 회원 데이터 조회)
        $eventDispatcher->addSubscriber(
            new MemberQuerySubscriber(
                $container->get(MemberRepository::class),
                $container->get(MemberLevelRepository::class),
            )
        );

        // Core 대시보드 위젯 등록
        // Note: boot 시점에는 Context 미존재 → Closure로 지연 해석
        $domainIdResolver = fn() => $container->has(Context::class)
            ? $container->get(Context::class)->getDomainId()
            : null;
        $registry = $container->get(DashboardWidgetRegistry::class);

        $registry->register('core.system_info', new SystemInfoWidget(), 0);
        $registry->register(
            'core.member_stats',
            new MemberStatsWidget($container->get(MemberRepository::class), $domainIdResolver),
            1
        );

        // 블록 페이지 → 메뉴 아이템 자동 등록/삭제
        $eventDispatcher->addSubscriber(
            new \Mublo\Subscriber\BlockPageMenuSubscriber($container)
        );

    }

    /**
     * 확장(Plugin/Package) 로드 후 Core 이벤트 구독자 등록
     *
     * Application.loadEnabledExtensions() 이후 호출.
     * PolicyService, ExtensionService 등 Package 의존 서비스 사용.
     */
    public static function bootPostExtensionSubscribers(DependencyContainer $container): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);

        // 도메인 생성/수정/삭제 이벤트 구독자 (기본 데이터 시딩)
        $eventDispatcher->addSubscriber(new DomainEventSubscriber(
            $container->get(MenuService::class),
            $container->get(\Mublo\Service\Member\PolicyService::class),
            $container->has(\Mublo\Service\Extension\ExtensionService::class)
                ? $container->get(\Mublo\Service\Extension\ExtensionService::class)
                : null
        ));
    }
}
