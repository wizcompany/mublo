<?php

namespace Mublo\Packages\Shop;

use Mublo\Core\Block\BlockRegistry;
use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Extension\InstallableExtensionInterface;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Core\Http\Request;
use Mublo\Core\Registry\ContractRegistry;
use Mublo\Contract\Payment\PaymentGatewayInterface;
use Mublo\Core\Registry\CategoryProviderRegistry;
use Mublo\Core\Rendering\AssetManager;
use Mublo\Enum\Block\BlockContentKind;
use Mublo\Infrastructure\Database\Database;
use Mublo\Infrastructure\Storage\FileUploader;
use Mublo\Infrastructure\Storage\SecureFileService;
use Mublo\Packages\Shop\Block\ProductConfigForm;
use Mublo\Packages\Shop\Block\ProductRenderer;
use Mublo\Service\Auth\AuthService;

// Repository
use Mublo\Packages\Shop\Repository\ShopConfigRepository;
use Mublo\Packages\Shop\Repository\CategoryRepository;
use Mublo\Packages\Shop\Repository\ProductRepository;
use Mublo\Packages\Shop\Repository\OptionPresetRepository;
use Mublo\Packages\Shop\Repository\ProductOptionRepository;
use Mublo\Packages\Shop\Repository\CartRepository;
use Mublo\Packages\Shop\Repository\OrderRepository;
use Mublo\Packages\Shop\Repository\ShippingRepository;
use Mublo\Packages\Shop\Repository\CouponRepository;
use Mublo\Packages\Shop\Repository\MemberAddressRepository;
use Mublo\Packages\Shop\Repository\OrderFieldRepository;
use Mublo\Packages\Shop\Repository\PaymentTransactionRepository;
use Mublo\Packages\Shop\Repository\OrderMemoRepository;
use Mublo\Packages\Shop\Repository\ProductInfoTemplateRepository;
use Mublo\Packages\Shop\Repository\ReviewRepository;
use Mublo\Packages\Shop\Repository\InquiryRepository;
use Mublo\Packages\Shop\Repository\WishlistRepository;
use Mublo\Packages\Shop\Repository\LevelPricingRepository;
use Mublo\Packages\Shop\Repository\PointLogRepository;
use Mublo\Packages\Shop\Repository\ExhibitionRepository;
use Mublo\Packages\Shop\Repository\ShipmentRepository;

// Service
use Mublo\Packages\Shop\Service\ShopConfigService;
use Mublo\Packages\Shop\Service\CategoryService;
use Mublo\Packages\Shop\Service\ProductService;
use Mublo\Packages\Shop\Service\OptionPresetService;
use Mublo\Packages\Shop\Service\CartService;
use Mublo\Packages\Shop\Service\CartCheckoutService;
use Mublo\Packages\Shop\Service\DirectBuyService;
use Mublo\Packages\Shop\Service\OrderService;
use Mublo\Packages\Shop\Service\OrderStateResolver;
use Mublo\Packages\Shop\Service\ShippingService;
use Mublo\Packages\Shop\Service\CouponService;
use Mublo\Packages\Shop\Service\PriceCalculator;
use Mublo\Packages\Shop\Service\PaymentService;
use Mublo\Packages\Shop\Service\MemberAddressService;
use Mublo\Packages\Shop\Service\OrderFieldService;
use Mublo\Packages\Shop\Service\RefundService;
use Mublo\Packages\Shop\Service\OrderMemoService;
use Mublo\Packages\Shop\Service\ProductInfoTemplateService;
use Mublo\Packages\Shop\Service\ReviewService;
use Mublo\Packages\Shop\Service\InquiryService;
use Mublo\Packages\Shop\Service\WishlistService;
use Mublo\Packages\Shop\Service\LevelPricingService;
use Mublo\Packages\Shop\Service\PointLogService;
use Mublo\Packages\Shop\Service\DashboardService;
use Mublo\Packages\Shop\Service\ExhibitionService;
use Mublo\Packages\Shop\Service\ShipmentService;
use Mublo\Service\Member\FieldEncryptionService;
use Mublo\Service\CustomField\CustomFieldFileHandler;

// Controller
use Mublo\Packages\Shop\Controller\Admin\ShopConfigController;
use Mublo\Packages\Shop\Controller\Admin\CategoryController;
use Mublo\Packages\Shop\Controller\Admin\ProductController as AdminProductController;
use Mublo\Packages\Shop\Controller\Admin\OptionPresetController;
use Mublo\Packages\Shop\Controller\Admin\OrderController as AdminOrderController;
use Mublo\Packages\Shop\Controller\Admin\CouponController;
use Mublo\Packages\Shop\Controller\Admin\ShippingTemplateController;
use Mublo\Packages\Shop\Controller\Admin\OrderStateController;
use Mublo\Packages\Shop\Controller\Admin\OrderFieldController;
use Mublo\Packages\Shop\Controller\Admin\ProductInfoTemplateController;
use Mublo\Packages\Shop\Controller\Admin\ReviewController as AdminReviewController;
use Mublo\Packages\Shop\Controller\Admin\InquiryController;
use Mublo\Packages\Shop\Controller\Admin\LevelPricingController;
use Mublo\Packages\Shop\Controller\Admin\DashboardController;
use Mublo\Packages\Shop\Controller\Admin\ExhibitionController as AdminExhibitionController;
use Mublo\Packages\Shop\Controller\Front\ProductController as FrontProductController;
use Mublo\Packages\Shop\Controller\Front\ReviewController as FrontReviewController;
use Mublo\Packages\Shop\Controller\Front\InquiryController as FrontInquiryController;
use Mublo\Packages\Shop\Controller\Front\WishlistController;
use Mublo\Packages\Shop\Controller\Front\CartController;
use Mublo\Packages\Shop\Controller\Front\OrderController as FrontOrderController;
use Mublo\Packages\Shop\Controller\Front\AddressController;
use Mublo\Packages\Shop\Controller\Front\CouponController as FrontCouponController;
use Mublo\Packages\Shop\Controller\Front\ExhibitionController as FrontExhibitionController;

// Gateway
use Mublo\Packages\Shop\Gateway\TossPaymentsGateway;
use Mublo\Packages\Shop\Gateway\MockPaymentGateway;

// Action
use Mublo\Packages\Shop\Service\ActionTypeRegistry;
use Mublo\Packages\Shop\Action\NotificationActionHandler;
use Mublo\Packages\Shop\Action\PointActionHandler;
use Mublo\Packages\Shop\Action\PointDeductActionHandler;
use Mublo\Packages\Shop\Action\OrderConfirmActionHandler;
use Mublo\Packages\Shop\Action\StockDeductActionHandler;
use Mublo\Packages\Shop\Action\StockRestoreActionHandler;
use Mublo\Packages\Shop\Action\WebhookActionHandler;
use Mublo\Service\Balance\BalanceManager;
use Mublo\Packages\Shop\EventSubscriber\ConfigurableActionSubscriber;
use Mublo\Packages\Shop\EventSubscriber\CouponRestoreSubscriber;
use Mublo\Packages\Shop\EventSubscriber\CouponAutoIssueSubscriber;
use Mublo\Packages\Shop\EventSubscriber\DomainEventSubscriber;
use Mublo\Packages\Shop\EventSubscriber\ExhibitionMenuSubscriber;
use Mublo\Packages\Shop\EventSubscriber\LoginFormSubscriber;
use Mublo\Packages\Shop\EventSubscriber\NotificationVariableSubscriber;
use Mublo\Service\Menu\MenuService;
use Mublo\Repository\Menu\MenuItemRepository;

class ShopProvider implements ExtensionProviderInterface, InstallableExtensionInterface
{
    public function register(DependencyContainer $container): void
    {
        // ── Repository ──
        $container->singleton(ShopConfigRepository::class, fn(DependencyContainer $c) =>
            new ShopConfigRepository($c->get(Database::class))
        );
        $container->singleton(CategoryRepository::class, fn(DependencyContainer $c) =>
            new CategoryRepository($c->get(Database::class))
        );
        $container->singleton(ProductRepository::class, fn(DependencyContainer $c) =>
            new ProductRepository($c->get(Database::class))
        );
        $container->singleton(OptionPresetRepository::class, fn(DependencyContainer $c) =>
            new OptionPresetRepository($c->get(Database::class))
        );
        $container->singleton(ProductOptionRepository::class, fn(DependencyContainer $c) =>
            new ProductOptionRepository($c->get(Database::class))
        );
        $container->singleton(CartRepository::class, fn(DependencyContainer $c) =>
            new CartRepository($c->get(Database::class))
        );
        $container->singleton(OrderRepository::class, fn(DependencyContainer $c) =>
            new OrderRepository($c->get(Database::class))
        );
        $container->singleton(ShippingRepository::class, fn(DependencyContainer $c) =>
            new ShippingRepository($c->get(Database::class))
        );
        $container->singleton(CouponRepository::class, fn(DependencyContainer $c) =>
            new CouponRepository($c->get(Database::class))
        );
        $container->singleton(MemberAddressRepository::class, fn(DependencyContainer $c) =>
            new MemberAddressRepository($c->get(Database::class))
        );
        $container->singleton(OrderFieldRepository::class, fn(DependencyContainer $c) =>
            new OrderFieldRepository($c->get(Database::class))
        );
        $container->singleton(PaymentTransactionRepository::class, fn(DependencyContainer $c) =>
            new PaymentTransactionRepository($c->get(Database::class))
        );
        $container->singleton(OrderMemoRepository::class, fn(DependencyContainer $c) =>
            new OrderMemoRepository($c->get(Database::class))
        );
        $container->singleton(ProductInfoTemplateRepository::class, fn(DependencyContainer $c) =>
            new ProductInfoTemplateRepository($c->get(Database::class))
        );
        $container->singleton(ReviewRepository::class, fn(DependencyContainer $c) =>
            new ReviewRepository($c->get(Database::class))
        );
        $container->singleton(InquiryRepository::class, fn(DependencyContainer $c) =>
            new InquiryRepository($c->get(Database::class))
        );
        $container->singleton(WishlistRepository::class, fn(DependencyContainer $c) =>
            new WishlistRepository($c->get(Database::class))
        );
        $container->singleton(LevelPricingRepository::class, fn(DependencyContainer $c) =>
            new LevelPricingRepository($c->get(Database::class))
        );
        $container->singleton(PointLogRepository::class, fn(DependencyContainer $c) =>
            new PointLogRepository($c->get(Database::class))
        );
        $container->singleton(ExhibitionRepository::class, fn(DependencyContainer $c) =>
            new ExhibitionRepository($c->get(Database::class))
        );
        $container->singleton(ShipmentRepository::class, fn(DependencyContainer $c) =>
            new ShipmentRepository($c->get(Database::class))
        );

        // ── Service ──
        $container->singleton(PriceCalculator::class, fn() => new PriceCalculator());

        $container->singleton(ShopConfigService::class, fn(DependencyContainer $c) =>
            new ShopConfigService(
                $c->get(ShopConfigRepository::class)
            )
        );
        $container->singleton(OrderStateResolver::class, fn(DependencyContainer $c) =>
            new OrderStateResolver(
                $c->get(ShopConfigService::class)
            )
        );
        $container->singleton(ActionTypeRegistry::class, function (DependencyContainer $c) {
            $registry = new ActionTypeRegistry();
            $balanceManager = $c->get(BalanceManager::class);
            $registry->register(new NotificationActionHandler($c->get(ContractRegistry::class)));
            $registry->register(new PointActionHandler($balanceManager));
            $registry->register(new PointDeductActionHandler($balanceManager));
            $registry->register(new OrderConfirmActionHandler($c->get(OrderRepository::class)));
            $orderRepo = $c->get(OrderRepository::class);
            $productRepo = $c->get(ProductRepository::class);
            $optionRepo = $c->get(ProductOptionRepository::class);
            $registry->register(new StockDeductActionHandler($orderRepo, $productRepo, $optionRepo));
            $registry->register(new StockRestoreActionHandler($orderRepo, $productRepo, $optionRepo));
            $registry->register(new WebhookActionHandler());
            return $registry;
        });
        $container->singleton(CategoryService::class, fn(DependencyContainer $c) =>
            new CategoryService(
                $c->get(CategoryRepository::class)
            )
        );
        $container->singleton(ProductService::class, fn(DependencyContainer $c) =>
            new ProductService(
                $c->get(ProductRepository::class),
                $c->get(ProductOptionRepository::class),
                $c->get(CategoryRepository::class),
                $c->get(PriceCalculator::class),
                $c->get(EventDispatcher::class)
            )
        );
        $container->singleton(OptionPresetService::class, fn(DependencyContainer $c) =>
            new OptionPresetService(
                $c->get(OptionPresetRepository::class),
                $c->get(ProductOptionRepository::class),
                $c->get(EventDispatcher::class)
            )
        );
        $container->singleton(DirectBuyService::class, fn(DependencyContainer $c) =>
            new DirectBuyService(
                $c->get(ProductRepository::class),
                $c->get(PriceCalculator::class),
                $c->get(\Mublo\Infrastructure\Session\SessionManager::class)
            )
        );
        $container->singleton(CartCheckoutService::class, fn(DependencyContainer $c) =>
            new CartCheckoutService(
                $c->get(CartRepository::class),
                $c->get(ProductRepository::class),
                $c->get(PriceCalculator::class),
                $c->get(ShopConfigService::class),
                $c->get(ShippingRepository::class),
                $c->get(\Mublo\Infrastructure\Session\SessionManager::class)
            )
        );
        $container->singleton(CartService::class, fn(DependencyContainer $c) =>
            new CartService(
                $c->get(CartRepository::class),
                $c->get(ProductRepository::class),
                $c->get(ProductOptionRepository::class),
                $c->get(PriceCalculator::class),
                $c->get(ShippingRepository::class),
                $c->get(ShopConfigService::class),
                $c->get(DirectBuyService::class),
                $c->get(\Mublo\Infrastructure\Session\SessionManager::class)
            )
        );
        $container->singleton(OrderService::class, fn(DependencyContainer $c) =>
            new OrderService(
                $c->get(OrderRepository::class),
                $c->get(CartRepository::class),
                $c->get(ProductRepository::class),
                $c->get(ProductOptionRepository::class),
                $c->get(PriceCalculator::class),
                $c->get(OrderStateResolver::class),
                $c->get(EventDispatcher::class),
                $c->get(FieldEncryptionService::class)
            )
        );
        $container->singleton(ShippingService::class, fn(DependencyContainer $c) =>
            new ShippingService(
                $c->get(ShippingRepository::class)
            )
        );
        $container->singleton(CouponService::class, fn(DependencyContainer $c) =>
            new CouponService(
                $c->get(CouponRepository::class),
                $c->get(OrderRepository::class),
                $c->get(EventDispatcher::class)
            )
        );
        $container->singleton(PaymentService::class, fn(DependencyContainer $c) =>
            new PaymentService(
                $c->get(ContractRegistry::class),
                $c->get(OrderRepository::class),
                $c->get(OrderService::class),
                $c->get(PriceCalculator::class),
                $c->get(PaymentTransactionRepository::class),
                $c->get(EventDispatcher::class)
            )
        );
        $container->singleton(MemberAddressService::class, fn(DependencyContainer $c) =>
            new MemberAddressService(
                $c->get(MemberAddressRepository::class)
            )
        );
        $container->singleton(OrderFieldService::class, fn(DependencyContainer $c) =>
            new OrderFieldService(
                $c->get(OrderFieldRepository::class),
                $c->get(FieldEncryptionService::class),
                $c->has(SecureFileService::class)
                    ? new CustomFieldFileHandler($c->get(SecureFileService::class))
                    : null
            )
        );
        $container->singleton(RefundService::class, fn(DependencyContainer $c) =>
            new RefundService(
                $c->get(PaymentService::class),
                $c->get(PaymentTransactionRepository::class),
                $c->get(OrderRepository::class),
                $c->get(OrderStateResolver::class),
                $c->get(EventDispatcher::class)
            )
        );
        $container->singleton(OrderMemoService::class, fn(DependencyContainer $c) =>
            new OrderMemoService(
                $c->get(OrderMemoRepository::class),
                $c->get(OrderRepository::class)
            )
        );
        $container->singleton(ProductInfoTemplateService::class, fn(DependencyContainer $c) =>
            new ProductInfoTemplateService(
                $c->get(ProductInfoTemplateRepository::class)
            )
        );
        $container->singleton(ReviewService::class, fn(DependencyContainer $c) =>
            new ReviewService(
                $c->get(ReviewRepository::class)
            )
        );
        $container->singleton(InquiryService::class, fn(DependencyContainer $c) =>
            new InquiryService(
                $c->get(InquiryRepository::class)
            )
        );
        $container->singleton(WishlistService::class, fn(DependencyContainer $c) =>
            new WishlistService(
                $c->get(WishlistRepository::class)
            )
        );
        $container->singleton(LevelPricingService::class, fn(DependencyContainer $c) =>
            new LevelPricingService(
                $c->get(LevelPricingRepository::class)
            )
        );
        $container->singleton(PointLogService::class, fn(DependencyContainer $c) =>
            new PointLogService(
                $c->get(PointLogRepository::class)
            )
        );
        $container->singleton(DashboardService::class, fn(DependencyContainer $c) =>
            new DashboardService(
                $c->get(OrderRepository::class),
                $c->get(ProductRepository::class)
            )
        );
        $container->singleton(ExhibitionService::class, fn(DependencyContainer $c) =>
            new ExhibitionService(
                $c->get(ExhibitionRepository::class),
                $c->get(EventDispatcher::class)
            )
        );
        $container->singleton(ShipmentService::class, fn(DependencyContainer $c) =>
            new ShipmentService(
                $c->get(ShipmentRepository::class),
                $c->get(OrderRepository::class)
            )
        );

        // ── Controller (Admin) ──
        $container->singleton(ShopConfigController::class, fn(DependencyContainer $c) =>
            new ShopConfigController(
                $c->get(ShopConfigService::class),
                $c->get(\Mublo\Core\Extension\MigrationRunner::class),
                $c->get(ContractRegistry::class),
                $c->get(ShippingService::class),
                $c->get(OrderFieldService::class),
                $c->get(\Mublo\Service\Member\PolicyService::class),
                $c->get(ProductInfoTemplateRepository::class),
                $c->get(\Mublo\Service\Member\MemberLevelService::class)
            )
        );
        $container->singleton(CategoryController::class, fn(DependencyContainer $c) =>
            new CategoryController(
                $c->get(CategoryService::class),
                $c->get(\Mublo\Service\Member\MemberLevelService::class)
            )
        );
        $container->singleton(AdminProductController::class, fn(DependencyContainer $c) =>
            new AdminProductController(
                $c->get(ProductService::class),
                $c->get(CategoryService::class),
                $c->get(OptionPresetService::class),
                $c->get(ShippingService::class),
                $c->get(FileUploader::class),
                $c->get(ShopConfigService::class),
                $c->get(\Mublo\Service\Member\MemberLevelService::class)
            )
        );
        $container->singleton(OptionPresetController::class, fn(DependencyContainer $c) =>
            new OptionPresetController($c->get(OptionPresetService::class))
        );
        $container->singleton(AdminOrderController::class, fn(DependencyContainer $c) =>
            new AdminOrderController(
                $c->get(OrderService::class),
                $c->get(OrderFieldService::class),
                $c->get(OrderStateResolver::class),
                $c->get(RefundService::class),
                $c->get(OrderMemoService::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(CouponController::class, fn(DependencyContainer $c) =>
            new CouponController($c->get(CouponService::class), $c->get(ProductService::class))
        );
        $container->singleton(ShippingTemplateController::class, fn(DependencyContainer $c) =>
            new ShippingTemplateController($c->get(ShippingService::class))
        );
        $container->singleton(OrderStateController::class, fn(DependencyContainer $c) =>
            new OrderStateController(
                $c->get(ShopConfigService::class),
                $c->get(OrderStateResolver::class),
                $c->get(ActionTypeRegistry::class)
            )
        );
        $container->singleton(OrderFieldController::class, fn(DependencyContainer $c) =>
            new OrderFieldController($c->get(OrderFieldService::class))
        );
        $container->singleton(ProductInfoTemplateController::class, fn(DependencyContainer $c) =>
            new ProductInfoTemplateController(
                $c->get(ProductInfoTemplateService::class),
                $c->get(CategoryService::class)
            )
        );
        $container->singleton(AdminReviewController::class, fn(DependencyContainer $c) =>
            new AdminReviewController($c->get(ReviewService::class))
        );
        $container->singleton(InquiryController::class, fn(DependencyContainer $c) =>
            new InquiryController(
                $c->get(AuthService::class),
                $c->get(InquiryService::class)
            )
        );
        $container->singleton(LevelPricingController::class, fn(DependencyContainer $c) =>
            new LevelPricingController(
                $c->get(LevelPricingService::class),
                $c->get(\Mublo\Service\Member\MemberLevelService::class)
            )
        );
        $container->singleton(DashboardController::class, fn(DependencyContainer $c) =>
            new DashboardController(
                $c->get(DashboardService::class)
            )
        );
        $container->singleton(AdminExhibitionController::class, fn(DependencyContainer $c) =>
            new AdminExhibitionController(
                $c->get(ExhibitionService::class),
                $c->get(ProductService::class)
            )
        );

        // ── Controller (Front) ──
        $container->singleton(FrontProductController::class, fn(DependencyContainer $c) =>
            new FrontProductController(
                $c->get(ProductService::class),
                $c->get(CategoryProviderRegistry::class),
                $c->get(ShopConfigService::class),
                $c->get(ReviewRepository::class)
            )
        );
        $container->singleton(CartController::class, fn(DependencyContainer $c) =>
            new CartController(
                $c->get(CartService::class),
                $c->get(CartCheckoutService::class),
                $c->get(DirectBuyService::class),
                $c->get(OrderService::class),
                $c->get(PaymentService::class),
                $c->get(AuthService::class),
                $c->get(MemberAddressService::class),
                $c->get(ShopConfigService::class),
                $c->get(OrderFieldService::class),
                $c->get(\Mublo\Infrastructure\Session\SessionManager::class)
            )
        );
        $container->singleton(FrontOrderController::class, fn(DependencyContainer $c) =>
            new FrontOrderController(
                $c->get(OrderService::class),
                $c->get(AuthService::class),
                $c->get(OrderFieldService::class),
                $c->get(OrderStateResolver::class)
            )
        );
        $container->singleton(AddressController::class, fn(DependencyContainer $c) =>
            new AddressController(
                $c->get(MemberAddressService::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(FrontCouponController::class, fn(DependencyContainer $c) =>
            new FrontCouponController(
                $c->get(CouponService::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(WishlistController::class, fn(DependencyContainer $c) =>
            new WishlistController(
                $c->get(WishlistService::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(FrontReviewController::class, fn(DependencyContainer $c) =>
            new FrontReviewController(
                $c->get(ReviewService::class),
                $c->get(OrderRepository::class),
                $c->get(AuthService::class),
                $c->get(FileUploader::class)
            )
        );
        $container->singleton(FrontInquiryController::class, fn(DependencyContainer $c) =>
            new FrontInquiryController(
                $c->get(InquiryService::class),
                $c->get(AuthService::class)
            )
        );
        $container->singleton(FrontExhibitionController::class, fn(DependencyContainer $c) =>
            new FrontExhibitionController(
                $c->get(ExhibitionService::class)
            )
        );

        // ── Block ──
        $container->singleton(ProductRenderer::class, function (DependencyContainer $c) {
            $renderer = new ProductRenderer(
                $c->get(ProductRepository::class),
                $c->get(ShopConfigService::class)
            );
            $renderer->assetManager = $c->get(AssetManager::class);
            return $renderer;
        });
        $container->singleton(ProductConfigForm::class, fn() => new ProductConfigForm());

        // ── Payment Gateway ──
        // ShopConfig에서 PG 설정을 로드하여 ContractRegistry에 등록
        // boot()에서 config가 준비된 후 실행
    }

    private function registerGateways(DependencyContainer $container): void
    {
        $contractRegistry = $container->get(ContractRegistry::class);

        // Mock 게이트웨이 (개발/테스트 환경용, 항상 등록)
        $contractRegistry->register(PaymentGatewayInterface::class, 'mock', new MockPaymentGateway());

        // 토스페이먼츠 (설정 존재 시 등록)
        try {
            $shopConfig = $container->get(ShopConfigService::class);
            $config = $shopConfig->get('pg_toss') ?? [];
            $clientKey = $config['client_key'] ?? '';
            $secretKey = $config['secret_key'] ?? '';
            if ($clientKey && $secretKey) {
                $contractRegistry->register(PaymentGatewayInterface::class, 'toss', new TossPaymentsGateway(
                    $clientKey,
                    $secretKey,
                    (bool) ($config['test_mode'] ?? true)
                ));
            }
        } catch (\Throwable) {
            // DB 없는 환경(설치 전)에서는 스킵
        }
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        // PG 게이트웨이 등록 (config 로드 후 실행)
        $this->registerGateways($container);

        // Context 속성 설정 (checkout 감지 등)
        $this->enrichContext($container, $context);

        // 카테고리 Provider 등록
        $container->get(CategoryProviderRegistry::class)->register(
            'shop',
            fn() => new ShopCategoryProvider($container->get(CategoryService::class))
        );

        $eventDispatcher = $container->get(EventDispatcher::class);

        // 관리자 메뉴 등록
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());

        // 로그인 폼 확장 (비회원 주문 버튼)
        $eventDispatcher->addSubscriber(new LoginFormSubscriber(
            $container->get(ShopConfigService::class)
        ));

        // 알림 변수 등록 (어떤 알림 플러그인이든 Contract 이벤트로 수신)
        $eventDispatcher->addSubscriber(new NotificationVariableSubscriber());

        // 상태별 액션 실행 (Config 기반)
        $eventDispatcher->addSubscriber(new ConfigurableActionSubscriber(
            $container->get(ShopConfigService::class),
            $container->get(ActionTypeRegistry::class)
        ));

        // 주문 취소/환불 시 쿠폰 자동 복원
        $eventDispatcher->addSubscriber(new CouponRestoreSubscriber(
            $container->get(CouponService::class)
        ));

        // 회원가입/등급변경 시 쿠폰 자동 발행
        $eventDispatcher->addSubscriber(new CouponAutoIssueSubscriber(
            $container->get(CouponService::class),
            $container->get(CouponRepository::class)
        ));

        // 도메인 생성 시 프론트 메뉴 자동 시딩
        $eventDispatcher->addSubscriber(new DomainEventSubscriber(
            $container->get(MenuService::class),
            $container->get(MenuItemRepository::class)
        ));

        // 기획전 생성/삭제 시 메뉴 아이템 자동 등록/삭제
        $eventDispatcher->addSubscriber(new ExhibitionMenuSubscriber($container));

        // 블록 콘텐츠 타입 등록
        BlockRegistry::registerContentType(
            type: 'product',
            kind: BlockContentKind::PACKAGE->value,
            title: '상품',
            rendererClass: ProductRenderer::class,
            configFormClass: ProductConfigForm::class,
            options: [
                'skinBasePath' => MUBLO_PACKAGE_PATH . '/Shop/views/Block',
                'hasItems' => true,
                'hasStyle' => true,
                'adminScript' => '/serve/package/Shop/assets/js/block-product.js',
                'adminScriptInit' => 'MubloBlockProduct',
            ]
        );

    }

    // =========================================================================
    // InstallableExtensionInterface — 프론트 메뉴 등록/삭제
    // =========================================================================

    /**
     * 첫 활성화 시 프론트 메뉴 등록
     *
     * - provider_type='package', provider_name='Shop'으로 출처 추적
     * - uninstall()에서 deleteByProvider()로 일괄 삭제 가능
     * - 메뉴 아이템만 등록 (menu_tree 배치는 관리자가 수동)
     * - 필요시 장바구니, 마이페이지(주문내역) 등 추가 가능
     */
    public function install(DependencyContainer $container, Context $context): void
    {
        $menuItemRepo = $container->get(MenuItemRepository::class);
        $domainId = $context->getDomainId();

        // DomainCreatedEvent로 이미 시딩된 경우 중복 방지
        $existing = $menuItemRepo->findByProvider($domainId, 'package', 'Shop');
        if (!empty($existing)) {
            return;
        }

        DomainEventSubscriber::seedMenus(
            $container->get(MenuService::class),
            $domainId
        );
    }

    /**
     * 비활성화 시 프론트 메뉴 삭제
     *
     * - DB 테이블/데이터는 보존 (메뉴만 삭제)
     * - deleteByProvider()로 Shop이 등록한 메뉴 일괄 삭제
     * - menu_tree에 배치된 메뉴도 MenuService.deleteItem 내부에서 정리됨
     */
    public function uninstall(DependencyContainer $container, Context $context): void
    {
        $menuService = $container->get(MenuService::class);
        $menuItemRepo = $container->get(MenuItemRepository::class);
        $domainId = $context->getDomainId();

        // Shop이 등록한 메뉴 아이템 조회 → 개별 삭제 (tree + unique_codes 정리)
        $items = $menuItemRepo->findByProvider($domainId, 'package', 'Shop');
        foreach ($items as $item) {
            $menuService->deleteItem((int) $item['item_id']);
        }
    }

    /**
     * Context 속성 설정
     *
     * 요청 URL/파라미터를 분석하여 Shop 관련 속성을 Context에 설정한다.
     * - active_package: Shop 영역이거나 checkout intent가 있을 때 'shop'
     * - shop.is_checkout: checkout 흐름일 때 true
     */
    private function enrichContext(DependencyContainer $container, Context $context): void
    {
        $request = $context->getRequest();
        $path = $request->getPath();

        $isShopArea = str_starts_with($path, '/shop/') || $path === '/shop';

        $intent = $request->get('intent', '');
        $redirect = $request->get('redirect', '');

        $isCheckout = (
            $intent === 'checkout'
            || str_contains($path, '/checkout')
            || str_contains($redirect, '/shop/checkout')
        );

        if ($isShopArea || $isCheckout) {
            $context->setAttribute('active_package', 'shop');
        }

        if ($isCheckout) {
            $context->setAttribute('shop.is_checkout', true);
        }
    }
}
