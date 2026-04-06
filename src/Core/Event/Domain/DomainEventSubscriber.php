<?php

namespace Mublo\Core\Event\Domain;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Service\Menu\MenuService;
use Mublo\Service\Member\PolicyService;
use Mublo\Service\Extension\ExtensionService;

/**
 * Core 도메인 이벤트 구독자
 *
 * 도메인 생성 시 기본 데이터 시딩 (메뉴, 약관, 확장 기능)
 * 기존 DomainsController에서 직접 호출하던 로직을 이벤트로 분리
 */
class DomainEventSubscriber implements EventSubscriberInterface
{
    private MenuService $menuService;
    private PolicyService $policyService;
    private ?ExtensionService $extensionService;

    public function __construct(
        MenuService $menuService,
        PolicyService $policyService,
        ?ExtensionService $extensionService = null
    ) {
        $this->menuService = $menuService;
        $this->policyService = $policyService;
        $this->extensionService = $extensionService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            DomainCreatedEvent::class => 'onDomainCreated',
        ];
    }

    /**
     * 도메인 생성 시 기본 데이터 시딩
     */
    public function onDomainCreated(DomainCreatedEvent $event): void
    {
        $domainId = $event->getDomainId();

        // 기본 메뉴 시드
        $this->menuService->seedDefaultMenus($domainId);

        // 기본 약관 시드
        $this->policyService->seedDefaultPolicies($domainId);

        // 기본 확장 기능(패키지) 시드
        $this->extensionService?->seedDefaultExtensions($domainId);
    }
}
