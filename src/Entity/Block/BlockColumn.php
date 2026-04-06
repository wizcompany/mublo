<?php
namespace Mublo\Entity\Block;

use DateTimeImmutable;
use Mublo\Enum\Block\BlockContentType;
use Mublo\Enum\Block\BlockContentKind;

/**
 * Class BlockColumn
 *
 * 블록 칸(Column) 엔티티
 *
 * 책임:
 * - block_columns 테이블의 데이터를 객체로 표현
 * - 블록의 개별 칸 설정 및 콘텐츠 관리
 *
 * 금지:
 * - DB 직접 접근
 * - 비즈니스 로직 (Service 담당)
 */
class BlockColumn
{
    // ========================================
    // 기본 필드
    // ========================================
    protected int $columnId;
    protected int $rowId;
    protected int $domainId;

    // ========================================
    // 칸 정보
    // ========================================
    protected int $columnIndex;
    protected ?string $width;

    // ========================================
    // 여백
    // ========================================
    protected ?string $pcPadding;
    protected ?string $mobilePadding;

    // ========================================
    // 배경 설정 (JSON)
    // ========================================
    protected ?array $backgroundConfig;

    // ========================================
    // 테두리 설정 (JSON)
    // ========================================
    protected ?array $borderConfig;

    // ========================================
    // 제목/문구 설정 (JSON)
    // ========================================
    protected ?array $titleConfig;

    // ========================================
    // 콘텐츠
    // ========================================
    protected ?BlockContentType $contentType;
    protected ?string $contentTypeRaw;
    protected BlockContentKind $contentKind;
    protected ?string $contentSkin;
    protected ?array $contentConfig;
    protected ?array $contentItems;

    // ========================================
    // 관리
    // ========================================
    protected int $sortOrder;
    protected bool $isActive;
    protected DateTimeImmutable $createdAt;
    protected DateTimeImmutable $updatedAt;

    /**
     * DB 로우 데이터로부터 BlockColumn 객체 생성
     */
    public static function fromArray(array $data): self
    {
        $column = new self();

        // 기본 필드
        $column->columnId = (int) ($data['column_id'] ?? 0);
        $column->rowId = (int) ($data['row_id'] ?? 0);
        $column->domainId = (int) ($data['domain_id'] ?? 0);

        // 칸 정보
        $column->columnIndex = (int) ($data['column_index'] ?? 0);
        $column->width = $data['width'] ?? null;

        // 여백
        $column->pcPadding = $data['pc_padding'] ?? null;
        $column->mobilePadding = $data['mobile_padding'] ?? null;

        // JSON 필드들
        $column->backgroundConfig = self::parseJsonArray($data['background_config'] ?? null);
        $column->borderConfig = self::parseJsonArray($data['border_config'] ?? null);
        $column->titleConfig = self::parseJsonArray($data['title_config'] ?? null);

        // 콘텐츠
        $column->contentTypeRaw = $data['content_type'] ?? null;
        $column->contentType = isset($data['content_type']) ? BlockContentType::tryFrom($data['content_type']) : null;
        $column->contentKind = BlockContentKind::tryFrom($data['content_kind'] ?? 'CORE') ?? BlockContentKind::CORE;
        $column->contentSkin = $data['content_skin'] ?? null;
        $column->contentConfig = self::parseJsonArray($data['content_config'] ?? null);
        $column->contentItems = self::parseJsonArray($data['content_items'] ?? null);

        // 관리
        $column->sortOrder = (int) ($data['sort_order'] ?? 0);
        $column->isActive = (bool) ($data['is_active'] ?? true);

        // 날짜
        $column->createdAt = self::parseDateTime($data['created_at'] ?? null);
        $column->updatedAt = self::parseDateTime($data['updated_at'] ?? null);

        return $column;
    }

    /**
     * JSON 문자열/배열을 배열로 파싱
     */
    private static function parseJsonArray($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /**
     * 날짜 문자열을 DateTimeImmutable로 변환
     */
    private static function parseDateTime(?string $datetime): DateTimeImmutable
    {
        if (empty($datetime)) {
            return new DateTimeImmutable();
        }

        try {
            return new DateTimeImmutable($datetime);
        } catch (\Exception $e) {
            return new DateTimeImmutable();
        }
    }

    /**
     * 배열로 변환
     */
    public function toArray(): array
    {
        return [
            'column_id' => $this->columnId,
            'row_id' => $this->rowId,
            'domain_id' => $this->domainId,
            'column_index' => $this->columnIndex,
            'width' => $this->width,
            'pc_padding' => $this->pcPadding,
            'mobile_padding' => $this->mobilePadding,
            'background_config' => $this->backgroundConfig,
            'border_config' => $this->borderConfig,
            'title_config' => $this->titleConfig,
            'content_type' => $this->contentTypeRaw,
            'content_kind' => $this->contentKind->value,
            'content_skin' => $this->contentSkin,
            'content_config' => $this->contentConfig,
            'content_items' => $this->contentItems,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    // ========================================
    // Getters - 기본 필드
    // ========================================

    public function getColumnId(): int
    {
        return $this->columnId;
    }

    public function getRowId(): int
    {
        return $this->rowId;
    }

    public function getDomainId(): int
    {
        return $this->domainId;
    }

    // ========================================
    // Getters - 칸 정보
    // ========================================

    public function getColumnIndex(): int
    {
        return $this->columnIndex;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    /**
     * 칸 번호 (1부터 시작)
     */
    public function getColumnNumber(): int
    {
        return $this->columnIndex + 1;
    }

    // ========================================
    // Getters - 여백
    // ========================================

    public function getPcPadding(): ?string
    {
        return $this->pcPadding;
    }

    public function getMobilePadding(): ?string
    {
        return $this->mobilePadding;
    }

    // ========================================
    // Getters - 배경 설정
    // ========================================

    public function getBackgroundConfig(): ?array
    {
        return $this->backgroundConfig;
    }

    public function getBackgroundColor(): ?string
    {
        return $this->backgroundConfig['color'] ?? null;
    }

    public function getBackgroundImage(): ?string
    {
        return $this->backgroundConfig['image'] ?? null;
    }

    /**
     * 배경 CSS 스타일 생성
     */
    public function getBackgroundStyle(): string
    {
        return BlockRow::buildBackgroundCss($this->backgroundConfig);
    }

    // ========================================
    // Getters - 테두리 설정
    // ========================================

    public function getBorderConfig(): ?array
    {
        return $this->borderConfig;
    }

    /**
     * 테두리 CSS 스타일 생성
     */
    public function getBorderStyle(): string
    {
        if (!$this->borderConfig) {
            return '';
        }

        $styles = [];

        $width = $this->borderConfig['width'] ?? null;
        $color = $this->borderConfig['color'] ?? null;
        $borderStyle = $this->borderConfig['style'] ?? 'solid';

        if ($width && $color) {
            $styles[] = "border: {$width} {$borderStyle} {$color}";
        }

        if (!empty($this->borderConfig['radius'])) {
            $styles[] = "border-radius: {$this->borderConfig['radius']}";
        }

        return implode('; ', $styles);
    }

    // ========================================
    // Getters - 제목 설정
    // ========================================

    public function getTitleConfig(): ?array
    {
        return $this->titleConfig;
    }

    /**
     * 제목 표시 여부
     */
    public function showTitle(): bool
    {
        return ($this->titleConfig['show'] ?? false) === true;
    }

    /**
     * 제목 텍스트
     */
    public function getTitleText(): ?string
    {
        return $this->titleConfig['text'] ?? null;
    }

    /**
     * 제목 색상
     */
    public function getTitleColor(): ?string
    {
        return $this->titleConfig['color'] ?? null;
    }

    /**
     * 제목 PC 폰트 크기
     */
    public function getTitlePcSize(): ?string
    {
        return $this->titleConfig['size_pc'] ?? null;
    }

    /**
     * 제목 Mobile 폰트 크기
     */
    public function getTitleMobileSize(): ?string
    {
        return $this->titleConfig['size_mo'] ?? null;
    }

    /**
     * 제목 위치 (left, center, right)
     */
    public function getTitlePosition(): string
    {
        return $this->titleConfig['position'] ?? 'left';
    }

    /**
     * 제목 PC 이미지
     */
    public function getTitlePcImage(): ?string
    {
        return $this->titleConfig['pc_image'] ?? null;
    }

    /**
     * 제목 Mobile 이미지
     */
    public function getTitleMobileImage(): ?string
    {
        return $this->titleConfig['mobile_image'] ?? null;
    }

    /**
     * 더보기 링크 사용 여부
     */
    public function hasMoreLink(): bool
    {
        return ($this->titleConfig['more_link'] ?? false) === true;
    }

    /**
     * 더보기 URL
     */
    public function getMoreUrl(): ?string
    {
        return $this->titleConfig['more_url'] ?? null;
    }

    /**
     * 문구 텍스트
     */
    public function getCopytext(): ?string
    {
        return $this->titleConfig['copytext'] ?? null;
    }

    /**
     * 문구 색상
     */
    public function getCopytextColor(): ?string
    {
        return $this->titleConfig['copytext_color'] ?? null;
    }

    // ========================================
    // Getters - 콘텐츠
    // ========================================

    public function getContentType(): ?BlockContentType
    {
        return $this->contentType;
    }

    /**
     * 콘텐츠 타입 문자열 반환 (Core Enum + Plugin/Package 타입 모두 포함)
     */
    public function getContentTypeString(): ?string
    {
        return $this->contentTypeRaw;
    }

    public function getContentKind(): BlockContentKind
    {
        return $this->contentKind;
    }

    /**
     * Core 콘텐츠 타입인지 확인
     */
    public function isCoreContent(): bool
    {
        return $this->contentKind === BlockContentKind::CORE;
    }

    /**
     * Plugin 콘텐츠 타입인지 확인
     */
    public function isPluginContent(): bool
    {
        return $this->contentKind === BlockContentKind::PLUGIN;
    }

    /**
     * Package 콘텐츠 타입인지 확인
     */
    public function isPackageContent(): bool
    {
        return $this->contentKind === BlockContentKind::PACKAGE;
    }

    /**
     * PC 출력 갯수
     */
    public function getPcCount(): int
    {
        return (int) ($this->contentConfig['pc_count'] ?? 5);
    }

    /**
     * MO 출력 갯수
     */
    public function getMoCount(): int
    {
        return (int) ($this->contentConfig['mo_count'] ?? 4);
    }

    /**
     * 출력 갯수 (PC 기준, 하위 호환용)
     */
    public function getContentCount(): int
    {
        return $this->getPcCount();
    }

    public function getContentSkin(): ?string
    {
        return $this->contentSkin;
    }

    /**
     * PC 출력 스타일 (list, slide, none)
     */
    public function getPcStyle(): string
    {
        return $this->contentConfig['pc_style'] ?: 'list';
    }

    /**
     * MO 출력 스타일 (list, slide, none)
     */
    public function getMoStyle(): string
    {
        return $this->contentConfig['mo_style'] ?: 'list';
    }

    /**
     * PC 1줄 출력 갯수
     */
    public function getPcCols(): string
    {
        return $this->contentConfig['pc_cols'] ?? '4';
    }

    /**
     * MO 1줄 출력 갯수
     */
    public function getMoCols(): string
    {
        return $this->contentConfig['mo_cols'] ?? '2';
    }

    /**
     * AOS 효과
     */
    public function getAos(): ?string
    {
        return $this->contentConfig['aos'] ?? null;
    }

    /**
     * AOS 애니메이션 시간 (ms)
     */
    public function getAosDuration(): int
    {
        return (int) ($this->contentConfig['aos_duration'] ?? 600);
    }

    /**
     * PC 자동재생 딜레이 (ms). 0 = 비활성
     */
    public function getPcAutoplay(): int
    {
        return (int) ($this->contentConfig['pc_autoplay'] ?? 0);
    }

    /**
     * MO 자동재생 딜레이 (ms). 0 = 비활성
     */
    public function getMoAutoplay(): int
    {
        return (int) ($this->contentConfig['mo_autoplay'] ?? 0);
    }

    /**
     * PC 무한 반복 여부
     */
    public function getPcLoop(): bool
    {
        return (bool) ($this->contentConfig['pc_loop'] ?? false);
    }

    /**
     * MO 무한 반복 여부
     */
    public function getMoLoop(): bool
    {
        return (bool) ($this->contentConfig['mo_loop'] ?? false);
    }

    /**
     * PC 슬라이드 이미지 크롭(cover) 모드 여부
     * true이면 가장 작은 이미지 높이에 맞춰 나머지를 object-fit: cover로 크롭
     */
    public function getPcSlideCover(): bool
    {
        return (bool) ($this->contentConfig['pc_slide_cover'] ?? false);
    }

    /**
     * MO 슬라이드 이미지 크롭(cover) 모드 여부
     */
    public function getMoSlideCover(): bool
    {
        return (bool) ($this->contentConfig['mo_slide_cover'] ?? false);
    }

    /**
     * MubloItemLayout용 data-* 속성 문자열 생성
     *
     * 스킨에서 <div class="mublo-item-layout" <?= $column->getLayoutDataAttributes() ?>> 로 사용
     */
    public function getLayoutDataAttributes(): string
    {
        $attrs = [
            'data-pc-style="' . htmlspecialchars($this->getPcStyle()) . '"',
            'data-mo-style="' . htmlspecialchars($this->getMoStyle()) . '"',
            'data-pc-cols="' . htmlspecialchars($this->getPcCols()) . '"',
            'data-mo-cols="' . htmlspecialchars($this->getMoCols()) . '"',
        ];

        if ($this->getPcAutoplay() > 0) {
            $attrs[] = 'data-pc-autoplay="' . $this->getPcAutoplay() . '"';
        }
        if ($this->getMoAutoplay() > 0) {
            $attrs[] = 'data-mo-autoplay="' . $this->getMoAutoplay() . '"';
        }
        if ($this->getPcLoop()) {
            $attrs[] = 'data-pc-loop="true"';
        }
        if ($this->getMoLoop()) {
            $attrs[] = 'data-mo-loop="true"';
        }

        if ($this->getPcSlideCover()) {
            $attrs[] = 'data-pc-slide-cover="true"';
        }
        if ($this->getMoSlideCover()) {
            $attrs[] = 'data-mo-slide-cover="true"';
        }

        return implode(' ', $attrs);
    }

    public function getContentConfig(): ?array
    {
        return $this->contentConfig;
    }

    public function getContentItems(): ?array
    {
        return $this->contentItems;
    }

    /**
     * 콘텐츠 설정 여부 (타입이 지정되어 있는지)
     *
     * Core Enum 타입 + Plugin/Package 타입 모두 감지
     */
    public function hasContent(): bool
    {
        return !empty($this->contentTypeRaw);
    }

    /**
     * 콘텐츠 설정의 특정 값 반환
     */
    public function getContentConfigValue(string $key, $default = null)
    {
        return $this->contentConfig[$key] ?? $default;
    }

    // ========================================
    // Getters - 관리
    // ========================================

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    // ========================================
    // CSS 스타일 생성 (통합)
    // ========================================

    /**
     * 칸의 전체 CSS 스타일 생성
     */
    public function getFullStyle(): string
    {
        $styles = [];

        // 너비
        if ($this->width) {
            $styles[] = "width: {$this->width}";
        }

        // 배경
        $bgStyle = $this->getBackgroundStyle();
        if ($bgStyle) {
            $styles[] = $bgStyle;
        }

        // 테두리
        $borderStyle = $this->getBorderStyle();
        if ($borderStyle) {
            $styles[] = $borderStyle;
        }

        return implode('; ', $styles);
    }
}
