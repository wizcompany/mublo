<?php
namespace Mublo\Controller\Admin;

use Mublo\Core\Context\Context;
use Mublo\Core\Response\ViewResponse;

class ManualController
{
    /**
     * GET /admin/manual
     */
    public function index(array $params, Context $context): ViewResponse
    {
        return ViewResponse::view('manual/index')->withData([
            'pageTitle' => '관리자 매뉴얼',
        ]);
    }
}

