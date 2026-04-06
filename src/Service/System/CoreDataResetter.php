<?php

namespace Mublo\Service\System;

use Mublo\Contract\DataResettableInterface;
use Mublo\Infrastructure\Database\Database;

/**
 * Core 데이터 초기화
 *
 * Core 테이블(회원, 블록, 메뉴)과 업로드 파일의 초기화를 담당합니다.
 */
class CoreDataResetter implements DataResettableInterface
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    public function getResetCategories(): array
    {
        return [
            [
                'key' => 'members',
                'label' => '회원',
                'description' => 'SUPER 회원을 제외한 전체 회원 및 관련 데이터(추가필드값, 약관동의, 토큰)를 삭제합니다.',
                'icon' => 'bi-people',
            ],
            [
                'key' => 'blocks',
                'label' => '블록',
                'description' => '블록 페이지, 행, 칼럼 데이터를 모두 삭제합니다.',
                'icon' => 'bi-grid',
            ],
            [
                'key' => 'menus',
                'label' => '메뉴',
                'description' => '메뉴 트리 및 메뉴 항목을 모두 삭제합니다.',
                'icon' => 'bi-list',
            ],
            [
                'key' => 'uploads',
                'label' => '업로드 파일',
                'description' => '업로드된 파일을 모두 삭제합니다. (로고/파비콘은 보존)',
                'icon' => 'bi-folder',
            ],
        ];
    }

    public function reset(string $category, int $domainId, Database $db): array
    {
        return match ($category) {
            'members' => $this->resetMembers($domainId),
            'blocks' => $this->resetBlocks($domainId),
            'menus' => $this->resetMenus($domainId),
            'uploads' => $this->resetUploads($domainId),
            default => ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => '알 수 없는 카테고리'],
        };
    }

    private function resetMembers(int $domainId): array
    {
        $cleared = 0;

        // SUPER 회원 ID 조회
        $superIds = $this->db->select(
            "SELECT m.member_id FROM members m
             JOIN member_levels ml ON m.level_value = ml.level_value AND m.domain_id = ml.domain_id
             WHERE m.domain_id = :domain_id AND ml.is_super = 1",
            ['domain_id' => $domainId]
        );
        $superMemberIds = array_column($superIds, 'member_id');

        if (empty($superMemberIds)) {
            $notInClause = '';
        } else {
            $placeholders = implode(',', array_fill(0, count($superMemberIds), '?'));
            $notInClause = "AND member_id NOT IN ({$placeholders})";
        }

        $this->db->execute("SET FOREIGN_KEY_CHECKS = 0");

        try {
            // 회원 연관 테이블 삭제
            $linkedTables = ['member_field_values', 'member_policy_agreements'];
            foreach ($linkedTables as $table) {
                if ($this->tableExists($table)) {
                    if (empty($superMemberIds)) {
                        $this->db->execute("DELETE FROM {$table} WHERE member_id IN (SELECT member_id FROM members WHERE domain_id = ?)", [$domainId]);
                    } else {
                        $params = array_merge([$domainId], $superMemberIds);
                        $this->db->execute("DELETE FROM {$table} WHERE member_id IN (SELECT member_id FROM members WHERE domain_id = ? {$notInClause})", $params);
                    }
                    $cleared++;
                }
            }

            // 토큰 테이블
            foreach (['password_reset_tokens', 'proxy_login_tokens'] as $table) {
                if ($this->tableExists($table)) {
                    $this->db->execute("DELETE FROM {$table} WHERE member_id IN (SELECT member_id FROM members WHERE domain_id = ?)", [$domainId]);
                    $cleared++;
                }
            }

            // 회원: 삭제하지 않고 SUPER 도메인으로 이전 + 일반 등급으로 변경 (SUPER 회원 제외)
            if (empty($superMemberIds)) {
                $this->db->execute(
                    "UPDATE members SET domain_id = 1, domain_group = '1', level_value = 1 WHERE domain_id = ?",
                    [$domainId]
                );
            } else {
                $params = array_merge([$domainId], $superMemberIds);
                $this->db->execute(
                    "UPDATE members SET domain_id = 1, domain_group = '1', level_value = 1 WHERE domain_id = ? {$notInClause}",
                    $params
                );
            }
            $cleared++;
        } finally {
            $this->db->execute("SET FOREIGN_KEY_CHECKS = 1");
        }

        return ['tables_cleared' => $cleared, 'files_deleted' => 0, 'details' => 'SUPER 회원 보존, 나머지 일반 등급으로 이전'];
    }

    private function resetBlocks(int $domainId): array
    {
        $cleared = 0;

        $this->db->execute("SET FOREIGN_KEY_CHECKS = 0");
        try {
            foreach (['block_columns', 'block_rows', 'block_pages'] as $table) {
                if ($this->tableExists($table)) {
                    $this->db->execute("DELETE FROM {$table} WHERE domain_id = ?", [$domainId]);
                    $cleared++;
                }
            }
        } finally {
            $this->db->execute("SET FOREIGN_KEY_CHECKS = 1");
        }

        return ['tables_cleared' => $cleared, 'files_deleted' => 0, 'details' => '블록 페이지/행/칼럼 삭제'];
    }

    private function resetMenus(int $domainId): array
    {
        $cleared = 0;

        $this->db->execute("SET FOREIGN_KEY_CHECKS = 0");
        try {
            foreach (['menu_tree', 'menu_items', 'member_level_denied_menus'] as $table) {
                if ($this->tableExists($table)) {
                    $this->db->execute("DELETE FROM {$table} WHERE domain_id = ?", [$domainId]);
                    $cleared++;
                }
            }
        } finally {
            $this->db->execute("SET FOREIGN_KEY_CHECKS = 1");
        }

        return ['tables_cleared' => $cleared, 'files_deleted' => 0, 'details' => '메뉴 트리/항목 삭제'];
    }

    private function resetUploads(int $domainId): array
    {
        $filesDeleted = 0;
        $storagePath = MUBLO_PUBLIC_STORAGE_PATH . '/D' . $domainId;

        if (!is_dir($storagePath)) {
            return ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => '업로드 디렉토리 없음'];
        }

        $items = array_diff(scandir($storagePath), ['.', '..']);
        foreach ($items as $item) {
            // site/ 디렉토리는 보존 (로고, 파비콘)
            if ($item === 'site') {
                continue;
            }

            $path = $storagePath . '/' . $item;
            if (is_dir($path)) {
                $filesDeleted += $this->deleteDirectoryRecursive($path);
            } else {
                unlink($path);
                $filesDeleted++;
            }
        }

        return ['tables_cleared' => 0, 'files_deleted' => $filesDeleted, 'details' => '업로드 파일 삭제 (site/ 보존)'];
    }

    private function deleteDirectoryRecursive(string $dir): int
    {
        $count = 0;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
                $count++;
            }
        }
        rmdir($dir);

        return $count;
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->db->selectOne("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
