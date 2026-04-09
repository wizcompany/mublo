<?php
namespace Mublo\Plugin\Banner;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Service\Admin\Event\AdminMenuBuildingEvent;

/**
 * Banner 플러그인 관리자 메뉴 Subscriber
 *
 * 블록 관리(004)에 배너 관련 서브메뉴 추가
 */
class AdminMenuSubscriber implements EventSubscriberInterface
{
    /**
     * 플러그인 정보 (code prefix용)
     */
    public const PLUGIN_NAME = 'Banner';

    /**
     * 구독할 이벤트 목록
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AdminMenuBuildingEvent::class => 'onAdminMenuBuilding',
        ];
    }

    /**
     * 관리자 메뉴 빌드 시 호출
     *
     * Core 메뉴(블록 관리 004)의 서브메뉴로 추가합니다.
     * 코드에는 prefix 없이 순수 코드만 지정하면 자동으로
     * P_Banner_{code} 형식으로 변환됩니다.
     */
    public function onAdminMenuBuilding(AdminMenuBuildingEvent $event): void
    {
        // 소스 정보 설정 (code prefix 적용을 위해)
        $event->setSource('plugin', self::PLUGIN_NAME);

        // 블록 관리(004) 서브메뉴로 추가
        $event->addSubmenusTo('004', [
            [
                'label' => '배너 관리',
                'url' => '/admin/banner/list',
                'code' => '001',  // → P_Banner_001
            ],
        ]);
    }
}
