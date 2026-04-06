<?php
namespace Mublo\Service\Block;

use Mublo\Core\Block\BlockRegistry;
use Mublo\Enum\Block\BlockPosition;
use Mublo\Core\Block\Renderer\RendererInterface;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Entity\Block\BlockRow;
use Mublo\Entity\Block\BlockColumn;
use Mublo\Repository\Block\BlockRowRepository;
use Mublo\Repository\Block\BlockColumnRepository;
use Mublo\Core\Rendering\AssetManager;
use Mublo\Infrastructure\Cache\CacheInterface;

/**
 * BlockRenderService
 *
 * 블록 프론트 렌더링 서비스
 *
 * 역할:
 * - 위치/페이지별 블록 렌더링
 * - 2단계 캐싱 (행 목록 + 행 콘텐츠 분리)
 * - DI 컨테이너를 통한 Renderer 인스턴스 관리
 * - 에러 핸들링 및 로깅
 *
 * 캐시 전략:
 * - 행 목록 캐시: block:ids:pos:{domainId}:{position} → row_id 배열
 * - 행 콘텐츠 캐시: block:row:{rowId} → 개별 행 HTML
 * - 행 1개 수정 시 해당 행만 재렌더링, 같은 위치의 다른 행은 캐시 유지
 */
class BlockRenderService
{
    private BlockRowRepository $rowRepository;
    private BlockColumnRepository $columnRepository;
    private CacheInterface $cache;
    private DependencyContainer $container;

    /**
     * 캐시 TTL (기본 1시간)
     */
    private const CACHE_TTL = 3600;

    /**
     * 캐시 프리픽스
     */
    private const CACHE_PREFIX = 'block:';

    /**
     * 로드된 렌더러 인스턴스
     */
    private array $renderers = [];

    /**
     * 디버그 모드 여부
     */
    private bool $isDebug;

    public function __construct(
        BlockRowRepository $rowRepository,
        BlockColumnRepository $columnRepository,
        CacheInterface $cache,
        DependencyContainer $container
    ) {
        $this->rowRepository = $rowRepository;
        $this->columnRepository = $columnRepository;
        $this->cache = $cache;
        $this->container = $container;
        $this->isDebug = ($_ENV['APP_DEBUG'] ?? 'false') === 'true';
    }

    // ========================================
    // 위치 기반 렌더링
    // ========================================

    /**
     * 위치별 블록 렌더링
     *
     * 2단계 캐시:
     * 1. 행 목록 캐시 (row_id 배열) → DB 조회 절약
     * 2. 행별 콘텐츠 캐시 (HTML) → 개별 행만 재렌더링
     *
     * @param int $domainId 도메인 ID
     * @param string $position 위치 (index, left, right, etc.)
     * @param string|null $menuCode 메뉴 코드 (특정 메뉴용)
     * @param bool $useCache 캐시 사용 여부
     * @return string 렌더링된 HTML
     */
    public function renderPosition(
        int $domainId,
        string $position,
        ?string $menuCode = null,
        bool $useCache = true
    ): string {
        // 디버그 모드에서는 캐시 비활성화
        if ($this->isDebug) {
            $useCache = false;
        }

        $debug = '';
        if ($this->isDebug) {
            $debug = "<!-- [Block] renderPosition: domain={$domainId}, position={$position}, menu={$menuCode} -->\n";
        }

        // 1단계: 행 목록 캐시에서 row 조회
        $rows = $this->getRowsForPosition($domainId, $position, $menuCode, $useCache);

        if ($this->isDebug) {
            $debug .= "<!-- [Block] rows found: " . count($rows) . " -->\n";
        }

        // 2단계: 각 행을 개별 캐시에서 렌더링
        $html = $this->renderRowsWithCache($rows, $useCache);

        return $debug . $html;
    }

    /**
     * 위치별 행 목록 캐시 무효화
     */
    public function invalidatePositionListCache(int $domainId, string $position, ?string $menuCode = null): void
    {
        $cacheKey = $this->getPositionListCacheKey($domainId, $position, $menuCode);
        $this->cache->delete($cacheKey);
    }

    // ========================================
    // 페이지 기반 렌더링
    // ========================================

    /**
     * 페이지별 블록 렌더링
     *
     * @param int $pageId 페이지 ID
     * @param bool $useCache 캐시 사용 여부
     * @return string 렌더링된 HTML
     */
    public function renderPage(int $pageId, bool $useCache = true): string
    {
        // 디버그 모드에서는 캐시 비활성화
        if ($this->isDebug) {
            $useCache = false;
        }

        // 1단계: 행 목록 캐시에서 row 조회
        $rows = $this->getRowsForPage($pageId, $useCache);

        // 2단계: 각 행을 개별 캐시에서 렌더링
        return $this->renderRowsWithCache($rows, $useCache);
    }

    /**
     * 페이지별 행 목록 캐시 무효화
     */
    public function invalidatePageListCache(int $pageId): void
    {
        $cacheKey = $this->getPageListCacheKey($pageId);
        $this->cache->delete($cacheKey);
    }

    // ========================================
    // 행 렌더링
    // ========================================

    /**
     * 단일 행 렌더링
     *
     * 캐시 시 HTML과 에셋(CSS/JS) 경로를 함께 저장하여
     * 캐시 히트 시에도 스킨 CSS/JS가 정상 등록됩니다.
     *
     * @param BlockRow $row 행 엔티티
     * @param bool $useCache 캐시 사용 여부
     * @return string 렌더링된 HTML
     */
    public function renderRow(BlockRow $row, bool $useCache = true): string
    {
        if (!$row->isActive()) {
            return '';
        }

        $cacheKey = $this->getRowCacheKey($row->getRowId());
        $assets = $this->getAssetManager();

        // 캐시 히트: HTML 반환 + 에셋 재등록
        if ($useCache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                // 레거시 호환: 문자열이면 HTML만 있는 구 캐시
                if (is_string($cached)) {
                    return $cached;
                }

                // 에셋 재등록
                if ($assets && is_array($cached)) {
                    foreach ($cached['c'] ?? [] as $css) {
                        $assets->addCss($css);
                    }
                    foreach ($cached['j'] ?? [] as $js) {
                        $assets->addJs($js);
                    }
                }

                return $cached['h'] ?? '';
            }
        }

        // 렌더링 전 에셋 스냅샷
        $cssBefore = $assets ? $assets->getCssPaths() : [];
        $jsBefore = $assets ? $assets->getJsPaths() : [];

        // buildRowHtml 내부에서 noCache 콘텐츠 감지 시 플래그 설정
        $this->_rowHasNoCache = false;
        $html = $this->buildRowHtml($row);

        if ($useCache && !$this->_rowHasNoCache && !empty($html)) {
            // 렌더링 중 추가된 에셋만 추출
            $newCss = $assets ? array_values(array_diff($assets->getCssPaths(), $cssBefore)) : [];
            $newJs = $assets ? array_values(array_diff($assets->getJsPaths(), $jsBefore)) : [];

            $this->cache->set($cacheKey, [
                'h' => $html,
                'c' => $newCss,
                'j' => $newJs,
            ], self::CACHE_TTL);
        }

        return $html;
    }

    /**
     * Entity 기반 행 렌더링 (미리보기용)
     *
     * DB 조회 없이 전달받은 Row/Column Entity로 직접 렌더링.
     * 캐시 미사용, 디버그 주석 미출력.
     *
     * @param BlockRow $row 행 엔티티
     * @param BlockColumn[] $columns 칸 엔티티 배열
     * @return string 렌더링된 HTML
     */
    public function renderRowFromEntities(BlockRow $row, array $columns): string
    {
        if (empty($columns)) {
            return '';
        }

        try {
            $rowAttributes = $this->buildRowAttributes($row);
            $rowStyle = $this->buildRowStyle($row);
            $columnsHtml = $this->buildColumnsHtml($row, $columns);

            if (trim($columnsHtml) === '') {
                return '';
            }

            $containerClass = $row->isWide() ? 'block-container--wide' : 'block-container--contained';

            return <<<HTML
<section{$rowAttributes}>
    <div class="block-container {$containerClass}">
        <div class="block-row" style="{$rowStyle}">
            {$columnsHtml}
        </div>
    </div>
</section>
HTML;
        } catch (\Throwable $e) {
            $this->logError("Preview render failed", [
                'error' => $e->getMessage(),
            ]);
            return '';
        }
    }

    /**
     * AssetManager 인스턴스 반환
     */
    private function getAssetManager(): ?AssetManager
    {
        try {
            return $this->container->get(AssetManager::class);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * 행 콘텐츠 캐시 무효화
     */
    public function invalidateRowCache(int $rowId): void
    {
        $cacheKey = $this->getRowCacheKey($rowId);
        $this->cache->delete($cacheKey);
    }

    /**
     * 행 목록을 개별 캐시를 활용하여 렌더링
     *
     * @param BlockRow[] $rows 행 목록
     * @param bool $useCache 캐시 사용 여부
     * @return string 렌더링된 HTML
     */
    private function renderRowsWithCache(array $rows, bool $useCache): string
    {
        $output = '';

        foreach ($rows as $row) {
            if (!$row->isActive()) {
                continue;
            }

            $output .= $this->renderRow($row, $useCache);
        }

        return $output;
    }

    /**
     * 위치별 행 목록 조회 (캐시 적용)
     *
     * @return BlockRow[]
     */
    private function getRowsForPosition(int $domainId, string $position, ?string $menuCode, bool $useCache): array
    {
        if (!$useCache) {
            return $this->rowRepository->findByPosition($domainId, $position, $menuCode);
        }

        $cacheKey = $this->getPositionListCacheKey($domainId, $position, $menuCode);
        $cachedIds = $this->cache->get($cacheKey);

        if ($cachedIds !== null && is_array($cachedIds)) {
            // 캐시된 ID로 행 조회
            return $this->getRowsByIds($cachedIds);
        }

        // DB 조회
        $rows = $this->rowRepository->findByPosition($domainId, $position, $menuCode);

        // row_id 배열 캐시
        $rowIds = array_map(fn(BlockRow $row) => $row->getRowId(), $rows);
        $this->cache->set($cacheKey, $rowIds, self::CACHE_TTL);

        return $rows;
    }

    /**
     * 페이지별 행 목록 조회 (캐시 적용)
     *
     * @return BlockRow[]
     */
    private function getRowsForPage(int $pageId, bool $useCache): array
    {
        if (!$useCache) {
            return $this->rowRepository->findByPage($pageId);
        }

        $cacheKey = $this->getPageListCacheKey($pageId);
        $cachedIds = $this->cache->get($cacheKey);

        if ($cachedIds !== null && is_array($cachedIds)) {
            return $this->getRowsByIds($cachedIds);
        }

        // DB 조회
        $rows = $this->rowRepository->findByPage($pageId);

        // row_id 배열 캐시
        $rowIds = array_map(fn(BlockRow $row) => $row->getRowId(), $rows);
        $this->cache->set($cacheKey, $rowIds, self::CACHE_TTL);

        return $rows;
    }

    /**
     * ID 배열로 행 엔티티 조회
     *
     * @param int[] $rowIds
     * @return BlockRow[]
     */
    private function getRowsByIds(array $rowIds): array
    {
        return $this->rowRepository->findByIds($rowIds);
    }

    /**
     * 행 HTML 빌드
     */
    /**
     * buildRowHtml 실행 중 noCache 콘텐츠 감지 플래그
     */
    private bool $_rowHasNoCache = false;

    /**
     * 캐시 키 변형 — 동일 rowId라도 컨텍스트(브랜드샵 등)가 다른 경우 분리
     * BrandContextSubscriber 등에서 setSiteOverride 이후 setCacheVariant()로 설정
     */
    private string $cacheVariant = '';

    public function setCacheVariant(string $variant): void
    {
        $this->cacheVariant = $variant;
    }

    private function buildRowHtml(BlockRow $row): string
    {
        $rowId = $row->getRowId();
        $debug = $this->isDebug ? "<!-- [Block] buildRowHtml: row_id={$rowId} -->\n" : '';

        try {
            $columns = $this->columnRepository->findByRow($rowId);

            // noCache 콘텐츠 감지 (로그인 위젯 등 사용자 상태 의존)
            foreach ($columns as $column) {
                $typeStr = $column->getContentTypeString();
                if ($typeStr && $column->isActive() && BlockRegistry::isNoCache($typeStr)) {
                    $this->_rowHasNoCache = true;
                    break;
                }
            }

            if ($this->isDebug) {
                $debug .= "<!-- [Block] row_id={$rowId}, columns=" . count($columns) . " -->\n";
            }

            if (empty($columns)) {
                return $this->isDebug
                    ? $debug . "<!-- [Block] row_id={$rowId} has no columns -->\n"
                    : '';
            }

            $rowAttributes = $this->buildRowAttributes($row);
            $rowStyle = $this->buildRowStyle($row);
            $columnsHtml = $this->buildColumnsHtml($row, $columns);

            // 칸 콘텐츠가 모두 비어있으면 행 자체를 출력하지 않음
            if (trim($columnsHtml) === '') {
                return $debug;
            }

            // 와이드 타입 여부
            $containerClass = $row->isWide() ? 'block-container--wide' : 'block-container--contained';

            $html = <<<HTML
{$debug}<section{$rowAttributes}>
    <div class="block-container {$containerClass}">
        <div class="block-row" style="{$rowStyle}">
            {$columnsHtml}
        </div>
    </div>
</section>
HTML;

            return $html;
        } catch (\Throwable $e) {
            $this->logError("Row build failed", [
                'row_id' => $rowId,
                'error' => $e->getMessage(),
            ]);
            return $this->renderErrorPlaceholder("Row build error: row_id={$rowId}");
        }
    }

    /**
     * 행 속성 빌드
     */
    private function buildRowAttributes(BlockRow $row): string
    {
        $attrs = [];

        // CSS ID
        if ($row->getSectionId()) {
            $attrs[] = 'id="' . htmlspecialchars($row->getSectionId()) . '"';
        }

        // CSS Class
        $classes = ['block-section'];
        if ($row->isWide()) {
            $classes[] = 'block-section--wide';
        }
        $attrs[] = 'class="' . implode(' ', $classes) . '"';

        // 배경 스타일
        $bgStyle = $row->getBackgroundStyle();
        if ($bgStyle) {
            $attrs[] = 'style="' . htmlspecialchars($bgStyle) . '"';
        }

        return ' ' . implode(' ', $attrs);
    }

    /**
     * 행 내부 스타일 빌드
     */
    private function buildRowStyle(BlockRow $row): string
    {
        $styles = [];

        // PC 패딩
        if ($row->getPcPadding()) {
            $styles[] = "padding: {$row->getPcPadding()}";
        }

        // 칸 간격 (gap)
        if ($row->getColumnMargin() > 0) {
            $styles[] = "gap: {$row->getColumnMargin()}px";
        }

        return implode('; ', $styles);
    }

    // ========================================
    // 칸 렌더링
    // ========================================

    /**
     * 칸 목록 HTML 빌드
     *
     * @param BlockRow $row 행 엔티티
     * @param BlockColumn[] $columns 칸 목록
     * @return string HTML
     */
    private function buildColumnsHtml(BlockRow $row, array $columns): string
    {
        $html = '';
        $totalColumns = count($columns);

        foreach ($columns as $index => $column) {
            if (!$column->isActive()) {
                continue;
            }

            $html .= $this->buildColumnHtml($column, $row, $totalColumns);
        }

        return $html;
    }

    /**
     * 단일 칸 HTML 빌드
     *
     * 타이틀과 콘텐츠는 각 렌더러의 스킨에서 처리
     */
    private function buildColumnHtml(BlockColumn $column, BlockRow $row, int $totalColumns): string
    {
        $contentHtml = $this->renderColumnContent($column);

        // 콘텐츠가 비어있으면 칸 자체를 출력하지 않음
        if ($contentHtml === '' || $contentHtml === null) {
            return '';
        }

        $columnStyle = $this->buildColumnStyle($column, $row, $totalColumns);
        $aosAttr = '';
        if ($column->getAos()) {
            $aosAttr = ' data-aos="' . htmlspecialchars($column->getAos()) . '"';
            $aosDuration = $column->getAosDuration();
            if ($aosDuration && $aosDuration !== 600) {
                $aosAttr .= ' data-aos-duration="' . $aosDuration . '"';
            }
        }

        $html = <<<HTML
<div class="block-column" style="{$columnStyle}"{$aosAttr}>
    {$contentHtml}
</div>
HTML;

        return $html;
    }

    /**
     * 칸 스타일 빌드
     */
    private function buildColumnStyle(BlockColumn $column, BlockRow $row, int $totalColumns): string
    {
        $styles = [];

        // 너비
        $width = $column->getWidth();
        if ($width) {
            $gap = $row->getColumnMargin();
            // % 너비에 gap이 있을 경우 auto-calc와 동일하게 gap 차감 보정
            if ($gap > 0 && $totalColumns > 1 && str_ends_with($width, '%')) {
                $gapShare = round($gap * ($totalColumns - 1) / $totalColumns, 2);
                $styles[] = "width: calc({$width} - {$gapShare}px)";
                $styles[] = "flex: 0 0 calc({$width} - {$gapShare}px)";
            } else {
                $styles[] = "width: {$width}";
                $styles[] = "flex: 0 0 {$width}";
            }
        } else {
            // 기본 균등 분할 (gap 고려)
            $gap = $row->getColumnMargin();
            $percent = round(100 / $totalColumns, 2);
            $unit = $row->getColumnWidthUnitString();

            if ($gap > 0 && $totalColumns > 1 && $unit === '%') {
                // gap 총량을 칸 수로 나눠 각 칸의 flex-basis에서 차감
                $gapShare = round($gap * ($totalColumns - 1) / $totalColumns, 2);
                $styles[] = "flex: 1 1 calc({$percent}% - {$gapShare}px)";
            } else {
                $styles[] = "flex: 1 1 {$percent}{$unit}";
            }
        }

        // 패딩
        if ($column->getPcPadding()) {
            $styles[] = "padding: {$column->getPcPadding()}";
        }

        // 배경
        $bgStyle = $column->getBackgroundStyle();
        if ($bgStyle) {
            $styles[] = $bgStyle;
        }

        // 테두리
        $borderStyle = $column->getBorderStyle();
        if ($borderStyle) {
            $styles[] = $borderStyle;
        }

        return implode('; ', $styles);
    }


    // ========================================
    // 콘텐츠 렌더링
    // ========================================

    /**
     * 칸 콘텐츠 렌더링
     */
    private function renderColumnContent(BlockColumn $column): string
    {
        $columnId = $column->getColumnId();
        $debug = $this->isDebug ? "<!-- [Block Debug] column_id={$columnId}" : '';

        if (!$column->hasContent()) {
            return $this->isDebug ? "<!-- [Block Debug] column_id={$columnId}, hasContent=false -->" : '';
        }

        $contentType = $column->getContentTypeString();

        if ($this->isDebug) {
            $debug .= ", type={$contentType}, hasContent=" . ($column->hasContent() ? 'true' : 'false');
        }

        if (!$contentType) {
            return $this->isDebug ? "<!-- [Block Debug] column_id={$columnId}, contentType=empty -->" : '';
        }

        try {
            $renderer = $this->getRenderer($contentType);
            $rendererClass = $renderer ? get_class($renderer) : 'null';

            if (!$renderer) {
                $this->logError("Unknown content type", [
                    'content_type' => $contentType,
                    'column_id' => $columnId,
                ]);
                return $this->renderErrorPlaceholder("Unknown content type: {$contentType}");
            }

            $result = $renderer->render($column);

            if ($this->isDebug) {
                $debug .= ", renderer={$rendererClass}, result_len=" . strlen($result) . " -->\n";
                return $debug . $result;
            }

            return $result;
        } catch (\Throwable $e) {
            $this->logError("Render failed", [
                'content_type' => $contentType,
                'column_id' => $columnId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return $this->renderErrorPlaceholder("Render error: {$contentType}");
        }
    }

    /**
     * 에러 발생 시 플레이스홀더 렌더링
     *
     * 디버그 모드에서만 에러 정보 표시
     */
    private function renderErrorPlaceholder(string $message): string
    {
        if ($this->isDebug) {
            return "<!-- [Block Error] {$message} -->";
        }

        // 프로덕션에서는 빈 문자열 반환 (에러 정보 노출 방지)
        return '';
    }

    /**
     * 에러 로깅
     *
     * 블록 렌더링 에러를 파일에 기록
     */
    private function logError(string $message, array $context = []): void
    {
        $logMessage = "[BlockRenderService] {$message}";

        if (!empty($context)) {
            $logMessage .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
        }

        error_log($logMessage);

        // 로그 파일에도 기록 (storage/logs/block_error.log)
        $logPath = defined('MUBLO_STORAGE_PATH')
            ? MUBLO_STORAGE_PATH . '/logs'
            : dirname(__DIR__, 3) . '/storage/logs';

        if (!is_dir($logPath)) {
            @mkdir($logPath, 0755, true);
        }

        $logFile = $logPath . '/block_error.log';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] {$logMessage}" . PHP_EOL;

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    /**
     * 렌더러 인스턴스 가져오기
     *
     * DI 컨테이너를 우선 사용하고, 실패 시 직접 생성 (Plugin 하위 호환)
     */
    private function getRenderer(string $contentType): ?RendererInterface
    {
        // 캐시된 인스턴스 반환
        if (isset($this->renderers[$contentType])) {
            return $this->renderers[$contentType];
        }

        // 렌더러 클래스 조회
        $rendererClass = BlockRegistry::getRendererClass($contentType);

        if (!$rendererClass || !class_exists($rendererClass)) {
            return null;
        }

        // 컨테이너에서 해석 시도 → 실패 시 직접 생성 (Plugin 호환)
        try {
            if ($this->container->canResolve($rendererClass)) {
                $renderer = $this->container->get($rendererClass);
            } else {
                $renderer = new $rendererClass();
            }
        } catch (\Throwable $e) {
            // DI 해석/직접 생성 모두 실패 시 null 반환
            $this->logError("Renderer instantiation failed", [
                'renderer_class' => $rendererClass,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (!$renderer instanceof RendererInterface) {
            return null;
        }

        // AssetManager 주입 (SkinRendererTrait 사용 렌더러)
        if (property_exists($renderer, 'assetManager') && $renderer->assetManager === null) {
            try {
                $renderer->assetManager = $this->container->get(AssetManager::class);
            } catch (\Throwable $e) {
                // AssetManager 미등록 시 무시 (스킨에서 $assets = null)
            }
        }

        $this->renderers[$contentType] = $renderer;

        return $renderer;
    }

    // ========================================
    // 캐시 키 생성
    // ========================================

    /**
     * 위치별 행 목록 캐시 키
     */
    private function getPositionListCacheKey(int $domainId, string $position, ?string $menuCode): string
    {
        $key = self::CACHE_PREFIX . "ids:pos:{$domainId}:{$position}";
        if ($menuCode) {
            $key .= ":{$menuCode}";
        }
        return $key;
    }

    /**
     * 페이지별 행 목록 캐시 키
     */
    private function getPageListCacheKey(int $pageId): string
    {
        return self::CACHE_PREFIX . "ids:page:{$pageId}";
    }

    /**
     * 행별 캐시 키
     */
    private function getRowCacheKey(int $rowId): string
    {
        $variant = $this->cacheVariant !== '' ? ':' . $this->cacheVariant : '';
        return self::CACHE_PREFIX . "row:{$rowId}{$variant}";
    }

    // ========================================
    // 캐시 관리
    // ========================================

    /**
     * 도메인별 전체 블록 캐시 무효화
     *
     * menuCode=null 캐시뿐 아니라 메뉴별 캐시도 함께 삭제
     */
    public function invalidateDomainCache(int $domainId): void
    {
        // 사용 중인 메뉴 코드 목록 조회
        $menuCodes = $this->rowRepository->getDistinctMenuCodes($domainId);

        // 모든 위치 × (글로벌 + 메뉴별) 캐시 무효화
        foreach (BlockPosition::options() as $position => $label) {
            // 글로벌 (menuCode=null)
            $this->invalidatePositionListCache($domainId, $position);

            // 메뉴별
            foreach ($menuCodes as $menuCode) {
                $this->invalidatePositionListCache($domainId, $position, $menuCode);
            }
        }
    }

    /**
     * 행 콘텐츠 변경 시 캐시 무효화 (칸 내용 수정)
     *
     * 행 콘텐츠만 변경된 경우: 해당 행 캐시만 삭제
     * 행 목록 캐시는 유지 (행 추가/삭제/순서 변경이 아니므로)
     *
     * @param int $rowId 변경된 행 ID
     */
    public function invalidateRowContentCache(int $rowId): void
    {
        $this->invalidateRowCache($rowId);
    }

    /**
     * 행 구조 변경 시 캐시 무효화 (행 추가/삭제/순서변경/활성화)
     *
     * 행 목록 + 행 콘텐츠 모두 삭제
     *
     * @param BlockRow $row 변경된 행
     */
    public function invalidateRowRelatedCache(BlockRow $row): void
    {
        // 행 콘텐츠 캐시
        $this->invalidateRowCache($row->getRowId());

        // 위치 행 목록 캐시
        if ($row->getPosition()) {
            $this->invalidatePositionListCache(
                $row->getDomainId(),
                $row->getPosition()->value,
                $row->getPositionMenu()
            );
        }

        // 페이지 행 목록 캐시
        if ($row->getPageId()) {
            $this->invalidatePageListCache($row->getPageId());
        }
    }
}
