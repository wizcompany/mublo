<?php
namespace Mublo\Infrastructure\Database;

use PDO;

/**
 * QueryBuilder
 *
 * 유연한 SQL 쿼리 빌더
 *
 * 특징:
 * - 메서드 체이닝
 * - Prepared Statement 자동 바인딩
 * - JOIN 지원 (JoinClause)
 * - 복잡한 WHERE 조건
 * - 집계 함수
 * - 페이지네이션
 */
class QueryBuilder
{
    protected Database $db;
    protected string $table;
    protected array $columns = ['*'];
    protected array $joins = [];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $groupBy = [];
    protected array $having = [];
    protected array $orderBy = [];
    protected ?int $limitValue = null;
    protected ?int $offsetValue = null;
    protected bool $distinct = false;

    /**
     * Constructor
     *
     * @param Database $db Database 인스턴스
     * @param string $table 테이블 이름 (프리픽스 자동 적용)
     */
    public function __construct(Database $db, string $table)
    {
        $this->db = $db;
        $this->table = $db->prefixTable($table);
    }

    /**
     * SELECT 컬럼 지정
     *
     * @param string|array $columns 컬럼 목록
     * @return self
     */
    public function select($columns = ['*']): self
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();
        return $this;
    }

    /**
     * DISTINCT 설정
     *
     * @return self
     */
    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    /**
     * 컬럼 식별자 유효성 검증 (SQL Injection 방지)
     *
     * @param string $column 검증할 컬럼명
     * @throws DatabaseException 유효하지 않은 식별자
     */
    private function assertIdentifier(string $column): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column)) {
            throw new DatabaseException("Invalid column identifier: {$column}");
        }
    }

    /**
     * WHERE/HAVING 연산자 유효성 검증
     *
     * @param string $operator 검증할 연산자
     * @throws DatabaseException 허용되지 않은 연산자
     */
    private function assertOperator(string $operator): void
    {
        $allowed = ['=', '!=', '<>', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT', 'BETWEEN', 'NOT BETWEEN'];
        if (!in_array(strtoupper($operator), $allowed, true)) {
            throw new DatabaseException("Invalid operator: {$operator}");
        }
    }

    /**
     * INSERT/UPDATE 컬럼 키 배열 유효성 검증
     *
     * @param array $data 검증할 키-값 배열
     * @throws DatabaseException 유효하지 않은 키
     */
    private function assertColumnKeys(array $data): void
    {
        foreach (array_keys($data) as $key) {
            $this->assertIdentifier((string)$key);
        }
    }

    /**
     * WHERE 조건 추가
     *
     * @param string|callable $column 컬럼명 또는 클로저
     * @param mixed $operator 연산자 또는 값
     * @param mixed $value 값
     * @param string $boolean AND/OR
     * @return self
     */
    public function where($column, $operator = null, $value = null, string $boolean = 'AND'): self
    {
        // 클로저 지원 (중첩 조건)
        // is_callable 대신 Closure 체크 — PHP 내장 함수명(filter_id 등)과 충돌 방지
        if ($column instanceof \Closure) {
            return $this->whereNested($column, $boolean);
        }

        // 2개 인자: where('column', 'value') -> where('column', '=', 'value')
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->assertIdentifier($column);
        $this->assertOperator($operator);

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => $boolean,
        ];

        $this->addBinding($value);

        return $this;
    }

    /**
     * OR WHERE 조건 추가
     *
     * @param string|callable $column 컬럼명 또는 클로저
     * @param mixed $operator 연산자 또는 값
     * @param mixed $value 값
     * @return self
     */
    public function orWhere($column, $operator = null, $value = null): self
    {
        return $this->where($column, $operator, $value, 'OR');
    }

    /**
     * WHERE IN 조건
     *
     * @param string $column 컬럼명
     * @param array $values 값 배열
     * @param string $boolean AND/OR
     * @return self
     */
    public function whereIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $this->assertIdentifier($column);

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];

        foreach ($values as $value) {
            $this->addBinding($value);
        }

        return $this;
    }

    /**
     * OR WHERE IN 조건
     *
     * @param string $column 컬럼명
     * @param array $values 값 배열
     * @return self
     */
    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'OR');
    }

    /**
     * WHERE NOT IN 조건
     *
     * @param string $column 컬럼명
     * @param array $values 값 배열
     * @param string $boolean AND/OR
     * @return self
     */
    public function whereNotIn(string $column, array $values, string $boolean = 'AND'): self
    {
        $this->assertIdentifier($column);

        $this->wheres[] = [
            'type' => 'not_in',
            'column' => $column,
            'values' => $values,
            'boolean' => $boolean,
        ];

        foreach ($values as $value) {
            $this->addBinding($value);
        }

        return $this;
    }

    /**
     * OR WHERE NOT IN 조건
     *
     * @param string $column 컬럼명
     * @param array $values 값 배열
     * @return self
     */
    public function orWhereNotIn(string $column, array $values): self
    {
        return $this->whereNotIn($column, $values, 'OR');
    }

    /**
     * WHERE NULL 조건
     *
     * @param string $column 컬럼명
     * @param string $boolean AND/OR
     * @return self
     */
    public function whereNull(string $column, string $boolean = 'AND'): self
    {
        $this->assertIdentifier($column);

        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * OR WHERE NULL 조건
     *
     * @param string $column 컬럼명
     * @return self
     */
    public function orWhereNull(string $column): self
    {
        return $this->whereNull($column, 'OR');
    }

    /**
     * WHERE NOT NULL 조건
     *
     * @param string $column 컬럼명
     * @param string $boolean AND/OR
     * @return self
     */
    public function whereNotNull(string $column, string $boolean = 'AND'): self
    {
        $this->assertIdentifier($column);

        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * OR WHERE NOT NULL 조건
     *
     * @param string $column 컬럼명
     * @return self
     */
    public function orWhereNotNull(string $column): self
    {
        return $this->whereNotNull($column, 'OR');
    }

    /**
     * WHERE BETWEEN 조건
     *
     * @param string $column 컬럼명
     * @param mixed $min 최소값
     * @param mixed $max 최대값
     * @param string $boolean AND/OR
     * @return self
     */
    public function whereBetween(string $column, $min, $max, string $boolean = 'AND'): self
    {
        $this->assertIdentifier($column);

        $this->wheres[] = [
            'type' => 'between',
            'column' => $column,
            'min' => $min,
            'max' => $max,
            'boolean' => $boolean,
        ];

        $this->addBinding($min);
        $this->addBinding($max);

        return $this;
    }

    /**
     * OR WHERE BETWEEN 조건
     *
     * @param string $column 컬럼명
     * @param mixed $min 최소값
     * @param mixed $max 최대값
     * @return self
     */
    public function orWhereBetween(string $column, $min, $max): self
    {
        return $this->whereBetween($column, $min, $max, 'OR');
    }

    /**
     * WHERE RAW (원시 SQL 조건)
     *
     * @param string $sql SQL 조건
     * @param array $bindings 바인딩 값
     * @param string $boolean AND/OR
     * @return self
     */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = [
            'type' => 'raw',
            'sql' => $sql,
            'boolean' => $boolean,
        ];

        foreach ($bindings as $binding) {
            $this->addBinding($binding);
        }

        return $this;
    }

    /**
     * OR WHERE RAW
     *
     * @param string $sql SQL 조건
     * @param array $bindings 바인딩 값
     * @return self
     */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->whereRaw($sql, $bindings, 'OR');
    }

    /**
     * 중첩 WHERE 조건 (클로저)
     *
     * @param callable $callback 클로저
     * @param string $boolean AND/OR
     * @return self
     */
    protected function whereNested(callable $callback, string $boolean = 'AND'): self
    {
        $query = new static($this->db, '');
        $query->table = $this->table;

        call_user_func($callback, $query);

        if (!empty($query->wheres)) {
            $this->wheres[] = [
                'type' => 'nested',
                'query' => $query,
                'boolean' => $boolean,
            ];

            foreach ($query->bindings as $binding) {
                $this->addBinding($binding);
            }
        }

        return $this;
    }

    /**
     * JOIN 추가
     *
     * @param string $table 테이블명
     * @param string|callable $first 첫 번째 컬럼 또는 클로저
     * @param string|null $operator 연산자
     * @param string|null $second 두 번째 컬럼
     * @param string $type JOIN 타입
     * @return self
     */
    public function join(string $table, $first, ?string $operator = null, ?string $second = null, string $type = 'INNER'): self
    {
        $join = new JoinClause($type, $this->db->prefixTable($table));

        if (is_callable($first)) {
            call_user_func($first, $join);
        } else {
            $join->on($first, $operator, $second);
        }

        $this->joins[] = $join;

        return $this;
    }

    /**
     * LEFT JOIN
     *
     * @param string $table 테이블명
     * @param string|callable $first 첫 번째 컬럼 또는 클로저
     * @param string|null $operator 연산자
     * @param string|null $second 두 번째 컬럼
     * @return self
     */
    public function leftJoin(string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * RIGHT JOIN
     *
     * @param string $table 테이블명
     * @param string|callable $first 첫 번째 컬럼 또는 클로저
     * @param string|null $operator 연산자
     * @param string|null $second 두 번째 컬럼
     * @return self
     */
    public function rightJoin(string $table, $first, ?string $operator = null, ?string $second = null): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * CROSS JOIN
     *
     * @param string $table 테이블명
     * @return self
     */
    public function crossJoin(string $table): self
    {
        $join = new JoinClause('CROSS', $this->db->prefixTable($table));
        $this->joins[] = $join;

        return $this;
    }

    /**
     * GROUP BY 추가
     *
     * @param string|array $columns 컬럼 목록
     * @return self
     */
    public function groupBy($columns): self
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        // 컬럼명 형식 검증 (SQL Injection 방지)
        foreach ($columns as $col) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $col)) {
                throw new DatabaseException("Invalid GROUP BY column: {$col}");
            }
        }

        $this->groupBy = array_merge($this->groupBy, $columns);

        return $this;
    }

    /**
     * HAVING 조건 추가
     *
     * @param string $column 컬럼명
     * @param string $operator 연산자
     * @param mixed $value 값
     * @return self
     */
    public function having(string $column, string $operator, $value): self
    {
        $this->assertIdentifier($column);
        $this->assertOperator($operator);

        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND',
        ];

        $this->addBinding($value);

        return $this;
    }

    /**
     * OR HAVING 조건 추가
     *
     * @param string $column 컬럼명
     * @param string $operator 연산자
     * @param mixed $value 값
     * @return self
     */
    public function orHaving(string $column, string $operator, $value): self
    {
        $this->having[] = [
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR',
        ];

        $this->addBinding($value);

        return $this;
    }

    /**
     * ORDER BY 추가
     *
     * @param string $column 컬럼명 또는 RAW SQL
     * @param string $direction 정렬 방향 (ASC/DESC)
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        // 컬럼명 형식 검증 (SQL Injection 방지)
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_.]*$/', $column)) {
            throw new DatabaseException("Invalid ORDER BY column: {$column}");
        }

        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            $direction = 'ASC';
        }

        $this->orderBy[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * ORDER BY DESC
     *
     * @param string $column 컬럼명
     * @return self
     */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * ORDER BY (Raw SQL)
     *
     * @param string $sql SQL 표현식 (예: "FIELD(id, ?, ?, ?)")
     * @param array $bindings 바인딩 값
     * @return self
     */
    public function orderByRaw(string $sql, array $bindings = []): self
    {
        $this->orderBy[] = [
            'raw' => $sql,
        ];

        if (!empty($bindings)) {
            $this->bindings = array_merge($this->bindings, $bindings);
        }

        return $this;
    }

    /**
     * LIMIT 설정
     *
     * @param int $limit 제한 수
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limitValue = $limit;
        return $this;
    }

    /**
     * OFFSET 설정
     *
     * @param int $offset 오프셋
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offsetValue = $offset;
        return $this;
    }

    /**
     * LIMIT과 OFFSET을 동시에 설정 (페이징)
     *
     * @param int $limit 제한 수
     * @param int $offset 오프셋
     * @return self
     */
    public function take(int $limit, int $offset = 0): self
    {
        $this->limit($limit);
        $this->offset($offset);
        return $this;
    }

    /**
     * 페이지 기반 페이징
     *
     * @param int $page 페이지 번호 (1부터 시작)
     * @param int $perPage 페이지당 항목 수
     * @return self
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        $offset = ($page - 1) * $perPage;
        return $this->take($perPage, $offset);
    }

    /**
     * SELECT 쿼리 실행 (여러 행)
     *
     * @return array
     * @throws DatabaseException
     */
    public function get(): array
    {
        $sql = $this->toSql();
        
        // [측정 시작]
        //$qStart = microtime(true);
        $result = $this->db->select($sql, $this->bindings);
        //$qEnd = microtime(true);
        
        // 전역 변수 누적
        //$GLOBALS['__queryTime'] = ($GLOBALS['__queryTime'] ?? 0) + ($qEnd - $qStart) * 1000;
        //$GLOBALS['__queryCount'] = ($GLOBALS['__queryCount'] ?? 0) + 1;
        
        return $result;
    }
    
    /**
     * SELECT 쿼리 실행 (단일 행)
     *
     * @return array|null
     * @throws DatabaseException
     */
    public function first(): ?array
    {
        $sql = $this->limit(1)->toSql();
        return $this->db->selectOne($sql, $this->bindings);
    }

    /**
     * COUNT 집계
     *
     * @param string $column 컬럼명
     * @return int
     * @throws DatabaseException
     */
    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate('COUNT', $column);
    }

    /**
     * 레코드 존재 여부 확인
     *
     * @return bool
     * @throws DatabaseException
     */
    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * SUM 집계
     *
     * @param string $column 컬럼명
     * @return float
     * @throws DatabaseException
     */
    public function sum(string $column): float
    {
        return (float) $this->aggregate('SUM', $column);
    }

    /**
     * AVG 집계
     *
     * @param string $column 컬럼명
     * @return float
     * @throws DatabaseException
     */
    public function avg(string $column): float
    {
        return (float) $this->aggregate('AVG', $column);
    }

    /**
     * MAX 집계
     *
     * @param string $column 컬럼명
     * @return mixed
     * @throws DatabaseException
     */
    public function max(string $column)
    {
        return $this->aggregate('MAX', $column);
    }

    /**
     * MIN 집계
     *
     * @param string $column 컬럼명
     * @return mixed
     * @throws DatabaseException
     */
    public function min(string $column)
    {
        return $this->aggregate('MIN', $column);
    }

    /**
     * 집계 함수 실행
     *
     * @param string $function 집계 함수명
     * @param string $column 컬럼명
     * @return mixed
     * @throws DatabaseException
     */
    protected function aggregate(string $function, string $column)
    {
        $originalColumns = $this->columns;
        $this->columns = ["{$function}({$column}) as aggregate"];

        $sql = $this->toSql();
        $result = $this->db->selectOne($sql, $this->bindings);

        $this->columns = $originalColumns;

        return $result['aggregate'] ?? 0;
    }

    /**
     * INSERT 쿼리 실행
     *
     * @param array $data 삽입 데이터
     * @return int 마지막 삽입 ID
     * @throws DatabaseException
     */
    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new DatabaseException('Insert data cannot be empty');
        }

        $this->assertColumnKeys($data);

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        return $this->db->insert($sql, array_values($data));
    }

    /**
     * INSERT ... ON DUPLICATE KEY UPDATE
     *
     * @param array $data 삽입 데이터
     * @param array $updateData 업데이트 데이터 (생략 시 $data 사용)
     * @return int
     * @throws DatabaseException
     */
    public function insertOrUpdate(array $data, array $updateData = []): int
    {
        if (empty($data)) {
            throw new DatabaseException('Insert data cannot be empty');
        }

        if (empty($updateData)) {
            $updateData = $data;
        }

        $this->assertColumnKeys($data);
        $this->assertColumnKeys($updateData);

        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";

        $updateParts = [];
        foreach (array_keys($updateData) as $column) {
            $updateParts[] = "{$column} = ?";
        }

        $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);

        $bindings = array_merge(array_values($data), array_values($updateData));

        return $this->db->insert($sql, $bindings);
    }

    /**
     * UPDATE 쿼리 실행
     *
     * @param array $data 업데이트 데이터
     * @return int 영향받은 행 수
     * @throws DatabaseException
     */
    public function update(array $data): int
    {
        if (empty($data)) {
            throw new DatabaseException('Update data cannot be empty');
        }

        $this->assertColumnKeys($data);

        $setParts = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $setParts[] = "{$column} = ?";
            $bindings[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $setParts);

        if (!empty($this->wheres)) {
            $sql .= ' ' . $this->buildWhere();
            $bindings = array_merge($bindings, $this->bindings);
        }

        return $this->db->execute($sql, $bindings);
    }

    /**
     * DELETE 쿼리 실행
     *
     * @return int 영향받은 행 수
     * @throws DatabaseException
     */
    public function delete(): int
    {
        $sql = "DELETE FROM {$this->table}";

        if (!empty($this->wheres)) {
            $sql .= ' ' . $this->buildWhere();
        }

        return $this->db->execute($sql, $this->bindings);
    }

    /**
     * SELECT SQL 생성
     *
     * @return string
     */
    public function toSql(): string
    {
        $sql = 'SELECT ';

        if ($this->distinct) {
            $sql .= 'DISTINCT ';
        }

        $sql .= implode(', ', $this->columns);
        $sql .= " FROM {$this->table}";

        // JOIN
        if (!empty($this->joins)) {
            foreach ($this->joins as $join) {
                $sql .= ' ' . $join->toSql();
            }
        }

        // WHERE
        if (!empty($this->wheres)) {
            $sql .= ' ' . $this->buildWhere();
        }

        // GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        // HAVING
        if (!empty($this->having)) {
            $sql .= ' ' . $this->buildHaving();
        }

        // ORDER BY
        if (!empty($this->orderBy)) {
            $parts = [];
            foreach ($this->orderBy as $order) {
                if (isset($order['raw'])) {
                    $parts[] = $order['raw'];
                } else {
                    $parts[] = "{$order['column']} {$order['direction']}";
                }
            }
            $sql .= ' ORDER BY ' . implode(', ', $parts);
        }

        // LIMIT
        if ($this->limitValue !== null) {
            $sql .= " LIMIT {$this->limitValue}";
        }

        // OFFSET
        if ($this->offsetValue !== null) {
            $sql .= " OFFSET {$this->offsetValue}";
        }

        return $sql;
    }

    /**
     * WHERE 절 SQL 생성
     *
     * @return string
     */
    protected function buildWhere(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = 'WHERE ';
        $parts = [];

        foreach ($this->wheres as $index => $where) {
            $part = '';

            if ($index > 0) {
                $part .= "{$where['boolean']} ";
            }

            switch ($where['type']) {
                case 'basic':
                    $part .= "{$where['column']} {$where['operator']} ?";
                    break;

                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $part .= "{$where['column']} IN ({$placeholders})";
                    break;

                case 'not_in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $part .= "{$where['column']} NOT IN ({$placeholders})";
                    break;

                case 'null':
                    $part .= "{$where['column']} IS NULL";
                    break;

                case 'not_null':
                    $part .= "{$where['column']} IS NOT NULL";
                    break;

                case 'between':
                    $part .= "{$where['column']} BETWEEN ? AND ?";
                    break;

                case 'raw':
                    $part .= $where['sql'];
                    break;

                case 'nested':
                    $nestedSql = $where['query']->buildWhere();
                    $part .= '(' . substr($nestedSql, 6) . ')'; // "WHERE " 제거
                    break;
            }

            $parts[] = $part;
        }

        return $sql . implode(' ', $parts);
    }

    /**
     * HAVING 절 SQL 생성
     *
     * @return string
     */
    protected function buildHaving(): string
    {
        if (empty($this->having)) {
            return '';
        }

        $sql = 'HAVING ';
        $parts = [];

        foreach ($this->having as $index => $having) {
            $part = '';

            if ($index > 0) {
                $part .= "{$having['boolean']} ";
            }

            $part .= "{$having['column']} {$having['operator']} ?";
            $parts[] = $part;
        }

        return $sql . implode(' ', $parts);
    }

    /**
     * 바인딩 추가
     *
     * @param mixed $value 바인딩 값
     */
    protected function addBinding($value): void
    {
        $this->bindings[] = $value;
    }

    /**
     * 쿼리 초기화 (재사용)
     *
     * @return self
     */
    public function reset(): self
    {
        $this->columns = ['*'];
        $this->joins = [];
        $this->wheres = [];
        $this->bindings = [];
        $this->groupBy = [];
        $this->having = [];
        $this->orderBy = [];
        $this->limitValue = null;
        $this->offsetValue = null;
        $this->distinct = false;

        return $this;
    }

    /**
     * 현재 바인딩 값 반환 (디버깅용)
     *
     * @return array
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * 디버그: SQL과 바인딩 출력
     *
     * @return array
     */
    public function debug(): array
    {
        return [
            'sql' => $this->toSql(),
            'bindings' => $this->bindings,
        ];
    }
}
