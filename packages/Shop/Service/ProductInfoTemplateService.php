<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Packages\Shop\Repository\ProductInfoTemplateRepository;

class ProductInfoTemplateService
{
    private ProductInfoTemplateRepository $repository;

    private const ALLOWED_FIELDS = [
        'tab_id', 'tab_name', 'subject', 'content', 'status', 'sort_order', 'category_code',
    ];

    public function __construct(ProductInfoTemplateRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getList(int $domainId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->repository->getListPaginated($domainId, $filters, $page, $perPage);
    }

    public function getTemplateById(int $id): ?array
    {
        return $this->repository->find($id);
    }

    public function getActive(int $domainId): array
    {
        return $this->repository->getActive($domainId);
    }

    public function save(int $domainId, array $data, ?int $id = null): Result
    {
        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));
        $filtered['domain_id'] = $domainId;

        if (empty($filtered['subject'])) {
            return Result::failure('제목을 입력해주세요.');
        }

        // category_code: 빈 값이면 NULL (전체 적용)
        if (isset($filtered['category_code']) && $filtered['category_code'] === '') {
            $filtered['category_code'] = null;
        }

        if ($id) {
            $existing = $this->repository->find($id);
            if (!$existing) {
                return Result::failure('템플릿을 찾을 수 없습니다.');
            }
            $this->repository->update($id, $filtered);
            return Result::success('템플릿이 수정되었습니다.');
        }

        // 신규 등록 시 tab_id 자동 생성
        if (empty($filtered['tab_id'])) {
            $filtered['tab_id'] = 'tab_' . bin2hex(random_bytes(6));
        }

        $newId = $this->repository->create($filtered);
        if (!$newId) {
            return Result::failure('템플릿 등록에 실패했습니다.');
        }

        return Result::success('템플릿이 등록되었습니다.', ['template_id' => $newId]);
    }

    public function delete(int $id): Result
    {
        $template = $this->repository->find($id);
        if (!$template) {
            return Result::failure('템플릿을 찾을 수 없습니다.');
        }

        $this->repository->delete($id);
        return Result::success('템플릿이 삭제되었습니다.');
    }
}
