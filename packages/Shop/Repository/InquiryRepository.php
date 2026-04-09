<?php

namespace Mublo\Packages\Shop\Repository;

use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;

class InquiryRepository
{
    private Database $db;
    private string $table = 'shop_inquiries';

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? DatabaseManager::getInstance()->connect();
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE inquiry_id = ?",
            [$id]
        ) ?: null;
    }

    public function getList(int $domainId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['q.domain_id = ?'];
        $params = [$domainId];

        if (!empty($filters['inquiry_status'])) {
            $where[] = 'q.inquiry_status = ?';
            $params[] = $filters['inquiry_status'];
        }
        if (!empty($filters['goods_id'])) {
            $where[] = 'q.goods_id = ?';
            $params[] = (int) $filters['goods_id'];
        }
        if (!empty($filters['member_id'])) {
            $where[] = 'q.member_id = ?';
            $params[] = (int) $filters['member_id'];
        }
        if (!empty($filters['is_visible']) || $filters['is_visible'] === '1') {
            $where[] = 'q.is_visible = ?';
            $params[] = (int) $filters['is_visible'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(q.title LIKE ? OR q.content LIKE ? OR q.author_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereClause = implode(' AND ', $where);

        $totalItems = (int) $this->db->selectOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} q WHERE {$whereClause}",
            $params
        )['cnt'];

        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $offset = ($page - 1) * $perPage;

        $items = $this->db->select(
            "SELECT q.*, p.goods_name
             FROM {$this->table} q
             LEFT JOIN shop_products p ON p.goods_id = q.goods_id
             WHERE {$whereClause}
             ORDER BY q.inquiry_id DESC
             LIMIT ? OFFSET ?",
            [...$params, $perPage, $offset]
        );

        return [
            'items' => $items,
            'pagination' => [
                'totalItems' => $totalItems,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => $totalPages,
            ],
        ];
    }

    public function create(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $columnList = implode(', ', $columns);

        return $this->db->insert(
            "INSERT INTO {$this->table} ({$columnList}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    public function update(int $id, array $data): int
    {
        $sets = [];
        $values = [];
        foreach ($data as $col => $val) {
            $sets[] = "`{$col}` = ?";
            $values[] = $val;
        }
        $values[] = $id;

        return $this->db->execute(
            "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE inquiry_id = ?",
            $values
        );
    }

    public function delete(int $id): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE inquiry_id = ?",
            [$id]
        );
    }

    public function batchUpdateFields(array $items): int
    {
        $updated = 0;
        foreach ($items as $inquiryId => $fields) {
            $allowed = array_intersect_key($fields, array_flip(['inquiry_status']));
            if (empty($allowed)) {
                continue;
            }
            if ($this->update((int) $inquiryId, $allowed) > 0) {
                $updated++;
            }
        }
        return $updated;
    }

    public function deleteByIds(array $inquiryIds): int
    {
        if (empty($inquiryIds)) {
            return 0;
        }
        $ids = array_map('intval', $inquiryIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE inquiry_id IN ({$placeholders})",
            $ids
        );
    }
}
