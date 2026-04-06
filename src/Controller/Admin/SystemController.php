<?php

namespace Mublo\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Http\Request;
use Mublo\Core\Context\Context;
use Mublo\Service\System\SystemService;
use Mublo\Service\System\DataResetService;
use Mublo\Service\Extension\ExtensionService;
use Mublo\Service\Auth\AuthService;

/**
 * Admin SystemController
 *
 * 시스템 관리 (캐시 초기화, 마이그레이션 점검/실행, 임시파일 정리, 데이터 초기화)
 *
 * GET  /admin/system              → 시스템 관리 페이지
 * POST /admin/system/clearCache   → 캐시 초기화 (AJAX)
 * POST /admin/system/runMigration → 마이그레이션 실행 (AJAX)
 * POST /admin/system/cleanupTemp  → 임시파일 정리 (AJAX)
 * POST /admin/system/resetData    → 항목별 데이터 초기화 (AJAX)
 * POST /admin/system/resetAll     → 전체 데이터 초기화 (AJAX)
 */
class SystemController
{
    private SystemService $systemService;
    private ExtensionService $extensionService;
    private DataResetService $dataResetService;
    private AuthService $authService;

    public function __construct(
        SystemService $systemService,
        ExtensionService $extensionService,
        DataResetService $dataResetService,
        AuthService $authService
    ) {
        $this->systemService = $systemService;
        $this->extensionService = $extensionService;
        $this->dataResetService = $dataResetService;
        $this->authService = $authService;
    }

    /**
     * 시스템 관리 페이지
     *
     * GET /admin/system
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId();
        $cacheInfo = $this->systemService->getCacheInfo();
        $migrationStatuses = $this->systemService->getAllMigrationStatus(
            $this->extensionService->getEnabledPlugins($domainId),
            $this->extensionService->getEnabledPackages($domainId)
        );

        $totalPending = 0;
        $totalExecuted = 0;
        foreach ($migrationStatuses as $status) {
            $totalPending += count($status['pending']);
            $totalExecuted += count($status['executed']);
        }

        $tempFileInfo = $this->systemService->getTempFileInfo();

        // 데이터 초기화 항목 (SUPER 전용)
        $resetItems = [];
        if ($this->authService->isSuper()) {
            $resetItems = $this->dataResetService->getResetItems($domainId);
        }

        return ViewResponse::view('system/index')
            ->withData([
                'pageTitle' => '시스템 관리',
                'title' => '시스템 관리',
                'description' => '캐시 초기화, 데이터베이스 마이그레이션 점검, 임시파일 정리를 수행합니다.',
                'cacheInfo' => $cacheInfo,
                'migrationStatuses' => $migrationStatuses,
                'totalPending' => $totalPending,
                'totalExecuted' => $totalExecuted,
                'tempFileInfo' => $tempFileInfo,
                'resetItems' => $resetItems,
                'isSuper' => $this->authService->isSuper(),
                'activeCode' => '002_005',
            ]);
    }

    /**
     * 캐시 초기화 (AJAX)
     *
     * POST /admin/system/clearCache
     */
    public function clearCache(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId();
        $domainName = $context->getDomain();
        $result = $this->systemService->clearAllCache($domainId, $domainName);

        return $result->isSuccess()
            ? JsonResponse::success(null, $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 미실행 마이그레이션 실행 (AJAX)
     *
     * POST /admin/system/runMigration
     */
    public function runMigration(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId();
        $result = $this->systemService->runPendingMigrations(
            $this->extensionService->getEnabledPlugins($domainId),
            $this->extensionService->getEnabledPackages($domainId)
        );

        if ($result->isSuccess()) {
            return JsonResponse::success($result->getData(), $result->getMessage());
        }

        return JsonResponse::error($result->getMessage(), $result->getData());
    }

    /**
     * 임시파일 정리 (AJAX)
     *
     * POST /admin/system/cleanupTemp
     */
    public function cleanupTemp(Request $request, Context $context): JsonResponse
    {
        $maxAgeHours = (int) ($request->json('maxAgeHours') ?? 24);
        if ($maxAgeHours < 1) {
            $maxAgeHours = 1;
        }

        $result = $this->systemService->cleanupTempFiles($maxAgeHours);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 항목별 데이터 초기화 (AJAX)
     *
     * POST /admin/system/resetData
     */
    public function resetData(Request $request, Context $context): JsonResponse
    {
        if (!$this->authService->isSuper()) {
            return JsonResponse::error('SUPER 관리자만 사용할 수 있습니다.');
        }

        $category = $request->json('category') ?? '';
        $password = $request->json('password') ?? '';

        if (empty($category) || empty($password)) {
            return JsonResponse::error('카테고리와 비밀번호를 입력해주세요.');
        }

        $domainId = $context->getDomainId();
        $memberId = $this->authService->id();

        $result = $this->dataResetService->resetCategory($category, $domainId, $memberId, $password);

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }

    /**
     * 전체 데이터 초기화 (AJAX)
     *
     * POST /admin/system/resetAll
     */
    public function resetAll(Request $request, Context $context): JsonResponse
    {
        if (!$this->authService->isSuper()) {
            return JsonResponse::error('SUPER 관리자만 사용할 수 있습니다.');
        }

        $password = $request->json('password') ?? '';
        $confirmText = $request->json('confirmText') ?? '';

        if (empty($password) || empty($confirmText)) {
            return JsonResponse::error('비밀번호와 확인 문구를 입력해주세요.');
        }

        $domainId = $context->getDomainId();
        $memberId = $this->authService->id();

        $result = $this->dataResetService->resetAll($domainId, $memberId, $password, $confirmText);

        // 전체 초기화 후 캐시도 초기화
        if ($result->isSuccess()) {
            $this->systemService->clearAllCache($domainId, $context->getDomain());
        }

        return $result->isSuccess()
            ? JsonResponse::success($result->getData(), $result->getMessage())
            : JsonResponse::error($result->getMessage());
    }
}
