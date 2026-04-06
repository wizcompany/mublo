<?php

namespace Mublo\Packages\Shop\Repository;

use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Packages\Shop\Entity\Exhibition;

class ExhibitionRepository
{
    private Database $db;
    private string $table      = 'shop_exhibitions';
    private string $itemTable  = 'shop_exhibition_items';

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? DatabaseManager::getInstance()->connect();
    }

    public function find(int $id): ?Exhibition
    {
        $row = $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE exhibition_id = ?",
            [$id]
        );
        return $row ? Exhibition::fromArray($row) : null;
    }

    public function findBySlug(int $domainId, string $slug): ?Exhibition
    {
        $row = $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE domain_id = ? AND slug = ?",
            [$domainId, $slug]
        );
        return $row ? Exhibition::fromArray($row) : null;
    }

    public function getList(int $domainId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where  = ['domain_id = ?'];
        $params = [$domainId];

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $where[]  = 'is_active = ?';
            $params[] = (int) $filters['is_active'];
        }
        if (!empty($filters['keyword'])) {
            $where[]  = 'title LIKE ?';
            $params[] = '%' . $filters['keyword'] . '%';
        }

        $whereClause = implode(' AND ', $where);

        $totalItems = (int) $this->db->selectOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE {$whereClause}",
            $params
        )['cnt'];

        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $offset     = ($page - 1) * $perPage;

        $items = $this->db->select(
            "SELECT * FROM {$this->table}
             WHERE {$whereClause}
             ORDER BY sort_order ASC, exhibition_id DESC
             LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );

        return [
            'items'      => array_map(fn($r) => Exhibition::fromArray($r), $items),
            'pagination' => [
                'totalItems'  => $totalItems,
                'perPage'     => $perPage,
                'currentPage' => $page,
                'totalPages'  => $totalPages,
            ],
        ];
    }

    /** 진행 중인 기획전 목록 (프론트용) */
    public function getActiveList(int $domainId): array
    {
        $now  = date('Y-m-d H:i:s');
        $rows = $this->db->select(
            "SELECT * FROM {$this->table}
             WHERE domain_id = ?
               AND is_active = 1
               AND (start_date IS NULL OR start_date <= ?)
               AND (end_date IS NULL OR end_date >= ?)
             ORDER BY sort_order ASC, exhibition_id DESC",
            [$domainId, $now, $now]
        );
        return array_map(fn($r) => Exhibition::fromArray($r), $rows);
    }

    public function create(array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $columnList   = implode(', ', $columns);

        return $this->db->insert(
            "INSERT INTO {$this->table} ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    public function update(int $id, array $data): int
    {
        $sets   = [];
        $values = [];
        foreach ($data as $col => $val) {
            $sets[]   = "`{$col}` = ?";
            $values[] = $val;
        }
        $values[] = $id;

        return $this->db->execute(
            "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE exhibition_id = ?",
            $values
        );
    }

    public function delete(int $id): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE exhibition_id = ?",
            [$id]
        );
    }

    // -------------------------------------------------------------------------
    // 기획전 아이템
    // -------------------------------------------------------------------------

    public function getItems(int $exhibitionId): array
    {
        return $this->db->select(
            "SELECT ei.*, p.goods_name, p.display_price,
                    (SELECT image_url FROM shop_product_images pi
                     WHERE pi.goods_id = ei.goods_id ORDER BY sort_order LIMIT 1) AS product_image
             FROM {$this->itemTable} ei
             LEFT JOIN shop_products p ON p.goods_id = ei.goods_id AND ei.target_type = 'goods'
             WHERE ei.exhibition_id = ?
             ORDER BY ei.sort_order ASC, ei.item_id ASC",
            [$exhibitionId]
        );
    }

    public function addItem(array $data): int
    {
        $columns      = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $columnList   = implode(', ', $columns);

        return $this->db->insert(
            "INSERT INTO {$this->itemTable} ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    public function deleteItem(int $itemId): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->itemTable} WHERE item_id = ?",
            [$itemId]
        );
    }

    public function deleteItemsByExhibition(int $exhibitionId): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->itemTable} WHERE exhibition_id = ?",
            [$exhibitionId]
        );
    }

    /** slug 중복 확인 */
    public function slugExists(int $domainId, string $slug, ?int $excludeId = null): bool
    {
        $sql    = "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE domain_id = ? AND slug = ?";
        $params = [$domainId, $slug];
        if ($excludeId !== null) {
            $sql    .= ' AND exhibition_id != ?';
            $params[] = $excludeId;
        }
        return (int) $this->db->selectOne($sql, $params)['cnt'] > 0;
    }
}
