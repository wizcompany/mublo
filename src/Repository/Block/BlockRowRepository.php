<?php
namespace Mublo\Repository\Block;

use Mublo\Entity\Block\BlockRow;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Repository\BaseRepository;

/**
 * BlockRow Repository
 *
 * 블록 행(Row) 데이터베이스 접근 담당
 *
 * 책임:
 * - block_rows 테이블 CRUD
 * - BlockRow Entity 반환
 *
 * 금지:
 * - 비즈니스 로직 (Service 담당)
 */
class BlockRowRepository extends BaseRepository
{
    protected string $table = 'block_rows';
    protected string $entityClass = BlockRow::class;
    protected string $primaryKey = 'row_id';

    public function __construct(?Database $db = null)
    {
        $db = $db ?? DatabaseManager::getInstance()->connect();
        parent::__construct($db);
    }

    /**
     * 도메인별 행 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @param int $limit 조회 개수
     * @param int $offset 시작 위치
     * @return BlockRow[]
     */
    public function findByDomain(int $domainId, int $limit = 100, int $offset = 0): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->orderBy('sort_order', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 페이지별 행 목록 조회
     *
     * @param int $pageId 페이지 ID
     * @return BlockRow[]
     */
    public function findByPage(int $pageId): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('page_id', '=', $pageId)
            ->where('is_active', '=', 1)
            ->whereRaw(
                'EXISTS (SELECT 1 FROM block_columns c WHERE c.row_id = block_rows.row_id AND c.is_active = 1)'
            )
            ->orderBy('sort_order', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 위치별 행 목록 조회 (프론트 렌더링용)
     *
     * @param int $domainId 도메인 ID
     * @param string $position 위치 (index, header, footer, left, right 등)
     * @param string|null $menuCode 특정 메뉴 코드 (선택)
     * @return BlockRow[]
     */
    public function findByPosition(int $domainId, string $position, ?string $menuCode = null): array
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('position', '=', $position)
            ->where('is_active', '=', 1);

        // 메뉴 코드 필터링: 해당 메뉴 또는 전체(null)
        if ($menuCode !== null) {
            $query->where(function ($q) use ($menuCode) {
                $q->whereNull('position_menu')
                    ->orWhere('position_menu', '=', $menuCode);
            });
        } else {
            $query->whereNull('position_menu');
        }

        // 칼럼이 존재하는 행만 반환 (빈 로우 프루닝)
        $query->whereRaw(
            'EXISTS (SELECT 1 FROM block_columns c WHERE c.row_id = block_rows.row_id AND c.is_active = 1)'
        );

        $rows = $query->orderBy('sort_order', 'ASC')->get();

        return $this->toEntities($rows);
    }

    /**
     * 위치별 모든 행 조회 (관리자용, is_active 무관)
     *
     * @param int $domainId 도메인 ID
     * @param string|null $position 위치 필터 (null이면 전체)
     * @return BlockRow[]
     */
    public function findAllByPosition(int $domainId, ?string $position = null): array
    {
        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->whereNull('page_id');

        if ($position !== null) {
            $query->where('position', '=', $position);
        }

        $rows = $query->orderBy('position', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 페이지별 모든 행 조회 (관리자용, is_active 무관)
     *
     * @param int $pageId 페이지 ID
     * @return BlockRow[]
     */
    public function findAllByPage(int $pageId): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('page_id', '=', $pageId)
            ->orderBy('sort_order', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 도메인별 행 수 조회
     */
    public function countByDomain(int $domainId): int
    {
        return $this->countBy(['domain_id' => $domainId]);
    }

    /**
     * 페이지별 행 수 조회
     */
    public function countByPage(int $pageId): int
    {
        return $this->countBy(['page_id' => $pageId]);
    }

    /**
     * 위치별 행 수 조회
     */
    public function countByPosition(int $domainId, string $position): int
    {
        return $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('position', '=', $position)
            ->count();
    }

    /**
     * 정렬 순서 업데이트
     *
     * @param int[] $rowIds 정렬된 행 ID 배열
     * @return bool
     */
    public function updateOrder(array $rowIds): bool
    {
        foreach ($rowIds as $index => $rowId) {
            $this->getDb()->table($this->table)
                ->where('row_id', '=', $rowId)
                ->update(['sort_order' => $index]);
        }

        return true;
    }

    /**
     * 지정된 행 ID들이 모두 해당 도메인에 속하는지 검증
     *
     * @param int[] $rowIds 행 ID 배열
     * @param int $domainId 도메인 ID
     * @return bool 모두 해당 도메인 소속이면 true
     */
    public function verifyAllBelongToDomain(array $rowIds, int $domainId): bool
    {
        if (empty($rowIds)) {
            return true;
        }

        $intIds = array_map('intval', $rowIds);
        $count = $this->getDb()->table($this->table)
            ->whereIn('row_id', $intIds)
            ->where('domain_id', '=', $domainId)
            ->count();

        return $count === count($intIds);
    }

    /**
     * 다음 정렬 순서 반환 (위치 기반)
     */
    public function getNextSortOrderByPosition(int $domainId, string $position): int
    {
        $maxOrder = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('position', '=', $position)
            ->max('sort_order');

        return ($maxOrder ?? -1) + 1;
    }

    /**
     * 다음 정렬 순서 반환 (페이지 기반)
     */
    public function getNextSortOrderByPage(int $pageId): int
    {
        $maxOrder = $this->getDb()->table($this->table)
            ->where('page_id', '=', $pageId)
            ->max('sort_order');

        return ($maxOrder ?? -1) + 1;
    }

    /**
     * 페이지네이션 (위치 기반)
     *
     * @param int $domainId 도메인 ID
     * @param string|null $position 위치 필터
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @return array
     */
    public function paginateByPosition(int $domainId, ?string $position = null, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->whereNull('page_id');

        if ($position !== null) {
            $query->where('position', '=', $position);
        }

        $total = (clone $query)->count();

        $rows = $query->orderBy('position', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $this->toEntities($rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * 페이지네이션 (페이지 기반)
     *
     * @param int $domainId 도메인 ID
     * @param int|null $pageId 페이지 ID 필터
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @return array
     */
    public function paginateByPage(int $domainId, ?int $pageId = null, int $page = 1, int $perPage = 15): array
    {
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $query = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->whereNotNull('page_id');

        if ($pageId !== null) {
            $query->where('page_id', '=', $pageId);
        }

        $total = (clone $query)->count();

        $rows = $query->orderBy('page_id', 'ASC')
            ->orderBy('sort_order', 'ASC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return [
            'data' => $this->toEntities($rows),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int)ceil($total / $perPage),
        ];
    }

    /**
     * 행 목록 조회 (페이지 정보 포함)
     *
     * @param int $domainId 도메인 ID
     * @return array
     */
    public function findByDomainWithPageInfo(int $domainId): array
    {
        $rows = $this->getDb()->table($this->table . ' AS r')
            ->select([
                'r.*',
                'p.page_code',
                'p.page_title',
            ])
            ->leftJoin('block_pages AS p', 'r.page_id', '=', 'p.page_id')
            ->where('r.domain_id', '=', $domainId)
            ->orderBy('r.position', 'ASC')
            ->orderBy('r.sort_order', 'ASC')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $blockRow = BlockRow::fromArray($row);
            $result[] = [
                'row' => $blockRow,
                'page_code' => $row['page_code'] ?? null,
                'page_title' => $row['page_title'] ?? null,
            ];
        }

        return $result;
    }

    /**
     * ID 배열로 행 일괄 조회
     *
     * @param int[] $rowIds 행 ID 배열
     * @return BlockRow[] ID 순서 유지
     */
    public function findByIds(array $rowIds): array
    {
        if (empty($rowIds)) {
            return [];
        }

        $rows = $this->getDb()->table($this->table)
            ->whereIn('row_id', $rowIds)
            ->get();

        // ID → Entity 매핑
        $mapped = [];
        foreach ($rows as $row) {
            $entity = BlockRow::fromArray($row);
            $mapped[$entity->getRowId()] = $entity;
        }

        // 원래 ID 순서 유지
        $result = [];
        foreach ($rowIds as $id) {
            if (isset($mapped[$id])) {
                $result[] = $mapped[$id];
            }
        }
        return $result;
    }

    /**
     * 도메인별 사용 중인 메뉴 코드 목록 조회
     *
     * @return string[] position_menu 값 배열 (null 제외)
     */
    public function getDistinctMenuCodes(int $domainId): array
    {
        $rows = $this->getDb()->table($this->table)
            ->select(['position_menu'])
            ->where('domain_id', '=', $domainId)
            ->whereNotNull('position_menu')
            ->distinct()
            ->get();

        return array_column($rows, 'position_menu');
    }

    /**
     * JSON 필드 업데이트 (background_config)
     */
    public function updateBackgroundConfig(int $rowId, ?array $config): bool
    {
        $jsonValue = $config === null ? null : json_encode($config, JSON_UNESCAPED_UNICODE);

        $affected = $this->getDb()->table($this->table)
            ->where('row_id', '=', $rowId)
            ->update(['background_config' => $jsonValue]);

        return $affected >= 0;
    }

    /**
     * 페이지 삭제 시 관련 행 삭제
     */
    public function deleteByPage(int $pageId): int
    {
        return $this->getDb()->table($this->table)
            ->where('page_id', '=', $pageId)
            ->delete();
    }
}
