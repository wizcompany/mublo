<?php

namespace Mublo\Packages\Shop\Repository;

use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;

class ReviewRepository
{
    private Database $db;
    private string $table = 'shop_reviews';

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? DatabaseManager::getInstance()->connect();
    }

    public function find(int $id): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE review_id = ?",
            [$id]
        ) ?: null;
    }

    public function getList(int $domainId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['r.domain_id = ?'];
        $params = [$domainId];

        if (isset($filters['is_visible']) && $filters['is_visible'] !== '') {
            $where[] = 'r.is_visible = ?';
            $params[] = (int) $filters['is_visible'];
        }
        if (!empty($filters['goods_id'])) {
            $where[] = 'r.goods_id = ?';
            $params[] = (int) $filters['goods_id'];
        }
        if (isset($filters['is_best']) && $filters['is_best'] !== '') {
            $where[] = 'r.is_best = ?';
            $params[] = (int) $filters['is_best'];
        }
        if (!empty($filters['member_id'])) {
            $where[] = 'r.member_id = ?';
            $params[] = (int) $filters['member_id'];
        }
        if (!empty($filters['keyword'])) {
            $where[] = '(r.content LIKE ? OR p.goods_name LIKE ?)';
            $kw = '%' . $filters['keyword'] . '%';
            $params[] = $kw;
            $params[] = $kw;
        }

        $whereClause = implode(' AND ', $where);

        $totalItems = (int) $this->db->selectOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} r
             LEFT JOIN shop_products p ON p.goods_id = r.goods_id
             WHERE {$whereClause}",
            $params
        )['cnt'];

        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $offset = ($page - 1) * $perPage;

        $items = $this->db->select(
            "SELECT r.*, p.goods_name,
                    (SELECT image_url FROM shop_product_images pi WHERE pi.goods_id = r.goods_id ORDER BY sort_order LIMIT 1) AS product_thumbnail
             FROM {$this->table} r
             LEFT JOIN shop_products p ON p.goods_id = r.goods_id
             WHERE {$whereClause}
             ORDER BY r.review_id DESC
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

    /**
     * 상품 ID 배열에 대한 리뷰 통계 배치 조회
     *
     * @return array [goods_id => ['count' => N, 'avg_rating' => N.N]]
     */
    public function getStatsByGoodsIds(int $domainId, array $goodsIds): array
    {
        if (empty($goodsIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($goodsIds), '?'));
        $rows = $this->db->select(
            "SELECT goods_id, COUNT(*) AS cnt, AVG(rating) AS avg_rating
             FROM {$this->table}
             WHERE domain_id = ? AND goods_id IN ({$placeholders}) AND is_visible = 1
             GROUP BY goods_id",
            [$domainId, ...array_map('intval', $goodsIds)]
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['goods_id']] = [
                'count' => (int) $row['cnt'],
                'avg_rating' => round((float) $row['avg_rating'], 1),
            ];
        }

        return $result;
    }

    public function getByGoodsId(int $domainId, int $goodsId, int $page = 1, int $perPage = 10): array
    {
        return $this->getList($domainId, ['goods_id' => $goodsId, 'is_visible' => 1], $page, $perPage);
    }

    public function getAverageRating(int $domainId, int $goodsId): float
    {
        $row = $this->db->selectOne(
            "SELECT AVG(rating) AS avg_rating FROM {$this->table} WHERE domain_id = ? AND goods_id = ? AND is_visible = 1",
            [$domainId, $goodsId]
        );
        return round((float) ($row['avg_rating'] ?? 0), 1);
    }

    public function findByOrderDetailId(int $orderDetailId): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE order_detail_id = ?",
            [$orderDetailId]
        ) ?: null;
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
            "UPDATE {$this->table} SET " . implode(', ', $sets) . " WHERE review_id = ?",
            $values
        );
    }

    public function delete(int $id): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE review_id = ?",
            [$id]
        );
    }

    public function batchUpdateFields(array $items): int
    {
        $updated = 0;
        foreach ($items as $reviewId => $fields) {
            $allowed = array_intersect_key($fields, array_flip(['is_visible', 'is_best']));
            if (empty($allowed)) {
                continue;
            }
            if ($this->update((int) $reviewId, $allowed) > 0) {
                $updated++;
            }
        }
        return $updated;
    }

    public function deleteByIds(array $reviewIds): int
    {
        if (empty($reviewIds)) {
            return 0;
        }
        $ids = array_map('intval', $reviewIds);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE review_id IN ({$placeholders})",
            $ids
        );
    }
}
