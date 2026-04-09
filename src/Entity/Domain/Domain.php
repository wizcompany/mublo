<?php
namespace Mublo\Entity\Domain;

/**
 * Class Domain
 *
 * domain_configs 테이블 엔티티
 */
class Domain
{
    private int $domainId;
    private string $domain;
    private string $domainGroup;              // 계층 구조 (예: 1/3/5)
    private ?int $memberId;                   // 소유자 회원 ID
    private string $status;                   // active, inactive, blocked
    private ?string $contractStartDate;       // 계약 시작일
    private ?string $contractEndDate;         // 계약 종료일
    private string $contractType;             // free, monthly, yearly, permanent
    private int $storageLimit;                // 저장공간 제한 (bytes)
    private int $memberLimit;                 // 회원수 제한
    private array $siteConfig;                // JSON 설정
    private array $companyConfig;             // JSON 설정 (회사 정보)
    private array $seoConfig;                 // JSON 설정
    private array $themeConfig;               // JSON 설정
    private array $extensionConfig;           // JSON 설정 (플러그인/패키지 활성화)
    private array $extraConfig;               // JSON 설정 (확장/임시)
    private ?string $createdAt;
    private ?string $updatedAt;

    public function __construct(
        int $domainId,
        string $domain,
        string $domainGroup = '',
        ?int $memberId = null,
        string $status = 'active',
        ?string $contractStartDate = null,
        ?string $contractEndDate = null,
        string $contractType = 'free',
        int $storageLimit = 1073741824,       // 1GB
        int $memberLimit = 0,
        array $siteConfig = [],
        array $companyConfig = [],
        array $seoConfig = [],
        array $themeConfig = [],
        array $extensionConfig = [],
        array $extraConfig = [],
        ?string $createdAt = null,
        ?string $updatedAt = null
    ) {
        $this->domainId = $domainId;
        $this->domain = $domain;
        $this->domainGroup = $domainGroup;
        $this->memberId = $memberId;
        $this->status = $status;
        $this->contractStartDate = $contractStartDate;
        $this->contractEndDate = $contractEndDate;
        $this->contractType = $contractType;
        $this->storageLimit = $storageLimit;
        $this->memberLimit = $memberLimit;
        $this->siteConfig = $siteConfig;
        $this->companyConfig = $companyConfig;
        $this->seoConfig = $seoConfig;
        $this->themeConfig = $themeConfig;
        $this->extensionConfig = $extensionConfig;
        $this->extraConfig = $extraConfig;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

    /**
     * DB 배열 데이터를 Entity로 변환
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (int)$data['domain_id'],
            $data['domain'],
            $data['domain_group'] ?? '',
            isset($data['member_id']) ? (int)$data['member_id'] : null,
            $data['status'] ?? 'active',
            $data['contract_start_date'] ?? null,
            $data['contract_end_date'] ?? null,
            $data['contract_type'] ?? 'free',
            (int)($data['storage_limit'] ?? 1073741824),
            (int)($data['member_limit'] ?? 0),
            self::parseJsonField($data['site_config'] ?? null),
            self::parseJsonField($data['company_config'] ?? null),
            self::parseJsonField($data['seo_config'] ?? null),
            self::parseJsonField($data['theme_config'] ?? null),
            self::parseJsonField($data['extension_config'] ?? null),
            self::parseJsonField($data['extra_config'] ?? null),
            $data['created_at'] ?? null,
            $data['updated_at'] ?? null
        );
    }

    /**
     * JSON 필드 파싱 헬퍼
     */
    private static function parseJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value) && $value !== '') {
            return json_decode($value, true) ?? [];
        }
        return [];
    }

    /**
     * Entity를 배열로 변환 (캐싱용)
     */
    public function toArray(): array
    {
        return [
            'domain_id' => $this->domainId,
            'domain' => $this->domain,
            'domain_group' => $this->domainGroup,
            'member_id' => $this->memberId,
            'status' => $this->status,
            'contract_start_date' => $this->contractStartDate,
            'contract_end_date' => $this->contractEndDate,
            'contract_type' => $this->contractType,
            'storage_limit' => $this->storageLimit,
            'member_limit' => $this->memberLimit,
            'site_config' => $this->siteConfig,
            'company_config' => $this->companyConfig,
            'seo_config' => $this->seoConfig,
            'theme_config' => $this->themeConfig,
            'extension_config' => $this->extensionConfig,
            'extra_config' => $this->extraConfig,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    // =========================================================================
    // Getters - 기본 정보
    // =========================================================================

    public function getDomainId(): int
    {
        return $this->domainId;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function getDomainGroup(): string
    {
        return $this->domainGroup;
    }

    public function getMemberId(): ?int
    {
        return $this->memberId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    // =========================================================================
    // Getters - 계약 정보
    // =========================================================================

    public function getContractStartDate(): ?string
    {
        return $this->contractStartDate;
    }

    public function getContractEndDate(): ?string
    {
        return $this->contractEndDate;
    }

    public function getContractType(): string
    {
        return $this->contractType;
    }

    public function getStorageLimit(): int
    {
        return $this->storageLimit;
    }

    /**
     * 저장공간 제한을 사람이 읽기 쉬운 형태로 반환
     */
    public function getStorageLimitFormatted(): string
    {
        $bytes = $this->storageLimit;
        if ($bytes >= 1099511627776) {
            return round($bytes / 1099511627776, 2) . ' TB';
        }
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }
        return round($bytes / 1024, 2) . ' KB';
    }

    public function getMemberLimit(): int
    {
        return $this->memberLimit;
    }

    // =========================================================================
    // Getters - JSON 설정
    // =========================================================================

    public function getSiteConfig(): array
    {
        return $this->siteConfig;
    }

    public function getCompanyConfig(): array
    {
        return $this->companyConfig;
    }

    public function getSeoConfig(): array
    {
        return $this->seoConfig;
    }

    public function getThemeConfig(): array
    {
        return $this->themeConfig;
    }

    public function getExtensionConfig(): array
    {
        return $this->extensionConfig;
    }

    public function getExtraConfig(): array
    {
        return $this->extraConfig;
    }

    // =========================================================================
    // Getters - 타임스탬프
    // =========================================================================

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    // =========================================================================
    // 상태 판단 메서드
    // =========================================================================

    /**
     * 활성 상태 여부
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * 비활성 상태 여부
     */
    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    /**
     * 차단 상태 여부
     */
    public function isBlocked(): bool
    {
        return $this->status === 'blocked';
    }

    /**
     * 계약 만료 여부 확인
     */
    public function isContractExpired(): bool
    {
        if (empty($this->contractEndDate)) {
            return false; // 무제한
        }
        return strtotime($this->contractEndDate) < time();
    }

    /**
     * 계약 시작 전 여부
     */
    public function isContractNotStarted(): bool
    {
        if (empty($this->contractStartDate)) {
            return false;
        }
        return strtotime($this->contractStartDate) > time();
    }

    /**
     * 접속 가능 여부 (활성 상태 + 계약 기간 유효)
     */
    public function isAccessible(): bool
    {
        if (!$this->isActive()) {
            return false;
        }
        if ($this->isContractExpired()) {
            return false;
        }
        if ($this->isContractNotStarted()) {
            return false;
        }
        return true;
    }

    /**
     * 무료 계약 여부
     */
    public function isFreeContract(): bool
    {
        return $this->contractType === 'free';
    }

    /**
     * 영구 계약 여부
     */
    public function isPermanentContract(): bool
    {
        return $this->contractType === 'permanent';
    }

    // =========================================================================
    // 설정 값 편의 메서드
    // =========================================================================

    /**
     * 사이트 타이틀 반환
     */
    public function getSiteTitle(): string
    {
        return $this->siteConfig['site_title'] ?? '';
    }

    /**
     * 관리자 이메일 반환
     */
    public function getAdminEmail(): string
    {
        return $this->siteConfig['admin_email'] ?? '';
    }

    /**
     * 아이디를 이메일로 사용하는지 여부
     */
    public function isUseEmailAsUserId(): bool
    {
        return (bool) ($this->siteConfig['use_email_as_userid'] ?? false);
    }

    /**
     * 회원가입 방식 (immediate: 바로 가입, approval: 관리자 승인)
     */
    public function getJoinType(): string
    {
        return $this->siteConfig['join_type'] ?? 'immediate';
    }

    /**
     * 가입 시 적용할 기본 레벨
     */
    public function getDefaultLevelValue(): int
    {
        return (int) ($this->siteConfig['default_level_value'] ?? 1);
    }

    /**
     * 활성화된 플러그인 목록
     */
    public function getEnabledPlugins(): array
    {
        return $this->extensionConfig['plugins'] ?? [];
    }

    /**
     * 활성화된 패키지 목록
     */
    public function getEnabledPackages(): array
    {
        return $this->extensionConfig['packages'] ?? [];
    }

    /**
     * 특정 플러그인 활성화 여부
     */
    public function isPluginEnabled(string $pluginName): bool
    {
        return in_array($pluginName, $this->getEnabledPlugins(), true);
    }

    /**
     * 특정 패키지 활성화 여부
     */
    public function isPackageEnabled(string $packageName): bool
    {
        return in_array($packageName, $this->getEnabledPackages(), true);
    }
}
