<?php

namespace Mublo\Packages\Shop\Repository;

use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;

class WishlistRepository
{
    private Database $db;
    private string $table = 'shop_wishlist';

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? DatabaseManager::getInstance()->connect();
    }

    public function find(int $memberId, int $goodsId): ?array
    {
        return $this->db->selectOne(
            "SELECT * FROM {$this->table} WHERE member_id = ? AND goods_id = ?",
            [$memberId, $goodsId]
        ) ?: null;
    }

    public function create(int $memberId, int $goodsId): int
    {
        return $this->db->insert(
            "INSERT IGNORE INTO {$this->table} (member_id, goods_id) VALUES (?, ?)",
            [$memberId, $goodsId]
        );
    }

    public function delete(int $memberId, int $goodsId): int
    {
        return $this->db->execute(
            "DELETE FROM {$this->table} WHERE member_id = ? AND goods_id = ?",
            [$memberId, $goodsId]
        );
    }

    public function getMemberWishlist(int $memberId, int $page = 1, int $perPage = 20): array
    {
        $totalItems = (int) $this->db->selectOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} w WHERE w.member_id = ?",
            [$memberId]
        )['cnt'];

        $totalPages = max(1, (int) ceil($totalItems / $perPage));
        $offset = ($page - 1) * $perPage;

        $items = $this->db->select(
            "SELECT w.*, p.goods_name, p.display_price, p.discount_type, p.discount_value,
                    p.is_active, p.stock_quantity, p.option_mode,
                    (SELECT image_url FROM shop_product_images pi WHERE pi.goods_id = w.goods_id ORDER BY sort_order LIMIT 1) AS main_image
             FROM {$this->table} w
             LEFT JOIN shop_products p ON p.goods_id = w.goods_id
             WHERE w.member_id = ?
             ORDER BY w.wishlist_id DESC
             LIMIT ? OFFSET ?",
            [$memberId, $perPage, $offset]
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

    public function countByGoodsId(int $goodsId): int
    {
        $row = $this->db->selectOne(
            "SELECT COUNT(*) AS cnt FROM {$this->table} WHERE goods_id = ?",
            [$goodsId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function countByGoodsIds(array $goodsIds): array
    {
        if (empty($goodsIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($goodsIds), '?'));
        $rows = $this->db->select(
            "SELECT goods_id, COUNT(*) AS cnt FROM {$this->table}
             WHERE goods_id IN ({$placeholders})
             GROUP BY goods_id",
            array_map('intval', $goodsIds)
        );

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['goods_id']] = (int) $row['cnt'];
        }
        return $result;
    }

    public function getMemberGoodsIds(int $memberId): array
    {
        $rows = $this->db->select(
            "SELECT goods_id FROM {$this->table} WHERE member_id = ?",
            [$memberId]
        );
        return array_column($rows, 'goods_id');
    }
}
