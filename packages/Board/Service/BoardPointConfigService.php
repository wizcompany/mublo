<?php
namespace Mublo\Packages\Board\Service;

use Mublo\Core\Result\Result;
use Mublo\Packages\Board\Repository\BoardPointConfigRepository;

/**
 * BoardPointConfigService
 *
 * 게시판 포인트 설정 관리
 *
 * 설정 해석 체인: 게시판별 → 그룹별 → 도메인 기본값
 */
class BoardPointConfigService
{
    private const DEFAULTS = [
        'article_write'     => ['enabled' => true,  'point' => 100, 'revoke' => true],
        'comment_write'     => ['enabled' => true,  'point' => 10,  'revoke' => true],
        'reaction_received' => ['enabled' => true,  'point' => 5,   'revoke' => true],
        'article_read'      => ['enabled' => false, 'point' => 0],
        'file_download'     => ['enabled' => false, 'point' => 0],
    ];

    /** 적립 액션 (revoke 필드 있음) */
    private const EARN_ACTIONS = ['article_write', 'comment_write', 'reaction_received'];

    /** 소비 액션 (revoke 필드 없음) */
    private const CONSUME_ACTIONS = ['article_read', 'file_download'];

    private array $domainCache = [];
    private array $scopeCache = [];

    public function __construct(private BoardPointConfigRepository $repo) {}

    // =========================================================================
    // 도메인 기본 설정
    // =========================================================================

    /**
     * 도메인 포인트 설정 반환 (기본값 병합)
     */
    public function getConfig(int $domainId): array
    {
        if (isset($this->domainCache[$domainId])) {
            return $this->domainCache[$domainId];
        }

        $saved = $this->repo->findByDomain($domainId);
        $config = array_replace_recursive(self::DEFAULTS, $saved ?? []);
        $this->domainCache[$domainId] = $config;

        return $config;
    }

    /**
     * 도메인 기본 설정에서 특정 액션 설정 반환
     */
    public function getActionConfig(int $domainId, string $action): array
    {
        $config = $this->getConfig($domainId);
        return $config[$action] ?? ['enabled' => false, 'point' => 0];
    }

    /**
     * 도메인 기본 설정 저장
     */
    public function save(int $domainId, array $formData): Result
    {
        $config = [];

        foreach (self::EARN_ACTIONS as $action) {
            $item = $formData[$action] ?? [];
            $config[$action] = [
                'enabled' => !empty($item['enabled']),
                'point'   => max(0, (int) ($item['point'] ?? self::DEFAULTS[$action]['point'])),
                'revoke'  => !empty($item['revoke']),
            ];
        }

        foreach (self::CONSUME_ACTIONS as $action) {
            $item = $formData[$action] ?? [];
            $config[$action] = [
                'enabled' => !empty($item['enabled']),
                'point'   => max(0, (int) ($item['point'] ?? 0)),
            ];
        }

        $this->repo->save($domainId, $config);
        unset($this->domainCache[$domainId]);

        return Result::success('게시판 포인트 설정이 저장되었습니다.');
    }

    // =========================================================================
    // 스코프별 설정 (그룹/게시판 오버라이드)
    // =========================================================================

    /**
     * 게시판 액션에 대한 최종 설정 반환 (스코프 체인)
     *
     * 조회 순서: 게시판별 → 그룹별 → 도메인 기본값
     */
    public function getBoardActionConfig(int $domainId, int $boardId, string $action, ?int $groupId = null): array
    {
        // 1. 게시판별 설정
        $boardConfig = $this->getScopeConfig($domainId, 'board', $boardId);
        if ($boardConfig !== null && isset($boardConfig[$action])) {
            return $boardConfig[$action];
        }

        // 2. 그룹별 설정
        if ($groupId !== null) {
            $groupConfig = $this->getScopeConfig($domainId, 'group', $groupId);
            if ($groupConfig !== null && isset($groupConfig[$action])) {
                return $groupConfig[$action];
            }
        }

        // 3. 도메인 기본값
        return $this->getActionConfig($domainId, $action);
    }

    /**
     * 스코프 설정 반환 (캐싱)
     */
    public function getScopeConfig(int $domainId, string $scopeType, int $scopeId): ?array
    {
        $cacheKey = "{$domainId}_{$scopeType}_{$scopeId}";

        if (array_key_exists($cacheKey, $this->scopeCache)) {
            return $this->scopeCache[$cacheKey];
        }

        $config = $this->repo->findScopeConfig($domainId, $scopeType, $scopeId);
        $this->scopeCache[$cacheKey] = $config;

        return $config;
    }

    /**
     * 스코프 설정 저장
     */
    public function saveScopeConfig(int $domainId, string $scopeType, int $scopeId, array $formData): Result
    {
        $config = [];

        foreach (self::EARN_ACTIONS as $action) {
            $item = $formData[$action] ?? [];
            $config[$action] = [
                'enabled' => !empty($item['enabled']),
                'point'   => max(0, (int) ($item['point'] ?? 0)),
                'revoke'  => !empty($item['revoke']),
            ];
        }

        foreach (self::CONSUME_ACTIONS as $action) {
            $item = $formData[$action] ?? [];
            $config[$action] = [
                'enabled' => !empty($item['enabled']),
                'point'   => max(0, (int) ($item['point'] ?? 0)),
            ];
        }

        // 게시판별 반응 타입 개별 포인트
        if ($scopeType === 'board' && !empty($formData['reaction_points'])) {
            $reactionPoints = [];
            foreach ($formData['reaction_points'] as $type => $point) {
                $p = max(0, (int) $point);
                if ($p > 0) {
                    $reactionPoints[$type] = $p;
                }
            }
            if (!empty($reactionPoints)) {
                $config['reaction_points'] = $reactionPoints;
            }
        }

        $this->repo->saveScopeConfig($domainId, $scopeType, $scopeId, $config);
        $this->clearScopeCache($domainId, $scopeType, $scopeId);

        $label = $scopeType === 'group' ? '그룹' : '게시판';
        return Result::success("{$label}별 포인트 설정이 저장되었습니다.");
    }

    /**
     * 스코프 설정 삭제 (기본값 복원)
     */
    public function deleteScopeConfig(int $domainId, string $scopeType, int $scopeId): Result
    {
        $this->repo->deleteScopeConfig($domainId, $scopeType, $scopeId);
        $this->clearScopeCache($domainId, $scopeType, $scopeId);

        $label = $scopeType === 'group' ? '그룹' : '게시판';
        return Result::success("{$label}별 포인트 설정이 초기화되었습니다.");
    }

    /**
     * 특정 도메인의 스코프별 설정 전체 목록
     *
     * @return array [scope_id => config_data, ...]
     */
    public function getAllScopeConfigs(int $domainId, string $scopeType): array
    {
        return $this->repo->findAllScopeConfigs($domainId, $scopeType);
    }

    /**
     * 반응 타입별 포인트 조회
     */
    public function getReactionTypePoint(int $domainId, int $boardId, string $reactionType): ?int
    {
        $boardConfig = $this->getScopeConfig($domainId, 'board', $boardId);
        if ($boardConfig !== null && isset($boardConfig['reaction_points'][$reactionType])) {
            return max(0, (int) $boardConfig['reaction_points'][$reactionType]);
        }

        return null;
    }

    // =========================================================================
    // 라벨
    // =========================================================================

    public static function getActionLabels(): array
    {
        return [
            'article_write'     => '게시글 작성',
            'comment_write'     => '댓글 작성',
            'reaction_received' => '반응 받기',
            'article_read'      => '게시글 읽기',
            'file_download'     => '파일 다운로드',
        ];
    }

    public static function getEarnActions(): array
    {
        return self::EARN_ACTIONS;
    }

    public static function getConsumeActions(): array
    {
        return self::CONSUME_ACTIONS;
    }

    // =========================================================================
    // 캐시
    // =========================================================================

    private function clearScopeCache(int $domainId, string $scopeType, int $scopeId): void
    {
        unset($this->scopeCache["{$domainId}_{$scopeType}_{$scopeId}"]);
    }
}
