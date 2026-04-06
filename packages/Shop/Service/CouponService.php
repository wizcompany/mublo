<?php

namespace Mublo\Packages\Shop\Service;

use Mublo\Core\Result\Result;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Event\EventInterface;
use Mublo\Packages\Shop\Repository\CouponRepository;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Entity\Coupon;

/**
 * CouponService
 *
 * 쿠폰 비즈니스 로직 + 이벤트 발행
 *
 * 책임:
 * - 쿠폰 정책(그룹) CRUD
 * - 쿠폰 발행/사용 관리
 * - 할인 금액 계산
 * - 발행 제한 검증
 *
 * 금지:
 * - Request/Response 직접 처리 (Controller 담당)
 * - DB 직접 접근 (Repository 담당)
 */
class CouponService
{
    private CouponRepository $couponRepository;
    private ?OrderRepository $orderRepository;
    private ?EventDispatcher $eventDispatcher;

    public function __construct(
        CouponRepository $couponRepository,
        ?OrderRepository $orderRepository = null,
        ?EventDispatcher $eventDispatcher = null
    ) {
        $this->couponRepository = $couponRepository;
        $this->orderRepository = $orderRepository;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * 이벤트 발행 헬퍼
     */
    private function dispatch(EventInterface $event): EventInterface
    {
        return $this->eventDispatcher?->dispatch($event) ?? $event;
    }

    /**
     * 쿠폰 정책 목록 조회 (관리자용)
     *
     * @param int $domainId 도메인 ID
     * @param int $page 페이지 번호
     * @param int $perPage 페이지당 개수
     * @return Result 성공 시 items, pagination 포함
     */
    public function getList(int $domainId, int $page, int $perPage = 20): Result
    {
        $result = $this->couponRepository->getList($domainId, $page, $perPage);

        return Result::success('', [
            'items' => array_map(fn($policy) => $policy->toArray(), $result['items']),
            'pagination' => $result['pagination'],
        ]);
    }

    /**
     * 쿠폰 정책 상세 조회
     *
     * @param int $couponGroupId 쿠폰 그룹 ID
     * @return Result 성공 시 coupon 포함
     */
    public function getDetail(int $couponGroupId): Result
    {
        $policy = $this->couponRepository->find($couponGroupId);
        if (!$policy) {
            return Result::failure('쿠폰 정책을 찾을 수 없습니다.');
        }

        return Result::success('', ['coupon' => $policy->toArray()]);
    }

    /**
     * 쿠폰 정책 생성
     *
     * @param int $domainId 도메인 ID
     * @param array $data 쿠폰 정책 데이터
     * @return Result 성공 시 coupon_group_id 포함
     */
    public function create(int $domainId, array $data): Result
    {
        // 필수 필드 검증
        if (empty($data['name'])) {
            return Result::failure('쿠폰 이름을 입력해주세요.');
        }

        $discountValue = (int) ($data['discount_value'] ?? 0);
        if ($discountValue <= 0) {
            return Result::failure('할인 금액(률)을 입력해주세요.');
        }

        // 발행 기간 검증
        if (!empty($data['issue_start']) && !empty($data['issue_end'])) {
            if (strtotime($data['issue_start']) > strtotime($data['issue_end'])) {
                return Result::failure('발행 시작일이 종료일보다 늦을 수 없습니다.');
            }
        }

        // 데이터 정규화
        $insertData = $this->normalizeData($data);
        $insertData['domain_id'] = $domainId;

        $couponGroupId = $this->couponRepository->create($insertData);
        if (!$couponGroupId) {
            return Result::failure('쿠폰 정책 생성에 실패했습니다.');
        }

        return Result::success('쿠폰 정책이 생성되었습니다.', ['coupon_group_id' => $couponGroupId]);
    }

    /**
     * 쿠폰 정책 수정
     *
     * @param int $couponGroupId 쿠폰 그룹 ID
     * @param array $data 수정할 데이터
     * @return Result
     */
    public function update(int $couponGroupId, array $data): Result
    {
        $policy = $this->couponRepository->find($couponGroupId);
        if (!$policy) {
            return Result::failure('쿠폰 정책을 찾을 수 없습니다.');
        }

        // 발행 기간 검증
        if (!empty($data['issue_start']) && !empty($data['issue_end'])) {
            if (strtotime($data['issue_start']) > strtotime($data['issue_end'])) {
                return Result::failure('발행 시작일이 종료일보다 늦을 수 없습니다.');
            }
        }

        $updateData = $this->normalizeData($data);

        $affected = $this->couponRepository->update($couponGroupId, $updateData);
        if ($affected === false) {
            return Result::failure('쿠폰 정책 수정에 실패했습니다.');
        }

        return Result::success('쿠폰 정책이 수정되었습니다.');
    }

    /**
     * 쿠폰 정책 삭제
     *
     * @param int $couponGroupId 쿠폰 그룹 ID
     * @return Result
     */
    public function delete(int $couponGroupId): Result
    {
        $policy = $this->couponRepository->find($couponGroupId);
        if (!$policy) {
            return Result::failure('쿠폰 정책을 찾을 수 없습니다.');
        }

        $deleted = $this->couponRepository->delete($couponGroupId);
        if (!$deleted) {
            return Result::failure('쿠폰 정책 삭제에 실패했습니다.');
        }

        return Result::success('쿠폰 정책이 삭제되었습니다.');
    }

    /**
     * 쿠폰 발행
     *
     * 발행 한도, 발행 기간, 회원별 제한 검증 후 쿠폰 발행
     *
     * @param int $couponGroupId 쿠폰 그룹 ID
     * @param int $memberId 회원 ID
     * @return Result 성공 시 coupon_id, coupon_number 포함
     */
    public function issueCoupon(int $couponGroupId, int $memberId): Result
    {
        // 쿠폰 정책 조회
        $policy = $this->couponRepository->find($couponGroupId);
        if (!$policy) {
            return Result::failure('쿠폰 정책을 찾을 수 없습니다.');
        }

        // 활성 상태 확인
        if (!$policy->isActive()) {
            return Result::failure('현재 발행이 중지된 쿠폰입니다.');
        }

        // 발행 기간 확인
        $now = time();
        if ($policy->getIssueStart() && strtotime($policy->getIssueStart()) > $now) {
            return Result::failure('쿠폰 발행 기간이 아닙니다.');
        }
        if ($policy->getIssueEnd() && strtotime($policy->getIssueEnd()) < $now) {
            return Result::failure('쿠폰 발행 기간이 종료되었습니다.');
        }

        // 총 발행량 제한 확인
        $totalLimit = $policy->getTotalIssueLimit();
        if ($totalLimit !== null) {
            $totalIssued = $this->couponRepository->getTotalIssuedCount($couponGroupId);
            if ($totalIssued >= $totalLimit) {
                return Result::failure('쿠폰 발행 수량이 모두 소진되었습니다.');
            }
        }

        // 회원별 발행 횟수 제한 확인
        $issuedCount = $this->couponRepository->getIssuedCount($couponGroupId, $memberId);
        if ($issuedCount >= $policy->getDownloadLimitPerMember()) {
            return Result::failure('쿠폰 발행 한도를 초과했습니다.');
        }

        // 쿠폰 번호 생성
        $couponNumber = $this->generateCouponNumber();

        // 유효기간 계산
        $issuedAt = date('Y-m-d H:i:s');
        $validDays = $policy->getValidDays();
        $validUntil = $validDays
            ? date('Y-m-d 23:59:59', strtotime("+{$validDays} days"))
            : ($policy->getIssueEnd() ?? date('Y-m-d 23:59:59', strtotime('+30 days')));

        // 쿠폰 발행
        $couponId = $this->couponRepository->issueCoupon([
            'coupon_group_id' => $couponGroupId,
            'member_id' => $memberId,
            'coupon_number' => $couponNumber,
            'issued_at' => $issuedAt,
            'valid_until' => $validUntil,
            'is_used' => 0,
            'status' => 'ISSUED',
        ]);

        if (!$couponId) {
            return Result::failure('쿠폰 발행에 실패했습니다.');
        }

        return Result::success('쿠폰이 발행되었습니다.', [
            'coupon_id' => $couponId,
            'coupon_number' => $couponNumber,
        ]);
    }

    /**
     * 쿠폰 사용
     *
     * 사용 가능 여부 검증, 할인 금액 계산 후 사용 처리
     *
     * @param int $couponId 발행된 쿠폰 ID
     * @param string $orderNo 주문번호
     * @param int $orderAmount 주문 금액
     * @param int $memberId 회원 ID (등급/첫주문 검증용)
     * @param int $memberLevel 회원 레벨값 (등급 제한 검증용)
     * @param array $orderItems 주문 상품 목록 [{goods_id, category_code, ...}] (GOODS/CATEGORY 검증용)
     * @param int $shippingFee 배송비 (SHIPPING 쿠폰 시 할인 대상 금액)
     * @return Result 성공 시 discount_amount 포함
     */
    public function useCoupon(int $couponId, string $orderNo, int $orderAmount, int $memberId = 0, int $memberLevel = 0, array $orderItems = [], int $shippingFee = 0): Result
    {
        // 발행 쿠폰 조회 (shop_coupon_issue)
        $couponRow = $this->couponRepository->findIssuedCoupon($couponId);
        if (!$couponRow) {
            return Result::failure('쿠폰을 찾을 수 없습니다.');
        }

        $coupon = Coupon::fromArray($couponRow);

        // 사용 가능 여부 확인
        if ($coupon->isUsed()) {
            return Result::failure('이미 사용된 쿠폰입니다.');
        }

        if ($coupon->isExpired()) {
            return Result::failure('유효기간이 만료된 쿠폰입니다.');
        }

        // 쿠폰 정책 조회
        $policy = $this->couponRepository->find($coupon->getCouponGroupId());
        if (!$policy) {
            return Result::failure('쿠폰 정책 정보를 찾을 수 없습니다.');
        }

        // 최소 주문 금액 확인
        if ($orderAmount < $policy->getMinOrderAmount()) {
            return Result::failure(
                '최소 주문 금액(' . number_format($policy->getMinOrderAmount()) . '원) 이상일 때 사용 가능합니다.'
            );
        }

        // 회원 등급 제한 확인
        $allowedLevels = $policy->getAllowedMemberLevels();
        if (!empty($allowedLevels) && $memberLevel > 0) {
            $levels = array_map('intval', explode(',', $allowedLevels));
            if (!in_array($memberLevel, $levels, true)) {
                return Result::failure('해당 회원 등급에서는 사용할 수 없는 쿠폰입니다.');
            }
        }

        // 첫 주문 전용 확인
        if ($policy->isFirstOrderOnly() && $memberId > 0) {
            $hasOrder = $this->orderRepository?->hasCompletedOrder($memberId);
            if ($hasOrder) {
                return Result::failure('첫 주문에만 사용 가능한 쿠폰입니다.');
            }
        }

        // 1인당 사용 횟수 제한 확인
        if ($memberId > 0) {
            $usedCount = $this->couponRepository->getUsedCount($coupon->getCouponGroupId(), $memberId);
            if ($usedCount >= $policy->getUseLimitPerMember()) {
                return Result::failure('쿠폰 사용 횟수를 초과했습니다.');
            }
        }

        // 중복 사용 정책 확인
        $duplicateCheck = $this->checkDuplicatePolicy($policy, $orderNo);
        if ($duplicateCheck !== null) {
            return $duplicateCheck;
        }

        // 적용 대상(coupon_method) 검증
        $targetCheck = $this->checkCouponTarget($policy, $orderItems);
        if ($targetCheck !== null) {
            return $targetCheck;
        }

        // 할인 금액 계산 (SHIPPING 쿠폰은 배송비 기준)
        $baseAmount = $policy->getCouponMethod() === 'SHIPPING' ? $shippingFee : $orderAmount;
        $discountAmount = $this->calculateDiscount($policy, $baseAmount);

        // 쿠폰 사용 처리
        $used = $this->couponRepository->useCoupon($couponId, $orderNo, $discountAmount);
        if (!$used) {
            return Result::failure('쿠폰 사용 처리에 실패했습니다.');
        }

        return Result::success('쿠폰이 적용되었습니다.', [
            'discount_amount' => $discountAmount,
        ]);
    }

    /**
     * 중복 사용 정책 검증
     *
     * @return Result|null 위반 시 Result::failure, 통과 시 null
     */
    private function checkDuplicatePolicy(object $policy, string $orderNo): ?Result
    {
        $duplicatePolicy = $policy->getDuplicatePolicy();

        if ($duplicatePolicy === 'ALLOW') {
            return null;
        }

        $usedCoupons = $this->couponRepository->getCouponsByOrderNo($orderNo);
        if (empty($usedCoupons)) {
            return null;
        }

        if ($duplicatePolicy === 'DENY_ALL') {
            return Result::failure('다른 쿠폰과 함께 사용할 수 없습니다.');
        }

        // DENY_SAME_METHOD: 같은 적용 대상(coupon_method) 쿠폰이 있으면 거부
        $currentMethod = $policy->getCouponMethod();
        foreach ($usedCoupons as $used) {
            if (($used['coupon_method'] ?? '') === $currentMethod) {
                return Result::failure('동일한 유형의 쿠폰이 이미 적용되어 있습니다.');
            }
        }

        return null;
    }

    /**
     * 적용 대상 검증 (coupon_method 기반)
     *
     * GOODS: target_goods_id가 주문 상품에 포함되어야 함
     * CATEGORY: target_category가 주문 상품 카테고리에 포함되어야 함
     * ORDER/SHIPPING: 대상 제한 없음 (주문 전체 / 배송비)
     * excluded_goods, excluded_categories: 제외 대상이 포함되면 거부
     *
     * @param object $policy CouponPolicy Entity
     * @param array $orderItems [{goods_id, category_code, ...}]
     * @return Result|null 위반 시 Result::failure, 통과 시 null
     */
    private function checkCouponTarget(object $policy, array $orderItems): ?Result
    {
        $method = $policy->getCouponMethod();

        // ORDER, SHIPPING은 상품 대상 제한 없음
        if ($method === 'ORDER' || $method === 'SHIPPING') {
            return null;
        }

        if (empty($orderItems)) {
            return Result::failure('쿠폰 적용 대상 상품 정보가 필요합니다.');
        }

        $goodsIds = array_map(fn($item) => (int) ($item['goods_id'] ?? 0), $orderItems);
        $categoryCodes = array_map(fn($item) => (string) ($item['category_code'] ?? ''), $orderItems);

        // 제외 상품 확인
        $excludedGoods = $policy->getExcludedGoods();
        if (!empty($excludedGoods)) {
            $excludedIds = array_map('intval', explode(',', $excludedGoods));
            $blocked = array_intersect($goodsIds, $excludedIds);
            if (!empty($blocked)) {
                return Result::failure('쿠폰 적용이 제외된 상품이 포함되어 있습니다.');
            }
        }

        // 제외 카테고리 확인
        $excludedCategories = $policy->getExcludedCategories();
        if (!empty($excludedCategories)) {
            $excludedCats = array_map('trim', explode(',', $excludedCategories));
            $blocked = array_intersect($categoryCodes, $excludedCats);
            if (!empty($blocked)) {
                return Result::failure('쿠폰 적용이 제외된 카테고리 상품이 포함되어 있습니다.');
            }
        }

        // GOODS 방식: 특정 상품 대상
        if ($method === 'GOODS') {
            $targetGoodsId = $policy->getTargetGoodsId();
            if ($targetGoodsId !== null && !in_array($targetGoodsId, $goodsIds, true)) {
                return Result::failure('이 쿠폰은 대상 상품에만 사용할 수 있습니다.');
            }
        }

        // CATEGORY 방식: 특정 카테고리 대상
        if ($method === 'CATEGORY') {
            $targetCategory = $policy->getTargetCategory();
            if ($targetCategory !== null && !in_array($targetCategory, $categoryCodes, true)) {
                return Result::failure('이 쿠폰은 대상 카테고리 상품에만 사용할 수 있습니다.');
            }
        }

        return null;
    }

    /**
     * 회원 쿠폰 목록 조회
     *
     * @param int $memberId 회원 ID
     * @return Result 성공 시 coupons 배열 포함
     */
    public function getMemberCoupons(int $memberId): Result
    {
        $coupons = $this->couponRepository->getMemberCoupons($memberId, 'ISSUED');

        return Result::success('', ['coupons' => $coupons]);
    }

    /**
     * 다운로드 가능한 쿠폰 목록 조회 (프론트용)
     *
     * 활성 상태이고, 발행 기간 내이고, DOWNLOAD 유형인 정책 중
     * 해당 회원이 발행 한도를 초과하지 않은 것만 반환
     */
    public function getDownloadableCoupons(int $domainId, int $memberId): Result
    {
        $policies = $this->couponRepository->getDownloadablePolicies($domainId);
        $now = time();

        $available = [];
        foreach ($policies as $policy) {
            // 발행 기간 확인
            if ($policy->getIssueStart() && strtotime($policy->getIssueStart()) > $now) {
                continue;
            }
            if ($policy->getIssueEnd() && strtotime($policy->getIssueEnd()) < $now) {
                continue;
            }

            // 회원별 발행 한도 확인
            $issuedCount = $this->couponRepository->getIssuedCount($policy->getCouponGroupId(), $memberId);
            if ($issuedCount >= $policy->getDownloadLimitPerMember()) {
                continue;
            }

            $item = $policy->toArray();
            $item['issued_count'] = $issuedCount;
            $available[] = $item;
        }

        return Result::success('', ['coupons' => $available]);
    }

    /**
     * 주문에 적용 가능한 회원 쿠폰 목록 (체크아웃용)
     *
     * 미사용/미만료 쿠폰 중 최소 주문금액, 등급, 사용횟수를 충족하는 것만 반환
     * 각 쿠폰에 예상 할인 금액도 포함
     */
    public function getApplicableCoupons(int $memberId, int $orderAmount, int $memberLevel = 0): Result
    {
        $coupons = $this->couponRepository->getMemberCouponsWithPolicy($memberId);

        $applicable = [];
        foreach ($coupons as $row) {
            // 사용됨 또는 만료됨
            if ((int) ($row['is_used'] ?? 0)) {
                continue;
            }
            if (!empty($row['valid_until']) && strtotime($row['valid_until']) < time()) {
                continue;
            }

            // 최소 주문금액
            $minAmount = (int) ($row['min_order_amount'] ?? 0);
            if ($orderAmount < $minAmount) {
                continue;
            }

            // 등급 제한
            $allowedLevels = $row['allowed_member_levels'] ?? '';
            if (!empty($allowedLevels) && $memberLevel > 0) {
                $levels = array_map('intval', explode(',', $allowedLevels));
                if (!in_array($memberLevel, $levels, true)) {
                    continue;
                }
            }

            // 사용 횟수 제한
            $useLimit = (int) ($row['use_limit_per_member'] ?? 1);
            $usedCount = $this->couponRepository->getUsedCount((int) $row['coupon_group_id'], $memberId);
            if ($usedCount >= $useLimit) {
                continue;
            }

            // 예상 할인금액 계산
            $discountType = $row['discount_type'] ?? 'FIXED';
            $discountValue = (int) ($row['discount_value'] ?? 0);
            $maxDiscount = isset($row['max_discount']) ? (int) $row['max_discount'] : null;

            if ($discountType === 'PERCENTAGE') {
                $estimated = (int) floor($orderAmount * ($discountValue / 100));
                if ($maxDiscount !== null && $estimated > $maxDiscount) {
                    $estimated = $maxDiscount;
                }
            } else {
                $estimated = $discountValue;
            }
            $estimated = min($estimated, $orderAmount);

            $row['estimated_discount'] = $estimated;
            $applicable[] = $row;
        }

        // 예상 할인금액 큰 순으로 정렬
        usort($applicable, fn($a, $b) => $b['estimated_discount'] - $a['estimated_discount']);

        return Result::success('', ['coupons' => $applicable]);
    }

    /**
     * 쿠폰 사용 복원 (주문 취소/환불 시)
     *
     * 만료된 쿠폰은 EXPIRED로 설정하고, 유효한 쿠폰만 ISSUED로 복원
     */
    public function restoreCoupon(int $couponId): Result
    {
        $couponRow = $this->couponRepository->findIssuedCoupon($couponId);
        if (!$couponRow) {
            return Result::failure('쿠폰을 찾을 수 없습니다.');
        }

        $coupon = Coupon::fromArray($couponRow);

        if (!$coupon->isUsed()) {
            return Result::failure('사용되지 않은 쿠폰입니다.');
        }

        // 만료된 쿠폰은 복원하지 않고 EXPIRED 처리
        if ($coupon->isExpired()) {
            $this->couponRepository->expireCoupon($couponId);
            return Result::success('만료된 쿠폰으로 EXPIRED 처리되었습니다.', ['restored' => false]);
        }

        $restored = $this->couponRepository->restoreCoupon($couponId);
        if (!$restored) {
            return Result::failure('쿠폰 복원에 실패했습니다.');
        }

        return Result::success('쿠폰이 복원되었습니다.', [
            'restored' => true,
            'coupon_id' => $couponId,
        ]);
    }

    /**
     * 주문에 사용된 모든 쿠폰 복원
     */
    public function restoreOrderCoupons(string $orderNo): Result
    {
        $usedCoupons = $this->couponRepository->getCouponsByOrderNo($orderNo);
        if (empty($usedCoupons)) {
            return Result::success('복원할 쿠폰이 없습니다.');
        }

        $restoredCount = 0;
        foreach ($usedCoupons as $coupon) {
            $result = $this->restoreCoupon((int) $coupon['coupon_id']);
            if ($result->get('restored', false)) {
                $restoredCount++;
            }
        }

        return Result::success("{$restoredCount}개의 쿠폰이 복원되었습니다.", [
            'restored_count' => $restoredCount,
        ]);
    }

    /**
     * 프로모션 코드로 쿠폰 등록 (발급)
     *
     * 프론트에서 코드 입력 → 정책 매칭 → 쿠폰 발행
     */
    public function registerByPromotionCode(string $code, int $memberId): Result
    {
        $code = trim(strtoupper($code));
        if (empty($code)) {
            return Result::failure('프로모션 코드를 입력해주세요.');
        }

        $policy = $this->couponRepository->findByPromotionCode($code);
        if (!$policy) {
            return Result::failure('유효하지 않은 프로모션 코드입니다.');
        }

        return $this->issueCoupon($policy->getCouponGroupId(), $memberId);
    }

    /**
     * 만료된 쿠폰 일괄 정리
     *
     * 유효기간이 지난 ISSUED 쿠폰을 EXPIRED로 변경
     *
     * @return int 처리된 쿠폰 수
     */
    public function expireOverdueCoupons(): int
    {
        return $this->couponRepository->expireOverdueCoupons();
    }

    /**
     * 할인 금액 계산
     *
     * 정액 할인: discount_value 그대로
     * 정률 할인: orderAmount * (discount_value / 100), max_discount 적용
     *
     * @param object $policy CouponPolicy Entity
     * @param int $orderAmount 주문 금액
     * @return int 할인 금액
     */
    private function calculateDiscount(object $policy, int $orderAmount): int
    {
        $discountType = $policy->getDiscountType();
        $discountValue = $policy->getDiscountValue();
        $maxDiscount = $policy->getMaxDiscount();

        if ($discountType === 'PERCENTAGE') {
            $discount = (int) floor($orderAmount * ($discountValue / 100));

            // 최대 할인 금액 제한
            if ($maxDiscount !== null && $discount > $maxDiscount) {
                $discount = $maxDiscount;
            }
        } else {
            // 정액 할인 (fixed)
            $discount = $discountValue;
        }

        // 주문 금액 초과 방지
        if ($discount > $orderAmount) {
            $discount = $orderAmount;
        }

        return $discount;
    }

    /**
     * 쿠폰 번호 생성
     *
     * 4자리-4자리-4자리-4자리 형식
     *
     * @return string 쿠폰 번호 (예: A3F2-K9B1-M7X4-P2N8)
     */
    private function generateCouponNumber(): string
    {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segments = [];

        for ($i = 0; $i < 4; $i++) {
            $segment = '';
            for ($j = 0; $j < 4; $j++) {
                $segment .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $segments[] = $segment;
        }

        return implode('-', $segments);
    }

    /**
     * 쿠폰 정책 데이터 정규화
     *
     * @param array $data 원본 데이터
     * @return array 정규화된 데이터
     */
    private function normalizeData(array $data): array
    {
        $normalized = [];

        $allowedFields = [
            'name', 'description',
            'coupon_type', 'coupon_method', 'discount_type', 'discount_value',
            'max_discount', 'min_order_amount',
            'issue_start', 'issue_end', 'valid_days',
            'target_goods_id', 'target_category', 'excluded_goods', 'excluded_categories',
            'duplicate_policy', 'use_limit_per_member', 'download_limit_per_member',
            'total_issue_limit', 'allowed_member_levels', 'first_order_only',
            'auto_issue_trigger', 'promotion_code',
            'is_active', 'staff_id',
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                // 정수 필드
                if (in_array($field, [
                    'discount_value', 'max_discount', 'min_order_amount',
                    'valid_days', 'target_goods_id',
                    'use_limit_per_member', 'download_limit_per_member',
                    'total_issue_limit', 'staff_id',
                ], true)) {
                    $value = $value !== null ? (int) $value : null;
                }

                // 불린 필드
                if (in_array($field, ['first_order_only', 'is_active'], true)) {
                    $value = (bool) $value ? 1 : 0;
                }

                // 프로모션 코드 대문자 + 빈 문자열 → null
                if ($field === 'promotion_code') {
                    $value = trim((string) $value);
                    $value = $value !== '' ? strtoupper($value) : null;
                }

                $normalized[$field] = $value;
            }
        }

        return $normalized;
    }
}
