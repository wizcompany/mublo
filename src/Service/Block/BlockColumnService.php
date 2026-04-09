<?php
namespace Mublo\Service\Block;

use Mublo\Repository\Block\BlockColumnRepository;
use Mublo\Repository\Block\BlockRowRepository;
use Mublo\Entity\Block\BlockColumn;
use Mublo\Core\Block\BlockRegistry;
use Mublo\Enum\Block\BlockContentKind;
use Mublo\Enum\Block\BlockContentType;
use Mublo\Helper\Security\HtmlSanitizer;
use Mublo\Helper\Form\FormHelper;
use Mublo\Core\Result\Result;

/**
 * BlockColumn Service
 *
 * 블록 칸(Column) 비즈니스 로직 담당
 *
 * 책임:
 * - 칸 CRUD 비즈니스 로직
 * - 콘텐츠 설정 관리
 * - 입력 검증 및 보안 필터링
 *
 * 금지:
 * - Request/Response 직접 처리 (Controller 담당)
 * - DB 직접 접근 (Repository 담당)
 */
class BlockColumnService
{
    /**
     * 유효한 content_kind 목록 (Enum 기반)
     */
    private static function getValidContentKinds(): array
    {
        return array_map(fn($case) => $case->value, BlockContentKind::cases());
    }

    /**
     * 문자열 필드 최대 길이
     */
    private const MAX_STRING_LENGTH = [
        'width' => 50,
        'pc_padding' => 100,
        'mobile_padding' => 100,
        'content_type' => 50,
        'content_kind' => 20,
        'content_skin' => 50,
    ];

    private BlockColumnRepository $repository;
    private BlockRowRepository $rowRepository;
    private ?BlockRenderService $renderService;

    public function __construct(
        BlockColumnRepository $repository,
        BlockRowRepository $rowRepository,
        ?BlockRenderService $renderService = null
    ) {
        $this->repository = $repository;
        $this->rowRepository = $rowRepository;
        $this->renderService = $renderService;
    }

    /**
     * 행별 칸 목록 조회
     *
     * @param int $rowId 행 ID
     * @return BlockColumn[]
     */
    public function getColumnsByRow(int $rowId): array
    {
        return $this->repository->findAllByRow($rowId);
    }

    /**
     * 행별 활성 칸 목록 조회 (프론트용)
     *
     * @param int $rowId 행 ID
     * @return BlockColumn[]
     */
    public function getActiveColumnsByRow(int $rowId): array
    {
        return $this->repository->findByRow($rowId);
    }

    /**
     * 단일 칸 조회
     */
    public function getColumn(int $columnId): ?BlockColumn
    {
        return $this->repository->find($columnId);
    }

    /**
     * 칸 생성
     *
     * @param int $domainId 도메인 ID
     * @param int $rowId 행 ID
     * @param array $data 칸 데이터
     * @return Result
     */
    public function createColumn(int $domainId, int $rowId, array $data): Result
    {
        // 행 존재 확인
        $row = $this->rowRepository->find($rowId);
        if (!$row) {
            return Result::failure('행을 찾을 수 없습니다.');
        }

        // 현재 칸 수 확인
        $currentCount = $this->repository->countByRow($rowId);
        if ($currentCount >= 4) {
            return Result::failure('한 행에 최대 4개의 칸만 생성할 수 있습니다.');
        }

        // 입력 검증
        $validation = $this->validateData($data);
        if (!$validation['valid']) {
            return Result::failure(implode(', ', $validation['errors']));
        }

        // HTML 콘텐츠 정화
        $data = $this->sanitizeHtmlContent($data);

        // 데이터 정규화
        $insertData = $this->normalizeData($data);
        $insertData['domain_id'] = $domainId;
        $insertData['row_id'] = $rowId;
        $insertData['column_index'] = $currentCount;

        // 생성
        $columnId = $this->repository->create($insertData);

        if ($columnId) {
            return Result::success('칸이 생성되었습니다.', ['column_id' => $columnId]);
        }

        return Result::failure('칸 생성에 실패했습니다.');
    }

    /**
     * 칸 수정
     *
     * @param int $columnId 칸 ID
     * @param array $data 수정 데이터
     * @return Result
     */
    public function updateColumn(int $columnId, array $data): Result
    {
        $column = $this->repository->find($columnId);

        if (!$column) {
            return Result::failure('칸을 찾을 수 없습니다.');
        }

        // 입력 검증
        $validation = $this->validateData($data);
        if (!$validation['valid']) {
            return Result::failure(implode(', ', $validation['errors']));
        }

        // HTML 콘텐츠 정화
        $data = $this->sanitizeHtmlContent($data);

        // 데이터 정규화
        $updateData = $this->normalizeData($data);

        // row_id, domain_id, column_index는 수정 불가
        unset($updateData['row_id'], $updateData['domain_id'], $updateData['column_index']);

        // 수정
        $affected = $this->repository->update($columnId, $updateData);

        if ($affected >= 0) {
            $this->invalidateRowCache($column->getRowId());
            return Result::success('칸이 수정되었습니다.');
        }

        return Result::failure('칸 수정에 실패했습니다.');
    }

    /**
     * 칸 삭제
     *
     * @param int $columnId 칸 ID
     * @return Result
     */
    public function deleteColumn(int $columnId): Result
    {
        $column = $this->repository->find($columnId);

        if (!$column) {
            return Result::failure('칸을 찾을 수 없습니다.');
        }

        $rowId = $column->getRowId();

        // 트랜잭션으로 삭제 + 재정렬 원자적 처리
        try {
            $this->repository->getDb()->transaction(function () use ($columnId, $rowId) {
                // 삭제
                $this->repository->delete($columnId);
                // 남은 칸들의 인덱스 재정렬
                $this->reindexColumns($rowId);
            });

            $this->invalidateRowCache($rowId);

            return Result::success('칸이 삭제되었습니다.');
        } catch (\Throwable $e) {
            return Result::failure('칸 삭제에 실패했습니다.');
        }
    }

    /**
     * 콘텐츠 설정 업데이트
     *
     * count/style은 content_config에 통합됨 (별도 파라미터 아님)
     *
     * @param int $columnId 칸 ID
     * @param string|null $type 콘텐츠 타입
     * @param string $kind 콘텐츠 종류 (CORE, PLUGIN, PACKAGE)
     * @param string|null $skin 스킨
     * @param array|null $config 추가 설정 (pc_count, mo_count, pc_style 등 포함)
     * @param array|null $items 선택된 아이템
     * @return array ['success' => bool, 'message' => string]
     */
    public function updateContent(
        int $columnId,
        ?string $type,
        string $kind = 'CORE',
        ?string $skin = null,
        ?array $config = null,
        ?array $items = null
    ): array {
        $column = $this->repository->find($columnId);

        if (!$column) {
            return [
                'success' => false,
                'message' => '칸을 찾을 수 없습니다.',
            ];
        }

        // 콘텐츠 타입 검증
        if ($type !== null && !BlockRegistry::hasContentType($type)) {
            return [
                'success' => false,
                'message' => "등록되지 않은 콘텐츠 타입입니다: {$type}",
            ];
        }

        // 콘텐츠 종류 검증
        if (!in_array($kind, self::getValidContentKinds(), true)) {
            return [
                'success' => false,
                'message' => "유효하지 않은 콘텐츠 종류입니다: {$kind}",
            ];
        }

        // HTML 콘텐츠 정화
        if ($type === BlockContentType::HTML->value && $config !== null && !empty($config['html'])) {
            $config['html'] = HtmlSanitizer::sanitize($config['html']);
        }

        $result = $this->repository->updateContent(
            $columnId,
            $type,
            $kind,
            $skin,
            $config,
            $items
        );

        if ($result) {
            $this->invalidateRowCache($column->getRowId());
            return [
                'success' => true,
                'message' => '콘텐츠 설정이 저장되었습니다.',
            ];
        }

        return [
            'success' => false,
            'message' => '콘텐츠 설정 저장에 실패했습니다.',
        ];
    }

    /**
     * 제목 설정 업데이트
     *
     * @param int $columnId 칸 ID
     * @param array|null $titleConfig 제목 설정
     * @return Result
     */
    public function updateTitleConfig(int $columnId, ?array $titleConfig): Result
    {
        $column = $this->repository->find($columnId);

        if (!$column) {
            return Result::failure('칸을 찾을 수 없습니다.');
        }

        $result = $this->repository->updateTitleConfig($columnId, $titleConfig);

        if ($result) {
            $this->invalidateRowCache($column->getRowId());
            return Result::success('제목 설정이 저장되었습니다.');
        }

        return Result::failure('제목 설정 저장에 실패했습니다.');
    }

    /**
     * 스타일 설정 업데이트
     *
     * @param int $columnId 칸 ID
     * @param array|null $backgroundConfig 배경 설정
     * @param array|null $borderConfig 테두리 설정
     * @return Result
     */
    public function updateStyleConfig(int $columnId, ?array $backgroundConfig, ?array $borderConfig): Result
    {
        $column = $this->repository->find($columnId);

        if (!$column) {
            return Result::failure('칸을 찾을 수 없습니다.');
        }

        $result = $this->repository->updateStyleConfig($columnId, $backgroundConfig, $borderConfig);

        if ($result) {
            $this->invalidateRowCache($column->getRowId());
            return Result::success('스타일 설정이 저장되었습니다.');
        }

        return Result::failure('스타일 설정 저장에 실패했습니다.');
    }

    /**
     * 행 캐시 무효화
     *
     * 칸 변경 시 해당 행의 렌더링 캐시를 삭제합니다.
     */
    private function invalidateRowCache(int $rowId): void
    {
        $this->renderService?->invalidateRowContentCache($rowId);
    }

    /**
     * 칸 인덱스 재정렬
     *
     * @param int $rowId 행 ID
     */
    private function reindexColumns(int $rowId): void
    {
        $columns = $this->repository->findAllByRow($rowId);

        foreach ($columns as $index => $column) {
            if ($column->getColumnIndex() !== $index) {
                $this->repository->update($column->getColumnId(), ['column_index' => $index]);
            }
        }
    }

    /**
     * 데이터 정규화
     *
     * FormHelper::normalizeFormData() 활용
     *
     * @param array $data 입력 데이터
     * @return array 정규화된 데이터
     */
    private function normalizeData(array $data): array
    {
        // FormHelper 스키마 정의
        // 삭제된 필드: content_count, content_style (content_config로 통합)
        $schema = [
            'numeric' => ['column_index', 'sort_order'],
            'bool' => ['is_active'],
            'json' => ['background_config', 'border_config', 'title_config', 'content_config', 'content_items'],
        ];

        $normalized = FormHelper::normalizeFormData($data, $schema);

        // 문자열 필드 (빈 문자열은 null로 처리)
        $stringFields = ['width', 'pc_padding', 'mobile_padding', 'content_type', 'content_kind', 'content_skin'];
        foreach ($stringFields as $field) {
            if (isset($data[$field])) {
                $value = trim($data[$field]);
                $normalized[$field] = ($value === '') ? null : $value;
            }
        }

        // 도메인 특화 후처리: content_kind 기본값
        if (empty($normalized['content_kind'])) {
            $normalized['content_kind'] = 'CORE';
        }

        return $normalized;
    }

    /**
     * 입력 데이터 검증
     *
     * @param array $data 입력 데이터
     * @return array ['valid' => bool, 'errors' => array]
     */
    private function validateData(array $data): array
    {
        $errors = [];

        // content_type 검증 (등록된 타입만 허용)
        if (!empty($data['content_type'])) {
            if (!BlockRegistry::hasContentType($data['content_type'])) {
                $errors['content_type'] = "등록되지 않은 콘텐츠 타입입니다: {$data['content_type']}";
            }
        }

        // content_kind 검증
        if (!empty($data['content_kind'])) {
            if (!in_array($data['content_kind'], self::getValidContentKinds(), true)) {
                $errors['content_kind'] = "유효하지 않은 콘텐츠 종류입니다: {$data['content_kind']}";
            }
        }

        // 문자열 길이 검증
        foreach (self::MAX_STRING_LENGTH as $field => $maxLength) {
            if (!empty($data[$field]) && mb_strlen($data[$field]) > $maxLength) {
                $errors[$field] = "{$field} 필드는 {$maxLength}자를 초과할 수 없습니다.";
            }
        }

        // CSS 값 형식 검증 (width, padding)
        $cssFields = ['width', 'pc_padding', 'mobile_padding'];
        foreach ($cssFields as $field) {
            if (!empty($data[$field]) && !$this->isValidCssValue($data[$field])) {
                $errors[$field] = "{$field} 필드의 형식이 올바르지 않습니다. (예: 100px, 50%, 10px 20px)";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * CSS 값 형식 검증
     *
     * @param string $value CSS 값
     * @return bool
     */
    private function isValidCssValue(string $value): bool
    {
        $value = trim($value);

        if ($value === '' || $value === 'auto' || $value === 'inherit' || $value === 'initial') {
            return true;
        }

        // 단일 값 또는 공백으로 구분된 여러 값 허용
        // 허용 패턴: 숫자 + 단위(px, %, em, rem, vh, vw)
        $pattern = '/^(\d+(?:\.\d+)?(?:px|%|em|rem|vh|vw)?(?:\s+\d+(?:\.\d+)?(?:px|%|em|rem|vh|vw)?)*|auto|inherit|initial)$/i';

        return preg_match($pattern, $value) === 1;
    }

    /**
     * HTML 콘텐츠 정화
     *
     * content_type이 'html'인 경우 content_config의 html 필드를 정화
     *
     * @param array $data 입력 데이터
     * @return array 정화된 데이터
     */
    private function sanitizeHtmlContent(array $data): array
    {
        if (($data['content_type'] ?? '') !== BlockContentType::HTML->value) {
            return $data;
        }

        // content_config 파싱
        $contentConfig = $data['content_config'] ?? null;

        if (is_string($contentConfig)) {
            $contentConfig = json_decode($contentConfig, true);
        }

        if (!is_array($contentConfig) || empty($contentConfig['html'])) {
            return $data;
        }

        // HTML 정화
        $contentConfig['html'] = HtmlSanitizer::sanitize($contentConfig['html']);

        // 다시 인코딩
        $data['content_config'] = is_string($data['content_config'])
            ? json_encode($contentConfig, JSON_UNESCAPED_UNICODE)
            : $contentConfig;

        return $data;
    }
}
