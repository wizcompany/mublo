<?php

namespace Mublo\Packages\Shop\EventSubscriber;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Packages\Shop\Event\ExhibitionCreatedEvent;
use Mublo\Packages\Shop\Event\ExhibitionDeletedEvent;
use Mublo\Service\Menu\MenuService;
use Mublo\Repository\Menu\MenuItemRepository;

/**
 * 기획전 → 메뉴 아이템 자동 등록/삭제
 *
 * 기획전 생성 → menu_items에 자동 등록 (menu_tree 배치는 관리자 수동)
 * 기획전 삭제 → 해당 menu_item 자동 삭제
 */
class ExhibitionMenuSubscriber implements EventSubscriberInterface
{
    private DependencyContainer $container;

    public function __construct(DependencyContainer $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ExhibitionCreatedEvent::class => 'onCreated',
            ExhibitionDeletedEvent::class => 'onDeleted',
        ];
    }

    public function onCreated(ExhibitionCreatedEvent $event): void
    {
        $menuService = $this->container->get(MenuService::class);

        $menuService->createItem($event->getDomainId(), [
            'label'         => $event->getTitle(),
            'url'           => $event->getUrl(),
            'icon'          => 'bi-megaphone',
            'provider_type' => 'package',
            'provider_name' => 'shop_exhibition',
        ]);
    }

    public function onDeleted(ExhibitionDeletedEvent $event): void
    {
        $menuService = $this->container->get(MenuService::class);
        $menuItemRepository = $this->container->get(MenuItemRepository::class);

        $targetUrl = $event->getUrl();

        $items = $menuItemRepository->findByProvider(
            $event->getDomainId(),
            'package',
            'shop_exhibition'
        );

        foreach ($items as $item) {
            if (($item['url'] ?? '') === $targetUrl) {
                $menuService->deleteItem((int) $item['item_id']);
                break;
            }
        }
    }
}
