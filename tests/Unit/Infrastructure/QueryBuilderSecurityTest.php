<?php
/**
 * tests/Unit/Infrastructure/QueryBuilderSecurityTest.php
 *
 * QueryBuilder / JoinClause 식별자·연산자 검증 보안 테스트
 */

namespace Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use Mublo\Infrastructure\Database\QueryBuilder;
use Mublo\Infrastructure\Database\JoinClause;
use Mublo\Infrastructure\Database\DatabaseException;
use Mublo\Infrastructure\Database\Database;

class QueryBuilderSecurityTest extends TestCase
{
    private QueryBuilder $qb;

    protected function setUp(): void
    {
        parent::setUp();

        $db = $this->createMock(Database::class);
        $db->method('prefixTable')->willReturnArgument(0);

        $this->qb = new QueryBuilder($db, 'users');
    }

    // ─── 정상 식별자 ───

    public function testWhereAcceptsValidColumn(): void
    {
        $this->qb->where('email', '=', 'test@example.com');
        $sql = $this->qb->toSql();
        $this->assertStringContainsString('email', $sql);
    }

    public function testWhereAcceptsDottedColumn(): void
    {
        $this->qb->where('u.email', '=', 'test@example.com');
        $sql = $this->qb->toSql();
        $this->assertStringContainsString('u.email', $sql);
    }

    public function testWhereAcceptsUnderscoreColumn(): void
    {
        $this->qb->where('created_at', '>=', '2026-01-01');
        $sql = $this->qb->toSql();
        $this->assertStringContainsString('created_at', $sql);
    }

    public function testOrderByAcceptsValidColumn(): void
    {
        $this->qb->orderBy('created_at', 'DESC');
        $sql = $this->qb->toSql();
        $this->assertStringContainsString('ORDER BY created_at DESC', $sql);
    }

    public function testInsertAcceptsValidKeys(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('prefixTable')->willReturnArgument(0);
        $db->method('insert')->willReturn(1);

        $qb = new QueryBuilder($db, 'users');
        $result = $qb->insert(['name' => 'John', 'email' => 'john@test.com']);
        $this->assertEquals(1, $result);
    }

    public function testUpdateAcceptsValidKeys(): void
    {
        $db = $this->createMock(Database::class);
        $db->method('prefixTable')->willReturnArgument(0);
        $db->method('execute')->willReturn(1);

        $qb = new QueryBuilder($db, 'users');
        $result = $qb->where('id', '=', 1)->update(['name' => 'Jane']);
        $this->assertEquals(1, $result);
    }

    // ─── where() 악성 입력 차단 ───

    public function testWhereRejectsSqlInjectionInColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->where('id; DROP TABLE users', '=', 1);
    }

    public function testWhereRejectsSpaceInColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->where('id DESC', '=', 1);
    }

    public function testWhereRejectsParenthesisInColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->where('sleep(1)', '=', 1);
    }

    public function testWhereRejectsCommentInColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->where('id--', '=', 1);
    }

    public function testWhereRejectsInvalidOperator(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->where('id', 'OR 1=1 --', 1);
    }

    public function testWhereRejectsSubqueryOperator(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->where('id', 'IN (SELECT', 1);
    }

    // ─── whereIn / whereNull / whereBetween 차단 ───

    public function testWhereInRejectsInvalidColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->whereIn('id; DROP TABLE', [1, 2, 3]);
    }

    public function testWhereNotInRejectsInvalidColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->whereNotIn('id OR 1=1', [1]);
    }

    public function testWhereNullRejectsInvalidColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->whereNull('deleted_at IS NOT NULL --');
    }

    public function testWhereNotNullRejectsInvalidColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->whereNotNull('col; DROP');
    }

    public function testWhereBetweenRejectsInvalidColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->whereBetween('price AND 1=1', 0, 100);
    }

    // ─── orderBy 차단 ───

    public function testOrderByRejectsSqlInjection(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->orderBy('id desc, sleep(1)');
    }

    public function testOrderByRejectsSemicolon(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->orderBy('id; --');
    }

    // ─── groupBy 차단 ───

    public function testGroupByRejectsInvalidColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->groupBy('status; DROP TABLE');
    }

    // ─── having 차단 ───

    public function testHavingRejectsInvalidColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->having('COUNT(*)', '>', 5);
    }

    public function testHavingRejectsInvalidOperator(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->having('cnt', 'OR 1=1', 5);
    }

    // ─── insert/update 키 검증 ───

    public function testInsertRejectsInvalidColumnKey(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->insert(['name = NOW()' => 'x']);
    }

    public function testInsertRejectsSqlInKey(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->insert(['id; DROP TABLE users' => 1]);
    }

    public function testUpdateRejectsInvalidColumnKey(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->where('id', '=', 1)->update(['name = NOW()' => 'x']);
    }

    public function testInsertOrUpdateRejectsInvalidInsertKey(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->insertOrUpdate(['col; DROP' => 'val']);
    }

    public function testInsertOrUpdateRejectsInvalidUpdateKey(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->insertOrUpdate(['name' => 'val'], ['col; DROP' => 'val']);
    }

    // ─── JoinClause 검증 ───

    public function testJoinOnAcceptsValidColumns(): void
    {
        $join = new JoinClause('INNER', 'orders');
        $join->on('users.id', '=', 'orders.user_id');
        $sql = $join->toSql();
        $this->assertStringContainsString('users.id = orders.user_id', $sql);
    }

    public function testJoinOnRejectsInvalidFirstColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $join = new JoinClause('INNER', 'orders');
        $join->on('a.id; DROP', '=', 'b.id');
    }

    public function testJoinOnRejectsInvalidSecondColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $join = new JoinClause('INNER', 'orders');
        $join->on('a.id', '=', 'b.id OR 1=1');
    }

    public function testJoinOnRejectsInvalidOperator(): void
    {
        $this->expectException(DatabaseException::class);
        $join = new JoinClause('INNER', 'orders');
        $join->on('a.id', 'LIKE', 'b.id');
    }

    public function testJoinOrOnRejectsInvalidColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $join = new JoinClause('INNER', 'orders');
        $join->on('a.id', '=', 'b.id');
        $join->orOn('a.name; --', '=', 'b.name');
    }

    // ─── 정상 연산자 허용 확인 ───

    /**
     * @dataProvider validOperatorProvider
     */
    public function testWhereAcceptsValidOperators(string $operator): void
    {
        $this->qb->where('id', $operator, 1);
        $sql = $this->qb->toSql();
        $this->assertStringContainsString($operator, $sql);
    }

    public static function validOperatorProvider(): array
    {
        return [
            ['='],
            ['!='],
            ['<>'],
            ['>'],
            ['>='],
            ['<'],
            ['<='],
            ['LIKE'],
            ['NOT LIKE'],
        ];
    }

    // ─── 네스티드 where는 클로저 내부에서도 검증 ───

    public function testNestedWhereRejectsInvalidColumn(): void
    {
        $this->expectException(DatabaseException::class);
        $this->qb->where(function ($query) {
            $query->where('valid_col', '=', 1);
            $query->where('invalid col', '=', 2);
        });
    }
}
