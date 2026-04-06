<?php
namespace Mublo\Controller\Front;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Context\Context;
use Mublo\Service\Block\BlockRenderService;

class IndexController
{
    private BlockRenderService $blockRenderService;

    public function __construct(BlockRenderService $blockRenderService)
    {
        $this->blockRenderService = $blockRenderService;
    }

    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;

        // index 위치의 블록 렌더링
        $blockHtml = $this->blockRenderService->renderPosition($domainId, 'index');

        return ViewResponse::view('index/index')
            ->withData([
                'pageTitle' => '',
                'blockHtml' => $blockHtml,
                '_pageConfig' => [
                    'use_fullpage' => 1,
                ],
            ]);
    }
}
