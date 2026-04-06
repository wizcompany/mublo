<?php

namespace Mublo\Plugin\VisitorStats;

use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Dashboard\DashboardWidgetRegistry;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Contract\DataResettableInterface;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\MigrationRunner;
use Mublo\Core\Session\SessionInterface;
use Mublo\Infrastructure\Database\Database;
use Mublo\Plugin\VisitorStats\Controller\VisitorStatsController;
use Mublo\Plugin\VisitorStats\Dashboard\VisitorStatsWidget;
use Mublo\Plugin\VisitorStats\Repository\VisitorDailyRepository;
use Mublo\Plugin\VisitorStats\Repository\VisitorHourlyRepository;
use Mublo\Plugin\VisitorStats\Repository\VisitorLogRepository;
use Mublo\Plugin\VisitorStats\Repository\VisitorPageRepository;
use Mublo\Plugin\VisitorStats\Repository\VisitorCampaignKeyRepository;
use Mublo\Plugin\VisitorStats\Repository\VisitorCampaignRepository;
use Mublo\Plugin\VisitorStats\Repository\VisitorReferrerRepository;
use Mublo\Plugin\VisitorStats\Repository\ConversionRepository;
use Mublo\Plugin\VisitorStats\Service\ConversionStatsService;
use Mublo\Plugin\VisitorStats\Service\VisitorCollector;
use Mublo\Plugin\VisitorStats\Service\VisitorStatsService;
use Mublo\Plugin\VisitorStats\Subscriber\VisitorTrackingSubscriber;

class VisitorStatsProvider implements ExtensionProviderInterface, DataResettableInterface
{
    public function register(DependencyContainer $container): void
    {
        // Repositories
        $container->singleton(VisitorLogRepository::class, function ($c) {
            return new VisitorLogRepository($c->get(Database::class));
        });
        $container->singleton(VisitorDailyRepository::class, function ($c) {
            return new VisitorDailyRepository($c->get(Database::class));
        });
        $container->singleton(VisitorHourlyRepository::class, function ($c) {
            return new VisitorHourlyRepository($c->get(Database::class));
        });
        $container->singleton(VisitorPageRepository::class, function ($c) {
            return new VisitorPageRepository($c->get(Database::class));
        });
        $container->singleton(VisitorReferrerRepository::class, function ($c) {
            return new VisitorReferrerRepository($c->get(Database::class));
        });
        $container->singleton(VisitorCampaignKeyRepository::class, function ($c) {
            return new VisitorCampaignKeyRepository($c->get(Database::class));
        });
        $container->singleton(VisitorCampaignRepository::class, function ($c) {
            return new VisitorCampaignRepository($c->get(Database::class));
        });

        // Conversion (form_submissions 기반)
        $container->singleton(ConversionRepository::class, function ($c) {
            return new ConversionRepository($c->get(Database::class));
        });

        // Services
        $container->singleton(VisitorCollector::class, function ($c) {
            return new VisitorCollector(
                $c->get(VisitorLogRepository::class),
                $c->get(VisitorDailyRepository::class),
                $c->get(VisitorHourlyRepository::class),
                $c->get(VisitorPageRepository::class),
                $c->get(VisitorReferrerRepository::class),
                $c->get(VisitorCampaignRepository::class),
                $c->get(SessionInterface::class),
            );
        });

        $container->singleton(VisitorStatsService::class, function ($c) {
            return new VisitorStatsService(
                $c->get(VisitorLogRepository::class),
                $c->get(VisitorDailyRepository::class),
                $c->get(VisitorHourlyRepository::class),
                $c->get(VisitorPageRepository::class),
                $c->get(VisitorReferrerRepository::class),
                $c->get(VisitorCampaignRepository::class),
                $c->get(VisitorCampaignKeyRepository::class),
            );
        });

        $container->singleton(ConversionStatsService::class, function ($c) {
            return new ConversionStatsService(
                $c->get(ConversionRepository::class),
                $c->get(VisitorCampaignRepository::class),
                $c->get(VisitorCampaignKeyRepository::class),
                $c->get(VisitorStatsService::class),
            );
        });

        // Controller
        $container->singleton(VisitorStatsController::class, function ($c) {
            return new VisitorStatsController(
                $c->get(VisitorStatsService::class),
                $c->get(ConversionStatsService::class),
                $c->get(VisitorLogRepository::class),
                $c->get(VisitorCampaignKeyRepository::class),
                $c->get(MigrationRunner::class)
            );
        });
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);

        // 관리자 메뉴
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());

        // 방문자 추적 (프론트에서만)
        $eventDispatcher->addSubscriber(
            new VisitorTrackingSubscriber(
                $container->get(VisitorCollector::class),
                $container->get(SessionInterface::class)
            )
        );

        // 대시보드 위젯
        try {
            $widgetRegistry = $container->get(DashboardWidgetRegistry::class);
            $widget = new VisitorStatsWidget(
                $container->get(VisitorDailyRepository::class),
                $context->getDomainId()
            );
            $widgetRegistry->register($widget->id(), $widget, 5);
        } catch (\Throwable $e) {
            // 위젯 등록 실패는 무시
        }
    }

    public function getResetCategories(): array
    {
        return [
            [
                'key' => 'visitor_stats',
                'label' => '방문자 통계',
                'description' => '방문자 통계 데이터를 모두 삭제합니다.',
                'icon' => 'bi-graph-up',
            ],
        ];
    }

    public function reset(string $category, int $domainId, Database $db): array
    {
        if ($category !== 'visitor_stats') {
            return ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => '알 수 없는 카테고리'];
        }

        $cleared = 0;
        $tables = [
            'plugin_visitor_logs',
            'plugin_visitor_daily',
            'plugin_visitor_hourly',
            'plugin_visitor_pages',
            'plugin_visitor_referrers',
            'plugin_visitor_campaigns',
        ];

        foreach ($tables as $table) {
            if ($this->tableExists($db, $table)) {
                $db->execute("DELETE FROM {$table} WHERE domain_id = ?", [$domainId]);
                $cleared++;
            }
        }

        return ['tables_cleared' => $cleared, 'files_deleted' => 0, 'details' => '방문자 통계 데이터 삭제'];
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
