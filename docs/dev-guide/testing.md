# 테스트


## 테스트 구조

```
tests/
├── Bootstrap.php          # 경로 상수, 환경 로딩
├── TestCase.php           # 기본 테스트 클래스
├── Unit/                  # 단위 테스트
│   ├── Core/              # App, Container, Context, Event, Http, Registry, Response
│   ├── Entity/            # Balance, Board
│   ├── Infrastructure/    # QueryBuilder 보안
│   ├── Packages/          # Shop 등 패키지 관련 테스트
│   ├── Repository/        # Balance, Board
│   └── Service/           # AdminMenu, Auth, Balance, Board, Member, Settings
├── Feature/               # 기능 테스트
│   └── Http/              # 라우팅, 요청/응답 흐름
└── Integration/           # 통합 테스트
    └── (Database 등)
```

## 테스트 실행

```bash
# 전체 테스트
composer test

# DI 위반 검사 + 테스트
composer check

# 특정 테스트만
vendor/bin/phpunit --filter=DispatcherTest

# 특정 스위트만
vendor/bin/phpunit --testsuite=Unit
```

## PHPUnit 설정

`phpunit.xml`:

```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>
    <testsuite name="Integration">
        <directory>tests/Integration</directory>
    </testsuite>
</testsuites>
```

환경: `APP_ENV=testing`, `APP_DEBUG=true`

## TestCase 기본 클래스

`tests/TestCase.php`

모든 테스트가 상속하는 기본 클래스입니다.

```php
namespace Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class TestCase extends PHPUnitTestCase
{
    // DI 컨테이너 접근
    protected function getContainer(): DependencyContainer { ... }

    // 임시 파일 경로
    protected function getTempPath(): string { ... }

    // tearDown 시 임시 파일 정리
    protected function cleanupTemp(): void { ... }
}
```

## 테스트 작성 패턴

### Unit 테스트 — Service, Entity

외부 의존성 없이 클래스의 로직만 테스트합니다.

```php
namespace Tests\Unit\Core\App;

use Tests\TestCase;
use Mublo\Core\App\Dispatcher;

class DispatcherTest extends TestCase
{
    public function testDispatchInjectsParamsAndContext(): void
    {
        $container = $this->getContainer();
        $dispatcher = new Dispatcher($container);

        // ... 테스트 로직 ...

        $this->assertInstanceOf(AbstractResponse::class, $response);
    }

    public function testDispatchRejectsNonPublicMethod(): void
    {
        $this->expectException(HttpNotFoundException::class);

        // private 메서드 호출 시도 → 예외
    }
}
```

### Integration 테스트 — Repository, DB

실제 데이터베이스 연결이 필요한 테스트입니다.

```php
namespace Tests\Integration;

use Tests\TestCase;

class DatabaseTest extends TestCase
{
    public function testDatabaseConnection(): void
    {
        $db = $this->getContainer()->get(Database::class);
        $this->assertNotNull($db);
    }
}
```

### Feature 테스트 — HTTP 흐름

전체 요청/응답 흐름을 테스트합니다.

```php
namespace Tests\Feature\Http;

use Tests\TestCase;

class RoutingTest extends TestCase
{
    public function testRouterClassExists(): void
    {
        $this->assertTrue(class_exists(Router::class));
    }
}
```

## TDD 워크플로우

```
1. 테스트 작성 (Red)   → 실패하는 테스트 먼저 작성
2. 코드 구현 (Green)   → 테스트를 통과하는 최소한의 코드
3. 리팩토링 (Refactor) → 코드 정리 (테스트는 계속 통과)
```

## DI 위반 검사

```bash
composer check-di
```

`tools/check-di-violations.php`가 Controller에서 Service를 건너뛰고 Repository를 직접 사용하는 등의 계층 위반을 검사합니다.

---

[< 이전: 플러그인 만들기](plugin-development.md) | [다음: 기여 가이드 >](contributing.md)
