<?php
namespace Mublo\Subscriber;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Core\Event\Block\BlockPageCreatedEvent;
use Mublo\Core\Event\Block\BlockPageDeletedEvent;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Service\Menu\MenuService;
use Mublo\Repository\Menu\MenuItemRepository;

/**
 * 블록 페이지 → 메뉴 아이템 자동 등록/삭제
 *
 * 페이지 생성 → menu_items에 자동 등록 (menu_tree 배치는 관리자 수동)
 * 페이지 삭제 → 해당 menu_item 자동 삭제
 */
class BlockPageMenuSubscriber implements EventSubscriberInterface
{
    private DependencyContainer $container;

    public function __construct(DependencyContainer $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BlockPageCreatedEvent::class => 'onPageCreated',
            BlockPageDeletedEvent::class => 'onPageDeleted',
        ];
    }

    /**
     * 블록 페이지 생성 → 메뉴 아이템 자동 등록
     */
    public function onPageCreated(BlockPageCreatedEvent $event): void
    {
        $menuService = $this->container->get(MenuService::class);

        $menuService->createItem($event->getDomainId(), [
            'label' => $event->getPageTitle(),
            'url' => '/p/' . $event->getPageCode(),
            'icon' => 'bi-file-earmark-text',
            'provider_type' => 'core',
            'provider_name' => 'blockpage',
        ]);
    }

    /**
     * 블록 페이지 삭제 → 해당 메뉴 아이템 자동 삭제
     */
    public function onPageDeleted(BlockPageDeletedEvent $event): void
    {
        $menuService = $this->container->get(MenuService::class);
        $menuItemRepository = $this->container->get(MenuItemRepository::class);

        $targetUrl = '/p/' . $event->getPageCode();

        $items = $menuItemRepository->findByProvider(
            $event->getDomainId(),
            'core',
            'blockpage'
        );

        foreach ($items as $item) {
            if (($item['url'] ?? '') === $targetUrl) {
                $menuService->deleteItem((int) $item['item_id']);
                break;
            }
        }
    }
}
