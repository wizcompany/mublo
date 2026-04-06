<?php

namespace Mublo\Service\System;

use Mublo\Contract\DataResettableInterface;
use Mublo\Core\Result\Result;
use Mublo\Infrastructure\Database\Database;
use Mublo\Repository\Member\MemberRepository;
use Mublo\Service\Extension\ExtensionService;

/**
 * DataResetService
 *
 * 데이터 초기화 오케스트레이션 서비스
 * - 초기화 가능 항목 수집 (Core + 활성 Plugin/Package)
 * - 비밀번호 검증
 * - 트랜잭션 래핑 실행
 */
class DataResetService
{
    private Database $db;
    private MemberRepository $memberRepository;
    private ExtensionService $extensionService;
    private CoreDataResetter $coreDataResetter;

    public function __construct(
        Database $db,
        MemberRepository $memberRepository,
        ExtensionService $extensionService,
        CoreDataResetter $coreDataResetter
    ) {
        $this->db = $db;
        $this->memberRepository = $memberRepository;
        $this->extensionService = $extensionService;
        $this->coreDataResetter = $coreDataResetter;
    }

    /**
     * 초기화 가능 항목 수집
     *
     * Core + 활성 Plugin/Package Provider 중 DataResettableInterface 구현체를 탐색
     *
     * @return array [['source' => 'core', 'name' => 'Core', 'categories' => [...], 'resetter' => DataResettableInterface]]
     */
    public function getResetItems(int $domainId): array
    {
        $items = [];

        // Core 항목
        $items[] = [
            'source' => 'core',
            'name' => 'Core',
            'categories' => $this->coreDataResetter->getResetCategories(),
            'resetter' => $this->coreDataResetter,
        ];

        // Plugin 항목
        $enabledPlugins = $this->extensionService->getEnabledPlugins($domainId);
        foreach ($enabledPlugins as $name) {
            $provider = $this->resolveProvider('plugin', $name);
            if ($provider instanceof DataResettableInterface) {
                $categories = $provider->getResetCategories();
                if (!empty($categories)) {
                    $items[] = [
                        'source' => 'plugin',
                        'name' => $name,
                        'categories' => $categories,
                        'resetter' => $provider,
                    ];
                }
            }
        }

        // Package 항목
        $enabledPackages = $this->extensionService->getEnabledPackages($domainId);
        foreach ($enabledPackages as $name) {
            $provider = $this->resolveProvider('package', $name);
            if ($provider instanceof DataResettableInterface) {
                $categories = $provider->getResetCategories();
                if (!empty($categories)) {
                    $items[] = [
                        'source' => 'package',
                        'name' => $name,
                        'categories' => $categories,
                        'resetter' => $provider,
                    ];
                }
            }
        }

        return $items;
    }

    /**
     * 비밀번호 검증
     */
    public function verifyPassword(int $memberId, string $password): bool
    {
        $member = $this->memberRepository->find($memberId);
        if (!$member) {
            return false;
        }

        return password_verify($password, $member->getPassword());
    }

    /**
     * 항목별 초기화 실행
     */
    public function resetCategory(string $categoryKey, int $domainId, int $memberId, string $password): Result
    {
        // 비밀번호 검증
        if (!$this->verifyPassword($memberId, $password)) {
            return Result::failure('비밀번호가 일치하지 않습니다.');
        }

        // SUPER 회원 재확인
        $member = $this->memberRepository->find($memberId);
        if (!$member || !$member->isSuper()) {
            return Result::failure('SUPER 관리자만 데이터를 초기화할 수 있습니다.');
        }

        // Resetter와 카테고리 찾기
        $resetItems = $this->getResetItems($domainId);
        $targetResetter = null;
        $targetLabel = '';

        foreach ($resetItems as $item) {
            foreach ($item['categories'] as $cat) {
                if ($cat['key'] === $categoryKey) {
                    $targetResetter = $item['resetter'];
                    $targetLabel = $cat['label'];
                    break 2;
                }
            }
        }

        if (!$targetResetter) {
            return Result::failure('초기화 대상을 찾을 수 없습니다.');
        }

        // 트랜잭션 내 실행
        try {
            $this->db->beginTransaction();
            $result = $targetResetter->reset($categoryKey, $domainId, $this->db);
            $this->db->commit();

            $this->writeResetLog('category', $categoryKey, $targetLabel, $domainId, $memberId, $result);

            return Result::success(
                "{$targetLabel} 데이터가 초기화되었습니다.",
                $result
            );
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("[DataReset] Error: category={$categoryKey}, domain={$domainId} — " . $e->getMessage());
            return Result::failure('초기화 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 전체 초기화 실행
     */
    public function resetAll(int $domainId, int $memberId, string $password, string $confirmText): Result
    {
        // 비밀번호 검증
        if (!$this->verifyPassword($memberId, $password)) {
            return Result::failure('비밀번호가 일치하지 않습니다.');
        }

        // 확인 문구 검증
        if ($confirmText !== '전체 초기화') {
            return Result::failure('확인 문구가 일치하지 않습니다.');
        }

        // SUPER 재확인
        $member = $this->memberRepository->find($memberId);
        if (!$member || !$member->isSuper()) {
            return Result::failure('SUPER 관리자만 전체 초기화를 수행할 수 있습니다.');
        }

        $resetItems = $this->getResetItems($domainId);
        $totalResult = ['tables_cleared' => 0, 'files_deleted' => 0, 'categories' => []];

        try {
            $this->db->beginTransaction();

            foreach ($resetItems as $item) {
                foreach ($item['categories'] as $cat) {
                    $result = $item['resetter']->reset($cat['key'], $domainId, $this->db);
                    $totalResult['tables_cleared'] += $result['tables_cleared'];
                    $totalResult['files_deleted'] += $result['files_deleted'];
                    $totalResult['categories'][] = $cat['label'];
                }
            }

            $this->db->commit();

            $this->writeResetLog('all', 'all', '전체 초기화', $domainId, $memberId, $totalResult);

            $categoriesList = implode(', ', $totalResult['categories']);
            return Result::success(
                "전체 초기화가 완료되었습니다. ({$categoriesList})",
                $totalResult
            );
        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log("[DataReset] resetAll Error: domain={$domainId} — " . $e->getMessage());
            return Result::failure('전체 초기화 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }

    /**
     * Provider 동적 해석 (ExtensionService.resolveProvider와 동일 로직)
     */
    private function resolveProvider(string $type, string $name): ?object
    {
        $namespace = $type === 'plugin' ? 'Plugin' : 'Packages';
        $providerClass = "Mublo\\{$namespace}\\{$name}\\{$name}Provider";

        if (!class_exists($providerClass)) {
            return null;
        }

        try {
            return new $providerClass();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * 초기화 로그 기록
     */
    private function writeResetLog(string $type, string $category, string $label, int $domainId, int $memberId, array $result): void
    {
        $logDir = MUBLO_STORAGE_PATH . '/logs/reset';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/reset_' . date('Ymd') . '.log';
        $member = $this->memberRepository->find($memberId);
        $userId = $member ? $member->getUserId() : 'unknown';

        $logEntry = sprintf(
            "[%s] type=%s, category=%s, label=%s, domain=%d, member=%s(#%d), tables=%d, files=%d\n",
            date('Y-m-d H:i:s'),
            $type,
            $category,
            $label,
            $domainId,
            $userId,
            $memberId,
            $result['tables_cleared'] ?? 0,
            $result['files_deleted'] ?? 0
        );

        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
