<?php
namespace Mublo\Service\Extension;

use Mublo\Core\Result\Result;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\InstallableExtensionInterface;
use Mublo\Repository\Domain\DomainRepository;
use Mublo\Infrastructure\Cache\DomainCache;
use Mublo\Infrastructure\Database\Database;
use Mublo\Core\App\Router;

/**
 * ExtensionService
 *
 * 플러그인/패키지 확장 관리 서비스
 *
 * 책임:
 * - manifest.json 스캔 및 파싱
 * - 플러그인/패키지 메타데이터 제공
 * - 도메인별 활성화 상태 관리
 */
class ExtensionService
{
    private DomainRepository $domainRepository;
    private DomainCache $domainCache;
    private Database $db;
    private string $pluginPath;
    private string $packagePath;

    public function __construct(DomainRepository $domainRepository, DomainCache $domainCache, Database $db)
    {
        $this->domainRepository = $domainRepository;
        $this->domainCache = $domainCache;
        $this->db = $db;

        // 경로 설정 (plugins/, packages/)
        $this->pluginPath = MUBLO_PLUGIN_PATH;
        $this->packagePath = MUBLO_PACKAGE_PATH;
    }

    /**
     * 모든 플러그인 manifest 조회
     *
     * @return array<string, array> ['PluginName' => manifest data, ...]
     */
    public function getPluginManifests(): array
    {
        return $this->scanManifests($this->pluginPath, 'plugin');
    }

    /**
     * 모든 패키지 manifest 조회
     *
     * @return array<string, array> ['PackageName' => manifest data, ...]
     */
    public function getPackageManifests(): array
    {
        return $this->scanManifests($this->packagePath, 'package');
    }

    /**
     * 모든 확장(플러그인+패키지) manifest 조회
     *
     * @return array ['plugins' => [...], 'packages' => [...]]
     */
    public function getAllManifests(): array
    {
        return [
            'plugins' => $this->getPluginManifests(),
            'packages' => $this->getPackageManifests(),
        ];
    }

    /**
     * 도메인의 활성화된 플러그인 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @return array 활성화된 플러그인 이름 배열
     */
    public function getEnabledPlugins(int $domainId): array
    {
        $config = $this->getExtensionConfig($domainId);
        $plugins = $config['plugins'] ?? [];

        // manifest에 "default": true인 플러그인은 항상 활성화
        foreach ($this->getPluginManifests() as $name => $manifest) {
            if (!empty($manifest['default']) && !in_array($name, $plugins)) {
                $plugins[] = $name;
            }
        }

        // "super_only" 플러그인: 루트 도메인에서 활성이면 하위에서도 강제 활성
        $this->applySuperOnlyPlugins($domainId, $plugins);

        return $plugins;
    }

    /**
     * super_only 플러그인 강제 활성화
     *
     * 루트 도메인(domain_group의 첫 세그먼트)에서 해당 플러그인이 활성이면
     * 하위 도메인에서도 자동 활성화한다.
     */
    private function applySuperOnlyPlugins(int $domainId, array &$plugins): void
    {
        $superOnlyPlugins = [];
        foreach ($this->getPluginManifests() as $name => $manifest) {
            if (!empty($manifest['super_only']) && !in_array($name, $plugins)) {
                $superOnlyPlugins[] = $name;
            }
        }

        if (empty($superOnlyPlugins)) {
            return;
        }

        // 루트 도메인 ID 결정 (domain_group의 첫 세그먼트)
        $domain = $this->domainRepository->find($domainId);
        if (!$domain) {
            return;
        }

        $group = $domain->getDomainGroup() ?? '';
        $rootId = (int) explode('/', $group)[0];

        if ($rootId <= 0 || $rootId === $domainId) {
            // 자기 자신이 루트면 자기 config에 있는지만 확인 (이미 $plugins에 포함)
            return;
        }

        // 루트 도메인의 활성 플러그인 조회
        $rootConfig = $this->getExtensionConfig($rootId);
        $rootPlugins = $rootConfig['plugins'] ?? [];

        foreach ($superOnlyPlugins as $name) {
            if (in_array($name, $rootPlugins)) {
                $plugins[] = $name;
            }
        }
    }

    /**
     * 도메인의 활성화된 패키지 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @return array 활성화된 패키지 이름 배열
     */
    public function getEnabledPackages(int $domainId): array
    {
        $config = $this->getExtensionConfig($domainId);
        $packages = $config['packages'] ?? [];

        // manifest에 "default": true가 선언된 패키지는 항상 활성화
        foreach ($this->getPackageManifests() as $name => $manifest) {
            if (!empty($manifest['default']) && !in_array($name, $packages)) {
                $packages[] = $name;
            }
        }

        return $packages;
    }

    /**
     * 도메인의 extension_config 조회
     *
     * @param int $domainId 도메인 ID
     * @return array
     */
    public function getExtensionConfig(int $domainId): array
    {
        $domain = $this->domainRepository->find($domainId);
        if (!$domain) {
            return $this->getDefaultExtensionConfig();
        }
        return array_merge($this->getDefaultExtensionConfig(), $domain->getExtensionConfig());
    }

    /**
     * 기본 extension_config
     *
     * @return array
     */
    public function getDefaultExtensionConfig(): array
    {
        return [
            'plugins' => [],
            'packages' => [],
            'installed' => [
                'plugins' => [],
                'packages' => [],
            ],
        ];
    }

    /**
     * 도메인의 확장 설정 저장
     *
     * container + context 전달 시 install/uninstall 라이프사이클 자동 실행
     *
     * @param int $domainId 도메인 ID
     * @param array $config 설정 배열 ['plugins' => [...], 'packages' => [...]]
     * @param DependencyContainer|null $container 라이프사이클 실행용 (null이면 생략)
     * @param Context|null $context 라이프사이클 실행용 (null이면 생략)
     * @return Result
     */
    public function saveExtensionConfig(
        int $domainId,
        array $config,
        ?DependencyContainer $container = null,
        ?Context $context = null
    ): Result {
        // 유효성 검증
        $validated = $this->validateExtensionConfig($config);
        if (!$validated['valid']) {
            return Result::failure($validated['message']);
        }

        // 기존 config 조회 (diff 비교용)
        $oldConfig = $this->getExtensionConfig($domainId);

        // 정규화
        $sanitized = $this->sanitizeExtensionConfig($config);
        $this->stripManagedSuperOnlyPluginsFromChildDomain($domainId, $sanitized);

        // installed 상태 유지 (기존 값에서 가져옴)
        $sanitized['installed'] = $oldConfig['installed'] ?? ['plugins' => [], 'packages' => []];

        // 라이프사이클 실행 (container + context가 있을 때만)
        if ($container && $context) {
            $sanitized = $this->executeLifecycle($domainId, $oldConfig, $sanitized, $container, $context);
        }

        // 저장
        $result = $this->domainRepository->updateExtensionConfig($domainId, $sanitized);

        if ($result) {
            $this->invalidateCaches($domainId);
            return Result::success('확장 설정이 저장되었습니다.');
        }
        return Result::failure('확장 설정 저장에 실패했습니다.');
    }

    /**
     * 도메인 캐시 + 라우터 캐시 무효화
     *
     * @param int $domainId 도메인 ID
     */
    private function invalidateCaches(int $domainId): void
    {
        $domain = $this->domainRepository->find($domainId);
        if (!$domain) {
            return;
        }

        $domainName = $domain->getDomain();
        $this->domainCache->delete($domainName);
        Router::clearRouteCache($domainName);
    }

    /**
     * 플러그인 활성화/비활성화 토글
     *
     * @param int $domainId 도메인 ID
     * @param string $pluginName 플러그인 이름
     * @param bool $enabled 활성화 여부
     * @return Result
     */
    public function togglePlugin(int $domainId, string $pluginName, bool $enabled): Result
    {
        $config = $this->getExtensionConfig($domainId);
        $plugins = $config['plugins'] ?? [];

        if ($enabled) {
            // 플러그인 존재 확인
            $manifests = $this->getPluginManifests();
            if (!isset($manifests[$pluginName])) {
                return Result::failure("플러그인 '{$pluginName}'을(를) 찾을 수 없습니다.");
            }
            if (!in_array($pluginName, $plugins)) {
                $plugins[] = $pluginName;
            }
        } else {
            $plugins = array_values(array_filter($plugins, fn($p) => $p !== $pluginName));
        }

        $config['plugins'] = $plugins;
        return $this->saveExtensionConfig($domainId, $config);
    }

    /**
     * 패키지 활성화/비활성화 토글
     *
     * @param int $domainId 도메인 ID
     * @param string $packageName 패키지 이름
     * @param bool $enabled 활성화 여부
     * @return Result
     */
    public function togglePackage(int $domainId, string $packageName, bool $enabled): Result
    {
        $config = $this->getExtensionConfig($domainId);
        $packages = $config['packages'] ?? [];

        if ($enabled) {
            // 패키지 존재 확인
            $manifests = $this->getPackageManifests();
            if (!isset($manifests[$packageName])) {
                return Result::failure("패키지 '{$packageName}'을(를) 찾을 수 없습니다.");
            }
            if (!in_array($packageName, $packages)) {
                $packages[] = $packageName;
            }
        } else {
            $packages = array_values(array_filter($packages, fn($p) => $p !== $packageName));
        }

        $config['packages'] = $packages;
        return $this->saveExtensionConfig($domainId, $config);
    }

    /**
     * 플러그인이 활성화되어 있는지 확인
     *
     * @param int $domainId 도메인 ID
     * @param string $pluginName 플러그인 이름
     * @return bool
     */
    public function isPluginEnabled(int $domainId, string $pluginName): bool
    {
        $enabled = $this->getEnabledPlugins($domainId);
        return in_array($pluginName, $enabled);
    }

    /**
     * 패키지가 활성화되어 있는지 확인
     *
     * @param int $domainId 도메인 ID
     * @param string $packageName 패키지 이름
     * @return bool
     */
    public function isPackageEnabled(int $domainId, string $packageName): bool
    {
        $enabled = $this->getEnabledPackages($domainId);
        return in_array($packageName, $enabled);
    }

    /**
     * 활성화된 확장 목록에 manifest 정보 포함하여 반환
     * (Admin UI용)
     *
     * @param int $domainId 도메인 ID
     * @return array ['plugins' => [...], 'packages' => [...]]
     */
    public function getExtensionsWithManifests(int $domainId): array
    {
        $allManifests = $this->getAllManifests();
        $enabledPlugins = $this->getEnabledPlugins($domainId);
        $enabledPackages = $this->getEnabledPackages($domainId);

        // 활성화된 항목을 저장 순서대로 먼저, 비활성화 항목을 뒤에 배치
        $plugins = $this->sortByEnabledOrder($allManifests['plugins'], $enabledPlugins);
        $packages = $this->sortByEnabledOrder($allManifests['packages'], $enabledPackages);

        return [
            'plugins' => $plugins,
            'packages' => $packages,
        ];
    }

    /**
     * 활성화 순서 유지 정렬
     *
     * 활성화된 항목은 enabledList 배열 순서대로 먼저 배치하고,
     * 비활성화 항목은 뒤에 이름순으로 배치한다.
     */
    private function sortByEnabledOrder(array $manifests, array $enabledList): array
    {
        $enabled = [];
        $disabled = [];

        // 활성화된 항목을 enabledList 순서대로 수집
        foreach ($enabledList as $name) {
            if (isset($manifests[$name])) {
                $enabled[$name] = array_merge($manifests[$name], ['enabled' => true]);
            }
        }

        // 비활성화 항목 수집
        foreach ($manifests as $name => $manifest) {
            if (!in_array($name, $enabledList)) {
                $disabled[$name] = array_merge($manifest, ['enabled' => false]);
            }
        }

        return $enabled + $disabled;
    }

    /**
     * manifest.json 파일 스캔
     *
     * @param string $basePath 스캔할 기본 경로
     * @param string $type 'plugin' 또는 'package'
     * @return array
     */
    private function scanManifests(string $basePath, string $type): array
    {
        $manifests = [];

        if (!is_dir($basePath)) {
            $this->debug("Extension path not found: {$basePath}");
            return $manifests;
        }

        $dirs = glob($basePath . '/*', GLOB_ONLYDIR);

        foreach ($dirs as $dir) {
            $manifestFile = $dir . '/manifest.json';

            if (!is_file($manifestFile)) {
                continue;
            }

            $name = basename($dir);
            $content = file_get_contents($manifestFile);
            $manifest = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->warning("Invalid manifest.json in {$dir}: " . json_last_error_msg());
                continue;
            }

            // 기본값 설정
            $manifests[$name] = array_merge([
                'name' => $name,
                'label' => $name,
                'description' => '',
                'version' => '1.0.0',
                'author' => '',
                'author_url' => '',
                'icon' => 'bi-puzzle',
                'type' => $type,
                'hidden' => false,
                'super_only' => false,
                'requires' => [],
            ], $manifest);
        }

        return $manifests;
    }

    /**
     * extension_config 유효성 검증
     *
     * @param array $config
     * @return array ['valid' => bool, 'message' => string]
     */
    private function validateExtensionConfig(array $config): array
    {
        // plugins가 배열인지 확인
        if (isset($config['plugins']) && !is_array($config['plugins'])) {
            return ['valid' => false, 'message' => 'plugins는 배열이어야 합니다.'];
        }

        // packages가 배열인지 확인
        if (isset($config['packages']) && !is_array($config['packages'])) {
            return ['valid' => false, 'message' => 'packages는 배열이어야 합니다.'];
        }

        // 플러그인 manifest 존재 검증
        foreach ($config['plugins'] ?? [] as $name) {
            if (!is_string($name) || empty(trim($name))) {
                continue;
            }
            $manifestPath = $this->pluginPath . '/' . $name . '/manifest.json';
            if (!file_exists($manifestPath)) {
                return ['valid' => false, 'message' => "플러그인 '{$name}'의 manifest.json을 찾을 수 없습니다."];
            }
        }

        // 패키지 manifest 존재 검증
        foreach ($config['packages'] ?? [] as $name) {
            if (!is_string($name) || empty(trim($name))) {
                continue;
            }
            $manifestPath = $this->packagePath . '/' . $name . '/manifest.json';
            if (!file_exists($manifestPath)) {
                return ['valid' => false, 'message' => "패키지 '{$name}'의 manifest.json을 찾을 수 없습니다."];
            }
        }

        return ['valid' => true, 'message' => ''];
    }

    /**
     * extension_config 정규화
     *
     * @param array $config
     * @return array
     */
    private function sanitizeExtensionConfig(array $config): array
    {
        $sanitized = [
            'plugins' => array_values(array_filter(
                $config['plugins'] ?? [],
                fn($v) => is_string($v) && !empty(trim($v))
            )),
            'packages' => array_values(array_filter(
                $config['packages'] ?? [],
                fn($v) => is_string($v) && !empty(trim($v))
            )),
        ];

        // installed 키가 있으면 보존
        if (isset($config['installed'])) {
            $sanitized['installed'] = $config['installed'];
        }

        return $sanitized;
    }

    /**
     * 하위 도메인에서 super_only 플러그인 직접 제어를 제거한다.
     *
     * super_only 플러그인은 루트 도메인이 켜고 끄며,
     * 하위 도메인은 저장 요청으로 이를 변경하지 않는다.
     */
    private function stripManagedSuperOnlyPluginsFromChildDomain(int $domainId, array &$config): void
    {
        $domain = $this->domainRepository->find($domainId);
        if (!$domain) {
            return;
        }

        $group = $domain->getDomainGroup() ?? '';
        $rootId = (int) explode('/', $group)[0];

        if ($rootId <= 0 || $rootId === $domainId) {
            return;
        }

        $superOnlyNames = [];
        foreach ($this->getPluginManifests() as $name => $manifest) {
            if (!empty($manifest['super_only'])) {
                $superOnlyNames[] = $name;
            }
        }

        if (empty($superOnlyNames)) {
            return;
        }

        $config['plugins'] = array_values(array_filter(
            $config['plugins'] ?? [],
            fn($name) => !in_array($name, $superOnlyNames, true)
        ));
    }

    // =========================================================================
    // Install 상태 추적
    // =========================================================================

    /**
     * 확장이 설치(install)된 적 있는지 확인
     */
    public function isInstalled(int $domainId, string $type, string $name): bool
    {
        $config = $this->getExtensionConfig($domainId);
        $installed = $config['installed'][$type . 's'] ?? [];
        return in_array($name, $installed);
    }

    /**
     * 확장을 설치 완료 상태로 마킹
     */
    private function markInstalled(array &$config, string $type, string $name): void
    {
        $key = $type . 's'; // 'plugin' → 'plugins', 'package' → 'packages'
        if (!isset($config['installed'][$key])) {
            $config['installed'][$key] = [];
        }
        if (!in_array($name, $config['installed'][$key])) {
            $config['installed'][$key][] = $name;
        }
    }

    /**
     * 확장의 설치 상태 마킹 해제
     */
    private function markUninstalled(array &$config, string $type, string $name): void
    {
        $key = $type . 's';
        if (isset($config['installed'][$key])) {
            $config['installed'][$key] = array_values(
                array_filter($config['installed'][$key], fn($n) => $n !== $name)
            );
        }
    }

    // =========================================================================
    // Install/Uninstall 라이프사이클
    // =========================================================================

    /**
     * 설정 변경 시 install/uninstall 라이프사이클 실행
     *
     * @param int $domainId 도메인 ID
     * @param array $oldConfig 기존 설정
     * @param array $newConfig 새 설정 (installed 키 포함, 참조로 수정됨)
     * @param DependencyContainer $container
     * @param Context $context
     * @return array 수정된 newConfig
     */
    private function executeLifecycle(
        int $domainId,
        array $oldConfig,
        array $newConfig,
        DependencyContainer $container,
        Context $context
    ): array {
        // 플러그인 라이프사이클
        $this->processLifecycleForType(
            'plugin', $oldConfig, $newConfig, $container, $context
        );

        // 패키지 라이프사이클
        $this->processLifecycleForType(
            'package', $oldConfig, $newConfig, $container, $context
        );

        return $newConfig;
    }

    /**
     * 타입별 라이프사이클 처리
     */
    private function processLifecycleForType(
        string $type,
        array $oldConfig,
        array &$newConfig,
        DependencyContainer $container,
        Context $context
    ): void {
        $key = $type . 's'; // 'plugins' or 'packages'
        $oldList = $oldConfig[$key] ?? [];
        $newList = $newConfig[$key] ?? [];
        $installedList = $newConfig['installed'][$key] ?? [];

        // 새로 활성화된 항목
        $activated = array_diff($newList, $oldList);
        foreach ($activated as $name) {
            // 미설치 상태이면 install 실행
            if (!in_array($name, $installedList)) {
                $provider = $this->resolveProvider($type, $name);
                if ($provider instanceof InstallableExtensionInterface) {
                    try {
                        $provider->register($container);
                        $provider->install($container, $context);
                        $this->markInstalled($newConfig, $type, $name);
                    } catch (\Throwable $e) {
                        error_log("Extension install error [{$type}:{$name}]: " . $e->getMessage());
                        // install 실패 시 활성 목록에서 제거 (불일치 방지)
                        $newConfig[$key] = array_values(
                            array_filter($newConfig[$key], fn($n) => $n !== $name)
                        );
                    }
                }
            }
        }

        // 비활성화된 항목
        $deactivated = array_diff($oldList, $newList);
        foreach ($deactivated as $name) {
            $provider = $this->resolveProvider($type, $name);
            if ($provider instanceof InstallableExtensionInterface) {
                try {
                    $provider->register($container);
                    $provider->uninstall($container, $context);
                    $this->markUninstalled($newConfig, $type, $name);
                } catch (\Throwable $e) {
                    error_log("Extension uninstall error [{$type}:{$name}]: " . $e->getMessage());
                    // uninstall 실패 시 활성 목록에 복원 (불일치 방지)
                    $newConfig[$key][] = $name;
                }
            }
        }
    }

    /**
     * Provider 인스턴스 생성
     *
     * @param string $type 'plugin' 또는 'package'
     * @param string $name 확장 이름
     * @return ExtensionProviderInterface|null
     */
    /**
     * 새 도메인의 default 패키지 시드 데이터 실행
     *
     * 도메인 생성 직후 Controller에서 호출.
     * extension_config에 등록된 패키지의 database/seeders/ PHP 파일을 실행.
     * 시더 함수에 $pdo, $domainId를 전달하여 도메인별 시드 가능.
     */
    public function seedDefaultExtensions(int $domainId): void
    {
        $config = $this->getExtensionConfig($domainId);
        $packages = $config['installed']['packages'] ?? [];

        if (empty($packages)) {
            return;
        }

        $pdo = $this->db->getPdo();

        foreach ($packages as $name) {
            $seederPath = $this->packagePath . '/' . $name . '/database/seeders';
            if (!is_dir($seederPath)) {
                continue;
            }

            $files = glob($seederPath . '/*.php') ?: [];
            sort($files);

            foreach ($files as $file) {
                try {
                    $seederFn = require $file;
                    if (is_callable($seederFn)) {
                        $seederFn($pdo, $domainId);
                    }
                } catch (\Throwable $e) {
                    error_log("Extension seed error [package:{$name}]: " . $e->getMessage());
                }
            }
        }
    }

    private function resolveProvider(string $type, string $name): ?ExtensionProviderInterface
    {
        $namespace = $type === 'plugin' ? 'Plugin' : 'Packages';
        $providerClass = "Mublo\\{$namespace}\\{$name}\\{$name}Provider";

        if (!class_exists($providerClass)) {
            return null;
        }

        try {
            return new $providerClass();
        } catch (\Throwable $e) {
            error_log("Provider resolve error [{$type}:{$name}]: " . $e->getMessage());
            return null;
        }
    }
}
