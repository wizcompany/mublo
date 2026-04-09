<?php

namespace Mublo\Plugin\Popup;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Core\Event\Rendering\FrontFootRenderEvent;
use Mublo\Plugin\Popup\Service\PopupService;

class FrontRenderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private PopupService $popupService
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FrontFootRenderEvent::class => 'onFrontFootRender',
        ];
    }

    public function onFrontFootRender(FrontFootRenderEvent $event): void
    {
        $skinPath = $this->popupService->getSkinPath($event->getDomainId());

        if (!file_exists($skinPath)) {
            return;
        }

        ob_start();
        include $skinPath;
        $html = ob_get_clean();

        $event->addHtml($html);
    }
}
