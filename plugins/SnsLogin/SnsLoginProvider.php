<?php
namespace Mublo\Plugin\SnsLogin;

use Mublo\Contract\DataResettableInterface;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\InstallableExtensionInterface;
use Mublo\Core\Extension\MigrationRunner;
use Mublo\Core\Session\SessionInterface;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Log\Logger;
use Mublo\Plugin\SnsLogin\Controller\Admin\SettingsController;
use Mublo\Service\Member\FieldEncryptionService;
use Mublo\Plugin\SnsLogin\Controller\Front\SnsAuthController;
use Mublo\Plugin\SnsLogin\Controller\Front\SnsProfileController;
use Mublo\Plugin\SnsLogin\Provider\GoogleProvider;
use Mublo\Plugin\SnsLogin\Provider\KakaoProvider;
use Mublo\Plugin\SnsLogin\Provider\NaverProvider;
use Mublo\Plugin\SnsLogin\Repository\SnsAccountRepository;
use Mublo\Plugin\SnsLogin\Repository\SnsLoginConfigRepository;
use Mublo\Plugin\SnsLogin\Service\SnsLoginConfigService;
use Mublo\Plugin\SnsLogin\Service\SnsLoginService;
use Mublo\Plugin\SnsLogin\Subscriber\LoginFormSubscriber;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Service\Auth\AuthService;
use Mublo\Service\Member\MemberService;

class SnsLoginProvider implements ExtensionProviderInterface, InstallableExtensionInterface, DataResettableInterface
{
    /**
     * 첫 활성화 시: 마이그레이션 자동 실행
     */
    public function install(DependencyContainer $container, Context $context): void
    {
        $runner = $container->get(MigrationRunner::class);
        $runner->run('plugin', 'SnsLogin', MUBLO_PLUGIN_PATH . '/SnsLogin/database/migrations');
    }

    /**
     * 비활성화 시: 데이터 보존 (테이블/설정 삭제 안 함)
     */
    public function uninstall(DependencyContainer $container, Context $context): void
    {
        // SNS 계정 연동 데이터는 회원 데이터이므로 비활성화 시 삭제하지 않음
    }

    public function register(DependencyContainer $container): void
    {
        $container->singleton(SnsLoginConfigRepository::class, fn($c) =>
            new SnsLoginConfigRepository($c->get(Database::class))
        );

        $container->singleton(SnsLoginConfigService::class, fn($c) =>
            new SnsLoginConfigService(
                $c->get(SnsLoginConfigRepository::class),
                $c->get(FieldEncryptionService::class),
            )
        );

        $container->singleton(SnsAccountRepository::class, fn($c) =>
            new SnsAccountRepository(
                $c->get(Database::class),
                $c->get(FieldEncryptionService::class),
            )
        );

        $container->singleton(SnsProviderRegistry::class, fn() => new SnsProviderRegistry());

        $container->singleton(SnsLoginService::class, fn($c) =>
            new SnsLoginService(
                $c->get(SnsAccountRepository::class),
                $c->get(MemberRepository::class),
                $c->get(AuthService::class),
                $c->get(SnsLoginConfigService::class),
                $c->get(SessionInterface::class),
            )
        );

        $container->singleton(SnsAuthController::class, fn($c) =>
            new SnsAuthController(
                $c->get(SnsProviderRegistry::class),
                $c->get(SnsLoginService::class),
                $c->get(SnsLoginConfigService::class),
                $c->get(SessionInterface::class),
                $c->get(Logger::class)->channel('sns-login'),
            )
        );

        $container->singleton(SnsProfileController::class, fn($c) =>
            new SnsProfileController(
                $c->get(SnsLoginService::class),
                $c->get(MemberRepository::class),
                $c->get(AuthService::class),
                $c->get(MemberService::class),
            )
        );

        $container->singleton(SettingsController::class, fn($c) =>
            new SettingsController(
                $c->get(SnsLoginConfigService::class),
                $c->get(MigrationRunner::class),
                $c->get(EventDispatcher::class),
            )
        );
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $registry        = $container->get(SnsProviderRegistry::class);
        $eventDispatcher = $container->get(EventDispatcher::class);

        // 구독자 등록은 항상 먼저 (DB 접근 전) — 설치 전에도 관리자 메뉴가 보여야 함
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());
        $eventDispatcher->addSubscriber(new LoginFormSubscriber($registry));

        // DB 접근이 필요한 설정 로드 — 테이블 미설치 시 예외를 잡아 무시
        try {
            $domainId      = $context->getDomainId() ?? 1;
            $configService = $container->get(SnsLoginConfigService::class);

            $enabledMap = $configService->getEnabledMap($domainId);
            $registry->setEnabled($enabledMap);

            $snsLogger = $container->get(Logger::class)->channel('sns-login');
            $this->registerProviders($registry, $configService, $domainId, $context, $snsLogger);
        } catch (\Throwable $e) {
            // 마이그레이션 미실행 상태에서도 관리자 메뉴는 동작해야 함
            error_log('[SnsLogin] boot config load failed (migration needed?): ' . $e->getMessage());
        }
    }

    private function registerProviders(
        SnsProviderRegistry   $registry,
        SnsLoginConfigService $configService,
        int                   $domainId,
        Context               $context,
        ?Logger               $logger = null,
    ): void {
        $request  = $context->getRequest();
        $scheme   = $request->isHttps() ? 'https' : 'http';
        $host     = $request->getHost();
        $baseUrl  = "{$scheme}://{$host}";

        $providers = ['naver', 'kakao', 'google'];

        foreach ($providers as $name) {
            $cfg         = $configService->getProviderConfig($domainId, $name);
            $clientId    = $cfg['client_id'] ?? '';
            $callbackUrl = "{$baseUrl}/sns-login/callback/{$name}";

            // client_id 없으면 스킵
            if (empty($clientId)) {
                continue;
            }

            $provider = match ($name) {
                'naver'  => new NaverProvider($clientId, $cfg['client_secret'] ?? '', $callbackUrl),
                'kakao'  => new KakaoProvider($clientId, $cfg['admin_key'] ?? '', $cfg['javascript_key'] ?? '', $callbackUrl, $logger),
                'google' => new GoogleProvider($clientId, $cfg['client_secret'] ?? '', $callbackUrl),
            };

            $registry->register($provider);
        }
    }

    public function getResetCategories(): array
    {
        return [
            [
                'key' => 'sns_accounts',
                'label' => 'SNS 연동',
                'description' => '회원 SNS 연동 계정 정보를 삭제합니다. (설정은 보존)',
                'icon' => 'bi-share',
            ],
        ];
    }

    public function reset(string $category, int $domainId, Database $db): array
    {
        if ($category !== 'sns_accounts') {
            return ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => '알 수 없는 카테고리'];
        }

        $cleared = 0;

        if ($this->tableExists($db, 'plugin_sns_login_accounts')) {
            $db->execute("DELETE FROM plugin_sns_login_accounts WHERE domain_id = ?", [$domainId]);
            $cleared++;
        }

        return ['tables_cleared' => $cleared, 'files_deleted' => 0, 'details' => 'SNS 연동 계정 삭제 (설정 보존)'];
    }

    private function tableExists(Database $db, string $table): bool
    {
        try {
            $db->selectOne("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
