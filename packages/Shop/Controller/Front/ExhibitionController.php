<?php

namespace Mublo\Packages\Shop\Controller\Front;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Context\Context;
use Mublo\Packages\Shop\Service\ExhibitionService;

class ExhibitionController
{
    private ExhibitionService $exhibitionService;

    public function __construct(ExhibitionService $exhibitionService)
    {
        $this->exhibitionService = $exhibitionService;
    }

    /** 기획전 목록 페이지 */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId    = $context->getDomainId() ?? 1;
        $exhibitions = $this->exhibitionService->getActiveList($domainId);

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Exhibition/List')
            ->withData([
                'pageTitle'   => '기획전',
                'exhibitions' => $exhibitions,
            ]);
    }

    /** 기획전 상세 페이지 */
    public function view(array $params, Context $context): ViewResponse
    {
        $domainId    = $context->getDomainId() ?? 1;
        $request     = $context->getRequest();
        $id          = (int) ($params['id'] ?? $params[0] ?? 0);

        $exhibition = $this->exhibitionService->getDetail($id);

        // 없거나 비활성이거나 기간 외인 경우 404
        if (!$exhibition || !$this->isVisible($exhibition)) {
            return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Exhibition/List')
                ->withData([
                    'pageTitle'   => '기획전',
                    'exhibitions' => $this->exhibitionService->getActiveList($domainId),
                    'error'       => '기획전을 찾을 수 없습니다.',
                ]);
        }

        return ViewResponse::absoluteView(dirname(__DIR__, 2) . '/views/Front/basic/Exhibition/View')
            ->withData([
                'pageTitle'  => $exhibition['title'],
                'exhibition' => $exhibition,
                'items'      => $exhibition['items'] ?? [],
            ]);
    }

    private function isVisible(array $exhibition): bool
    {
        if (!$exhibition['is_active']) {
            return false;
        }
        $now = time();
        if (!empty($exhibition['start_date']) && strtotime($exhibition['start_date']) > $now) {
            return false;
        }
        if (!empty($exhibition['end_date']) && strtotime($exhibition['end_date']) < $now) {
            return false;
        }
        return true;
    }
}
