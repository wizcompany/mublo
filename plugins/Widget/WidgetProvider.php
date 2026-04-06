<?php

namespace Mublo\Plugin\Widget;

use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\MigrationRunner;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Storage\FileUploader;
use Mublo\Plugin\Widget\Controller\WidgetController;
use Mublo\Plugin\Widget\Repository\WidgetItemRepository;
use Mublo\Plugin\Widget\Repository\WidgetConfigRepository;
use Mublo\Plugin\Widget\Service\WidgetService;

class WidgetProvider implements ExtensionProviderInterface
{
    public function register(DependencyContainer $container): void
    {
        $container->singleton(WidgetItemRepository::class, fn(DependencyContainer $c) =>
            new WidgetItemRepository($c->get(Database::class))
        );

        $container->singleton(WidgetConfigRepository::class, fn(DependencyContainer $c) =>
            new WidgetConfigRepository($c->get(Database::class))
        );

        $container->singleton(WidgetService::class, fn(DependencyContainer $c) =>
            new WidgetService(
                $c->get(WidgetItemRepository::class),
                $c->get(WidgetConfigRepository::class)
            )
        );

        $container->singleton(WidgetController::class, fn(DependencyContainer $c) =>
            new WidgetController(
                $c->get(WidgetService::class),
                $c->get(MigrationRunner::class),
                $c->get(FileUploader::class)
            )
        );
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());
        $eventDispatcher->addSubscriber(new FrontRenderSubscriber(
            $container->get(WidgetService::class)
        ));
    }
}
