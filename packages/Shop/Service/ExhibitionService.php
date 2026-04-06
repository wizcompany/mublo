<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Packages\Shop\Repository\ExhibitionRepository;
use Mublo\Packages\Shop\Event\ExhibitionCreatedEvent;
use Mublo\Packages\Shop\Event\ExhibitionDeletedEvent;

/**
 * ExhibitionService
 *
 * 기획전 비즈니스 로직
 *
 * 책임:
 * - 기획전 CRUD
 * - 기획전 상품/카테고리 연결
 * - 기간 및 활성화 상태 검증
 */
class ExhibitionService
{
    private ExhibitionRepository $exhibitionRepository;
    private ?EventDispatcher $eventDispatcher;

    private const ALLOWED_FIELDS = [
        'title', 'description', 'slug',
        'banner_image', 'banner_mobile_image',
        'start_date', 'end_date',
        'is_active', 'sort_order',
    ];

    public function __construct(
        ExhibitionRepository $exhibitionRepository,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->exhibitionRepository = $exhibitionRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }

    public function getList(int $domainId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $result = $this->exhibitionRepository->getList($domainId, $filters, $page, $perPage);
        return [
            'items'      => array_map(fn($e) => $e->toArray(), $result['items']),
            'pagination' => $result['pagination'],
        ];
    }

    public function getDetail(int $exhibitionId): ?array
    {
        $exhibition = $this->exhibitionRepository->find($exhibitionId);
        if (!$exhibition) {
            return null;
        }
        $data        = $exhibition->toArray();
        $data['items'] = $this->exhibitionRepository->getItems($exhibitionId);
        return $data;
    }

    public function getActiveList(int $domainId): array
    {
        return array_map(
            fn($e) => $e->toArray(),
            $this->exhibitionRepository->getActiveList($domainId)
        );
    }

    public function create(int $domainId, array $data): Result
    {
        if (empty($data['title'])) {
            return Result::failure('기획전 제목을 입력해주세요.');
        }

        // 기간 검증
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
                return Result::failure('종료일이 시작일보다 늦어야 합니다.');
            }
        }

        $insertData = $this->filterData($data);
        $insertData['domain_id'] = $domainId;

        // slug 중복 확인
        if (!empty($insertData['slug'])) {
            if ($this->exhibitionRepository->slugExists($domainId, $insertData['slug'])) {
                return Result::failure('이미 사용 중인 슬러그입니다.');
            }
        }

        $id = $this->exhibitionRepository->create($insertData);
        if (!$id) {
            return Result::failure('기획전 생성에 실패했습니다.');
        }

        $this->dispatch(new ExhibitionCreatedEvent(
            $domainId,
            $id,
            $data['title'],
            $insertData['slug'] ?? null
        ));

        return Result::success('기획전이 등록되었습니다.', ['exhibition_id' => $id]);
    }

    public function update(int $exhibitionId, array $data): Result
    {
        $exhibition = $this->exhibitionRepository->find($exhibitionId);
        if (!$exhibition) {
            return Result::failure('기획전을 찾을 수 없습니다.');
        }

        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            if (strtotime($data['start_date']) >= strtotime($data['end_date'])) {
                return Result::failure('종료일이 시작일보다 늦어야 합니다.');
            }
        }

        $updateData = $this->filterData($data);

        // slug 중복 확인
        if (!empty($updateData['slug'])) {
            if ($this->exhibitionRepository->slugExists($exhibition->getDomainId(), $updateData['slug'], $exhibitionId)) {
                return Result::failure('이미 사용 중인 슬러그입니다.');
            }
        }

        $this->exhibitionRepository->update($exhibitionId, $updateData);
        return Result::success('기획전이 수정되었습니다.');
    }

    public function delete(int $exhibitionId): Result
    {
        $exhibition = $this->exhibitionRepository->find($exhibitionId);
        if (!$exhibition) {
            return Result::failure('기획전을 찾을 수 없습니다.');
        }

        $domainId = $exhibition->getDomainId();
        $this->exhibitionRepository->delete($exhibitionId);

        $this->dispatch(new ExhibitionDeletedEvent($domainId, $exhibitionId));

        return Result::success('기획전이 삭제되었습니다.');
    }

    public function addItem(int $exhibitionId, array $data): Result
    {
        if (!$this->exhibitionRepository->find($exhibitionId)) {
            return Result::failure('기획전을 찾을 수 없습니다.');
        }

        $targetType = $data['target_type'] ?? '';
        if (!in_array($targetType, ['goods', 'category'], true)) {
            return Result::failure('대상 유형이 올바르지 않습니다.');
        }

        $insertData = [
            'exhibition_id' => $exhibitionId,
            'target_type'   => $targetType,
            'goods_id'      => isset($data['goods_id']) ? (int) $data['goods_id'] : null,
            'category_code' => $data['category_code'] ?? null,
            'sort_order'    => (int) ($data['sort_order'] ?? 0),
        ];

        $itemId = $this->exhibitionRepository->addItem($insertData);
        if (!$itemId) {
            return Result::failure('아이템 추가에 실패했습니다.');
        }

        return Result::success('아이템이 추가되었습니다.', ['item_id' => $itemId]);
    }

    public function deleteItem(int $itemId): Result
    {
        $deleted = $this->exhibitionRepository->deleteItem($itemId);
        return $deleted
            ? Result::success('아이템이 삭제되었습니다.')
            : Result::failure('아이템 삭제에 실패했습니다.');
    }

    public function syncItems(int $exhibitionId, array $items): Result
    {
        if (!$this->exhibitionRepository->find($exhibitionId)) {
            return Result::failure('기획전을 찾을 수 없습니다.');
        }

        $this->exhibitionRepository->deleteItemsByExhibition($exhibitionId);

        foreach ($items as $idx => $item) {
            $this->exhibitionRepository->addItem([
                'exhibition_id' => $exhibitionId,
                'target_type'   => $item['target_type'] ?? 'goods',
                'goods_id'      => isset($item['goods_id']) ? (int) $item['goods_id'] : null,
                'category_code' => $item['category_code'] ?? null,
                'sort_order'    => (int) ($item['sort_order'] ?? $idx),
            ]);
        }

        return Result::success('아이템이 동기화되었습니다.');
    }

    private function filterData(array $data): array
    {
        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));

        if (isset($filtered['is_active'])) {
            $filtered['is_active'] = (int) (bool) $filtered['is_active'];
        }
        if (isset($filtered['sort_order'])) {
            $filtered['sort_order'] = (int) $filtered['sort_order'];
        }

        // 빈 문자열 날짜 → null
        foreach (['start_date', 'end_date'] as $field) {
            if (isset($filtered[$field]) && $filtered[$field] === '') {
                $filtered[$field] = null;
            }
        }

        // 빈 slug → null
        if (isset($filtered['slug']) && trim($filtered['slug']) === '') {
            $filtered['slug'] = null;
        }

        return $filtered;
    }
}
