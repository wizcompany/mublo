<?php
namespace Mublo\Core\Extension;

use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;

/**
 * ExtensionManager
 *
 * 플러그인/패키지 확장 로딩 관리자
 *
 * 책임:
 * - 활성화된 플러그인/패키지 로딩
 * - Provider 인스턴스화 및 실행
 * - 각 Provider가 자신의 이벤트 구독자를 boot()에서 등록
 *
 * 사용 시점:
 * - Application::run() 에서 Context 생성 및 도메인 검증 후 호출
 */
class ExtensionManager
{
    private DependencyContainer $container;
    private string $pluginPath;
    private string $packagePath;

    /**
     * 로딩된 Provider 인스턴스
     * @var ExtensionProviderInterface[]
     */
    private array $loadedProviders = [];

    /**
     * 로딩된 확장 정보
     * @var array
     */
    private array $loadedExtensions = [
        'plugins' => [],
        'packages' => [],
    ];

    public function __construct(DependencyContainer $container)
    {
        $this->container = $container;

        // 경로 설정 (plugins/, packages/)
        $this->pluginPath = MUBLO_PLUGIN_PATH;
        $this->packagePath = MUBLO_PACKAGE_PATH;
    }

    /**
     * 활성화된 확장 로딩
     *
     * @param Context $context 현재 요청 Context
     * @param array $enabledPlugins 활성화된 플러그인 목록
     * @param array $enabledPackages 활성화된 패키지 목록
     */
    public function loadExtensions(
        Context $context,
        array $enabledPlugins,
        array $enabledPackages
    ): void {
        // 1. 플러그인 로딩
        foreach ($enabledPlugins as $pluginName) {
            $this->loadPlugin($pluginName, $context);
        }

        // 2. 패키지 로딩
        foreach ($enabledPackages as $packageName) {
            $this->loadPackage($packageName, $context);
        }

        // 3. 모든 Provider boot() 호출
        // 각 Provider가 boot()에서 모든 이벤트 구독자를 등록
        $this->bootProviders($context);
    }

    /**
     * 플러그인 로딩
     *
     * @param string $pluginName 플러그인 이름
     * @param Context $context
     */
    private function loadPlugin(string $pluginName, Context $context): void
    {
        $providerClass = "Mublo\\Plugin\\{$pluginName}\\{$pluginName}Provider";

        if (!class_exists($providerClass)) {
            // Provider가 없으면 스킵 (간단한 플러그인)
            $this->loadedExtensions['plugins'][] = [
                'name' => $pluginName,
                'hasProvider' => false,
            ];
            return;
        }

        try {
            /** @var ExtensionProviderInterface $provider */
            $provider = new $providerClass();

            // register() 호출
            $provider->register($this->container);

            $this->loadedProviders[] = $provider;
            $this->loadedExtensions['plugins'][] = [
                'name' => $pluginName,
                'hasProvider' => true,
                'providerClass' => $providerClass,
            ];
        } catch (\Throwable $e) {
            // 오류 로깅 (프로덕션에서는 조용히 스킵)
            error_log("Plugin load error [{$pluginName}]: " . $e->getMessage());
        }
    }

    /**
     * 패키지 로딩
     *
     * @param string $packageName 패키지 이름
     * @param Context $context
     */
    private function loadPackage(string $packageName, Context $context): void
    {
        $providerClass = "Mublo\\Packages\\{$packageName}\\{$packageName}Provider";

        if (!class_exists($providerClass)) {
            $this->loadedExtensions['packages'][] = [
                'name' => $packageName,
                'hasProvider' => false,
            ];
            return;
        }

        try {
            /** @var ExtensionProviderInterface $provider */
            $provider = new $providerClass();

            // register() 호출
            $provider->register($this->container);

            $this->loadedProviders[] = $provider;
            $this->loadedExtensions['packages'][] = [
                'name' => $packageName,
                'hasProvider' => true,
                'providerClass' => $providerClass,
            ];
        } catch (\Throwable $e) {
            error_log("Package load error [{$packageName}]: " . $e->getMessage());
        }
    }

    /**
     * 모든 로딩된 Provider의 boot() 호출
     *
     * @param Context $context
     */
    private function bootProviders(Context $context): void
    {
        foreach ($this->loadedProviders as $provider) {
            try {
                $provider->boot($this->container, $context);
            } catch (\Throwable $e) {
                $class = get_class($provider);
                error_log("Provider boot error [{$class}]: " . $e->getMessage());
            }
        }
    }

    /**
     * 로딩된 확장 정보 반환
     *
     * @return array
     */
    public function getLoadedExtensions(): array
    {
        return $this->loadedExtensions;
    }

    /**
     * 특정 플러그인이 로딩되었는지 확인
     *
     * @param string $pluginName
     * @return bool
     */
    public function isPluginLoaded(string $pluginName): bool
    {
        foreach ($this->loadedExtensions['plugins'] as $plugin) {
            if ($plugin['name'] === $pluginName) {
                return true;
            }
        }
        return false;
    }

    /**
     * 특정 패키지가 로딩되었는지 확인
     *
     * @param string $packageName
     * @return bool
     */
    public function isPackageLoaded(string $packageName): bool
    {
        foreach ($this->loadedExtensions['packages'] as $package) {
            if ($package['name'] === $packageName) {
                return true;
            }
        }
        return false;
    }
}
