<?php

namespace Mublo\Core\Block;

use PDO;

/**
 * BlockSeeder
 *
 * 설치 시 기본 블록 구성을 JSON 파일로부터 자동 생성.
 * 설치 프로세스(PDO 직접 사용)에서 호출되므로 DI 컨테이너에 의존하지 않는다.
 *
 * JSON 파일 위치: database/seeders/block-templates/*.json
 */
class BlockSeeder
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 기본 블록 시드 실행
     *
     * @param int $domainId 대상 도메인 ID
     * @return array{success: bool, message: string, created?: int}
     */
    public function seed(int $domainId): array
    {
        $seederPath = MUBLO_ROOT_PATH . '/database/seeders/block-templates';

        if (!is_dir($seederPath)) {
            return ['success' => true, 'message' => '블록 시드 디렉토리 없음 (스킵)', 'created' => 0];
        }

        $files = glob($seederPath . '/*.json');
        if (empty($files)) {
            return ['success' => true, 'message' => '블록 시드 파일 없음 (스킵)', 'created' => 0];
        }

        sort($files);

        try {
            $this->pdo->beginTransaction();

            $created = 0;
            foreach ($files as $file) {
                $json = file_get_contents($file);
                $template = json_decode($json, true);

                if (!$template || empty($template['rows'])) {
                    continue;
                }

                if ($this->processTemplate($domainId, $template)) {
                    $created++;
                }
            }

            $this->pdo->commit();

            return [
                'success' => true,
                'message' => "{$created}개 블록 구성 생성 완료",
                'created' => $created,
            ];
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => '블록 시드 실패: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 단일 템플릿 처리
     */
    private function processTemplate(int $domainId, array $template): bool
    {
        $pageId = null;

        // 1. 페이지 생성 (페이지 기반일 때)
        if (!empty($template['page'])) {
            $pageCode = $template['page']['page_code'] ?? '';
            if (empty($pageCode)) {
                return false;
            }

            // 이미 존재하면 스킵
            $stmt = $this->pdo->prepare(
                'SELECT page_id FROM block_pages WHERE domain_id = :domain_id AND page_code = :page_code'
            );
            $stmt->execute(['domain_id' => $domainId, 'page_code' => $pageCode]);
            if ($stmt->fetchColumn()) {
                return false;
            }

            $pageId = $this->createPage($domainId, $template['page']);
        }

        $position = $template['position'] ?? null;

        // page도 position도 없으면 스킵
        if (!$pageId && !$position) {
            return false;
        }

        // 2. 행 + 칼럼 생성
        foreach ($template['rows'] as $sortOrder => $rowData) {
            $columns = $rowData['columns'] ?? [];
            unset($rowData['columns']);

            // CORE 타입만 필터 (설치 시점에는 Plugin/Package 미로드)
            $validColumns = array_values(array_filter($columns, function ($col) {
                return ($col['content_kind'] ?? 'CORE') === 'CORE';
            }));

            if (empty($validColumns)) {
                continue;
            }

            $rowId = $this->createRow($domainId, $rowData, $sortOrder, $pageId, $position);
            if (!$rowId) {
                continue;
            }

            foreach ($validColumns as $col) {
                $this->createColumn($domainId, $rowId, $col);
            }
        }

        return true;
    }

    /**
     * 블록 페이지 생성
     */
    private function createPage(int $domainId, array $page): int
    {
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO block_pages (
            domain_id, page_code, page_title, page_description,
            layout_type, use_fullpage, custom_width,
            use_header, use_footer, allow_level, is_active,
            created_at, updated_at
        ) VALUES (
            :domain_id, :page_code, :page_title, :page_description,
            :layout_type, :use_fullpage, :custom_width,
            :use_header, :use_footer, :allow_level, :is_active,
            :created_at, :updated_at
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'domain_id' => $domainId,
            'page_code' => $page['page_code'],
            'page_title' => $page['page_title'] ?? '',
            'page_description' => $page['page_description'] ?? '',
            'layout_type' => $page['layout_type'] ?? 1,
            'use_fullpage' => $page['use_fullpage'] ?? 0,
            'custom_width' => $page['custom_width'] ?? null,
            'use_header' => ($page['use_header'] ?? true) ? 1 : 0,
            'use_footer' => ($page['use_footer'] ?? true) ? 1 : 0,
            'allow_level' => $page['allow_level'] ?? 0,
            'is_active' => ($page['is_active'] ?? true) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * 블록 행 생성
     */
    private function createRow(int $domainId, array $row, int $sortOrder, ?int $pageId, ?string $position): int
    {
        $now = date('Y-m-d H:i:s');

        $backgroundConfig = isset($row['background_config'])
            ? json_encode($row['background_config'], JSON_UNESCAPED_UNICODE)
            : null;

        $sql = "INSERT INTO block_rows (
            domain_id, page_id, position, section_id, admin_title,
            width_type, column_count, column_margin, column_width_unit,
            pc_height, mobile_height, pc_padding, mobile_padding,
            background_config, sort_order, is_active,
            created_at, updated_at
        ) VALUES (
            :domain_id, :page_id, :position, :section_id, :admin_title,
            :width_type, :column_count, :column_margin, :column_width_unit,
            :pc_height, :mobile_height, :pc_padding, :mobile_padding,
            :background_config, :sort_order, :is_active,
            :created_at, :updated_at
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'domain_id' => $domainId,
            'page_id' => $pageId,
            'position' => $position,
            'section_id' => $row['section_id'] ?? '',
            'admin_title' => $row['admin_title'] ?? '',
            'width_type' => $row['width_type'] ?? 1,
            'column_count' => $row['column_count'] ?? 1,
            'column_margin' => $row['column_margin'] ?? 0,
            'column_width_unit' => $row['column_width_unit'] ?? 1,
            'pc_height' => $row['pc_height'] ?? '',
            'mobile_height' => $row['mobile_height'] ?? '',
            'pc_padding' => $row['pc_padding'] ?? '',
            'mobile_padding' => $row['mobile_padding'] ?? '',
            'background_config' => $backgroundConfig,
            'sort_order' => $sortOrder,
            'is_active' => ($row['is_active'] ?? true) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * 블록 칼럼 생성
     */
    private function createColumn(int $domainId, int $rowId, array $col): void
    {
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO block_columns (
            row_id, domain_id, column_index, width,
            pc_padding, mobile_padding,
            background_config, border_config, title_config,
            content_type, content_kind, content_skin,
            content_config, content_items,
            sort_order, is_active,
            created_at, updated_at
        ) VALUES (
            :row_id, :domain_id, :column_index, :width,
            :pc_padding, :mobile_padding,
            :background_config, :border_config, :title_config,
            :content_type, :content_kind, :content_skin,
            :content_config, :content_items,
            :sort_order, :is_active,
            :created_at, :updated_at
        )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'row_id' => $rowId,
            'domain_id' => $domainId,
            'column_index' => $col['column_index'] ?? 0,
            'width' => $col['width'] ?? '100%',
            'pc_padding' => $col['pc_padding'] ?? '',
            'mobile_padding' => $col['mobile_padding'] ?? '',
            'background_config' => $this->jsonEncode($col['background_config'] ?? null),
            'border_config' => $this->jsonEncode($col['border_config'] ?? null),
            'title_config' => $this->jsonEncode($col['title_config'] ?? null),
            'content_type' => $col['content_type'] ?? 'html',
            'content_kind' => $col['content_kind'] ?? 'CORE',
            'content_skin' => $col['content_skin'] ?? 'basic',
            'content_config' => $this->jsonEncode($col['content_config'] ?? null),
            'content_items' => $this->jsonEncode($col['content_items'] ?? null),
            'sort_order' => $col['column_index'] ?? 0,
            'is_active' => ($col['is_active'] ?? true) ? 1 : 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * 배열/객체를 JSON 문자열로 변환 (null은 null 반환)
     */
    private function jsonEncode(mixed $value): ?string
    {
        if ($value === null || (is_array($value) && empty($value))) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
