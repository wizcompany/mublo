<?php

namespace Mublo\Core\Install;

/**
 * EnvironmentChecker
 *
 * 설치 환경 체크 (PHP 버전, 확장 모듈, 디렉토리 권한)
 */
class EnvironmentChecker
{
    /**
     * 필수 PHP 버전
     */
    private const REQUIRED_PHP_VERSION = '8.2.0';

    /**
     * 필수 PHP 확장 모듈
     */
    private const REQUIRED_EXTENSIONS = [
        'pdo',
        'pdo_mysql',
        'mysqli',
        'mbstring',
        'openssl',
        'json',
        'curl',
        'fileinfo',
    ];

    /**
     * 권장 PHP 확장 모듈
     */
    private const RECOMMENDED_EXTENSIONS = [
        'gd',
        'zip',
        'xml',
        'intl',
    ];

    /**
     * 쓰기 권한이 필요한 디렉토리
     */
    private array $writableDirectories = [];

    public function __construct()
    {
        // 웹 루트 하위 storage 라벨을 실제 디렉토리명으로 표시
        $publicDirName = basename(MUBLO_PUBLIC_PATH);

        $this->writableDirectories = [
            'config'                       => MUBLO_CONFIG_PATH,
            'storage'                      => MUBLO_STORAGE_PATH,
            $publicDirName . '/storage'    => MUBLO_PUBLIC_STORAGE_PATH,
        ];
    }

    /**
     * 전체 환경 체크
     */
    public function checkAll(): array
    {
        $checks = [
            'php_version' => $this->checkPhpVersion(),
            'extensions'  => $this->checkExtensions(),
            'permissions' => $this->checkPermissions(),
        ];

        // ❗ 여기서만 종합 판단
        $checks['overall_status'] = $this->getOverallStatus($checks);

        return $checks;
    }

    /**
     * PHP 버전 체크
     */
    private function checkPhpVersion(): array
    {
        $currentVersion = PHP_VERSION;
        $isValid = version_compare($currentVersion, self::REQUIRED_PHP_VERSION, '>=');

        return [
            'required' => self::REQUIRED_PHP_VERSION,
            'current'  => $currentVersion,
            'status'   => $isValid ? 'OK' : 'FAIL',
            'message'  => $isValid
                ? "PHP {$currentVersion} (요구사항 충족)"
                : "PHP {$currentVersion} (최소 " . self::REQUIRED_PHP_VERSION . " 필요)",
        ];
    }

    /**
     * PHP 확장 모듈 체크
     */
    private function checkExtensions(): array
    {
        $result = [
            'required'     => [],
            'recommended'  => [],
        ];

        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $loaded = extension_loaded($ext);
            $result['required'][$ext] = [
                'loaded'  => $loaded,
                'status'  => $loaded ? 'OK' : 'FAIL',
                'message' => $loaded ? '설치됨' : '미설치 (필수)',
            ];
        }

        foreach (self::RECOMMENDED_EXTENSIONS as $ext) {
            $loaded = extension_loaded($ext);
            $result['recommended'][$ext] = [
                'loaded'  => $loaded,
                'status'  => $loaded ? 'OK' : 'WARNING',
                'message' => $loaded ? '설치됨' : '미설치 (권장)',
            ];
        }

        return $result;
    }

    /**
     * 디렉토리 권한 체크
     */
    private function checkPermissions(): array
    {
        $result = [];

        foreach ($this->writableDirectories as $label => $path) {
            $exists   = file_exists($path);
            $readable = $exists && is_readable($path);
            $writable = $exists && is_writable($path);

            if (!$exists) {
                $status  = 'FAIL';
                $message = '디렉토리가 존재하지 않음';
            } elseif (!$readable) {
                $status  = 'FAIL';
                $message = '읽기 권한 없음';
            } elseif (!$writable) {
                $status  = 'FAIL';
                $message = '쓰기 권한 없음';
            } else {
                $status  = 'OK';
                $message = '읽기/쓰기 가능';
            }

            $result[$label] = [
                'path'     => $path,
                'exists'   => $exists,
                'readable' => $readable,
                'writable' => $writable,
                'status'   => $status,
                'message'  => $message,
            ];
        }

        return $result;
    }

    /**
     * 전체 상태 판단 (재귀 ❌, 분석 전용)
     */
    private function getOverallStatus(array $checks): string
    {
        if ($checks['php_version']['status'] === 'FAIL') {
            return 'FAIL';
        }

        foreach ($checks['extensions']['required'] as $info) {
            if ($info['status'] === 'FAIL') {
                return 'FAIL';
            }
        }

        foreach ($checks['permissions'] as $info) {
            if ($info['status'] === 'FAIL') {
                return 'FAIL';
            }
        }

        foreach ($checks['extensions']['recommended'] as $info) {
            if ($info['status'] === 'WARNING') {
                return 'WARNING';
            }
        }

        return 'OK';
    }

    /**
     * 설치 가능 여부 판단
     */
    public function canInstall(): bool
    {
        $checks = $this->checkAll();
        return in_array($checks['overall_status'], ['OK', 'WARNING'], true);
    }
}
