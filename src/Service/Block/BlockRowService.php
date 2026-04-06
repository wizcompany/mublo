<?php
namespace Mublo\Service\Block;

use Mublo\Repository\Block\BlockRowRepository;
use Mublo\Repository\Block\BlockColumnRepository;
use Mublo\Repository\Block\BlockPageRepository;
use Mublo\Entity\Block\BlockRow;
use Mublo\Enum\Block\BlockPosition;
use Mublo\Infrastructure\Database\Database;
use Mublo\Helper\Form\FormHelper;
use Mublo\Core\Result\Result;

/**
 * BlockRow Service
 *
 * 블록 행(Row) 비즈니스 로직 담당
 *
 * 책임:
 * - 행 CRUD 비즈니스 로직
 * - 칸(Column) 동기화
 * - 정렬 순서 관리
 *
 * 금지:
 * - Request/Response 직접 처리 (Controller 담당)
 * - DB 직접 접근 (Repository 담당)
 */
class BlockRowService
{
    private BlockRowRepository $repository;
    private BlockColumnRepository $columnRepository;
    private BlockPageRepository $pageRepository;
    private Database $db;
    private BlockRenderService $renderService;

    /**
     * 유효한 위치 목록
     */
    public const VALID_POSITIONS = ['index', 'left', 'right', 'subhead', 'subfoot', 'contenthead', 'contentfoot'];

    public function __construct(
        BlockRowRepository $repository,
        BlockColumnRepository $columnRepository,
        BlockPageRepository $pageRepository,
        Database $db,
        BlockRenderService $renderService
    ) {
        $this->repository = $repository;
        $this->columnRepository = $columnRepository;
        $this->pageRepository = $pageRepository;
        $this->db = $db;
        $this->renderService = $renderService;
    }

    /**
     * 캐시 무효화 (행 변경 후 호출)
     */
    private function invalidateCache(BlockRow $row): void
    {
        $this->renderService->invalidateRowRelatedCache($row);
    }

    /**
     * 도메인 소유권 검증
     *
     * @param BlockRow $row 검증 대상 행
     * @param int|null $domainId 요청 도메인 ID (null이면 검증 생략)
     * @return Result|null 검증 실패 시 Result, 통과 시 null
     */
    private function verifyDomain(BlockRow $row, ?int $domainId): ?Result
    {
        if ($domainId !== null && $row->getDomainId() !== $domainId) {
            return Result::failure('해당 행에 대한 권한이 없습니다.');
        }
        return null;
    }

    /**
     * 도메인별 행 목록 조회
     *
     * @param int $domainId 도메인 ID
     * @return BlockRow[]
     */
    public function getRows(int $domainId): array
    {
        return $this->repository->findByDomain($domainId);
    }

    /**
     * 위치별 행 목록 조회 (관리자용)
     *
     * @param int $domainId 도메인 ID
     * @param string|null $position 위치 필터
     * @return BlockRow[]
     */
    public function getRowsByPosition(int $domainId, ?string $position = null): array
    {
        return $this->repository->findAllByPosition($domainId, $position);
    }

    /**
     * 페이지별 행 목록 조회 (관리자용)
     *
     * @param int $pageId 페이지 ID
     * @return BlockRow[]
     */
    public function getRowsByPage(int $pageId): array
    {
        return $this->repository->findAllByPage($pageId);
    }

    /**
     * 위치별 활성 행 목록 조회 (프론트용)
     *
     * @param int $domainId 도메인 ID
     * @param string $position 위치
     * @param string|null $menuCode 메뉴 코드
     * @return BlockRow[]
     */
    public function getActiveRowsByPosition(int $domainId, string $position, ?string $menuCode = null): array
    {
        return $this->repository->findByPosition($domainId, $position, $menuCode);
    }

    /**
     * 페이지별 활성 행 목록 조회 (프론트용)
     *
     * @param int $pageId 페이지 ID
     * @return BlockRow[]
     */
    public function getActiveRowsByPage(int $pageId): array
    {
        return $this->repository->findByPage($pageId);
    }

    /**
     * 단일 행 조회
     */
    public function getRow(int $rowId): ?BlockRow
    {
        return $this->repository->find($rowId);
    }

    /**
     * 행 생성 (칸 포함)
     *
     * @param int $domainId 도메인 ID
     * @param array $data 행 데이터
     * @param array $columnsData 칸 데이터 배열
     * @return Result
     */
    public function createRow(int $domainId, array $data, array $columnsData = []): Result
    {
        // 위치 또는 페이지 중 정확히 하나 필수 (상호배타)
        $hasPosition = !empty($data['position']);
        $hasPage = !empty($data['page_id']);

        if (!$hasPosition && !$hasPage) {
            return Result::failure('출력 위치 또는 페이지를 선택해주세요.');
        }
        if ($hasPosition && $hasPage) {
            return Result::failure('출력 위치와 페이지를 동시에 지정할 수 없습니다.');
        }

        // 위치 유효성 검사
        if ($hasPosition && !in_array($data['position'], self::VALID_POSITIONS, true)) {
            return Result::failure('유효하지 않은 위치입니다.');
        }

        // 페이지 존재 확인
        if ($hasPage) {
            $page = $this->pageRepository->find((int) $data['page_id']);
            if (!$page) {
                return Result::failure('선택한 페이지가 존재하지 않습니다.');
            }
        }

        $this->db->beginTransaction();

        try {
            // 데이터 정규화
            $insertData = $this->normalizeData($data);
            $insertData['domain_id'] = $domainId;

            // 다음 정렬 순서
            if (!empty($data['page_id'])) {
                $insertData['sort_order'] = $this->repository->getNextSortOrderByPage((int) $data['page_id']);
            } else {
                $insertData['sort_order'] = $this->repository->getNextSortOrderByPosition($domainId, $data['position']);
            }

            // 행 생성
            $rowId = $this->repository->create($insertData);

            if (!$rowId) {
                throw new \Exception('행 생성 실패');
            }

            // 칸 생성
            if (!empty($columnsData)) {
                $this->columnRepository->replaceByRow($rowId, $domainId, $columnsData);
            }

            $this->db->commit();

            // 캐시 무효화
            $newRow = $this->repository->find($rowId);
            if ($newRow) {
                $this->invalidateCache($newRow);
            }

            return Result::success('행이 생성되었습니다.', ['row_id' => $rowId]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            return Result::failure('행 생성에 실패했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 행 수정 (칸 동기화 포함)
     *
     * @param int $rowId 행 ID
     * @param array $data 수정 데이터
     * @param array $columnsData 칸 데이터 배열
     * @return Result
     */
    public function updateRow(int $rowId, array $data, array $columnsData = [], ?int $domainId = null): Result
    {
        $row = $this->repository->find($rowId);

        if (!$row) {
            return Result::failure('행을 찾을 수 없습니다.');
        }

        // 도메인 소유권 검증
        $deny = $this->verifyDomain($row, $domainId);
        if ($deny) {
            return $deny;
        }

        // 위치 유효성 검사
        if (!empty($data['position']) && !in_array($data['position'], self::VALID_POSITIONS, true)) {
            return Result::failure('유효하지 않은 위치입니다.');
        }

        // 페이지 변경 시 존재 확인
        if (!empty($data['page_id']) && (int) $data['page_id'] !== $row->getPageId()) {
            $page = $this->pageRepository->find((int) $data['page_id']);
            if (!$page) {
                return Result::failure('선택한 페이지가 존재하지 않습니다.');
            }
        }

        // 수정 전 위치 정보 보존 (position 변경 시 이전 캐시 무효화용)
        $oldRow = clone $row;

        $this->db->beginTransaction();

        try {
            // 데이터 정규화
            $updateData = $this->normalizeData($data);

            // domain_id, sort_order는 수정 불가
            unset($updateData['domain_id'], $updateData['sort_order']);

            // 행 수정
            $this->repository->update($rowId, $updateData);

            // 칼 동기화 (배열이 전달된 경우에만)
            if (!empty($columnsData) || array_key_exists('columns', $data)) {
                $this->columnRepository->replaceByRow($rowId, $row->getDomainId(), $columnsData);
            }

            $this->db->commit();

            // 캐시 무효화: 이전 위치 (position/menu 변경 대비)
            $this->invalidateCache($oldRow);

            // 캐시 무효화: 변경 후 위치
            $updatedRow = $this->repository->find($rowId);
            if ($updatedRow) {
                $this->invalidateCache($updatedRow);
            }

            return Result::success('행이 수정되었습니다.');
        } catch (\Exception $e) {
            $this->db->rollBack();
            return Result::failure('행 수정에 실패했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 행 삭제 (연결된 칸도 함께 삭제)
     *
     * @param int $rowId 행 ID
     * @return Result
     */
    public function deleteRow(int $rowId, ?int $domainId = null): Result
    {
        $row = $this->repository->find($rowId);

        if (!$row) {
            return Result::failure('행을 찾을 수 없습니다.');
        }

        // 도메인 소유권 검증
        $deny = $this->verifyDomain($row, $domainId);
        if ($deny) {
            return $deny;
        }

        // 삭제 전에 캐시 무효화를 위한 정보 저장
        $rowForCache = clone $row;

        $this->db->beginTransaction();

        try {
            // 연결된 칸 삭제
            $this->columnRepository->deleteByRow($rowId);

            // 행 삭제
            $this->repository->delete($rowId);

            $this->db->commit();

            // 캐시 무효화
            $this->invalidateCache($rowForCache);

            return Result::success('행이 삭제되었습니다.');
        } catch (\Exception $e) {
            $this->db->rollBack();
            return Result::failure('행 삭제에 실패했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 정렬 순서 업데이트
     *
     * @param int[] $rowIds 정렬된 행 ID 배열
     * @return Result
     */
    public function updateOrder(array $rowIds, ?int $domainId = null): Result
    {
        if (empty($rowIds)) {
            return Result::failure('정렬할 행 목록이 비어있습니다.');
        }

        // 도메인 소유권 배치 검증
        if ($domainId !== null) {
            if (!$this->repository->verifyAllBelongToDomain($rowIds, $domainId)) {
                return Result::failure('권한이 없는 행이 포함되어 있습니다.');
            }
        }

        $result = $this->repository->updateOrder($rowIds);

        if ($result) {
            // 캐시 무효화: 첫 번째 행 기준으로 위치/페이지 목록 캐시 무효화
            $firstRow = $this->repository->find((int) $rowIds[0]);
            if ($firstRow) {
                $this->invalidateCache($firstRow);
            }
            return Result::success('정렬 순서가 변경되었습니다.');
        }

        return Result::failure('정렬 순서 변경에 실패했습니다.');
    }

    /**
     * 정렬 순서 직접 설정
     *
     * @param array $orders [row_id => sort_order, ...]
     * @return Result
     */
    public function setOrder(array $orders, ?int $domainId = null): Result
    {
        if (empty($orders)) {
            return Result::failure('순서 정보가 비어있습니다.');
        }

        // 도메인 소유권 배치 검증
        if ($domainId !== null) {
            $rowIds = array_keys($orders);
            if (!$this->repository->verifyAllBelongToDomain($rowIds, $domainId)) {
                return Result::failure('권한이 없는 행이 포함되어 있습니다.');
            }
        }

        $updated = 0;
        $rowsToInvalidate = [];

        foreach ($orders as $rowId => $sortOrder) {
            $rowId = (int) $rowId;
            $sortOrder = (int) $sortOrder;

            $row = $this->repository->find($rowId);
            if ($row) {
                $this->repository->update($rowId, ['sort_order' => $sortOrder]);
                $rowsToInvalidate[] = $row;
                $updated++;
            }
        }

        // 캐시 무효화
        foreach ($rowsToInvalidate as $row) {
            $this->invalidateCache($row);
        }

        if ($updated > 0) {
            return Result::success("{$updated}개 항목의 순서가 변경되었습니다.");
        }

        return Result::failure('변경된 항목이 없습니다.');
    }

    /**
     * 행 복사 (칸 포함)
     *
     * @param int $rowId 원본 행 ID
     * @param string|null $targetPosition 대상 위치 (null이면 page_id 필수)
     * @param int|null $targetPageId 대상 페이지 ID (null이면 position 필수)
     * @param int|null $targetDomainId 대상 도메인 ID (null이면 같은 도메인)
     * @return Result
     */
    public function copyRow(
        int $rowId,
        ?string $targetPosition = null,
        ?int $targetPageId = null,
        ?int $targetDomainId = null
    ): Result {
        // 원본 행 조회
        $sourceRow = $this->repository->find($rowId);
        if (!$sourceRow) {
            return Result::failure('복사할 행을 찾을 수 없습니다.');
        }

        // 대상 위치/페이지 결정
        $position = $targetPosition ?? $sourceRow->getPosition();
        $pageId = $targetPageId ?? $sourceRow->getPageId();
        $domainId = $targetDomainId ?? $sourceRow->getDomainId();

        // 교차 도메인 복사 방지: 원본과 대상 도메인이 다르면 거부
        if ($domainId !== $sourceRow->getDomainId()) {
            return Result::failure('다른 도메인으로의 복사는 허용되지 않습니다.');
        }

        // 대상 페이지 도메인 교차 검증
        if ($pageId) {
            $page = $this->pageRepository->find((int) $pageId);
            if ($page && $page->getDomainId() !== $domainId) {
                return Result::failure('대상 페이지가 다른 도메인에 속해 있습니다.');
            }
        }

        // 위치 또는 페이지 중 하나 필수
        if (empty($position) && empty($pageId)) {
            return Result::failure('대상 위치 또는 페이지를 지정해주세요.');
        }

        // 위치 유효성 검사
        if (!empty($position) && !in_array($position, self::VALID_POSITIONS, true)) {
            return Result::failure('유효하지 않은 위치입니다.');
        }

        $this->db->beginTransaction();

        try {
            // 행 데이터 복사
            $newRowData = [
                'domain_id' => $domainId,
                'page_id' => $pageId ?: null,
                'position' => $pageId ? null : $position,
                'position_menu' => $sourceRow->getPositionMenu(),
                'section_id' => $sourceRow->getSectionId(),
                'admin_title' => $sourceRow->getAdminTitle() ? $sourceRow->getAdminTitle() . ' (복사)' : null,
                'width_type' => $sourceRow->getWidthType(),
                'column_count' => $sourceRow->getColumnCount(),
                'column_margin' => $sourceRow->getColumnMargin(),
                'column_width_unit' => $sourceRow->getColumnWidthUnit(),
                'pc_height' => $sourceRow->getPcHeight(),
                'mobile_height' => $sourceRow->getMobileHeight(),
                'pc_padding' => $sourceRow->getPcPadding(),
                'mobile_padding' => $sourceRow->getMobilePadding(),
                'background_config' => $sourceRow->getBackgroundConfig()
                    ? json_encode($sourceRow->getBackgroundConfig(), JSON_UNESCAPED_UNICODE)
                    : null,
                'is_active' => $sourceRow->isActive() ? 1 : 0,
            ];

            // 다음 정렬 순서
            if ($pageId) {
                $newRowData['sort_order'] = $this->repository->getNextSortOrderByPage($pageId);
            } else {
                $newRowData['sort_order'] = $this->repository->getNextSortOrderByPosition($domainId, $position);
            }

            // 새 행 생성
            $newRowId = $this->repository->create($newRowData);
            if (!$newRowId) {
                throw new \Exception('행 복사 실패');
            }

            // 칸 복사
            $sourceColumns = $this->columnRepository->findAllByRow($rowId);
            $columnsData = [];

            foreach ($sourceColumns as $column) {
                $columnsData[] = [
                    'column_index' => $column->getColumnIndex(),
                    'width' => $column->getWidth(),
                    'pc_padding' => $column->getPcPadding(),
                    'mobile_padding' => $column->getMobilePadding(),
                    'background_config' => $column->getBackgroundConfig(),
                    'border_config' => $column->getBorderConfig(),
                    'title_config' => $column->getTitleConfig(),
                    'content_type' => $column->getContentTypeString(),
                    'content_kind' => $column->getContentKind()->value,
                    'content_skin' => $column->getContentSkin(),
                    'content_config' => $column->getContentConfig(),
                    'content_items' => $column->getContentItems(),
                    'is_active' => $column->isActive() ? 1 : 0,
                ];
            }

            if (!empty($columnsData)) {
                $this->columnRepository->replaceByRow($newRowId, $domainId, $columnsData);
            }

            $this->db->commit();

            // 캐시 무효화
            $newRow = $this->repository->find($newRowId);
            if ($newRow) {
                $this->invalidateCache($newRow);
            }

            return Result::success('행이 복사되었습니다.', ['row_id' => $newRowId]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            return Result::failure('행 복사에 실패했습니다: ' . $e->getMessage());
        }
    }

    /**
     * 행 이동
     *
     * @param int $rowId 행 ID
     * @param string|null $targetPosition 대상 위치 (null이면 page_id 필수)
     * @param int|null $targetPageId 대상 페이지 ID (null이면 position 필수)
     * @return Result
     */
    public function moveRow(int $rowId, ?string $targetPosition = null, ?int $targetPageId = null, ?int $domainId = null): Result
    {
        $row = $this->repository->find($rowId);
        if (!$row) {
            return Result::failure('이동할 행을 찾을 수 없습니다.');
        }

        // 도메인 소유권 검증
        $deny = $this->verifyDomain($row, $domainId);
        if ($deny) {
            return $deny;
        }

        // 위치 또는 페이지 중 하나 필수
        if (empty($targetPosition) && empty($targetPageId)) {
            return Result::failure('대상 위치 또는 페이지를 지정해주세요.');
        }

        // 위치 유효성 검사
        if (!empty($targetPosition) && !in_array($targetPosition, self::VALID_POSITIONS, true)) {
            return Result::failure('유효하지 않은 위치입니다.');
        }

        // 페이지 존재 확인 + 도메인 교차 검증
        if ($targetPageId) {
            $page = $this->pageRepository->find($targetPageId);
            if (!$page) {
                return Result::failure('대상 페이지가 존재하지 않습니다.');
            }
            if ($domainId !== null && $page->getDomainId() !== $domainId) {
                return Result::failure('대상 페이지가 다른 도메인에 속해 있습니다.');
            }
        }

        $this->db->beginTransaction();

        try {
            $updateData = [];

            if ($targetPageId) {
                // 페이지 기반으로 이동
                $updateData['page_id'] = $targetPageId;
                $updateData['position'] = null;
                $updateData['sort_order'] = $this->repository->getNextSortOrderByPage($targetPageId);
            } else {
                // 위치 기반으로 이동
                $updateData['page_id'] = null;
                $updateData['position'] = $targetPosition;
                $updateData['sort_order'] = $this->repository->getNextSortOrderByPosition(
                    $row->getDomainId(),
                    $targetPosition
                );
            }

            // 이동 전 위치 캐시 무효화를 위한 정보 저장
            $oldRow = clone $row;

            $this->repository->update($rowId, $updateData);
            $this->db->commit();

            // 이전 위치 캐시 무효화
            $this->invalidateCache($oldRow);

            // 새 위치 캐시 무효화
            $updatedRow = $this->repository->find($rowId);
            if ($updatedRow) {
                $this->invalidateCache($updatedRow);
            }

            return Result::success('행이 이동되었습니다.');
        } catch (\Exception $e) {
            $this->db->rollBack();
            return Result::failure('행 이동에 실패했습니다: ' . $e->getMessage());
        }
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
        return $this->repository->paginateByPosition($domainId, $position, $page, $perPage);
    }

    /**
     * 유효한 위치 목록 반환
     */
    public function getValidPositions(): array
    {
        return BlockPosition::options();
    }

    /**
     * 데이터 정규화
     *
     * FormHelper::normalizeFormData() 활용 + 도메인 특화 후처리
     */
    private function normalizeData(array $data): array
    {
        // FormHelper 스키마 정의
        $schema = [
            'numeric' => ['page_id', 'width_type', 'column_count', 'column_margin', 'column_width_unit', 'sort_order'],
            'bool' => ['is_active'],
        ];

        $normalized = FormHelper::normalizeFormData($data, $schema);

        // 도메인 특화 후처리

        // 정수 필드 중 0 허용 필드 처리
        $zeroAllowedFields = ['width_type', 'column_margin', 'sort_order'];
        $positiveOnlyFields = ['page_id', 'column_count', 'column_width_unit'];

        foreach ($positiveOnlyFields as $field) {
            if (isset($normalized[$field]) && $normalized[$field] <= 0) {
                $normalized[$field] = null;
            }
        }

        // JSON 필드: background_config (배열 → JSON 문자열 변환)
        if (isset($data['background_config'])) {
            if (is_array($data['background_config'])) {
                $normalized['background_config'] = json_encode($data['background_config'], JSON_UNESCAPED_UNICODE);
            } elseif (is_string($data['background_config']) && !empty($data['background_config'])) {
                $normalized['background_config'] = $data['background_config'];
            } else {
                $normalized['background_config'] = null;
            }
        }

        // NOT NULL 필드 기본값 보장
        $defaults = [
            'column_margin' => 0,
            'column_count' => 1,
            'column_width_unit' => 0,
            'width_type' => 0,
            'is_active' => 1,
        ];
        foreach ($defaults as $field => $default) {
            if (!isset($normalized[$field]) || $normalized[$field] === null) {
                $normalized[$field] = $default;
            }
        }

        // 칸 수 제한 (1~4)
        if (isset($normalized['column_count'])) {
            $normalized['column_count'] = max(1, min(4, $normalized['column_count']));
        }

        // section_id 자동 생성 (빈 값이면)
        if (empty($normalized['section_id'])) {
            $normalized['section_id'] = 'section-' . bin2hex(random_bytes(4));
        }

        // 컨테이너 내부 위치는 width_type을 최대넓이(1)로 강제
        $wideAllowedPositions = [BlockPosition::INDEX->value, BlockPosition::SUBHEAD->value, BlockPosition::SUBFOOT->value];
        $position = $normalized['position'] ?? '';
        if ($position !== '' && !in_array($position, $wideAllowedPositions, true)) {
            $normalized['width_type'] = 1;
        }

        return $normalized;
    }
}
