<?php

namespace Mublo\Packages\Shop\EventSubscriber;

use Mublo\Core\Event\Auth\LoginFormRenderingEvent;
use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Packages\Shop\Service\ShopConfigService;

/**
 * Shop 로그인 폼 Subscriber
 *
 * 체크아웃에서 로그인 페이지로 넘어온 경우,
 * "비회원으로 주문하기" 버튼을 로그인 폼에 주입한다.
 *
 * 조건: active_package=shop AND shop.is_checkout=true
 */
class LoginFormSubscriber implements EventSubscriberInterface
{
    private const VIEW_BASE_PATH = '/Shop/views/Front';

    private ShopConfigService $configService;

    public function __construct(ShopConfigService $configService)
    {
        $this->configService = $configService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginFormRenderingEvent::class => 'onLoginFormRendering',
        ];
    }

    public function onLoginFormRendering(LoginFormRenderingEvent $event): void
    {
        $context = $event->getContext();

        // Shop 패키지 활성 + 체크아웃 흐름일 때만 표시
        if ($context->getAttribute('active_package') !== 'shop') {
            return;
        }
        if (!$context->getAttribute('shop.is_checkout')) {
            return;
        }

        $event->addHtml($this->renderView('Ui/GuestOrderButton', $context), 100);
    }

    /**
     * 패키지 프론트 뷰 렌더링 (스킨 fallback 포함)
     *
     * 탐색 순서:
     *   1. {basePath}/{skin_name}/{viewName}.php  (쇼핑몰 설정 스킨)
     *   2. {basePath}/basic/{viewName}.php         (기본 폴백)
     */
    private function renderView(string $viewName, \Mublo\Core\Context\Context $context): string
    {
        $domainId = $context->getDomainId();
        $config = $this->configService->getConfig($domainId)->get('config', []);
        $skin = $config['skin_name'] ?? 'basic';
        $basePath = MUBLO_PACKAGE_PATH . self::VIEW_BASE_PATH;

        $viewFile = "{$basePath}/{$skin}/{$viewName}.php";
        if (!file_exists($viewFile)) {
            $viewFile = "{$basePath}/basic/{$viewName}.php";
        }

        ob_start();
        include $viewFile;
        return ob_get_clean();
    }
}
