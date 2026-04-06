<?php

namespace Mublo\Packages\Shop\EventSubscriber;

use Mublo\Core\Event\Domain\DomainCreatedEvent;
use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Repository\Menu\MenuItemRepository;
use Mublo\Service\Menu\MenuService;

/**
 * 도메인 생성 시 Shop 프론트 메뉴 자동 시딩
 *
 * 패키지가 활성화된 상태에서 새 도메인이 생성되면
 * install()과 동일한 메뉴를 자동으로 등록한다.
 */
class DomainEventSubscriber implements EventSubscriberInterface
{
    private MenuService $menuService;
    private MenuItemRepository $menuItemRepo;

    public function __construct(MenuService $menuService, MenuItemRepository $menuItemRepo)
    {
        $this->menuService = $menuService;
        $this->menuItemRepo = $menuItemRepo;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DomainCreatedEvent::class => 'onDomainCreated',
        ];
    }

    public function onDomainCreated(DomainCreatedEvent $event): void
    {
        $domainId = $event->getDomainId();

        // 이미 Shop 메뉴가 존재하면 건너뜀 (중복 방지)
        $existing = $this->menuItemRepo->findByProvider($domainId, 'package', 'Shop');
        if (!empty($existing)) {
            return;
        }

        self::seedMenus($this->menuService, $domainId);
    }

    /**
     * Shop 프론트 메뉴 시딩 (install + DomainCreatedEvent 공용)
     */
    public static function seedMenus(MenuService $menuService, int $domainId): void
    {
        // 프론트 메뉴
        $menuService->createItem($domainId, [
            'label' => '상품',
            'url' => '/shop',
            'icon' => 'bi-bag',
            'provider_type' => 'package',
            'provider_name' => 'Shop',
        ]);

        $menuService->createItem($domainId, [
            'label' => '장바구니',
            'url' => '/shop/cart',
            'icon' => 'bi-cart',
            'provider_type' => 'package',
            'provider_name' => 'Shop',
        ]);

        // 마이페이지 메뉴
        $menuService->createItem($domainId, [
            'label' => '주문내역',
            'url' => '/shop/orders',
            'icon' => 'bi-receipt',
            'visibility' => 'member',
            'show_in_mypage' => 1,
            'mypage_order' => 500,
            'provider_type' => 'package',
            'provider_name' => 'Shop',
        ]);

        $menuService->createItem($domainId, [
            'label' => '쿠폰',
            'url' => '/shop/coupons',
            'icon' => 'bi-ticket-perforated',
            'visibility' => 'member',
            'show_in_mypage' => 1,
            'mypage_order' => 600,
            'provider_type' => 'package',
            'provider_name' => 'Shop',
        ]);
    }
}
