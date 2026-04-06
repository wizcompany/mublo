<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Packages\Shop\Repository\WishlistRepository;

class WishlistService
{
    private WishlistRepository $wishlistRepository;

    public function __construct(WishlistRepository $wishlistRepository)
    {
        $this->wishlistRepository = $wishlistRepository;
    }

    public function toggle(int $memberId, int $goodsId): Result
    {
        $existing = $this->wishlistRepository->find($memberId, $goodsId);

        if ($existing) {
            $this->wishlistRepository->delete($memberId, $goodsId);
            return Result::success('찜이 취소되었습니다.', ['wishlisted' => false]);
        }

        $this->wishlistRepository->create($memberId, $goodsId);
        return Result::success('찜에 추가되었습니다.', ['wishlisted' => true]);
    }

    public function isWishlisted(int $memberId, int $goodsId): bool
    {
        return $this->wishlistRepository->find($memberId, $goodsId) !== null;
    }

    public function getMemberWishlist(int $memberId, int $page = 1, int $perPage = 20): array
    {
        return $this->wishlistRepository->getMemberWishlist($memberId, $page, $perPage);
    }

    public function countByGoodsId(int $goodsId): int
    {
        return $this->wishlistRepository->countByGoodsId($goodsId);
    }

    public function countByGoodsIds(array $goodsIds): array
    {
        return $this->wishlistRepository->countByGoodsIds($goodsIds);
    }

    public function getMemberGoodsIds(int $memberId): array
    {
        return $this->wishlistRepository->getMemberGoodsIds($memberId);
    }
}
