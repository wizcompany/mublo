<?php

namespace Mublo\Controller\Front;

use Mublo\Core\Context\Context;
use Mublo\Core\Http\Request;
use Mublo\Core\Response\FileResponse;

/**
 * RobotsController
 *
 * /robots.txt 요청 처리
 *
 * 우선순위:
 * 1. 도메인 seo_config.robots_txt 값이 있으면 해당 내용 반환
 * 2. 없으면 public/robots.txt 파일 폴백
 */
class RobotsController
{
    public function index(Request $request, Context $context): FileResponse
    {
        $domainInfo = $context->getDomainInfo();
        $seoConfig  = $domainInfo?->getSeoConfig() ?? [];
        $robotsTxt  = trim($seoConfig['robots_txt'] ?? '');

        if ($robotsTxt !== '') {
            return new FileResponse(
                filePath: null,
                statusCode: 200,
                headers: ['Content-Type' => 'text/plain; charset=utf-8'],
                content: $robotsTxt
            );
        }

        $fallbackPath = MUBLO_PUBLIC_PATH . '/robots.txt';

        return new FileResponse(
            filePath: $fallbackPath,
            statusCode: 200,
            headers: ['Content-Type' => 'text/plain; charset=utf-8']
        );
    }
}
