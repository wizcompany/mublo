<?php

namespace Mublo\Packages\Shop\Repository;

use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Packages\Shop\Entity\ProductOption;
use Mublo\Repository\BaseRepository;

/**
 * ProductOption Repository
 *
 * 상품 옵션 데이터베이스 접근 담당
 *
 * 책임:
 * - shop_product_options 테이블 CRUD
 * - shop_product_option_values 관리
 * - shop_product_option_combos 관리
 * - ProductOption Entity 반환
 *
 * 금지:
 * - 비즈니스 로직 (Service 담당)
 */
class ProductOptionRepository extends BaseRepository
{
    protected string $table = 'shop_product_options';
    protected string $entityClass = ProductOption::class;
    protected string $primaryKey = 'option_id';

    public function __construct(?Database $db = null)
    {
        $db = $db ?? DatabaseManager::getInstance()->connect();
        parent::__construct($db);
    }

    /**
     * 상품별 옵션 + 값 조회
     *
     * 2단계 쿼리: 옵션 조회 후 각 옵션의 값을 그룹핑하여 반환
     *
     * @param int $goodsId 상품 ID
     * @return array 옵션 배열 (각 옵션에 values 키 포함)
     */
    public function getByProduct(int $goodsId): array
    {
        // 1단계: 옵션 조회
        $optionRows = $this->getDb()->table($this->table)
            ->where('goods_id', '=', $goodsId)
            ->orderBy('sort_order', 'ASC')
            ->get();

        if (empty($optionRows)) {
            return [];
        }

        // 옵션 ID 수집
        $optionIds = array_column($optionRows, 'option_id');

        // 2단계: 옵션 값 조회
        $valueRows = $this->getDb()->table('shop_product_option_values')
            ->whereIn('option_id', $optionIds)
            ->orderBy('sort_order', 'ASC')
            ->get();

        // 옵션 ID별 값 그룹핑
        $valuesByOption = [];
        foreach ($valueRows as $value) {
            $valuesByOption[$value['option_id']][] = $value;
        }

        // 옵션 + 값 조합
        $result = [];
        foreach ($optionRows as $option) {
            $optionId = $option['option_id'];
            $result[] = [
                'option' => ProductOption::fromArray($option),
                'values' => $valuesByOption[$optionId] ?? [],
            ];
        }

        return $result;
    }

    /**
     * 옵션 값 목록 조회
     *
     * @param int $optionId 옵션 ID
     * @return array raw array 목록
     */
    public function getValues(int $optionId): array
    {
        return $this->getDb()->table('shop_product_option_values')
            ->where('option_id', '=', $optionId)
            ->orderBy('sort_order', 'ASC')
            ->get();
    }

    /**
     * 상품 옵션 조합 목록 조회
     *
     * @param int $goodsId 상품 ID
     * @return array raw array 목록
     */
    public function getCombos(int $goodsId): array
    {
        return $this->getDb()->table('shop_product_option_combos')
            ->where('goods_id', '=', $goodsId)
            ->get();
    }

    /**
     * 상품 옵션 생성
     *
     * @param int $goodsId 상품 ID
     * @param array $data 옵션 데이터
     * @return int 생성된 option_id
     */
    public function createOption(int $goodsId, array $data): int
    {
        $data['goods_id'] = $goodsId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return $this->getDb()->table($this->table)->insert($data);
    }

    /**
     * 옵션 값 생성
     *
     * @param int $optionId 옵션 ID
     * @param array $data 값 데이터
     * @return int 생성된 value_id
     */
    public function createValue(int $optionId, array $data): int
    {
        $data['option_id'] = $optionId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return $this->getDb()->table('shop_product_option_values')->insert($data);
    }

    /**
     * 옵션 조합 생성
     *
     * @param int $goodsId 상품 ID
     * @param array $data 조합 데이터
     * @return int 생성된 combo_id
     */
    public function createCombo(int $goodsId, array $data): int
    {
        $data['goods_id'] = $goodsId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return $this->getDb()->table('shop_product_option_combos')->insert($data);
    }

    /**
     * 상품 옵션 전체 삭제 (옵션 + 값 + 조합)
     *
     * 상품 옵션 재구성 시 사용
     *
     * @param int $goodsId 상품 ID
     */
    public function deleteProductOptions(int $goodsId): void
    {
        // 옵션 ID 목록 조회
        $optionRows = $this->getDb()->table($this->table)
            ->where('goods_id', '=', $goodsId)
            ->get();

        // 각 옵션의 값 삭제
        foreach ($optionRows as $option) {
            $this->getDb()->table('shop_product_option_values')
                ->where('option_id', '=', $option['option_id'])
                ->delete();
        }

        // 옵션 삭제
        $this->getDb()->table($this->table)
            ->where('goods_id', '=', $goodsId)
            ->delete();

        // 조합 삭제
        $this->getDb()->table('shop_product_option_combos')
            ->where('goods_id', '=', $goodsId)
            ->delete();
    }

    /**
     * 옵션 조합 재고 수정
     *
     * @param int $comboId 조합 ID
     * @param int $quantity 재고 수량
     * @return bool 성공 여부
     */
    public function updateComboStock(int $comboId, int $quantity): bool
    {
        $affected = $this->getDb()->table('shop_product_option_combos')
            ->where('combo_id', '=', $comboId)
            ->update([
                'stock_quantity' => $quantity,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $affected >= 0;
    }

    /**
     * 옵션 값 재고 수정
     *
     * @param int $valueId 옵션 값 ID
     * @param int $quantity 재고 수량
     * @return bool 성공 여부
     */
    public function updateValueStock(int $valueId, int $quantity): bool
    {
        $affected = $this->getDb()->table('shop_product_option_values')
            ->where('value_id', '=', $valueId)
            ->update([
                'stock_quantity' => $quantity,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        return $affected >= 0;
    }

    /**
     * 옵션 조합 재고 증감 (atomic)
     *
     * @param int $comboId 조합 ID
     * @param int $delta 증감량 (양수=증가, 음수=차감)
     * @return int 영향받은 행 수
     */
    public function adjustComboStock(int $comboId, int $delta): int
    {
        if ($delta >= 0) {
            return $this->getDb()->execute(
                "UPDATE shop_product_option_combos
                 SET stock_quantity = stock_quantity + ?
                 WHERE combo_id = ? AND stock_quantity IS NOT NULL",
                [$delta, $comboId]
            );
        }

        return $this->getDb()->execute(
            "UPDATE shop_product_option_combos
             SET stock_quantity = GREATEST(stock_quantity + ?, 0)
             WHERE combo_id = ? AND stock_quantity IS NOT NULL",
            [$delta, $comboId]
        );
    }

    /**
     * 옵션 값 재고 증감 (atomic)
     *
     * @param int $valueId 옵션 값 ID
     * @param int $delta 증감량 (양수=증가, 음수=차감)
     * @return int 영향받은 행 수
     */
    public function adjustValueStock(int $valueId, int $delta): int
    {
        if ($delta >= 0) {
            return $this->getDb()->execute(
                "UPDATE shop_product_option_values
                 SET stock_quantity = stock_quantity + ?
                 WHERE value_id = ? AND stock_quantity IS NOT NULL",
                [$delta, $valueId]
            );
        }

        return $this->getDb()->execute(
            "UPDATE shop_product_option_values
             SET stock_quantity = GREATEST(stock_quantity + ?, 0)
             WHERE value_id = ? AND stock_quantity IS NOT NULL",
            [$delta, $valueId]
        );
    }

    /**
     * 옵션 값 단건 조회 (value_id 기준)
     *
     * @param int $valueId 옵션 값 ID
     * @return array|null raw array
     */
    public function findValue(int $valueId): ?array
    {
        $row = $this->getDb()->table('shop_product_option_values')
            ->where('value_id', '=', $valueId)
            ->first();

        return $row ?: null;
    }

    /**
     * 옵션 조합 단건 조회 (combo_id 기준)
     *
     * @param int $comboId 조합 ID
     * @return array|null raw array
     */
    public function findCombo(int $comboId): ?array
    {
        $row = $this->getDb()->table('shop_product_option_combos')
            ->where('combo_id', '=', $comboId)
            ->first();

        return $row ?: null;
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
