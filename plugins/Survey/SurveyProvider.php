<?php
namespace Mublo\Plugin\Survey;

use Mublo\Core\Block\BlockRegistry;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\InstallableExtensionInterface;
use Mublo\Core\Extension\MigrationRunner;
use Mublo\Core\Rendering\AssetManager;
use Mublo\Enum\Block\BlockContentKind;
use Mublo\Infrastructure\Database\Database;
use Mublo\Service\Auth\AuthService;
use Mublo\Plugin\Survey\AdminMenuSubscriber;
use Mublo\Plugin\Survey\Block\SurveyConfigForm;
use Mublo\Plugin\Survey\Block\SurveyItemsProvider;
use Mublo\Plugin\Survey\Block\SurveyRenderer;
use Mublo\Plugin\Survey\Controller\Admin\SurveyAdminController;
use Mublo\Plugin\Survey\Controller\Front\SurveyController;
use Mublo\Plugin\Survey\Repository\SurveyAnswerRepository;
use Mublo\Plugin\Survey\Repository\SurveyQuestionRepository;
use Mublo\Plugin\Survey\Repository\SurveyRepository;
use Mublo\Plugin\Survey\Repository\SurveyResponseRepository;
use Mublo\Plugin\Survey\Service\SurveyResultService;
use Mublo\Plugin\Survey\Service\SurveyService;
use Mublo\Plugin\Survey\Service\SurveySubmitService;

class SurveyProvider implements ExtensionProviderInterface, InstallableExtensionInterface
{
    public function register(DependencyContainer $container): void
    {
        // Repository
        $container->singleton(SurveyRepository::class,
            fn($c) => new SurveyRepository($c->get(Database::class)));
        $container->singleton(SurveyQuestionRepository::class,
            fn($c) => new SurveyQuestionRepository($c->get(Database::class)));
        $container->singleton(SurveyResponseRepository::class,
            fn($c) => new SurveyResponseRepository($c->get(Database::class)));
        $container->singleton(SurveyAnswerRepository::class,
            fn($c) => new SurveyAnswerRepository($c->get(Database::class)));

        // Service
        $container->singleton(SurveyService::class, function ($c) {
            return new SurveyService(
                $c->get(SurveyRepository::class),
                $c->get(SurveyQuestionRepository::class),
                $c->get(SurveyResponseRepository::class),
                $c->get(SurveyAnswerRepository::class),
            );
        });

        $container->singleton(SurveySubmitService::class, function ($c) {
            return new SurveySubmitService(
                $c->get(SurveyRepository::class),
                $c->get(SurveyQuestionRepository::class),
                $c->get(SurveyResponseRepository::class),
                $c->get(SurveyAnswerRepository::class),
                $c->get(EventDispatcher::class),
            );
        });

        $container->singleton(SurveyResultService::class, function ($c) {
            return new SurveyResultService(
                $c->get(SurveyRepository::class),
                $c->get(SurveyQuestionRepository::class),
                $c->get(SurveyResponseRepository::class),
                $c->get(SurveyAnswerRepository::class),
            );
        });

        // Controller
        $container->singleton(SurveyAdminController::class, function ($c) {
            return new SurveyAdminController(
                $c->get(SurveyService::class),
                $c->get(SurveyResultService::class),
                $c->get(MigrationRunner::class),
            );
        });

        $container->singleton(SurveyController::class, function ($c) {
            return new SurveyController(
                $c->get(SurveyService::class),
                $c->get(SurveySubmitService::class),
                $c->get(AuthService::class),
            );
        });

        // Block
        $container->singleton(SurveyRenderer::class, function ($c) {
            $renderer = new SurveyRenderer(
                $c->get(SurveyService::class),
                $c->get(SurveyResultService::class),
                $c->get(SurveySubmitService::class),
            );
            $renderer->assetManager = $c->get(AssetManager::class);
            return $renderer;
        });
        $container->singleton(SurveyConfigForm::class, fn() => new SurveyConfigForm());
        $container->singleton(SurveyItemsProvider::class,
            fn($c) => new SurveyItemsProvider($c->get(SurveyRepository::class)));
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());

        BlockRegistry::registerContentType(
            type:            'survey',
            kind:            BlockContentKind::PLUGIN->value,
            title:           '설문조사',
            rendererClass:   SurveyRenderer::class,
            configFormClass: SurveyConfigForm::class,
            options: [
                'hasItems'     => true,
                'hasStyle'     => true,
                'itemsProvider' => SurveyItemsProvider::class,
                'skinBasePath' => MUBLO_PLUGIN_PATH . '/Survey/views/Block',
            ]
        );
    }

    public function install(DependencyContainer $container, Context $context): void
    {
        $runner = $container->get(MigrationRunner::class);
        $runner->run('plugin', 'Survey', MUBLO_PLUGIN_PATH . '/Survey/database/migrations');
    }

    public function uninstall(DependencyContainer $container, Context $context): void {}
}
