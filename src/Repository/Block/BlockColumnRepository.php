<?php
namespace Mublo\Repository\Block;

use Mublo\Entity\Block\BlockColumn;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Database\DatabaseManager;
use Mublo\Repository\BaseRepository;

/**
 * BlockColumn Repository
 *
 * 블록 칸(Column) 데이터베이스 접근 담당
 *
 * 책임:
 * - block_columns 테이블 CRUD
 * - BlockColumn Entity 반환
 *
 * 금지:
 * - 비즈니스 로직 (Service 담당)
 */
class BlockColumnRepository extends BaseRepository
{
    protected string $table = 'block_columns';
    protected string $entityClass = BlockColumn::class;
    protected string $primaryKey = 'column_id';

    public function __construct(?Database $db = null)
    {
        $db = $db ?? DatabaseManager::getInstance()->connect();
        parent::__construct($db);
    }

    /**
     * 행별 칸 목록 조회
     *
     * @param int $rowId 행 ID
     * @return BlockColumn[]
     */
    public function findByRow(int $rowId): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('row_id', '=', $rowId)
            ->where('is_active', '=', 1)
            ->orderBy('column_index', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 행별 모든 칸 조회 (관리자용, is_active 무관)
     *
     * @param int $rowId 행 ID
     * @return BlockColumn[]
     */
    public function findAllByRow(int $rowId): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('row_id', '=', $rowId)
            ->orderBy('column_index', 'ASC')
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 도메인별 칸 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @param int $limit 조회 개수
     * @param int $offset 시작 위치
     * @return BlockColumn[]
     */
    public function findByDomain(int $domainId, int $limit = 100, int $offset = 0): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->orderBy('row_id', 'ASC')
            ->orderBy('column_index', 'ASC')
            ->limit($limit)
            ->offset($offset)
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 행+인덱스로 칸 조회
     */
    public function findByRowAndIndex(int $rowId, int $columnIndex): ?BlockColumn
    {
        return $this->findOneBy([
            'row_id' => $rowId,
            'column_index' => $columnIndex,
        ]);
    }

    /**
     * 행별 칸 수 조회
     */
    public function countByRow(int $rowId): int
    {
        return $this->countBy(['row_id' => $rowId]);
    }

    /**
     * 도메인별 칸 수 조회
     */
    public function countByDomain(int $domainId): int
    {
        return $this->countBy(['domain_id' => $domainId]);
    }

    /**
     * 콘텐츠 타입별 칸 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @param string $contentType 콘텐츠 타입
     * @return BlockColumn[]
     */
    public function findByContentType(int $domainId, string $contentType): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('content_type', '=', $contentType)
            ->where('is_active', '=', 1)
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 콘텐츠 종류별 칸 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @param string $contentKind 콘텐츠 종류 (CORE, PLUGIN, PACKAGE)
     * @return BlockColumn[]
     */
    public function findByContentKind(int $domainId, string $contentKind): array
    {
        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('content_kind', '=', $contentKind)
            ->where('is_active', '=', 1)
            ->get();

        return $this->toEntities($rows);
    }

    /**
     * 행 삭제 시 관련 칸 삭제
     */
    public function deleteByRow(int $rowId): int
    {
        return $this->getDb()->table($this->table)
            ->where('row_id', '=', $rowId)
            ->delete();
    }

    /**
     * 행의 칸 일괄 저장 (기존 삭제 후 새로 생성)
     *
     * @param int $rowId 행 ID
     * @param int $domainId 도메인 ID
     * @param array $columnsData 칸 데이터 배열
     * @return bool
     */
    public function replaceByRow(int $rowId, int $domainId, array $columnsData): bool
    {
        // 기존 칸 삭제
        $this->deleteByRow($rowId);

        // 새 칸 저장
        foreach ($columnsData as $index => $columnData) {
            $columnData['row_id'] = $rowId;
            $columnData['domain_id'] = $domainId;
            $columnData['column_index'] = $index;
            $columnData['created_at'] = date('Y-m-d H:i:s');
            $columnData['updated_at'] = date('Y-m-d H:i:s');

            // JSON 필드 처리
            $jsonFields = ['background_config', 'border_config', 'title_config', 'content_config', 'content_items'];
            foreach ($jsonFields as $field) {
                if (isset($columnData[$field]) && is_array($columnData[$field])) {
                    $columnData[$field] = json_encode($columnData[$field], JSON_UNESCAPED_UNICODE);
                }
            }

            $this->getDb()->table($this->table)->insert($columnData);
        }

        return true;
    }

    /**
     * JSON 필드 업데이트
     *
     * @param int $columnId 칸 ID
     * @param string $field 필드명
     * @param array|null $value 값
     * @return bool
     */
    public function updateJsonField(int $columnId, string $field, ?array $value): bool
    {
        $allowedFields = ['background_config', 'border_config', 'title_config', 'content_config', 'content_items'];

        if (!in_array($field, $allowedFields, true)) {
            return false;
        }

        $jsonValue = $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE);

        $affected = $this->getDb()->table($this->table)
            ->where('column_id', '=', $columnId)
            ->update([$field => $jsonValue]);

        return $affected >= 0;
    }

    /**
     * 콘텐츠 설정 업데이트
     *
     * @param int $columnId 칸 ID
     * @param string|null $type 콘텐츠 타입
     * @param string $kind 콘텐츠 종류
     * @param string|null $skin 스킨
     * @param array|null $config 추가 설정 (pc_count, mo_count, aos, pc_style 등 포함)
     * @param array|null $items 선택된 아이템 (게시판 ID 목록 또는 이미지 배열)
     * @return bool
     */
    public function updateContent(
        int $columnId,
        ?string $type,
        string $kind = 'CORE',
        ?string $skin = null,
        ?array $config = null,
        ?array $items = null
    ): bool {
        $data = [
            'content_type' => $type,
            'content_kind' => $kind,
            'content_skin' => $skin,
            'content_config' => $config ? json_encode($config, JSON_UNESCAPED_UNICODE) : null,
            'content_items' => $items ? json_encode($items, JSON_UNESCAPED_UNICODE) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $affected = $this->getDb()->table($this->table)
            ->where('column_id', '=', $columnId)
            ->update($data);

        return $affected >= 0;
    }

    /**
     * 제목 설정 업데이트
     *
     * @param int $columnId 칸 ID
     * @param array|null $titleConfig 제목 설정
     * @return bool
     */
    public function updateTitleConfig(int $columnId, ?array $titleConfig): bool
    {
        return $this->updateJsonField($columnId, 'title_config', $titleConfig);
    }

    /**
     * 스타일 설정 업데이트 (배경 + 테두리)
     *
     * @param int $columnId 칸 ID
     * @param array|null $backgroundConfig 배경 설정
     * @param array|null $borderConfig 테두리 설정
     * @return bool
     */
    public function updateStyleConfig(int $columnId, ?array $backgroundConfig, ?array $borderConfig): bool
    {
        $data = [
            'background_config' => $backgroundConfig ? json_encode($backgroundConfig, JSON_UNESCAPED_UNICODE) : null,
            'border_config' => $borderConfig ? json_encode($borderConfig, JSON_UNESCAPED_UNICODE) : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $affected = $this->getDb()->table($this->table)
            ->where('column_id', '=', $columnId)
            ->update($data);

        return $affected >= 0;
    }

    /**
     * 특정 콘텐츠 아이템을 사용하는 칸 조회
     * (예: 특정 게시판 ID가 content_items에 포함된 칸)
     *
     * @param int $domainId 도메인 ID
     * @param string $contentType 콘텐츠 타입
     * @param mixed $itemId 아이템 ID
     * @return BlockColumn[]
     */
    public function findByContentItem(int $domainId, string $contentType, $itemId): array
    {
        // JSON 배열 내 검색 (MySQL JSON_CONTAINS 사용)
        $rows = $this->getDb()->table($this->table)
            ->where('domain_id', '=', $domainId)
            ->where('content_type', '=', $contentType)
            ->whereRaw("JSON_CONTAINS(content_items, ?)", [json_encode($itemId)])
            ->get();

        return $this->toEntities($rows);
    }
}
