<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Packages\Shop\Repository\MemberAddressRepository;

/**
 * MemberAddress Service
 *
 * 회원 배송지 주소록 비즈니스 로직
 *
 * 규칙:
 * - 최대 10개/회원
 * - 첫 번째 주소 → 자동 기본 배송지
 * - 기본 배송지 삭제 시 → 최근 주소로 이관
 */
class MemberAddressService
{
    private const MAX_ADDRESSES = 10;

    private MemberAddressRepository $repository;

    public function __construct(MemberAddressRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * 배송지 목록 조회
     */
    public function getList(int $memberId, int $domainId): Result
    {
        $entities = $this->repository->findByMember($memberId, $domainId);
        $addresses = array_map(fn($e) => $e->toArray(), $entities);

        return Result::success('', ['addresses' => $addresses]);
    }

    /**
     * 배송지 추가
     */
    public function create(int $memberId, int $domainId, array $data): Result
    {
        $count = $this->repository->countByMember($memberId, $domainId);

        if ($count >= self::MAX_ADDRESSES) {
            return Result::failure('배송지는 최대 ' . self::MAX_ADDRESSES . '개까지 저장할 수 있습니다.');
        }

        $recipientName = trim($data['recipient_name'] ?? '');
        $recipientPhone = trim($data['recipient_phone'] ?? '');
        $zipCode = trim($data['zip_code'] ?? '');
        $address1 = trim($data['address1'] ?? '');

        if ($recipientName === '' || $zipCode === '' || $address1 === '') {
            return Result::failure('수령인, 우편번호, 주소는 필수 입력입니다.');
        }

        // 첫 번째 주소면 자동 기본 배송지
        $isDefault = ($count === 0) ? 1 : (int) ($data['is_default'] ?? 0);

        $addressId = $this->repository->create([
            'member_id' => $memberId,
            'domain_id' => $domainId,
            'address_name' => trim($data['address_name'] ?? ''),
            'recipient_name' => $recipientName,
            'recipient_phone' => $recipientPhone,
            'zip_code' => $zipCode,
            'address1' => $address1,
            'address2' => trim($data['address2'] ?? ''),
            'is_default' => $isDefault,
        ]);

        // 기본 배송지 설정 시 기존 해제
        if ($isDefault && $addressId > 0) {
            $this->repository->setDefault($memberId, $domainId, $addressId);
        }

        return Result::success('배송지가 저장되었습니다.', ['address_id' => $addressId]);
    }

    /**
     * 배송지 수정
     */
    public function update(int $memberId, int $addressId, array $data): Result
    {
        $existing = $this->repository->find($addressId);

        if (!$existing || $existing->getMemberId() !== $memberId) {
            return Result::failure('배송지를 찾을 수 없습니다.');
        }

        $recipientName = trim($data['recipient_name'] ?? '');
        $zipCode = trim($data['zip_code'] ?? '');
        $address1 = trim($data['address1'] ?? '');

        if ($recipientName === '' || $zipCode === '' || $address1 === '') {
            return Result::failure('수령인, 우편번호, 주소는 필수 입력입니다.');
        }

        $updateData = [
            'address_name' => trim($data['address_name'] ?? ''),
            'recipient_name' => $recipientName,
            'recipient_phone' => trim($data['recipient_phone'] ?? ''),
            'zip_code' => $zipCode,
            'address1' => $address1,
            'address2' => trim($data['address2'] ?? ''),
        ];

        $this->repository->updateAddress($addressId, $updateData);

        return Result::success('배송지가 수정되었습니다.');
    }

    /**
     * 배송지 삭제
     */
    public function delete(int $memberId, int $domainId, int $addressId): Result
    {
        $existing = $this->repository->find($addressId);

        if (!$existing || $existing->getMemberId() !== $memberId) {
            return Result::failure('배송지를 찾을 수 없습니다.');
        }

        $wasDefault = $existing->isDefault();

        $this->repository->delete($addressId);

        // 기본 배송지 삭제 시 → 최근 주소로 이관
        if ($wasDefault) {
            $remaining = $this->repository->findByMember($memberId, $domainId);
            if (!empty($remaining)) {
                $this->repository->setDefault($memberId, $domainId, $remaining[0]->getAddressId());
            }
        }

        return Result::success('배송지가 삭제되었습니다.');
    }

    /**
     * 기본 배송지 설정
     */
    public function setDefault(int $memberId, int $domainId, int $addressId): Result
    {
        $existing = $this->repository->find($addressId);

        if (!$existing || $existing->getMemberId() !== $memberId) {
            return Result::failure('배송지를 찾을 수 없습니다.');
        }

        $this->repository->setDefault($memberId, $domainId, $addressId);

        return Result::success('기본 배송지가 변경되었습니다.');
    }
}
