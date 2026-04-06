<?php

namespace Mublo\Plugin\Widget;

use Mublo\Core\Event\EventSubscriberInterface;
use Mublo\Core\Event\Rendering\FrontFootRenderEvent;
use Mublo\Plugin\Widget\Service\WidgetService;

class FrontRenderSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private WidgetService $widgetService
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
        $domainId = $event->getDomainId();
        $data = $this->widgetService->getActiveWidgets($domainId);
        $config = $data['config'];

        $html = '';

        // 각 포지션별 스킨 렌더링
        foreach (['left', 'right', 'mobile'] as $position) {
            if (empty($data[$position])) {
                continue;
            }

            $items = $data[$position];
            $skinPath = $this->widgetService->getSkinPath($domainId, $position);

            if (!file_exists($skinPath)) {
                continue;
            }

            ob_start();
            include $skinPath;  // $items, $position, $config 사용 가능
            $html .= ob_get_clean();
        }

        if ($html !== '') {
            $event->addHtml($html);
        }
    }
}
