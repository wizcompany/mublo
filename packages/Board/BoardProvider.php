<?php

namespace Mublo\Packages\Board;

use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\InstallableExtensionInterface;
use Mublo\Contract\DataResettableInterface;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Block\BlockRegistry;
use Mublo\Core\Rendering\AssetManager;
use Mublo\Core\Dashboard\DashboardWidgetRegistry;
use Mublo\Enum\Block\BlockContentKind;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Storage\FileUploader;
use Mublo\Infrastructure\Image\ImageProcessor;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Service\Auth\AuthService;
use Mublo\Service\Balance\BalanceManager;

// Repository
use Mublo\Packages\Board\Repository\BoardArticleRepository;
use Mublo\Packages\Board\Repository\BoardCommentRepository;
use Mublo\Packages\Board\Repository\BoardConfigRepository;
use Mublo\Packages\Board\Repository\BoardCategoryRepository;
use Mublo\Packages\Board\Repository\BoardGroupRepository;
use Mublo\Packages\Board\Repository\BoardAttachmentRepository;
use Mublo\Packages\Board\Repository\BoardPermissionRepository;
use Mublo\Packages\Board\Repository\BoardReactionRepository;
use Mublo\Packages\Board\Repository\BoardLinkRepository;
use Mublo\Packages\Board\Repository\BoardCategoryMappingRepository;
use Mublo\Packages\Board\Repository\BoardGroupAdminRepository;
use Mublo\Packages\Board\Repository\BoardPointConfigRepository;

// Service
use Mublo\Packages\Board\Service\BoardArticleService;
use Mublo\Packages\Board\Service\BoardCommentService;
use Mublo\Packages\Board\Service\BoardConfigService;
use Mublo\Packages\Board\Service\BoardCategoryService;
use Mublo\Packages\Board\Service\BoardGroupService;
use Mublo\Packages\Board\Service\BoardPermissionService;
use Mublo\Packages\Board\Service\BoardReactionService;
use Mublo\Packages\Board\Service\BoardFileService;
use Mublo\Packages\Board\Service\CommunityService;
use Mublo\Packages\Board\Service\BoardPointConfigService;
use Mublo\Packages\Board\Service\BoardPointService;

// Block
use Mublo\Packages\Board\Block\BoardRenderer;
use Mublo\Packages\Board\Block\BoardGroupRenderer;

// Subscriber
use Mublo\Packages\Board\Subscriber\AdminMenuSubscriber;
use Mublo\Packages\Board\Subscriber\MenuAutoRegistrationSubscriber;
use Mublo\Packages\Board\Subscriber\BoardSearchSubscriber;
use Mublo\Packages\Board\Subscriber\BoardPointSubscriber;
use Mublo\Packages\Board\Subscriber\DomainEventSubscriber;
use Mublo\Packages\Board\Subscriber\ReactionPointSubscriber;
use Mublo\Packages\Board\Subscriber\MypageContentSubscriber;
use Mublo\Packages\Board\Subscriber\BlockContentItemsSubscriber;
use Mublo\Packages\Board\Subscriber\PageTypeSubscriber;

// Widget
use Mublo\Packages\Board\Widget\RecentNoticesWidget;

class BoardProvider implements ExtensionProviderInterface, InstallableExtensionInterface, DataResettableInterface
{
    public function register(DependencyContainer $container): void
    {
        // ── Repository ──
        $container->singleton(BoardArticleRepository::class, fn(DependencyContainer $c) =>
            new BoardArticleRepository($c->get(Database::class))
        );
        $container->singleton(BoardCommentRepository::class, fn(DependencyContainer $c) =>
            new BoardCommentRepository($c->get(Database::class))
        );
        $container->singleton(BoardConfigRepository::class, fn(DependencyContainer $c) =>
            new BoardConfigRepository($c->get(Database::class))
        );
        $container->singleton(BoardCategoryRepository::class, fn(DependencyContainer $c) =>
            new BoardCategoryRepository($c->get(Database::class))
        );
        $container->singleton(BoardGroupRepository::class, fn(DependencyContainer $c) =>
            new BoardGroupRepository($c->get(Database::class))
        );
        $container->singleton(BoardAttachmentRepository::class, fn(DependencyContainer $c) =>
            new BoardAttachmentRepository($c->get(Database::class))
        );
        $container->singleton(BoardPermissionRepository::class, fn(DependencyContainer $c) =>
            new BoardPermissionRepository($c->get(Database::class))
        );
        $container->singleton(BoardReactionRepository::class, fn(DependencyContainer $c) =>
            new BoardReactionRepository($c->get(Database::class))
        );
        $container->singleton(BoardLinkRepository::class, fn(DependencyContainer $c) =>
            new BoardLinkRepository($c->get(Database::class))
        );
        $container->singleton(BoardCategoryMappingRepository::class, fn(DependencyContainer $c) =>
            new BoardCategoryMappingRepository($c->get(Database::class))
        );
        $container->singleton(BoardGroupAdminRepository::class, fn(DependencyContainer $c) =>
            new BoardGroupAdminRepository($c->get(Database::class))
        );
        $container->singleton(BoardPointConfigRepository::class, fn(DependencyContainer $c) =>
            new BoardPointConfigRepository($c->get(Database::class))
        );

        // ── Service ──
        $container->singleton(BoardPermissionService::class, fn(DependencyContainer $c) =>
            new BoardPermissionService(
                $c->get(BoardGroupRepository::class),
                $c->get(BoardCategoryMappingRepository::class),
                $c->get(BoardPermissionRepository::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(BoardArticleService::class, fn(DependencyContainer $c) =>
            new BoardArticleService(
                $c->get(BoardArticleRepository::class),
                $c->get(BoardConfigRepository::class),
                $c->get(MemberRepository::class),
                $c->get(BoardPermissionService::class),
                $c->get(EventDispatcher::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(BoardCommentService::class, fn(DependencyContainer $c) =>
            new BoardCommentService(
                $c->get(BoardCommentRepository::class),
                $c->get(BoardArticleRepository::class),
                $c->get(BoardConfigRepository::class),
                $c->get(MemberRepository::class),
                $c->get(BoardPermissionService::class),
                $c->get(EventDispatcher::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(BoardConfigService::class, fn(DependencyContainer $c) =>
            new BoardConfigService(
                $c->get(BoardConfigRepository::class),
                $c->get(BoardGroupRepository::class),
                $c->get(BoardCategoryMappingRepository::class),
                $c->get(BoardArticleRepository::class),
                $c->get(EventDispatcher::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(BoardCategoryService::class, fn(DependencyContainer $c) =>
            new BoardCategoryService(
                $c->get(BoardCategoryRepository::class),
                $c->get(BoardCategoryMappingRepository::class)
            )
        );
        $container->singleton(BoardGroupService::class, fn(DependencyContainer $c) =>
            new BoardGroupService(
                $c->get(BoardGroupRepository::class),
                $c->get(BoardPermissionRepository::class)
            )
        );
        $container->singleton(BoardReactionService::class, fn(DependencyContainer $c) =>
            new BoardReactionService(
                $c->get(BoardReactionRepository::class),
                $c->get(BoardArticleRepository::class),
                $c->get(BoardCommentRepository::class),
                $c->get(BoardConfigRepository::class),
                $c->get(MemberRepository::class),
                $c->get(BoardPermissionService::class),
                $c->get(EventDispatcher::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(BoardFileService::class, fn(DependencyContainer $c) =>
            new BoardFileService(
                $c->get(BoardAttachmentRepository::class),
                $c->get(BoardLinkRepository::class),
                $c->get(BoardArticleRepository::class),
                $c->get(BoardConfigRepository::class),
                $c->get(MemberRepository::class),
                $c->get(BoardPermissionService::class),
                $c->get(EventDispatcher::class),
                $c->get(AuthService::class),
                $c->get(FileUploader::class),
                $c->get(ImageProcessor::class)
            )
        );
        $container->singleton(CommunityService::class, fn(DependencyContainer $c) =>
            new CommunityService(
                $c->get(BoardArticleRepository::class),
                $c->get(BoardConfigRepository::class),
                $c->get(BoardGroupRepository::class),
                $c->get(BoardPermissionService::class)
            )
        );
        $container->singleton(BoardPointConfigService::class, fn(DependencyContainer $c) =>
            new BoardPointConfigService(
                $c->get(BoardPointConfigRepository::class)
            )
        );
        $container->singleton(BoardPointService::class, fn(DependencyContainer $c) =>
            new BoardPointService(
                $c->get(BalanceManager::class),
                $c->get(BoardPointConfigService::class)
            )
        );

        // ── Block ──
        $container->singleton(BoardRenderer::class, function (DependencyContainer $c) {
            $renderer = new BoardRenderer(
                $c->get(BoardArticleRepository::class),
                $c->get(BoardConfigRepository::class)
            );
            $renderer->assetManager = $c->get(AssetManager::class);
            return $renderer;
        });
        $container->singleton(BoardGroupRenderer::class, function (DependencyContainer $c) {
            $renderer = new BoardGroupRenderer(
                $c->get(BoardArticleRepository::class),
                $c->get(BoardConfigRepository::class),
                $c->get(BoardGroupRepository::class)
            );
            $renderer->assetManager = $c->get(AssetManager::class);
            return $renderer;
        });
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);

        // 블록 콘텐츠 타입 등록
        BlockRegistry::registerContentType(
            type: 'board',
            kind: BlockContentKind::PACKAGE->value,
            title: '게시판 최신글',
            rendererClass: BoardRenderer::class,
            options: [
                'hasItems' => true,
                'hasStyle' => true,
                'skinBasePath' => MUBLO_PACKAGE_PATH . '/Board/views/Block/',
            ]
        );
        BlockRegistry::registerContentType(
            type: 'boardgroup',
            kind: BlockContentKind::PACKAGE->value,
            title: '게시판 그룹',
            rendererClass: BoardGroupRenderer::class,
            options: [
                'hasItems' => true,
                'hasStyle' => true,
                'skinBasePath' => MUBLO_PACKAGE_PATH . '/Board/views/Block/',
            ]
        );

        // 이벤트 구독자 등록
        $eventDispatcher->addSubscriber(new MenuAutoRegistrationSubscriber($container));
        $eventDispatcher->addSubscriber(new BoardSearchSubscriber($container->get(BoardArticleRepository::class)));
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());

        // 도메인 생성 시 기본 게시판 자동 시딩
        $eventDispatcher->addSubscriber(new DomainEventSubscriber(
            $container->get(Database::class)
        ));

        // 포인트 이벤트 구독자 등록
        $eventDispatcher->addSubscriber(new BoardPointSubscriber(
            $container->get(BoardPointService::class),
            $container->get(BoardConfigRepository::class)
        ));
        $eventDispatcher->addSubscriber(new ReactionPointSubscriber(
            $container->get(BoardPointService::class)
        ));

        // Core 디커플링 구독자 (Mypage, Block, PageType)
        $eventDispatcher->addSubscriber(new MypageContentSubscriber(
            $container->get(BoardArticleRepository::class),
            $container->get(BoardCommentRepository::class),
        ));
        $eventDispatcher->addSubscriber(new BlockContentItemsSubscriber(
            $container->get(BoardConfigRepository::class),
            $container->get(BoardGroupRepository::class),
        ));
        $eventDispatcher->addSubscriber(new PageTypeSubscriber());

        // 대시보드 위젯 등록
        $registry = $container->get(DashboardWidgetRegistry::class);
        $domainIdResolver = fn() => $container->has(Context::class) ? $container->get(Context::class)->getDomainId() : null;
        $registry->register(
            'board.recent_notices',
            new RecentNoticesWidget($container->get(BoardArticleRepository::class), $domainIdResolver),
            2
        );
    }

    /**
     * 관리자 패널에서 첫 활성화 시 실행
     *
     * 기본 게시판 그룹 + 공지사항/자유게시판 생성 (없을 경우에만)
     */
    public function install(DependencyContainer $container, Context $context): void
    {
        DomainEventSubscriber::seedBoards(
            $container->get(Database::class),
            $context->getDomainId()
        );
    }

    /**
     * 관리자 패널에서 비활성화 시 실행
     *
     * DB 데이터는 보존 (테이블/게시글 유지)
     */
    public function uninstall(DependencyContainer $container, Context $context): void
    {
        // 게시판 데이터는 보존 — 별도 삭제 없음
    }

    // ── DataResettableInterface ──

    public function getResetCategories(): array
    {
        return [
            [
                'key' => 'board',
                'label' => '게시판',
                'description' => '게시글, 댓글, 반응, 첨부파일을 삭제합니다. (게시판 설정/그룹/카테고리는 보존)',
                'icon' => 'bi-clipboard',
            ],
        ];
    }

    public function reset(string $category, int $domainId, Database $db): array
    {
        if ($category !== 'board') {
            return ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => '알 수 없는 카테고리'];
        }

        $cleared = 0;

        $db->execute("SET FOREIGN_KEY_CHECKS = 0");

        try {
            // 삭제 대상 테이블 (FK 의존 순서: 자식 → 부모)
            $deleteTables = [
                'board_reactions',
                'board_comments',
                'board_attachments',
                'board_links',
                'board_articles',
            ];

            foreach ($deleteTables as $table) {
                if ($this->tableExists($db, $table)) {
                    $db->execute("DELETE FROM {$table} WHERE domain_id = ?", [$domainId]);
                    $cleared++;
                }
            }
        } finally {
            $db->execute("SET FOREIGN_KEY_CHECKS = 1");
        }

        // 파일 삭제: public/storage/D{domainId}/board/
        $filesDeleted = 0;
        $boardStoragePath = MUBLO_PUBLIC_STORAGE_PATH . '/D' . $domainId . '/board';

        if (is_dir($boardStoragePath)) {
            $filesDeleted = $this->deleteDirectoryRecursive($boardStoragePath);
        }

        return [
            'tables_cleared' => $cleared,
            'files_deleted' => $filesDeleted,
            'details' => '게시글/댓글/반응/첨부파일 삭제 (설정/그룹/카테고리 보존)',
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
