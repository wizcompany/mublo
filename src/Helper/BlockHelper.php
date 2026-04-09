<?php
namespace Mublo\Helper;

use Mublo\Core\Container\DependencyContainer;
use Mublo\Service\Block\BlockRenderService;

/**
 * BlockHelper
 *
 * 블록 렌더링 헬퍼 함수
 *
 * 레이아웃 파일에서 쉽게 블록을 출력할 수 있도록 지원
 *
 * 사용 예:
 * ```php
 * // 레이아웃 파일에서
 * <?= BlockHelper::position($domainId, 'index') ?>
 * <?= BlockHelper::position($domainId, 'left', $currentMenu) ?>
 *
 * // 블록 페이지에서
 * <?= BlockHelper::page($pageId) ?>
 * ```
 */
class BlockHelper
{
    private static ?BlockRenderService $renderService = null;

    /**
     * 위치별 블록 출력
     *
     * @param int $domainId 도메인 ID
     * @param string $position 위치 (index, left, right, subhead, subfoot, contenthead, contentfoot)
     * @param string|null $menuCode 메뉴 코드 (특정 메뉴용)
     * @param bool $useCache 캐시 사용 여부
     * @return string 렌더링된 HTML
     */
    public static function position(
        int $domainId,
        string $position,
        ?string $menuCode = null,
        bool $useCache = true
    ): string {
        return self::getRenderService()->renderPosition($domainId, $position, $menuCode, $useCache);
    }

    /**
     * 페이지별 블록 출력
     *
     * @param int $pageId 페이지 ID
     * @param bool $useCache 캐시 사용 여부
     * @return string 렌더링된 HTML
     */
    public static function page(int $pageId, bool $useCache = true): string
    {
        return self::getRenderService()->renderPage($pageId, $useCache);
    }

    /**
     * 메인 화면 블록 (index 위치)
     */
    public static function index(int $domainId, bool $useCache = true): string
    {
        return self::position($domainId, 'index', null, $useCache);
    }

    /**
     * 왼쪽 사이드바 블록
     */
    public static function left(int $domainId, ?string $menuCode = null, bool $useCache = true): string
    {
        return self::position($domainId, 'left', $menuCode, $useCache);
    }

    /**
     * 오른쪽 사이드바 블록
     */
    public static function right(int $domainId, ?string $menuCode = null, bool $useCache = true): string
    {
        return self::position($domainId, 'right', $menuCode, $useCache);
    }

    /**
     * 서브페이지 상단 블록
     */
    public static function subhead(int $domainId, ?string $menuCode = null, bool $useCache = true): string
    {
        return self::position($domainId, 'subhead', $menuCode, $useCache);
    }

    /**
     * 서브페이지 하단 블록
     */
    public static function subfoot(int $domainId, ?string $menuCode = null, bool $useCache = true): string
    {
        return self::position($domainId, 'subfoot', $menuCode, $useCache);
    }

    /**
     * 콘텐츠 상단 블록
     */
    public static function contenthead(int $domainId, ?string $menuCode = null, bool $useCache = true): string
    {
        return self::position($domainId, 'contenthead', $menuCode, $useCache);
    }

    /**
     * 콘텐츠 하단 블록
     */
    public static function contentfoot(int $domainId, ?string $menuCode = null, bool $useCache = true): string
    {
        return self::position($domainId, 'contentfoot', $menuCode, $useCache);
    }

    /**
     * 캐시 무효화 - 위치별
     */
    public static function invalidatePosition(int $domainId, string $position, ?string $menuCode = null): void
    {
        self::getRenderService()->invalidatePositionListCache($domainId, $position, $menuCode);
    }

    /**
     * 캐시 무효화 - 페이지별
     */
    public static function invalidatePage(int $pageId): void
    {
        self::getRenderService()->invalidatePageListCache($pageId);
    }

    /**
     * 캐시 무효화 - 도메인 전체
     */
    public static function invalidateDomain(int $domainId): void
    {
        self::getRenderService()->invalidateDomainCache($domainId);
    }

    /**
     * RenderService 인스턴스 반환
     */
    private static function getRenderService(): BlockRenderService
    {
        if (self::$renderService === null) {
            self::$renderService = DependencyContainer::getInstance()->get(BlockRenderService::class);
        }

        return self::$renderService;
    }

    /**
     * RenderService 인스턴스 설정 (테스트용)
     */
    public static function setRenderService(?BlockRenderService $service): void
    {
        self::$renderService = $service;
    }
}
