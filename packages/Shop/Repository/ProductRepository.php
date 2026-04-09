<?php

namespace Mublo\Packages\Shop\Repository;

use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Packages\Shop\Entity\Product;
use Mublo\Repository\BaseRepository;

/**
 * Product Repository
 *
 * 상품 데이터베이스 접근 담당
 *
 * 책임:
 * - shop_products 테이블 CRUD
 * - Product Entity 반환
 * - 상품 이미지/상세정보 관리
 * - 필터링 + 페이지네이션 목록 조회
 *
 * 금지:
 * - 비즈니스 로직 (Service 담당)
 */
class ProductRepository extends BaseRepository
{
    protected string $table = 'shop_products';
    protected string $entityClass = Product::class;
    protected string $primaryKey = 'goods_id';

    public function __construct(?Database $db = null)
    {
        $db = $db ?? DatabaseManager::getInstance()->connect();
        parent::__construct($db);
    }

    /**
     * 상품 목록 조회 (페이지네이션 + 필터)
     *
     * @param int $domainId 도메인 ID
     * @param array $filters 필터 조건 (category_code, keyword, is_active, sort)
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @return array ['items' => Product[], 'pagination' => [...]]
     */
    public function getList(int $domainId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId);

        // 카테고리 필터 (하위 카테고리 포함)
        if (!empty($filters['category_codes'])) {
            $query->whereIn('category_code', $filters['category_codes']);
        } elseif (!empty($filters['category_code'])) {
            $query->where('category_code', '=', $filters['category_code']);
        }

        // 키워드 검색 (상품명)
        if (!empty($filters['keyword'])) {
            $query->where('goods_name', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        // 활성 상태 필터
        if (isset($filters['is_active'])) {
            $query->where('is_active', '=', (int) $filters['is_active']);
        }

        // 전체 개수
        $total = $query->count();

        // 정렬
        $sort = $filters['sort'] ?? 'newest';
        switch ($sort) {
            case 'price_asc':
                $query->orderBy('display_price', 'ASC');
                break;
            case 'price_desc':
                $query->orderBy('display_price', 'DESC');
                break;
            case 'popular':
                $query->orderBy('hit', 'DESC');
                break;
            case 'newest':
            default:
                $query->orderBy('created_at', 'DESC');
                break;
        }

        // 페이지네이션
        $offset = ($page - 1) * $perPage;
        $rows = $query->limit($perPage)->offset($offset)->get();

        return [
            'items' => $this->toEntities($rows),
            'pagination' => [
                'totalItems' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    /**
     * 상품 개수 조회 (필터 적용)
     *
     * @param int $domainId 도메인 ID
     * @param array $filters 필터 조건
     * @return int
     */
    public function getCount(int $domainId, array $filters = []): int
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId);

        if (!empty($filters['category_code'])) {
            $query->where('category_code', '=', $filters['category_code']);
        }

        if (!empty($filters['keyword'])) {
            $query->where('goods_name', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', '=', (int) $filters['is_active']);
        }

        return $query->count();
    }

    /**
     * 상품 일괄 삭제
     *
     * @param array $goodsIds 삭제할 상품 ID 배열
     * @return int 삭제된 행 수
     */
    public function deleteMultiple(array $goodsIds): int
    {
        if (empty($goodsIds)) {
            return 0;
        }

        return $this->getDb()->table($this->table)
            ->whereIn($this->primaryKey, $goodsIds)
            ->delete();
    }

    /**
     * 도메인 소유 검증: 주어진 상품 ID 중 해당 도메인에 속하는 것만 반환
     *
     * @param array $goodsIds 상품 ID 배열
     * @param int $domainId 도메인 ID
     * @return array 해당 도메인에 속하는 goods_id 배열
     */
    public function filterByDomain(array $goodsIds, int $domainId): array
    {
        if (empty($goodsIds)) {
            return [];
        }

        $rows = $this->getDb()->table($this->table)
            ->select([$this->primaryKey])
            ->whereIn($this->primaryKey, $goodsIds)
            ->where('domain_id', '=', $domainId)
            ->get();

        return array_column($rows, $this->primaryKey);
    }

    /**
     * 상품 대표 이미지 배치 조회
     *
     * N+1 방지: 목록 조회 후 한 번의 WHERE IN 쿼리로 일괄 조회
     *
     * @param array $goodsIds 상품 ID 배열
     * @return array [goods_id => ['image_url' => ..., 'thumbnail_url' => ...]]
     */
    public function getMainImages(array $goodsIds): array
    {
        if (empty($goodsIds)) {
            return [];
        }

        // is_main=1 우선, 없으면 sort_order 가장 작은 이미지 폴백
        $rows = $this->getDb()->table('shop_product_images')
            ->whereIn('goods_id', $goodsIds)
            ->orderBy('is_main', 'DESC')
            ->orderBy('sort_order', 'ASC')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $gid = (int) $row['goods_id'];
            if (!isset($map[$gid])) {
                $map[$gid] = $row;
            }
        }

        return $map;
    }

    /**
     * 상품 이미지 목록 조회
     *
     * @param int $goodsId 상품 ID
     * @return array raw array 목록
     */
    public function getImages(int $goodsId): array
    {
        return $this->getDb()->table('shop_product_images')
            ->where('goods_id', '=', $goodsId)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    /**
     * 상품 이미지 생성
     *
     * @param array $data 이미지 데이터
     * @return int 생성된 image_id
     */
    public function createImage(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');

        return $this->getDb()->table('shop_product_images')->insert($data);
    }

    /**
     * 상품 이미지 삭제
     *
     * @param int $imageId 이미지 ID
     * @return bool 성공 여부
     */
    public function deleteImage(int $imageId): bool
    {
        $affected = $this->getDb()->table('shop_product_images')
            ->where('image_id', '=', $imageId)
            ->delete();

        return $affected > 0;
    }

    /**
     * 상품 이미지 일괄 삭제
     *
     * @param int $goodsId 상품 ID
     * @return int 삭제된 행 수
     */
    public function deleteImages(int $goodsId): int
    {
        return $this->getDb()->table('shop_product_images')
            ->where('goods_id', '=', $goodsId)
            ->delete();
    }

    /**
     * 상품 상세정보 일괄 삭제
     *
     * @param int $goodsId 상품 ID
     * @return int 삭제된 행 수
     */
    public function deleteDetails(int $goodsId): int
    {
        return $this->getDb()->table('shop_product_details')
            ->where('goods_id', '=', $goodsId)
            ->delete();
    }

    /**
     * 상품 상세정보 조회
     *
     * @param int $goodsId 상품 ID
     * @return array raw array 목록
     */
    public function getDetails(int $goodsId): array
    {
        return $this->getDb()->table('shop_product_details')
            ->where('goods_id', '=', $goodsId)
            ->get();
    }

    /**
     * 상품 상세정보 저장
     *
     * @param int $goodsId 상품 ID
     * @param array $data 상세정보 데이터
     * @return int 생성된 detail_id
     */
    public function saveDetail(int $goodsId, array $data): int
    {
        $data['goods_id'] = $goodsId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return $this->getDb()->table('shop_product_details')->insert($data);
    }

    /**
     * ID 배열로 상품 조회 (블록 렌더링용)
     *
     * @param array $goodsIds 상품 ID 배열
     * @return array Product Entity 배열
     */
    public function findByIds(array $goodsIds): array
    {
        if (empty($goodsIds)) {
            return [];
        }

        $rows = $this->getDb()->table($this->table)
            ->whereIn($this->primaryKey, $goodsIds)
            ->where('is_active', '=', 1)
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 블록 에디터용 상품 목록 조회 (활성 상품)
     *
     * @param int $domainId 도메인 ID
     * @return array raw 배열 목록
     */
    public function getActiveList(int $domainId): array
    {
        return $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('is_active', '=', 1)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * 블록 에디터용 상품 목록 (페이징 + 필터)
     *
     * @param int $domainId 도메인 ID
     * @param array $filters ['category_code' => string, 'keyword' => string]
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @return array ['items' => raw[], 'pagination' => [...]]
     */
    public function getActiveListPaginated(int $domainId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('is_active', '=', 1);

        if (!empty($filters['category_codes'])) {
            $query->whereIn('category_code', $filters['category_codes']);
        } elseif (!empty($filters['category_code'])) {
            $query->where('category_code', '=', $filters['category_code']);
        }

        if (!empty($filters['keyword'])) {
            $query->where('goods_name', 'LIKE', '%' . $filters['keyword'] . '%');
        }

        $total = $query->count();

        $query->orderBy('created_at', 'DESC');
        $offset = ($page - 1) * $perPage;
        $rows = $query->limit($perPage)->offset($offset)->get();

        return [
            'items' => $rows,
            'pagination' => [
                'totalItems' => $total,
                'perPage' => $perPage,
                'currentPage' => $page,
                'totalPages' => $total > 0 ? (int) ceil($total / $perPage) : 1,
            ],
        ];
    }

    /**
     * 조회수 증가
     */
    public function incrementHit(int $goodsId): void
    {
        $this->getDb()->execute(
            "UPDATE {$this->table} SET hit = hit + 1 WHERE goods_id = ?",
            [$goodsId]
        );
    }

    /**
     * 재고 증감 (atomic)
     *
     * stock_quantity가 NULL(미관리)이면 무시.
     * 차감 시 0 미만으로 내려가지 않도록 GREATEST 사용.
     *
     * @param int $goodsId 상품 ID
     * @param int $delta 증감량 (양수=증가, 음수=차감)
     * @return int 영향받은 행 수
     */
    public function adjustStock(int $goodsId, int $delta): int
    {
        if ($delta >= 0) {
            return $this->getDb()->execute(
                "UPDATE {$this->table}
                 SET stock_quantity = stock_quantity + ?
                 WHERE goods_id = ? AND stock_quantity IS NOT NULL",
                [$delta, $goodsId]
            );
        }

        return $this->getDb()->execute(
            "UPDATE {$this->table}
             SET stock_quantity = GREATEST(stock_quantity + ?, 0)
             WHERE goods_id = ? AND stock_quantity IS NOT NULL",
            [$delta, $goodsId]
        );
    }

    /**
     * 생성 타임스탬프 필드명
     */
    protected function getCreatedAtField(): ?string
    {
        return 'created_at';
    }

    /**
     * 수정 타임스탬프 필드명
     */
    protected function getUpdatedAtField(): ?string
    {
        return 'updated_at';
    }
}
