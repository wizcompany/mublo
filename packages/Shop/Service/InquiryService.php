<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Packages\Shop\Repository\InquiryRepository;

class InquiryService
{
    private InquiryRepository $inquiryRepository;

    private const ALLOWED_FIELDS = [
        'goods_id', 'member_id', 'inquiry_type', 'title', 'content',
        'is_secret', 'author_name',
    ];

    public function __construct(InquiryRepository $inquiryRepository)
    {
        $this->inquiryRepository = $inquiryRepository;
    }

    public function getList(int $domainId, array $filters = [], int $page = 1, int $perPage = 20): array
    {
        return $this->inquiryRepository->getList($domainId, $filters, $page, $perPage);
    }

    public function getDetail(int $inquiryId): ?array
    {
        return $this->inquiryRepository->find($inquiryId);
    }

    public function createInquiry(int $domainId, array $data): Result
    {
        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));
        $filtered['domain_id'] = $domainId;
        $filtered['inquiry_status'] = 'WAITING';

        if (empty($filtered['title'])) {
            return Result::failure('제목을 입력해주세요.');
        }

        $id = $this->inquiryRepository->create($filtered);

        return $id
            ? Result::success('문의가 등록되었습니다.', ['inquiry_id' => $id])
            : Result::failure('문의 등록에 실패했습니다.');
    }

    public function updateInquiry(int $inquiryId, array $data): Result
    {
        $filtered = array_intersect_key($data, array_flip(self::ALLOWED_FIELDS));

        $ok = $this->inquiryRepository->update($inquiryId, $filtered);
        return $ok
            ? Result::success('문의가 수정되었습니다.')
            : Result::failure('문의 수정에 실패했습니다.');
    }

    public function answer(int $inquiryId, string $reply, int $staffId): Result
    {
        $inquiry = $this->inquiryRepository->find($inquiryId);
        if (!$inquiry) {
            return Result::failure('문의를 찾을 수 없습니다.');
        }

        $ok = $this->inquiryRepository->update($inquiryId, [
            'reply' => $reply,
            'replied_at' => date('Y-m-d H:i:s'),
            'reply_staff_id' => $staffId,
            'inquiry_status' => 'REPLIED',
        ]);

        return $ok
            ? Result::success('답변이 등록되었습니다.')
            : Result::failure('답변 등록에 실패했습니다.');
    }

    public function deleteInquiry(int $inquiryId): Result
    {
        $ok = $this->inquiryRepository->delete($inquiryId);
        return $ok
            ? Result::success('문의가 삭제되었습니다.')
            : Result::failure('문의 삭제에 실패했습니다.');
    }

    public function batchUpdate(array $items): Result
    {
        if (empty($items)) {
            return Result::failure('수정할 항목이 없습니다.');
        }

        $updated = $this->inquiryRepository->batchUpdateFields($items);

        return Result::success("{$updated}건이 수정되었습니다.", ['updated_count' => $updated]);
    }

    public function batchDelete(array $inquiryIds): Result
    {
        if (empty($inquiryIds)) {
            return Result::failure('삭제할 항목이 없습니다.');
        }

        $deleted = $this->inquiryRepository->deleteByIds($inquiryIds);

        return Result::success("{$deleted}건이 삭제되었습니다.", ['deleted_count' => $deleted]);
    }
}
