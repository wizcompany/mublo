<?php
namespace Mublo\Controller\Admin;

use Mublo\Core\Response\ViewResponse;
use Mublo\Core\Response\JsonResponse;
use Mublo\Core\Context\Context;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Service\Extension\ExtensionService;
use Mublo\Service\Auth\AuthService;

/**
 * Admin ExtensionsController
 *
 * 플러그인/패키지 확장 기능 관리 컨트롤러
 */
class ExtensionsController
{
    private ExtensionService $extensionService;
    private DependencyContainer $container;
    private ?AuthService $authService;

    public function __construct(ExtensionService $extensionService, DependencyContainer $container, ?AuthService $authService = null)
    {
        $this->extensionService = $extensionService;
        $this->container = $container;
        $this->authService = $authService;
    }

    /**
     * 확장 기능 관리 페이지
     *
     * GET /admin/extensions
     */
    public function index(array $params, Context $context): ViewResponse
    {
        $domainId = $context->getDomainId() ?? 1;

        // 플러그인/패키지 목록 (활성화 상태 포함)
        $extensions = $this->extensionService->getExtensionsWithManifests($domainId);

        return ViewResponse::view('extensions/index')
            ->withData([
                'pageTitle' => '확장 기능',
                'plugins' => $extensions['plugins'] ?? [],
                'packages' => $extensions['packages'] ?? [],
                'isSuper' => $this->authService?->isSuper() ?? false,
            ]);
    }

    /**
     * 확장 기능 저장 (AJAX)
     *
     * POST /admin/extensions/update
     */
    public function update(array $params, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId() ?? 1;
        $request = $context->getRequest();
        $formData = $request->input('formData') ?? [];

        // formData가 비어있어도 됨 (모든 확장 기능 비활성화 가능)
        try {
            $extensionConfig = [
                'plugins' => $formData['plugins'] ?? [],
                'packages' => $formData['packages'] ?? [],
            ];

            // container + context 전달 → install/uninstall 라이프사이클 자동 실행
            $result = $this->extensionService->saveExtensionConfig(
                $domainId,
                $extensionConfig,
                $this->container,
                $context
            );

            if ($result->isSuccess()) {
                return JsonResponse::success($result->getMessage());
            }

            return JsonResponse::error($result->getMessage());
        } catch (\Exception $e) {
            return JsonResponse::error('확장 기능 저장 중 오류가 발생했습니다: ' . $e->getMessage());
        }
    }
}
