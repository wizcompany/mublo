<?php

namespace Mublo\Plugin\Popup;

use Mublo\Contract\DataResettableInterface;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\MigrationRunner;
use Mublo\Infrastructure\Database\Database;
use Mublo\Plugin\Popup\Controller\PopupController;
use Mublo\Plugin\Popup\Repository\PopupConfigRepository;
use Mublo\Plugin\Popup\Repository\PopupRepository;
use Mublo\Plugin\Popup\Service\PopupService;

class PopupProvider implements ExtensionProviderInterface, DataResettableInterface
{
    public function register(DependencyContainer $container): void
    {
        $container->singleton(PopupRepository::class, fn(DependencyContainer $c) =>
            new PopupRepository($c->get(Database::class))
        );

        $container->singleton(PopupConfigRepository::class, fn(DependencyContainer $c) =>
            new PopupConfigRepository($c->get(Database::class))
        );

        $container->singleton(PopupService::class, fn(DependencyContainer $c) =>
            new PopupService(
                $c->get(PopupRepository::class),
                $c->get(PopupConfigRepository::class)
            )
        );

        $container->singleton(PopupController::class, function (DependencyContainer $c) {
            return new PopupController(
                $c->get(PopupService::class),
                $c->get(MigrationRunner::class)
            );
        });
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());
        $eventDispatcher->addSubscriber(new FrontRenderSubscriber(
            $container->get(PopupService::class)
        ));
    }

    public function getResetCategories(): array
    {
        return [
            [
                'key' => 'popups',
                'label' => '팝업',
                'description' => '등록된 팝업을 모두 삭제합니다.',
                'icon' => 'bi-window-stack',
            ],
        ];
    }

    public function reset(string $category, int $domainId, Database $db): array
    {
        if ($category !== 'popups') {
            return ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => '알 수 없는 카테고리'];
        }

        $cleared = 0;

        if ($this->tableExists($db, 'popups')) {
            $db->execute("DELETE FROM popups WHERE domain_id = ?", [$domainId]);
            $cleared++;
        }

        if ($this->tableExists($db, 'plugin_popup_configs')) {
            $db->execute("DELETE FROM plugin_popup_configs WHERE domain_id = ?", [$domainId]);
            $cleared++;
        }

        return [
            'tables_cleared' => $cleared,
            'files_deleted' => 0,
            'details' => '팝업 및 팝업 설정 삭제',
        ];
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
