# 패키지 만들기

## 패키지란?

Package는 **독립적인 업무 영역**을 담당하는 모듈입니다. 자체 Controller, Service, Repository, Entity, View, 마이그레이션을 갖습니다.

| 구분 | Plugin | Package |
|------|--------|---------|
| 역할 | Core에 부가 기능 추가 | 독립 애플리케이션 |
| 예시 | 배너, FAQ, 포인트 | 게시판, 쇼핑몰, 예약 |
| 규모 | Service 1~3개 | Controller/Service/Repository 다수 |

먼저 읽을 문서:
- [이벤트 시스템](event-system.md)
- [Contract 시스템](contract-system.md)
- [Manifest 기준](manifest-standard.md)

패키지는 보통 두 가지를 함께 사용한다.
- 외부에 기능을 알리는 쪽: Event 구독/발행
- 다른 확장에 기능을 제공하는 쪽: Contract 구현

## 디렉토리 구조

```
packages/MyPackage/
├── MyPackageProvider.php       # 진입점 (필수)
├── manifest.json               # 메타데이터 (필수)
├── routes.php                  # URL 라우팅 (필수)
├── Controller/
│   ├── Admin/                  # 관리자 컨트롤러
│   │   └── MyAdminController.php
│   └── Front/                  # 프론트 컨트롤러
│       └── MyController.php
├── Service/
│   └── MyService.php
├── Repository/
│   └── MyRepository.php
├── Entity/
│   └── MyEntity.php
├── Enum/
│   └── MyStatus.php
├── Event/
│   └── MyCreatedEvent.php
├── Subscriber/
│   ├── AdminMenuSubscriber.php
│   └── OtherSubscriber.php
├── Block/                      # 블록 렌더러 (선택)
│   └── MyRenderer.php
├── Widget/                     # 대시보드 위젯 (선택)
│   └── MyWidget.php
├── views/
│   ├── Admin/
│   │   └── MyAdmin/
│   │       └── Index.php
│   ├── Front/
│   │   └── MyFront/
│   │       └── List.php
│   └── Block/                  # 블록 스킨
│       └── mytype/
│           └── basic/
│               └── basic.php
└── database/
    └── migrations/
        ├── 001_create_my_tables.sql
        └── 002_add_some_column.sql
```

## manifest.json

manifest의 표준 키와 레거시 키 정리는 [Manifest 기준](manifest-standard.md)을 따른다.

```json
{
    "name": "MyPackage",
    "label": "내 패키지",
    "description": "패키지 설명",
    "version": "1.0.0",
    "author": "개발자명",
    "author_url": "https://example.com",
    "icon": "bi-box",
    "default": false,
    "requires": {
        "core": ">=1.0.0"
    }
}
```

- `default: true` — 신규 설치 시 자동 활성화, 마이그레이션 자동 실행
- `icon` — Bootstrap Icons 클래스명
- `title`, `provider` 같은 레거시 키는 패키지 manifest에 사용하지 않는다

## Provider 작성

`ExtensionProviderInterface`를 구현합니다.

```php
namespace Mublo\Packages\MyPackage;

use Mublo\Core\Extension\ExtensionProviderInterface;
use Mublo\Core\Container\DependencyContainer;
use Mublo\Core\Context\Context;
use Mublo\Core\Event\EventDispatcher;
use Mublo\Infrastructure\Database\Database;

class MyPackageProvider implements ExtensionProviderInterface
{
    public function register(DependencyContainer $container): void
    {
        // Repository 등록
        $container->singleton(MyRepository::class, fn($c) =>
            new MyRepository($c->get(Database::class))
        );

        // Service 등록
        $container->singleton(MyService::class, fn($c) =>
            new MyService(
                $c->get(MyRepository::class),
                $c->get(EventDispatcher::class)
            )
        );
    }

    public function boot(DependencyContainer $container, Context $context): void
    {
        $eventDispatcher = $container->get(EventDispatcher::class);

        // 관리자 메뉴 등록
        $eventDispatcher->addSubscriber(new AdminMenuSubscriber());

        // 블록 콘텐츠 타입 등록 (선택)
        BlockRegistry::registerContentType(
            type: 'mytype',
            kind: BlockContentKind::PACKAGE->value,
            title: '내 블록',
            rendererClass: MyRenderer::class,
            options: [
                'skinBasePath' => MUBLO_PACKAGE_PATH . '/MyPackage/views/Block/',
            ]
        );

        // 대시보드 위젯 등록 (선택)
        $registry = $container->get(DashboardWidgetRegistry::class);
        $registry->register('mypackage.widget', new MyWidget(...), 3);
    }
}
```

### register() vs boot()

| 단계 | 시점 | 할 일 | 하면 안 되는 것 |
|------|------|-------|----------------|
| `register()` | Context 생성 전 | DI 서비스 등록 | Context 접근, 이벤트 구독 |
| `boot()` | Context 생성 후 | 이벤트 구독, 블록/위젯 등록, Contract 바인딩 | 없음 (자유) |

권장:
- 코어 안정 이벤트를 우선 사용
- 새 패키지 고유 이벤트는 정말 재사용 가치가 있을 때만 추가

## 라우트 등록

`routes.php`에서 `PrefixedRouteCollector`를 받아 라우트를 정의합니다.

```php
use Mublo\Core\App\PrefixedRouteCollector;
use Mublo\Core\Middleware\AdminMiddleware;
use Mublo\Packages\MyPackage\Controller\Front\MyController;
use Mublo\Packages\MyPackage\Controller\Admin\MyAdminController;

return function (PrefixedRouteCollector $r): void {
    // 프론트 라우트 → /mypackage/list
    $r->addRoute('GET', '/list', [
        'controller' => MyController::class,
        'method'     => 'list',
    ]);

    $r->addRoute('GET', '/{id:\d+}', [
        'controller' => MyController::class,
        'method'     => 'view',
    ]);

    // 관리자 라우트 → /admin/mypackage/list
    $r->addRoute('GET', '/admin/list', [
        'controller' => MyAdminController::class,
        'method'     => 'index',
        'middleware' => [AdminMiddleware::class],
    ]);

    $r->addRoute('POST', '/admin/store', [
        'controller' => MyAdminController::class,
        'method'     => 'store',
        'middleware' => [AdminMiddleware::class],
    ]);

    // 접두사 없는 라우트 (특수한 경우)
    $r->addRawRoute('GET', '/special-page', [
        'controller' => MyController::class,
        'method'     => 'specialPage',
    ]);
};
```

URL 접두사는 디렉토리명에서 자동 생성됩니다: `MyPackage` → `/mypackage/...`

## 마이그레이션

`database/migrations/` 디렉토리에 SQL 파일을 번호 순으로 작성합니다.

```sql
-- database/migrations/001_create_my_tables.sql

CREATE TABLE IF NOT EXISTS my_items (
    item_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_domain_status (domain_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

> 테이블명에 `{prefix}` 플레이스홀더를 사용하지 마세요. 접두사 시스템은 없습니다.

## 이벤트 구독

### 관리자 메뉴

```php
class AdminMenuSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [AdminMenuBuildingEvent::class => 'onAdminMenuBuilding'];
    }

    public function onAdminMenuBuilding(AdminMenuBuildingEvent $event): void
    {
        $event->setSource('package', 'MyPackage');

        // 독립 메뉴 추가
        $event->addPackageMenu('내 패키지', 'bi-box', [
            ['label' => '목록',   'url' => '/admin/mypackage/list',     'code' => '001'],
            ['label' => '설정',   'url' => '/admin/mypackage/settings', 'code' => '002'],
        ]);
    }
}
```

Code는 자동으로 `K_MyPackage_001` 형식으로 프리픽스됩니다.

### 코어 이벤트 활용 우선순위

패키지가 가장 자주 쓰는 코어 이벤트:

| 목적 | 권장 이벤트 |
|------|-------------|
| 관리자 메뉴 추가 | `AdminMenuBuildingEvent` |
| 신규 도메인 기본 데이터 시딩 | `DomainCreatedEvent` |
| 검색 소스 등록/검색 결과 공급 | `SearchSourceCollectEvent`, `SearchEvent` |
| 마이페이지 탭 콘텐츠 공급 | `MypageContentQueryEvent` |
| 블록 콘텐츠 후보 공급 | `BlockContentItemsCollectEvent` |
| 로그인 UI 확장 | `LoginFormRenderingEvent` |

이 이벤트들의 실제 발행 지점과 안정성 분류는 [이벤트 시스템](event-system.md) 문서를 기준으로 본다.

### 검색 통합

```php
class SearchSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            SearchSourceCollectEvent::class => 'onSearchSourceCollect',
            SearchEvent::class => 'onSearch',
        ];
    }

    public function onSearchSourceCollect(SearchSourceCollectEvent $event): void
    {
        $event->addSource('mypackage', '내 패키지');
    }

    public function onSearch(SearchEvent $event): void
    {
        if (!$event->isTargetSource('mypackage')) return;
        // 검색 결과 추가
        $event->addResults($results);
    }
}
```

검색은 코어가 패키지 구현을 직접 알지 않는 대표적인 Event 패턴이다. 새 패키지에 검색 화면이 필요하면, 먼저 `SearchSourceCollectEvent` + `SearchEvent` 조합을 검토한다.

## DataResettableInterface (선택)

관리자 데이터 초기화 기능을 제공하려면 Provider에서 구현합니다.

```php
class MyPackageProvider implements ExtensionProviderInterface, DataResettableInterface
{
    public function getResetCategories(): array
    {
        return [[
            'key'         => 'mypackage',
            'label'       => '내 패키지',
            'description' => '데이터를 삭제합니다. (설정은 보존)',
            'icon'        => 'bi-box',
        ]];
    }

    public function reset(string $category, int $domainId, Database $db): array
    {
        if ($category !== 'mypackage') {
            return ['tables_cleared' => 0, 'files_deleted' => 0, 'details' => ''];
        }

        $db->execute("DELETE FROM my_items WHERE domain_id = ?", [$domainId]);

        return [
            'tables_cleared' => 1,
            'files_deleted'  => 0,
            'details'        => '데이터 삭제 완료',
        ];
    }
}
```

## 실제 예시: Board 패키지

Board 패키지의 `register()`에서 등록하는 서비스:

- Repository 12개 (BoardArticleRepository, BoardCommentRepository 등)
- Service 11개 (BoardArticleService, BoardPermissionService 등)
- Block Renderer 2개 (BoardRenderer, BoardGroupRenderer)

`boot()`에서 등록하는 것:

- 블록 콘텐츠 타입 2개 (`board`, `boardgroup`)
- Subscriber 9개 (AdminMenu, Search, Point, Mypage, Block, Domain, Menu 등)
- 대시보드 위젯 1개 (RecentNoticesWidget)

이 규모가 "Package"의 전형적인 크기입니다. Plugin보다 훨씬 크고, 자체적으로 완결된 기능을 제공합니다.

---

[< 이전: 블록 시스템 개발](block-system.md) | [다음: 플러그인 만들기 >](plugin-development.md)
