# 기여 가이드

Mublo Shop 패키지에 기여해 주셔서 감사합니다.

## 개발 환경 설정

```bash
# 저장소 클론
git clone https://github.com/mublo-framework/shop.git
cd shop

# Mublo Framework에 패키지 배치
# packages/Shop/ 에 위치해야 합니다

# 의존성 설치 (프레임워크 루트에서)
composer install

# 테스트 실행
./vendor/bin/phpunit packages/Shop/tests/
```

## 브랜치 전략

| 브랜치 | 용도 |
|--------|------|
| `main` | 안정 릴리즈 |
| `develop` | 다음 릴리즈 개발 |
| `feature/{기능명}` | 새 기능 |
| `fix/{버그명}` | 버그 수정 |

## Pull Request 절차

1. `develop` 브랜치에서 작업 브랜치를 생성합니다.
2. 코드를 작성하고 **테스트를 함께** 추가합니다.
3. 모든 테스트가 통과하는지 확인합니다.
4. PR 제목은 한국어로 작성합니다.
5. `develop` → `main` 방향으로 PR을 작성합니다.

## 코딩 규칙

### 네이밍
- 클래스/인터페이스: `PascalCase`
- 메서드/변수: `camelCase`
- 상수: `UPPER_SNAKE_CASE`
- DB 컬럼: `snake_case`

### 계층 규칙

```
Controller → Service → Repository → DB
```

- **Controller**: Request 파싱, Response 반환만 담당. 비즈니스 로직 금지.
- **Service**: 비즈니스 로직, `Result` 객체 반환.
- **Repository**: DB 쿼리, Entity 반환. 비즈니스 로직 금지.
- **Entity**: 데이터 표현과 상태 판단 메서드만 포함.

### Result 패턴

```php
// Service에서
return Result::success('처리 완료', ['id' => $id]);
return Result::failure('오류 메시지');

// Controller에서
if ($result->isSuccess()) { ... }
$value = $result->get('key', $default);
```

### DB API

```php
$this->db->select($sql, $params);     // SELECT 여러 행
$this->db->selectOne($sql, $params);  // SELECT 단일 행
$this->db->insert($sql, $params);     // INSERT → lastInsertId
$this->db->execute($sql, $params);    // UPDATE/DELETE → rowCount
```

## 테스트 작성

새 기능에는 반드시 단위 테스트를 추가합니다.

```php
namespace Tests\Shop\Unit\Service;

use Tests\Shop\TestCase;

class MyServiceTest extends TestCase
{
    public function testSomething(): void
    {
        // Arrange
        $repo = $this->createMock(MyRepository::class);
        $service = new MyService($repo);

        // Act
        $result = $service->doSomething([...]);

        // Assert
        $this->assertTrue($result->isSuccess());
    }
}
```

테스트 실행:

```bash
./vendor/bin/phpunit packages/Shop/tests/
```

## 커밋 메시지

- 한국어로 작성합니다.
- `feat:`, `fix:`, `refactor:`, `test:`, `docs:` 접두사를 사용합니다.

```
feat(shop): 기획전 아이템 정렬 기능 추가
fix(shop): 쿠폰 만료일 비교 오류 수정
test(shop): OrderService 상태 전이 테스트 추가
```

## 마이그레이션

DB 스키마 변경 시 `database/migrations/` 에 순번 파일을 추가합니다.

```
database/migrations/042_add_exhibition_view_count.sql
```

파일명 형식: `{순번}_{설명}.sql`

## 라이선스

이 패키지에 기여하면 MIT 라이선스로 배포되는 것에 동의한 것으로 간주됩니다.
