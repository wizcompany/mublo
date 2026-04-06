<?php
namespace Mublo\Plugin\Faq;

use Mublo\Contract\Faq\FaqQueryInterface;
use Mublo\Contract\DataResettableInterface;
use Mublo\Core\Block\BlockRegistry;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\InstallableExtensionInterface;
use Mublo\Core\Extension\MigrationRunner;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Rendering\AssetManager;
use Mublo\Core\Registry\ContractRegistry;
use Mublo\Enum\Block\BlockContentKind;
use Mublo\Infrastructure\Database\Database;
use Mublo\Repository\Domain\DomainRepository;
use Mublo\Plugin\Faq\Block\FaqConfigForm;
use Mublo\Plugin\Faq\Block\FaqItemsProvider;
use Mublo\Plugin\Faq\Block\FaqRenderer;
use Mublo\Plugin\Faq\Controller\Admin\FaqCategoryController;
use Mublo\Plugin\Faq\Controller\Admin\FaqItemController;
use Mublo\Plugin\Faq\Controller\Front\FaqController;
use Mublo\Plugin\Faq\Repository\FaqRepository;
use Mublo\Plugin\Faq\Service\FaqService;
use Mublo\Repository\Menu\MenuItemRepository;
use Mublo\Service\Menu\MenuService;

/**
 * FaqProvider
 *
 * FAQ 플러그인 Provider
 */
class FaqProvider implements ExtensionProviderInterface, InstallableExtensionInterface, DataResettableInterface
{
    public function register(DependencyContainer $container): void
    {
        // Repository
        $container->singleton(FaqRepository::class, function ($c) {
            return new FaqRepository($c->get(Database::class));
        });

        // Service
        $container->singleton(FaqService::class, function ($c) {
            return new FaqService($c->get(FaqRepository::class));
        });

        // Admin Controller
        $container->singleton(FaqCategoryController::class, function ($c) {
            return new FaqCategoryController($c->get(FaqService::class));
        });

        $container->singleton(FaqItemController::class, function ($c) {
            return new FaqItemController(
                $c->get(FaqService::class),
                $c->get(MigrationRunner::class),
                $c->get(DomainRepository::class)
            );
        });

        // Front Controller
        $container->singleton(FaqController::class, function ($c) {
            return new FaqController($c->get(FaqService::class));
        });

        // Block
        $container->singleton(FaqRenderer::class, function ($c) {
            $renderer = new FaqRenderer($c->get(FaqService::class));
            $renderer->assetManager = $c->get(AssetManager::class);
            return $renderer;
        });

        $container->singleton(FaqConfigForm::class, function () {
            return new FaqConfigForm();
        });

        $container->singleton(FaqItemsProvider::class, function ($c) {
            return new FaqItemsProvider($c->get(FaqRepository::class));
        });
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);

        // 관리자 메뉴 등록
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());

        // Contract 바인딩: FaqQueryInterface → FaqService
        $contractRegistry = $container->get(ContractRegistry::class);
        $contractRegistry->bind(
            FaqQueryInterface::class,
            $container->get(FaqService::class)
        );

        // 블록 콘텐츠 타입 등록
        BlockRegistry::registerContentType(
            type: 'faq',
            kind: BlockContentKind::PLUGIN->value,
            title: 'FAQ',
            rendererClass: FaqRenderer::class,
            configFormClass: FaqConfigForm::class,
            options: [
                'skinBasePath' => MUBLO_PLUGIN_PATH . '/Faq/views/Block',
                'hasItems' => true,
                'itemsProvider' => FaqItemsProvider::class,
                'hasStyle' => true,
            ]
        );
    }

    /**
     * 첫 활성화 시: 마이그레이션 + 프론트 메뉴 등록
     */
    public function install(DependencyContainer $container, Context $context): void
    {
        // DB 마이그레이션
        $runner = $container->get(MigrationRunner::class);
        $runner->run('plugin', 'Faq', MUBLO_PLUGIN_PATH . '/Faq/database/migrations');

        // 프론트 메뉴 아이템 등록
        $menuService = $container->get(MenuService::class);
        $domainId = $context->getDomainId();

        $menuService->createItem($domainId, [
            'label' => 'FAQ',
            'url' => '/faq',
            'icon' => 'bi-question-circle',
            'provider_type' => 'plugin',
            'provider_name' => 'Faq',
        ]);
    }

    /**
     * 비활성화 시: 프론트 메뉴 삭제 (DB 데이터는 보존)
     */
    public function uninstall(DependencyContainer $container, Context $context): void
    {
        $menuService = $container->get(MenuService::class);
        $menuItemRepo = $container->get(MenuItemRepository::class);
        $domainId = $context->getDomainId();

        $items = $menuItemRepo->findByProvider($domainId, 'plugin', 'Faq');
        foreach ($items as $item) {
            $menuService->deleteItem((int) $item['item_id']);
        }
    }

    public function getResetCategories(): array
    {
        return [
            [
                'key' => 'faq',
                'label' => 'FAQ',
                'description' => 'FAQ 카테고리와 항목을 모두 삭제합니다.',
                'icon' => 'bi-question-circle',
            ],
        ];
    }

    public function reset(string $category, int $domainId, Database $db): array
    {
        if ($category !== 'faq') {
            return ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => '알 수 없는 카테고리'];
        }

        $cleared = 0;

        // faq_items 먼저 삭제 (외래키 순서)
        if ($this->tableExists($db, 'faq_items')) {
            $db->execute("DELETE FROM faq_items WHERE domain_id = ?", [$domainId]);
            $cleared++;
        }

        // faq_categories 삭제
        if ($this->tableExists($db, 'faq_categories')) {
            $db->execute("DELETE FROM faq_categories WHERE domain_id = ?", [$domainId]);
            $cleared++;
        }

        return [
            'tables_cleared' => $cleared,
            'files_deleted' => 0,
            'details' => 'FAQ 카테고리 및 항목 삭제',
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
