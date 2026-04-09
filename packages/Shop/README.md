# Mublo Shop 패키지

Mublo Framework용 범용 쇼핑몰 패키지입니다. 상품 관리부터 결제, 배송, 리뷰까지 쇼핑몰 운영에 필요한 모든 기능을 제공합니다.

## 주요 기능

- **상품 관리** — 상품 등록/수정/삭제, 다중 이미지, 옵션(단일/조합/추가), 카테고리, 상품정보 템플릿
- **주문 / 결제** — 장바구니, 체크아웃, PG 연동(토스페이먼츠 기본 제공), 주문 조회, 비회원 주문
- **주문 상태 FSM** — Config 기반 상태 전이 정의, 상태별 자동 액션(알림/포인트/재고/웹훅)
- **쿠폰** — 정액/정률 할인, 대상 제한(상품/카테고리/전체/배송비), 발행 한도, 프로모션 코드
- **배송 템플릿** — 무료/조건부 무료/정액/수량 기반 배송비, 지역별 추가 배송비
- **리뷰 / 문의** — 구매후기(포토/텍스트), 상품문의 QnA, 관리자 답변
- **위시리스트** — 상품 찜 목록 (회원)
- **등급별 가격** — 회원 등급에 따른 할인율/추가 적립률 설정
- **포인트** — 구매/리뷰 적립, 사용, 포인트 내역 관리
- **기획전(Exhibition)** — 기간 기반 기획전, 상품/카테고리 연결
- **PII 암호화** — 주문자/수령인/배송지 8개 필드 AES-256-GCM 암호화, Blind Index 검색
- **블록 연동** — 페이지 빌더에서 상품 블록 배치 (스킨 선택 가능)

## 설치 방법

### 1. 패키지 디렉토리에 배치

```
packages/
└── Shop/          ← 이 패키지를 여기에 배치
```

### 2. 관리자 > 패키지 관리에서 활성화

패키지를 활성화하면 DB 마이그레이션과 기본 메뉴 등록이 자동으로 실행됩니다.

### 3. PG 설정 (선택)

관리자 > 쇼핑몰 설정 > PG 설정에서 토스페이먼츠 클라이언트 키/시크릿 키를 입력합니다.
개발 환경에서는 Mock PG가 자동으로 사용됩니다.

## 디렉토리 구조

```
packages/Shop/
├── Action/                # 주문 상태 액션 핸들러 (알림, 포인트, 재고, 웹훅)
├── Block/                 # 블록 에디터용 상품 블록
├── Controller/
│   ├── Admin/             # 관리자 컨트롤러 (상품, 주문, 쿠폰 등)
│   └── Front/             # 프론트 컨트롤러 (상품, 장바구니, 주문 등)
├── Entity/                # 도메인 모델 (Product, Order, Coupon, CartItem 등)
├── Enum/                  # Enum 정의 (OrderAction, PaymentMethod 등)
├── Event/                 # 이벤트 클래스 (OrderStatusChangedEvent 등)
├── EventSubscriber/       # 이벤트 구독자 (CouponRestore, DomainEvent 등)
├── Gateway/               # PG 게이트웨이 구현체 (Toss, Mock)
├── Helper/                # 프레젠터 (ProductPresenter)
├── Repository/            # 데이터 접근 (OrderRepository, ProductRepository 등)
├── Service/               # 비즈니스 로직 (OrderService, CartService 등)
├── database/
│   └── migrations/        # DB 마이그레이션 SQL
├── tests/                 # PHPUnit 테스트
│   ├── Feature/
│   └── Unit/
├── views/
│   ├── Admin/             # 관리자 뷰 템플릿
│   ├── Block/             # 블록 에디터 스킨
│   └── Front/
│       └── basic/         # 프론트 기본 스킨
├── AdminMenuSubscriber.php
├── ShopProvider.php
├── routes.php
└── manifest.json
```

## 간단한 사용 예시

### 서비스 직접 사용 (DI)

```php
// 장바구니에 상품 추가
$cartService = $container->get(CartService::class);
$result = $cartService->addItem($sessionId, $memberId, [
    'goods_id'  => 10,
    'quantity'  => 2,
    'option_id' => 5,
]);

if ($result->isSuccess()) {
    echo $result->getMessage(); // "장바구니에 담겼습니다."
}

// 주문 생성
$orderService = $container->get(OrderService::class);
$result = $orderService->createOrder($domainId, $memberId, $orderData, $items);
$orderNo = $result->get('order_no');

// 주문 상태 변경
$result = $orderService->updateStatus($orderNo, 'PAID', $adminId);
```

### 이벤트로 확장

```php
// 주문 완료 시 적립금 지급 (EventSubscriber 예시)
class MyPointSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [OrderStatusChangedEvent::class => 'onStatusChanged'];
    }

    public function onStatusChanged(OrderStatusChangedEvent $event): void
    {
        if ($event->getNewStatus() === 'COMPLETE') {
            // 포인트 적립 처리
        }
    }
}

// Provider.boot()에서 등록
$eventDispatcher->addSubscriber(new MyPointSubscriber());
```

### PG 게이트웨이 추가 (Plugin)

```php
// 자체 PG 구현체를 ContractRegistry에 등록
$contractRegistry->register('inicis', new InicisGateway($clientId, $secretKey));
```

## 라이선스

MIT License — 자세한 내용은 [LICENSE](LICENSE) 파일을 참고하세요.
