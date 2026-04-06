<?php
namespace Mublo\Plugin\Banner;

use Mublo\Core\Block\BlockRegistry;
use Mublo\Contract\DataResettableInterface;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\MigrationRunner;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Enum\Block\BlockContentKind;
use Mublo\Core\Rendering\AssetManager;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Storage\FileUploader;
use Mublo\Plugin\Banner\Block\BannerConfigForm;
use Mublo\Plugin\Banner\Block\BannerRenderer;
use Mublo\Plugin\Banner\Controller\BannerController;
use Mublo\Plugin\Banner\Repository\BannerRepository;
use Mublo\Plugin\Banner\Service\BannerService;

/**
 * BannerProvider
 *
 * 배너 플러그인 Provider
 */
class BannerProvider implements ExtensionProviderInterface, DataResettableInterface
{
    public function register(DependencyContainer $container): void
    {
        // Repository
        $container->singleton(BannerRepository::class, function ($c) {
            return new BannerRepository($c->get(Database::class));
        });

        // Service
        $container->singleton(BannerService::class, function ($c) {
            return new BannerService(
                $c->get(BannerRepository::class),
                $c->get(EventDispatcher::class)
            );
        });

        // Controller
        $container->singleton(BannerController::class, function ($c) {
            return new BannerController(
                $c->get(BannerService::class),
                $c->get(MigrationRunner::class),
                $c->get(FileUploader::class),
                $c->get(EventDispatcher::class)
            );
        });

        // Block
        $container->singleton(BannerRenderer::class, function ($c) {
            $renderer = new BannerRenderer(
                $c->get(BannerService::class),
                $c->get(EventDispatcher::class)
            );
            $renderer->assetManager = $c->get(AssetManager::class);
            return $renderer;
        });

        $container->singleton(BannerConfigForm::class, function () {
            return new BannerConfigForm();
        });

    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);

        // 관리자 메뉴 구독자 등록
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());

        // 블록 콘텐츠 타입 등록
        BlockRegistry::registerContentType(
            type: 'banner',
            kind: BlockContentKind::PLUGIN->value,
            title: '배너',
            rendererClass: BannerRenderer::class,
            configFormClass: BannerConfigForm::class,
            options: [
                'skinBasePath' => MUBLO_PLUGIN_PATH . '/Banner/views/Block',
                'hasItems' => true,
                'hasStyle' => true,
                'adminScript' => '/serve/plugin/Banner/assets/js/block-banner.js',
                'adminScriptInit' => 'MubloBlockBanner',
            ]
        );

    }

    public function getResetCategories(): array
    {
        return [
            [
                'key' => 'banners',
                'label' => '배너',
                'description' => '등록된 배너를 모두 삭제합니다.',
                'icon' => 'bi-image',
            ],
        ];
    }

    public function reset(string $category, int $domainId, Database $db): array
    {
        if ($category !== 'banners') {
            return ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => '알 수 없는 카테고리'];
        }

        $cleared = 0;
        $filesDeleted = 0;

        // 테이블 삭제
        if ($this->tableExists($db, 'banners')) {
            $db->execute("DELETE FROM banners WHERE domain_id = ?", [$domainId]);
            $cleared++;
        }

        // 배너 이미지 파일 삭제
        $bannerDir = MUBLO_PUBLIC_STORAGE_PATH . '/D' . $domainId . '/banner';
        if (is_dir($bannerDir)) {
            $filesDeleted = $this->deleteDirectoryRecursive($bannerDir);
        }

        return [
            'tables_cleared' => $cleared,
            'files_deleted' => $filesDeleted,
            'details' => '배너 데이터 및 이미지 파일 삭제',
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

    private function deleteDirectoryRecursive(string $dir): int
    {
        $count = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
                $count++;
            }
        }
        rmdir($dir);

        return $count;
    }
}
