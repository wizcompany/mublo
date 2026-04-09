<?php
/**
 * packages/Shop/tests/Unit/Service/CouponServiceTest.php
 *
 * CouponService 단위 테스트
 *
 * 검증 항목:
 * - create()       — 이름 누락 거부, 할인값 0 거부, 기간 역전 거부, 성공
 * - issueCoupon()  — 비활성 거부, 발행 기간 외 거부, 한도 초과 거부, 성공
 * - useCoupon()    — 이미 사용됨 거부, 만료됨 거부, 최소금액 미달 거부, 성공
 * - restoreCoupon() — 미사용 거부, 만료 시 EXPIRED 처리, 유효 시 ISSUED 복원
 * - registerByPromotionCode() — 코드 없음 거부, 유효하지 않은 코드 거부, 성공 시 issueCoupon 호출
 */

namespace Tests\Shop\Unit\Service;

use Tests\Shop\TestCase;
use Mublo\Packages\Shop\Service\CouponService;
use Mublo\Packages\Shop\Repository\CouponRepository;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Entity\Coupon;
use Mublo\Packages\Shop\Entity\CouponPolicy;

class CouponServiceTest extends TestCase
{
    private CouponRepository $couponRepo;
    private OrderRepository $orderRepo;
    private CouponService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->couponRepo = $this->createMock(CouponRepository::class);
        $this->orderRepo  = $this->createMock(OrderRepository::class);
        $this->service    = new CouponService($this->couponRepo, $this->orderRepo);
    }

    private function makePolicyEntity(array $overrides = []): CouponPolicy
    {
        return CouponPolicy::fromArray(array_merge([
            'coupon_group_id'          => 1,
            'domain_id'                => 1,
            'name'                     => '테스트쿠폰',
            'coupon_type'              => 'DOWNLOAD',
            'coupon_method'            => 'ORDER',
            'discount_type'            => 'FIXED',
            'discount_value'           => 2000,
            'max_discount'             => null,
            'min_order_amount'         => 0,
            'issue_start'              => null,
            'issue_end'                => null,
            'valid_days'               => 30,
            'download_limit_per_member'=> 1,
            'use_limit_per_member'     => 1,
            'total_issue_limit'        => null,
            'allowed_member_levels'    => '',
            'first_order_only'         => false,
            'duplicate_policy'         => 'ALLOW',
            'coupon_method_target'     => null,
            'target_goods_id'          => null,
            'target_category'          => null,
            'excluded_goods'           => null,
            'excluded_categories'      => null,
            'auto_issue_trigger'       => null,
            'promotion_code'           => null,
            'is_active'                => true,
            'staff_id'                 => null,
            'created_at'               => '2026-01-01 00:00:00',
        ], $overrides));
    }

    private function makeCouponEntity(array $overrides = []): Coupon
    {
        return Coupon::fromArray($this->makeCouponData($overrides));
    }

    // =========================================================
    // create()
    // =========================================================

    public function testCreateFailsWhenNameIsEmpty(): void
    {
        $result = $this->service->create(1, ['name' => '', 'discount_value' => 1000]);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('이름', $result->getMessage());
    }

    public function testCreateFailsWhenDiscountValueIsZero(): void
    {
        $result = $this->service->create(1, ['name' => '쿠폰', 'discount_value' => 0]);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('할인', $result->getMessage());
    }

    public function testCreateFailsWhenIssueStartAfterEnd(): void
    {
        $result = $this->service->create(1, [
            'name'          => '쿠폰',
            'discount_value'=> 1000,
            'issue_start'   => '2026-12-31',
            'issue_end'     => '2026-01-01',
        ]);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('시작일', $result->getMessage());
    }

    public function testCreateSuccessReturnsCouponGroupId(): void
    {
        $this->couponRepo->method('create')->willReturn(5);

        $result = $this->service->create(1, [
            'name'           => '신규 할인',
            'discount_value' => 1000,
            'is_active'      => true,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(5, $result->get('coupon_group_id'));
    }

    // =========================================================
    // issueCoupon()
    // =========================================================

    public function testIssueCouponFailsWhenPolicyNotFound(): void
    {
        $this->couponRepo->method('find')->willReturn(null);

        $result = $this->service->issueCoupon(99, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('찾을 수 없', $result->getMessage());
    }

    public function testIssueCouponFailsWhenPolicyIsInactive(): void
    {
        $policy = $this->makePolicyEntity(['is_active' => false]);
        $this->couponRepo->method('find')->willReturn($policy);

        $result = $this->service->issueCoupon(1, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('발행이 중지', $result->getMessage());
    }

    public function testIssueCouponFailsWhenBeforeIssueStart(): void
    {
        $policy = $this->makePolicyEntity([
            'issue_start' => date('Y-m-d H:i:s', strtotime('+7 days')),
        ]);
        $this->couponRepo->method('find')->willReturn($policy);

        $result = $this->service->issueCoupon(1, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('기간', $result->getMessage());
    }

    public function testIssueCouponFailsWhenAfterIssueEnd(): void
    {
        $policy = $this->makePolicyEntity([
            'issue_end' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $this->couponRepo->method('find')->willReturn($policy);

        $result = $this->service->issueCoupon(1, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('종료', $result->getMessage());
    }

    public function testIssueCouponFailsWhenTotalLimitExceeded(): void
    {
        $policy = $this->makePolicyEntity(['total_issue_limit' => 10]);
        $this->couponRepo->method('find')->willReturn($policy);
        $this->couponRepo->method('getTotalIssuedCount')->willReturn(10);

        $result = $this->service->issueCoupon(1, 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('소진', $result->getMessage());
    }

    public function testIssueCouponFailsWhenMemberLimitExceeded(): void
    {
        $policy = $this->makePolicyEntity(['download_limit_per_member' => 1]);
        $this->couponRepo->method('find')->willReturn($policy);
        $this->couponRepo->method('getTotalIssuedCount')->willReturn(0);
        $this->couponRepo->method('getIssuedCount')->willReturn(1); // 이미 발행

        $result = $this->service->issueCoupon(1, 42);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('한도', $result->getMessage());
    }

    public function testIssueCouponSuccessReturnsCouponId(): void
    {
        $policy = $this->makePolicyEntity();
        $this->couponRepo->method('find')->willReturn($policy);
        $this->couponRepo->method('getTotalIssuedCount')->willReturn(0);
        $this->couponRepo->method('getIssuedCount')->willReturn(0);
        $this->couponRepo->method('issueCoupon')->willReturn(7);

        $result = $this->service->issueCoupon(1, 42);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(7, $result->get('coupon_id'));
        $this->assertNotEmpty($result->get('coupon_number'));
    }

    // =========================================================
    // useCoupon()
    // =========================================================

    public function testUseCouponFailsWhenCouponNotFound(): void
    {
        $this->couponRepo->method('findIssuedCoupon')->willReturn(null);

        $result = $this->service->useCoupon(99, 'ORD001', 30000);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('찾을 수 없', $result->getMessage());
    }

    public function testUseCouponFailsWhenAlreadyUsed(): void
    {
        $coupon = $this->makeCouponEntity(['is_used' => true, 'status' => 'USED']);
        $this->couponRepo->method('findIssuedCoupon')->willReturn($coupon->toArray());

        $result = $this->service->useCoupon(1, 'ORD001', 30000);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('이미 사용', $result->getMessage());
    }

    public function testUseCouponFailsWhenExpired(): void
    {
        $coupon = $this->makeCouponEntity([
            'is_used'     => false,
            'valid_until' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'status'      => 'ISSUED',
        ]);
        $this->couponRepo->method('findIssuedCoupon')->willReturn($coupon->toArray());

        $result = $this->service->useCoupon(1, 'ORD001', 30000);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('만료', $result->getMessage());
    }

    public function testUseCouponFailsWhenOrderAmountBelowMinimum(): void
    {
        $coupon = $this->makeCouponEntity(['is_used' => false, 'status' => 'ISSUED']);
        $policy = $this->makePolicyEntity(['min_order_amount' => 50000]);
        $this->couponRepo->method('findIssuedCoupon')->willReturn($coupon->toArray());
        $this->couponRepo->method('find')->willReturn($policy);

        $result = $this->service->useCoupon(1, 'ORD001', 10000);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('최소', $result->getMessage());
    }

    public function testUseCouponSuccessReturnsDiscountAmount(): void
    {
        $coupon = $this->makeCouponEntity(['is_used' => false, 'status' => 'ISSUED']);
        $policy = $this->makePolicyEntity([
            'discount_type'  => 'FIXED',
            'discount_value' => 3000,
            'min_order_amount' => 0,
        ]);
        $this->couponRepo->method('findIssuedCoupon')->willReturn($coupon->toArray());
        $this->couponRepo->method('find')->willReturn($policy);
        $this->couponRepo->method('getCouponsByOrderNo')->willReturn([]);
        $this->couponRepo->method('useCoupon')->willReturn(true);

        $result = $this->service->useCoupon(1, 'ORD001', 30000);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(3000, $result->get('discount_amount'));
    }

    public function testUseCouponPercentageDiscountCalculation(): void
    {
        $coupon = $this->makeCouponEntity(['is_used' => false, 'status' => 'ISSUED']);
        $policy = $this->makePolicyEntity([
            'discount_type'  => 'PERCENTAGE',
            'discount_value' => 10,    // 10%
            'max_discount'   => null,
            'min_order_amount' => 0,
        ]);
        $this->couponRepo->method('findIssuedCoupon')->willReturn($coupon->toArray());
        $this->couponRepo->method('find')->willReturn($policy);
        $this->couponRepo->method('getCouponsByOrderNo')->willReturn([]);
        $this->couponRepo->method('useCoupon')->willReturn(true);

        $result = $this->service->useCoupon(1, 'ORD001', 50000);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(5000, $result->get('discount_amount')); // 50000 * 10% = 5000
    }

    public function testUseCouponPercentageDiscountRespectMaxDiscount(): void
    {
        $coupon = $this->makeCouponEntity(['is_used' => false, 'status' => 'ISSUED']);
        $policy = $this->makePolicyEntity([
            'discount_type'  => 'PERCENTAGE',
            'discount_value' => 50,    // 50%
            'max_discount'   => 2000,  // 최대 2000원
            'min_order_amount' => 0,
        ]);
        $this->couponRepo->method('findIssuedCoupon')->willReturn($coupon->toArray());
        $this->couponRepo->method('find')->willReturn($policy);
        $this->couponRepo->method('getCouponsByOrderNo')->willReturn([]);
        $this->couponRepo->method('useCoupon')->willReturn(true);

        $result = $this->service->useCoupon(1, 'ORD001', 20000);

        $this->assertTrue($result->isSuccess());
        $this->assertSame(2000, $result->get('discount_amount')); // 10000원이지만 max 2000
    }

    // =========================================================
    // restoreCoupon()
    // =========================================================

    public function testRestoreCouponFailsWhenNotUsed(): void
    {
        $coupon = $this->makeCouponEntity(['is_used' => false, 'status' => 'ISSUED']);
        $this->couponRepo->method('findIssuedCoupon')->willReturn($coupon->toArray());

        $result = $this->service->restoreCoupon(1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('사용되지 않은', $result->getMessage());
    }

    public function testRestoreCouponSetsExpiredWhenOverdue(): void
    {
        $coupon = $this->makeCouponEntity([
            'is_used'     => true,
            'status'      => 'USED',
            'valid_until' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);
        $this->couponRepo->method('findIssuedCoupon')->willReturn($coupon->toArray());
        $this->couponRepo->expects($this->once())->method('expireCoupon');

        $result = $this->service->restoreCoupon(1);

        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->get('restored'));
    }

    public function testRestoreCouponRestoresValidCoupon(): void
    {
        $coupon = $this->makeCouponEntity([
            'is_used'     => true,
            'status'      => 'USED',
            'valid_until' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        $this->couponRepo->method('findIssuedCoupon')->willReturn($coupon->toArray());
        $this->couponRepo->method('restoreCoupon')->willReturn(true);

        $result = $this->service->restoreCoupon(1);

        $this->assertTrue($result->isSuccess());
        $this->assertTrue($result->get('restored'));
    }

    // =========================================================
    // registerByPromotionCode()
    // =========================================================

    public function testRegisterByPromotionCodeFailsWhenCodeIsEmpty(): void
    {
        $result = $this->service->registerByPromotionCode('', 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('코드', $result->getMessage());
    }

    public function testRegisterByPromotionCodeFailsWhenCodeNotFound(): void
    {
        $this->couponRepo->method('findByPromotionCode')->willReturn(null);

        $result = $this->service->registerByPromotionCode('INVALID123', 1);

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('유효하지 않은', $result->getMessage());
    }
}
