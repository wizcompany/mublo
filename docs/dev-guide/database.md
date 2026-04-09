# 데이터베이스

## Database 클래스 API

`src/Infrastructure/Database/Database.php`

PDO 래퍼로, 안전한 쿼리 실행과 트랜잭션, 느린 쿼리 감지를 제공합니다.

### SELECT — 여러 행 조회

```php
$rows = $db->select('SELECT * FROM members WHERE status = ?', ['active']);
// 반환: array (연관 배열의 배열)
```

### SELECT — 단일 행 조회

```php
$row = $db->selectOne('SELECT * FROM members WHERE member_id = ?', [1]);
// 반환: array|null
```

### INSERT — ID 반환

```php
$id = $db->insert(
    'INSERT INTO members (userid, nickname) VALUES (?, ?)',
    ['john', 'John']
);
// 반환: int (lastInsertId)
```

### UPDATE / DELETE — 영향받은 행 수

```php
$affected = $db->execute(
    'UPDATE members SET status = ? WHERE member_id = ?',
    ['inactive', 1]
);
// 반환: int (rowCount)
```

> **존재하지 않는 메서드:** `query()`, `getPrefix()`, `raw()` — 전부 없습니다. 위 4개가 전부입니다.

## QueryBuilder

`$db->table('테이블명')`으로 시작하여 메서드 체이닝으로 쿼리를 구성합니다.

### 기본 조회

```php
// 전체 조회
$rows = $db->table('members')->where('status', 'active')->get();

// 단일 행
$row = $db->table('members')->where('member_id', 1)->first();

// 컬럼 지정
$rows = $db->table('members')
    ->select('member_id', 'userid', 'nickname')
    ->where('status', 'active')
    ->get();
```

### WHERE 조건

```php
// 기본 (= 생략 가능)
->where('status', 'active')
->where('age', '>', 18)
->where('score', '>=', 100)

// OR 조건
->orWhere('status', 'pending')

// IN / NOT IN
->whereIn('status', ['active', 'pending'])
->whereNotIn('level_value', [1, 2])

// NULL 체크
->whereNull('deleted_at')
->whereNotNull('email')

// BETWEEN
->whereBetween('created_at', '2025-01-01', '2025-12-31')

// Raw SQL (사용자 입력 금지)
->whereRaw('YEAR(created_at) = ?', [2025])

// 중첩 조건 (Closure)
->where(function($q) {
    $q->where('status', 'active')
      ->orWhere('status', 'pending');
})
```

### JOIN

```php
// INNER JOIN
$db->table('members as m')
    ->select('m.member_id', 'm.nickname', 'o.order_no')
    ->join('orders as o', 'm.member_id', '=', 'o.member_id')
    ->get();

// LEFT JOIN
->leftJoin('orders as o', 'm.member_id', '=', 'o.member_id')

// 복합 조건 JOIN
->join('orders', function($join) {
    $join->on('members.member_id', '=', 'orders.member_id')
         ->orOn('members.backup_id', '=', 'orders.member_id');
})
```

### GROUP BY / HAVING

```php
$db->table('members')
    ->select('level_value')
    ->groupBy('level_value')
    ->having('count', '>', 10)
    ->get();
```

### ORDER BY / LIMIT

```php
->orderBy('created_at', 'DESC')   // 또는 orderByDesc('created_at')
->limit(20)
->offset(40)

// 페이지 기반 (page=3, perPage=20 → offset=40)
->forPage(3, 20)
```

### INSERT / UPDATE / DELETE

```php
// INSERT
$id = $db->table('members')->insert([
    'userid' => 'john',
    'nickname' => 'John',
    'status' => 'active',
]);

// UPDATE
$affected = $db->table('members')
    ->where('member_id', 1)
    ->update(['status' => 'inactive']);

// DELETE (반드시 WHERE와 함께)
$deleted = $db->table('members')
    ->where('status', 'blocked')
    ->delete();

// INSERT OR UPDATE (UPSERT)
$db->table('settings')->insertOrUpdate(
    ['key' => 'site_name', 'value' => 'Mublo'],  // INSERT 데이터
    ['value' => 'Mublo']                           // UPDATE 데이터
);
```

### 집계 함수

```php
$db->table('members')->count();                          // int
$db->table('members')->where('status', 'active')->exists(); // bool
$db->table('orders')->sum('total_amount');               // float
$db->table('orders')->avg('total_amount');               // float
$db->table('orders')->max('total_amount');               // mixed
$db->table('orders')->min('total_amount');               // mixed
```

### 디버깅

```php
// SQL 확인 (실행하지 않음)
$sql = $db->table('members')->where('status', 'active')->toSql();
// → "SELECT * FROM members WHERE status = ?"

// 바인딩 값 확인
$bindings = $db->table('members')->where('status', 'active')->getBindings();
// → ['active']

// 쿼리 로그 활성화
$db->enableQueryLog(true);
$db->setSlowQueryThreshold(0.5); // 500ms 이상이면 느린 쿼리
// ... 쿼리 실행 ...
$log = $db->getQueryLog(); // [{query, params, duration_ms, error, time}, ...]
```

## 트랜잭션

### 수동 트랜잭션

```php
$db->beginTransaction();
try {
    $db->execute('UPDATE members SET point_balance = point_balance - ? WHERE member_id = ?', [100, $memberId]);
    $db->insert('INSERT INTO balance_logs (...) VALUES (...)', [...]);
    $db->commit();
} catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
}
```

### 콜백 트랜잭션

```php
$result = $db->transaction(function(Database $db) {
    $db->execute(...);
    $db->insert(...);
    return $logId; // 반환값이 $result에 담김
});
// 예외 발생 시 자동 rollBack
```

## BaseRepository

`src/Repository/BaseRepository.php`

모든 Repository의 부모 클래스입니다. 상속하면 기본 CRUD가 제공됩니다.

### 자식 클래스 작성

```php
class MemberRepository extends BaseRepository
{
    protected string $table = 'members';
    protected string $entityClass = Member::class;
    protected string $primaryKey = 'member_id';

    // 커스텀 메서드 추가
    public function findByDomainAndUserId(int $domainId, string $userId): ?Member
    {
        $row = $this->db->table($this->table)
            ->where('domain_id', $domainId)
            ->where('userid', $userId)
            ->first();

        return $row ? $this->entityClass::fromArray($row) : null;
    }
}
```

### 제공되는 메서드

| 메서드 | 반환 | 설명 |
|--------|------|------|
| `find($id)` | `?Entity` | ID로 단일 조회 |
| `all($limit, $offset)` | `Entity[]` | 전체 조회 |
| `findBy($conditions)` | `Entity[]` | 조건 조회 |
| `findOneBy($conditions)` | `?Entity` | 조건으로 단일 조회 |
| `existsBy($conditions)` | `bool` | 존재 여부 |
| `countBy($conditions)` | `int` | 개수 |
| `create($data)` | `int\|null` | 생성 (ID 반환) |
| `update($id, $data)` | `int` | 수정 (영향 행 수) |
| `delete($id)` | `int` | 삭제 (영향 행 수) |
| `paginate($page, $perPage)` | `array` | 페이지네이션 |
| `getDb()` | `Database` | DB 인스턴스 접근 |

`create()`은 `created_at`을, `update()`는 `updated_at`을 자동으로 추가합니다.

## 마이그레이션

### 파일 위치

| 대상 | 위치 |
|------|------|
| Core | `database/migrations/` |
| Package | `packages/{Name}/database/migrations/` |
| Plugin | `plugins/{Name}/database/migrations/` |

### 네이밍 규칙

```
{번호}_{설명}.sql
```

- `001_create_domain_tables.sql`
- `002_create_member_tables.sql`
- `010_add_article_thumbnail.sql`

번호 순서대로 실행됩니다.

현재 마이그레이션은 1.0.0 기준 최종 스키마를 반영한 베이스라인입니다.
Core, Package, Plugin의 초기 `CREATE` 파일에는 현재 최종 컬럼이 이미 포함되어 있고, 이후 스키마 변경이 생기면 후속 번호의 업데이트 마이그레이션을 추가합니다.

### SQL 파일 형식

```sql
-- 주석은 무시됩니다

CREATE TABLE IF NOT EXISTS members (
    member_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    domain_id INT UNSIGNED NOT NULL,
    userid VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    nickname VARCHAR(100) NOT NULL,
    status ENUM('active','inactive','dormant','blocked') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_domain_userid (domain_id, userid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 여러 문장을 세미콜론으로 구분
ALTER TABLE members ADD COLUMN email VARCHAR(200) NULL AFTER nickname;
```

### @optional-table 지시자

참조하는 테이블이 없을 때 건너뛰도록 표시합니다.

```sql
-- @optional-table: shop_orders

ALTER TABLE members ADD FOREIGN KEY (id) REFERENCES shop_orders(member_id);
```

### 마이그레이션 추적

`schema_migrations` 테이블이 실행 이력을 관리합니다.

```sql
CREATE TABLE schema_migrations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source ENUM('core', 'plugin', 'package') NOT NULL,
    name VARCHAR(100) NOT NULL,              -- 'core', 'Banner', 'Board' 등
    file VARCHAR(200) NOT NULL,              -- '001_create_domain_tables.sql'
    executed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_migration (name, file)
);
```

### 멱등성 처리

마이그레이션 러너는 아래 오류를 자동으로 무시합니다:
- 컬럼 중복 (1060)
- 키 중복 (1061)
- 컬럼 미존재 (1054)
- 컬럼/키 삭제 불가 (1091)

이미 적용된 변경이면 에러 없이 넘어갑니다.

> **주의:** 테이블명에 `{prefix}` 플레이스홀더를 사용하지 마세요. 접두사 시스템은 완전히 제거되었습니다. 테이블명을 그대로 씁니다.

---

[< 이전: 라우팅과 미들웨어](routing.md) | [다음: 이벤트 시스템 >](event-system.md)
