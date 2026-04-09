# Contract 시스템

## Contract란?

Contract는 Plugin이 데이터를 제공하고 Package가 이를 소비하는 **pull 패턴**입니다.

| 패턴 | 용도 | 예시 |
|------|------|------|
| **Event (push)** | "무언가 일어났다" 알림 | 회원가입됨, 주문상태변경됨 |
| **Contract (pull)** | "데이터를 줘" 요청 | FAQ 목록 조회, 결제 처리 |

인터페이스는 소비자(Package)도 구현자(Plugin)도 아닌 **중립 위치** `src/Contract/`에 배치합니다.

```
Package(소비자) → ContractRegistry → Plugin(구현체)
                       ↑
              src/Contract/ (인터페이스)
```

이벤트와의 관계:
- 이벤트 시스템은 [이벤트 시스템](event-system.md) 문서를 먼저 본다
- Contract는 “데이터/기능을 **조회하거나 호출**해야 할 때” 사용한다
- Event는 “어떤 일이 **발생했음**을 알리고 반응하게 할 때” 사용한다

빠른 판단 기준:

| 상황 | 권장 방식 |
|------|----------|
| 메뉴 추가, 로그인 폼 확장, 회원가입 후처리 | Event |
| 검색 결과 공급, 마이페이지 항목 공급 | Event |
| FAQ 목록 조회, 결제 게이트웨이 선택, 알림 게이트웨이 호출 | Contract |
| 특정 구현체를 명시적으로 선택해야 함 | Contract |

> **주의:** Event와 Contract를 동시에 만들기 전에, 한 가지 패턴만으로 충분한지 먼저 판단하세요.
> - “호출해서 값을 받는 구조”인데 Event로 억지 구현하지 않는다
> - “발생 사실에 대한 후처리”인데 Contract로 결합을 강하게 만들지 않는다

## ContractRegistry

`src/Core/Registry/ContractRegistry.php`

두 가지 모드를 지원합니다.

### 1:1 바인딩 — 단일 구현체

```php
// Plugin Provider.boot()에서 등록
$registry->bind(FaqQueryInterface::class, new FaqService($repo));

// 또는 지연 생성 (Closure)
$registry->bind(FaqQueryInterface::class, fn() => new FaqService($repo));
```

```php
// Package Controller에서 소비
$faqService = $registry->resolve(FaqQueryInterface::class);
$categories = $faqService->getCategories($domainId);
```

```php
// 존재 여부 확인
$registry->has(FaqQueryInterface::class); // bool
```

- `bind()` — 구현체 등록 (이미 등록되어 있으면 `DuplicateRegistryException`)
- `resolve()` — 구현체 조회 (없으면 `RegistryNotFoundException`)
- `has()` — 등록 여부 확인

### 1:N 등록 — 여러 구현체

결제 게이트웨이처럼 여러 구현체가 필요한 경우 키로 구분합니다.

```php
// TossPay Plugin
$registry->register(
    PaymentGatewayInterface::class,
    'tosspay',                              // 키
    fn() => new TossPayGateway($config),    // Closure (지연 생성)
    ['label' => 'Toss 결제']               // 메타데이터
);

// Inicis Plugin
$registry->register(
    PaymentGatewayInterface::class,
    'inicis',
    fn() => new InicisGateway($config),
    ['label' => 'Inicis 결제']
);
```

```php
// 특정 키로 조회
$gateway = $registry->get(PaymentGatewayInterface::class, 'tosspay');

// 전체 키 목록
$keys = $registry->keys(PaymentGatewayInterface::class);
// → ['tosspay', 'inicis']

// 키 존재 여부
$registry->hasKey(PaymentGatewayInterface::class, 'tosspay'); // true

// 메타데이터 조회 (인스턴스 생성 없이)
$meta = $registry->getMeta(PaymentGatewayInterface::class, 'tosspay');
// → ['label' => 'Toss 결제']

// 전체 메타데이터
$allMeta = $registry->allMeta(PaymentGatewayInterface::class);
// → ['tosspay' => ['label' => 'Toss 결제'], 'inicis' => ['label' => 'Inicis 결제']]
```

메타데이터는 관리자 목록 페이지에서 구현체를 인스턴스화하지 않고 이름/아이콘 등을 표시할 때 유용합니다.

## Contract 작성

### 인터페이스 위치

```
src/Contract/{도메인}/
  └── {Name}Interface.php
```

### 인터페이스 예시

```php
// src/Contract/Faq/FaqQueryInterface.php

namespace Mublo\Contract\Faq;

interface FaqQueryInterface
{
    public function getCategories(int $domainId): array;
    public function getByCategorySlugs(int $domainId, array $slugs): array;
    public function getGroupedAll(int $domainId): array;
    public function getGroupedPaginated(int $domainId, int $page, int $perPage): array;
}
```

## Plugin에서 Contract 구현

```php
// plugins/Faq/Service/FaqService.php

namespace Mublo\Plugin\Faq\Service;

use Mublo\Contract\Faq\FaqQueryInterface;

class FaqService implements FaqQueryInterface
{
    public function __construct(private FaqRepository $faqRepository) {}

    public function getCategories(int $domainId): array
    {
        return $this->faqRepository->findCategoriesWithCount($domainId);
    }

    public function getByCategorySlugs(int $domainId, array $slugs): array
    {
        // ... 구현 ...
    }

    public function getGroupedAll(int $domainId): array
    {
        // ... 구현 ...
    }

    public function getGroupedPaginated(int $domainId, int $page, int $perPage): array
    {
        // ... 구현 ...
    }

    // Contract 외 자체 CRUD 메서드도 같은 클래스에 가능
    public function createItem(int $domainId, array $data): Result { ... }
}
```

### Provider에서 바인딩

```php
// plugins/Faq/FaqProvider.php

public function boot(DependencyContainer $container, Context $context): void
{
    $registry = $container->get(ContractRegistry::class);
    $registry->bind(
        FaqQueryInterface::class,
        $container->get(FaqService::class)
    );
}
```

추천 패턴:
- UI 주입이나 후처리는 Event
- 다른 Package가 재사용할 조회 API는 Contract
- 두 패턴이 모두 필요하면, 보통 “조회는 Contract / 후처리는 Event”로 나눈다

## Package에서 Contract 소비

```php
// packages/Mshop/Controller/Front/FaqController.php

namespace Mublo\Packages\Mshop\Controller\Front;

use Mublo\Contract\Faq\FaqQueryInterface;
use Mublo\Core\Registry\ContractRegistry;

class FaqController
{
    public function __construct(private ContractRegistry $contractRegistry) {}

    public function list(Request $request, Context $context): JsonResponse
    {
        $domainId = $context->getDomainId();
        $page = max(1, (int) ($request->query('page') ?? 1));

        try {
            /** @var FaqQueryInterface $faqService */
            $faqService = $this->contractRegistry->resolve(FaqQueryInterface::class);
            $data = $faqService->getGroupedPaginated($domainId, $page, 10);
        } catch (\Throwable) {
            // FAQ 플러그인 미설치 시 graceful degradation
            $data = ['groups' => [], 'totalItems' => 0, 'perPage' => 10, 'currentPage' => 1, 'totalPages' => 0];
        }

        return JsonResponse::success($data);
    }
}
```

## 기존 Contract 목록

### PaymentGatewayInterface

`src/Contract/Payment/PaymentGatewayInterface.php`

PG사 결제 연동. 1:N 등록 (TossPay, Inicis 등).

```php
interface PaymentGatewayInterface
{
    public function prepare(array $orderData): array;
    public function verify(string $transactionId): array;
    public function cancel(string $transactionId, int $amount, string $reason = ''): array;
    public function getClientConfig(): array;
    public function getCheckoutScript(): ?string;
}
```

### NotificationGatewayInterface

`src/Contract/Notification/NotificationGatewayInterface.php`

알림 발송 (알림톡, SMS, 이메일). 1:N 등록.

```php
interface NotificationGatewayInterface
{
    public function send(string $channel, string $templateCode, string $recipient, array $fieldValues): array;
    public function getSupportedChannels(): array;
    public function getChannelTree(int $domainId): array;
}
```

### CategoryProviderInterface

`src/Contract/Category/CategoryProviderInterface.php`

카테고리 트리 제공. 1:N 등록 (Shop, Rental 등 각 패키지가 제공).

```php
interface CategoryProviderInterface
{
    public function getTree(int $domainId, ?int $depth = null): array;
}
```

반환 형식: `[['icon', 'code', 'label', 'link', 'children' => [...]]]`

### FaqQueryInterface

`src/Contract/Faq/FaqQueryInterface.php`

FAQ 데이터 조회. 1:1 바인딩 (FAQ 플러그인).

---

[< 이전: 이벤트 시스템](event-system.md) | [다음: 블록 시스템 개발 >](block-system.md)

### IdentityVerificationInterface

`src/Contract/Identity/IdentityVerificationInterface.php`

본인인증 (NICE, KMC 등). 1:1 바인딩.

```php
interface IdentityVerificationInterface
{
    public function prepare(array $params): array;
    public function verify(array $callbackData): array;
    public function getClientConfig(): array;
    public function getClientScript(): ?string;
}
```

### CachePurgerInterface

`src/Contract/Cache/CachePurgerInterface.php`

CDN 캐시 퍼지 (Cloudflare 등). 1:1 바인딩.

```php
interface CachePurgerInterface
{
    public function purgeForDomain(int $domainId, ?int $pageId = null): void;
}
```

### DataResettableInterface

`src/Contract/DataResettableInterface.php`

데이터 초기화. Provider가 직접 구현 (별도 Registry 불필요).

```php
interface DataResettableInterface
{
    public function getResetCategories(): array;
    public function reset(string $category, int $domainId, Database $db): array;
}
```

## 언제 Contract를 쓰고 언제 Event를 쓰는가

| 상황 | 선택 | 이유 |
|------|------|------|
| "FAQ 목록 줘" | **Contract** | 데이터 pull (호출 시점에 필요) |
| "결제 처리해줘" | **Contract** | 동기 호출 + 결과 반환 |
| "회원이 가입했어" | **Event** | 알림 push (누가 듣는지 모름) |
| "주문 상태가 바뀌었어" | **Event** | 부수효과 (로그, 알림, 포인트) |
| "이 회원 포인트 차감해줘" | **Core Service** | BalanceManager가 Core에 있음 |

---

[< 이전: 이벤트 시스템](event-system.md) | [다음: 블록 시스템 개발 >](block-system.md)
