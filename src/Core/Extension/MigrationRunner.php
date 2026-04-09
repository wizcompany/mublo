<?php
namespace Mublo\Core\Extension;

use Mublo\Infrastructure\Database\Database;

/**
 * MigrationRunner
 *
 * Core / Plugin / Package DB 마이그레이션 통합 실행 및 추적.
 *
 * 추적 테이블: schema_migrations
 *   - source : 'core' | 'plugin' | 'package'
 *   - name   : 식별자 (__core__, Banner, Shop 등)
 *   - file   : SQL 파일명
 *
 * 사용 예:
 * ```php
 * // Core
 * $runner->run('core', '__core__', '/path/to/migrations');
 *
 * // Plugin
 * $runner->run('plugin', 'Banner', '/path/to/plugin/migrations');
 *
 * // Package
 * $runner->run('package', 'Shop', '/path/to/package/migrations');
 * ```
 */
class MigrationRunner
{
    private Database $db;
    private string $trackingTable = 'schema_migrations';

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * 마이그레이션 상태 조회
     *
     * @return array ['pending' => [...], 'executed' => [...]]
     */
    public function getStatus(string $source, string $name, string $migrationPath): array
    {
        $allFiles = $this->getMigrationFiles($migrationPath);

        if (empty($allFiles)) {
            return ['pending' => [], 'executed' => []];
        }

        $this->ensureTrackingTable();

        $executed = $this->getExecutedMigrations($name);

        $pending = [];
        foreach ($allFiles as $file) {
            $filename = basename($file);
            if (!in_array($filename, $executed, true)) {
                $pending[] = $filename;
            }
        }

        return [
            'pending'  => $pending,
            'executed' => $executed,
        ];
    }

    /**
     * 미실행 마이그레이션 실행
     *
     * @return array ['success' => bool, 'executed' => [...], 'error' => string|null]
     */
    public function run(string $source, string $name, string $migrationPath): array
    {
        $status = $this->getStatus($source, $name, $migrationPath);

        if (empty($status['pending'])) {
            return ['success' => true, 'executed' => [], 'error' => null];
        }

        $executed = [];
        $pdo = $this->db->getPdo();

        foreach ($status['pending'] as $filename) {
            $filePath = rtrim($migrationPath, '/\\') . '/' . $filename;

            if (!is_file($filePath)) {
                continue;
            }

            try {
                $sql = file_get_contents($filePath);

                // @optional-table 주석 파싱: 해당 테이블 부재 시 관련 쿼리 에러를 무시
                $optionalTables = $this->parseOptionalTables($sql);

                $queries = array_filter(
                    array_map(function ($q) {
                        $lines = explode("\n", $q);
                        $lines = array_filter($lines, fn($line) => !str_starts_with(trim($line), '--'));
                        return trim(implode("\n", $lines));
                    }, explode(';', $sql)),
                    fn($q) => !empty($q)
                );

                foreach ($queries as $query) {
                    try {
                        // query()로 실행하여 결과셋을 PDOStatement로 받고 즉시 해제
                        // exec()는 SET @var=(SELECT ...) 등의 내부 결과셋을 소비하지 못함
                        $stmt = $pdo->query($query);
                        if ($stmt) {
                            $stmt->closeCursor();
                        }
                    } catch (\PDOException $e) {
                        // "Table doesn't exist" (1146) 에러이고 optional-table에 해당하면 스킵
                        if ($e->getCode() === '42S02' && !empty($optionalTables) && $this->isOptionalTableError($e->getMessage(), $optionalTables)) {
                            continue;
                        }
                        // ALTER TABLE 중복 컬럼/키 에러 무시 (CREATE TABLE에 이미 반영된 경우)
                        if ($this->isIdempotentError($e)) {
                            continue;
                        }
                        throw $e;
                    }
                }

                $this->recordMigration($source, $name, $filename);
                $executed[] = $filename;
            } catch (\PDOException $e) {
                return [
                    'success'  => false,
                    'executed' => $executed,
                    'error'    => "[{$filename}] " . $e->getMessage(),
                ];
            }
        }

        return ['success' => true, 'executed' => $executed, 'error' => null];
    }

    /**
     * 마이그레이션을 실행됨으로 마킹 (실제 SQL 실행 없이)
     *
     * Installer가 설치 완료 시 이미 실행한 파일들을 이력에 등록할 때 사용.
     */
    public function markExecuted(string $source, string $name, string $filename): void
    {
        $this->ensureTrackingTable();
        $this->recordMigration($source, $name, $filename);
    }

    /**
     * 추적 테이블 생성 (없을 때만) + 레거시 이전
     */
    private function ensureTrackingTable(): void
    {
        $pdo = $this->db->getPdo();

        $pdo->exec("CREATE TABLE IF NOT EXISTS `{$this->trackingTable}` (
            `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `source`      ENUM('core', 'plugin', 'package') NOT NULL DEFAULT 'plugin',
            `name`        VARCHAR(100) NOT NULL,
            `file`        VARCHAR(200) NOT NULL,
            `executed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uk_migration` (`name`, `file`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    }

    /**
     * SQL 파일 목록 (이름순 정렬)
     */
    private function getMigrationFiles(string $migrationPath): array
    {
        if (!is_dir($migrationPath)) {
            return [];
        }

        $files = glob(rtrim($migrationPath, '/\\') . '/*.sql');

        if ($files === false) {
            return [];
        }

        sort($files);
        return $files;
    }

    /**
     * 이미 실행된 파일명 목록
     */
    private function getExecutedMigrations(string $name): array
    {
        try {
            $stmt = $this->db->getPdo()->prepare(
                "SELECT `file` FROM `{$this->trackingTable}` WHERE `name` = ? ORDER BY `id` ASC"
            );
            $stmt->execute([$name]);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * SQL 내 @optional-table 주석에서 테이블명 파싱
     *
     * 형식: -- @optional-table: table1, table2
     * 해당 테이블이 존재하지 않아서 발생하는 에러를 무시함
     *
     * @return string[] 선택적 테이블명 배열
     */
    private function parseOptionalTables(string $sql): array
    {
        if (preg_match('/--\s*@optional-table:\s*(.+)/i', $sql, $matches)) {
            return array_map('trim', explode(',', $matches[1]));
        }
        return [];
    }

    /**
     * PDOException 메시지가 optional 테이블 부재로 인한 것인지 확인
     */
    private function isOptionalTableError(string $message, array $optionalTables): bool
    {
        foreach ($optionalTables as $table) {
            if (stripos($message, $table) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * ALTER TABLE 멱등성 관련 무시 가능 에러인지 확인
     *
     * - 42S21 (1060): Duplicate column name — ADD COLUMN 중복
     * - 42S22 (1054): Unknown column — DROP COLUMN 대상 없음
     * - 42000 (1061): Duplicate key name — ADD INDEX/KEY 중복
     * - 42000 (1072): Key column doesn't exist — DROP COLUMN IF EXISTS 시 인덱스 참조 컬럼 부재
     * - 42000 (1091): Can't DROP; check that column/key exists
     */
    private function isIdempotentError(\PDOException $e): bool
    {
        $code = $e->getCode();
        return in_array($code, ['42S21', '42S22'], true)
            || ($code === '42000' && preg_match('/\b(1061|1072|1091)\b/', $e->getMessage()));
    }

    /**
     * 실행 이력 기록
     */
    private function recordMigration(string $source, string $name, string $filename): void
    {
        $stmt = $this->db->getPdo()->prepare(
            "INSERT IGNORE INTO `{$this->trackingTable}` (source, name, file) VALUES (?, ?, ?)"
        );
        $stmt->execute([$source, $name, $filename]);
    }
}
