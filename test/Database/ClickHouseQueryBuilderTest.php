<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQueryTest\Database;

use TxAdmin\ClickHouseQuery\Exceptions\ClickHouseQueryException;
use TxAdmin\ClickHouseQueryTest\Mocks\MockConnectionAdapter;

/**
 * ClickHouseQueryBuilder 及其 Trait 公开方法单元测试
 *
 * SQL 构建类用例通过 toSql() / getBuildWhere() 断言，无需真实数据库。
 * 执行类用例（get/first/count/paginate）通过 MockConnectionAdapter 模拟返回。
 *
 * @covers \TxAdmin\ClickHouseQuery\Database\QueryBuilder\ClickHouseQueryBuilder
 */
class ClickHouseQueryBuilderTest extends DatabaseTestCase
{
    // =========================================================================
    // SELECT / FROM
    // =========================================================================

    /** @test select() 指定字段，toSql() 输出对应 SELECT 子句 */
    public function testSelectToSql(): void
    {
        $sql = $this->createBuilder()
            ->select(['id', 'name'])
            ->toSql();

        $this->assertStringContainsString('SELECT id, name', $sql);
        $this->assertStringContainsString('FROM test_table', $sql);
    }

    /** @test 未调用 select() 时默认 SELECT * */
    public function testDefaultSelectUsesStar(): void
    {
        $sql = $this->createBuilder()->toSql();

        $this->assertStringContainsString('SELECT *', $sql);
    }

    /** @test from() 覆盖构造器传入的默认表名 */
    public function testFromOverridesDefaultTable(): void
    {
        $sql = $this->createBuilder()
            ->from('other_table')
            ->toSql();

        $this->assertStringContainsString('FROM other_table', $sql);
        $this->assertStringNotContainsString('FROM test_table', $sql);
    }

    /** @test getFrom() 返回自定义 FROM 值 */
    public function testGetFrom(): void
    {
        $builder = $this->createBuilder()->from('custom_from');

        $this->assertSame('custom_from', $builder->getFrom());
    }

    /** @test with() 构建 CTE 子查询，主查询 FROM 引用别名 */
    public function testWithClause(): void
    {
        $sql = $this->createBuilder()
            ->with('sub', function ($q) {
                $q->select(['id'])->where(['status' => 1]);
            })
            ->from('sub')
            ->select(['id'])
            ->toSql();

        $this->assertStringContainsString('WITH sub AS (', $sql);
        $this->assertStringContainsString('FROM sub', $sql);
    }

    // =========================================================================
    // WHERE 条件（WhereBuilderTrait）
    // =========================================================================

    /** @test where() 支持等值写法与 [field, operator, value] 数组写法 */
    public function testWhereEqualAndArrayCondition(): void
    {
        $sql = $this->createBuilder()
            ->where(['status' => 1])
            ->where([['age', '>', 18]])
            ->toSql();

        $this->assertStringContainsString('`status` = 1', $sql);
        $this->assertStringContainsString('`age` > 18', $sql);
        $this->assertStringContainsString('WHERE', $sql);
    }

    /** @test orWhere() 多条 OR 条件自动加括号 */
    public function testOrWhere(): void
    {
        $where = $this->createBuilder()
            ->withoutGlobalConditions()
            ->where(['a' => 1])
            ->orWhere(['b' => 2, 'c' => 3])
            ->getBuildWhere();

        $this->assertStringContainsString('`a` = 1 OR (`b` = 2 OR `c` = 3)', $where);
    }

    /** @test whereNull() / whereNotNull() */
    public function testWhereNullAndWhereNotNull(): void
    {
        $sql = $this->createBuilder()
            ->whereNull('deleted_at')
            ->whereNotNull('created_at')
            ->toSql();

        $this->assertStringContainsString('`deleted_at` IS NULL', $sql);
        $this->assertStringContainsString('`created_at` IS NOT NULL', $sql);
    }

    /** @test whereIn() / whereNotIn() */
    public function testWhereInAndWhereNotIn(): void
    {
        $sql = $this->createBuilder()
            ->whereIn('id', [1, 2, 3])
            ->whereNotIn('type', ['a', 'b'])
            ->toSql();

        $this->assertStringContainsString('`id` IN (1,2,3)', $sql);
        $this->assertStringContainsString("`type` NOT IN ('a','b')", $sql);
    }

    /** @test whereBetween() */
    public function testWhereBetween(): void
    {
        $sql = $this->createBuilder()
            ->whereBetween('score', 10, 20)
            ->toSql();

        $this->assertStringContainsString('`score` BETWEEN 10 AND 20', $sql);
    }

    /** @test whereGroup() 嵌套条件组 */
    public function testWhereGroup(): void
    {
        $sql = $this->createBuilder()
            ->where(['a' => 1])
            ->whereGroup(function ($q) {
                $q->where(['b' => 2])->orWhere(['c' => 3]);
            })
            ->toSql();

        $this->assertStringContainsString('`a` = 1', $sql);
        $this->assertStringContainsString('(`b` = 2 OR `c` = 3)', $sql);
    }

    /** @test whereRaw() 原始条件 + ? 占位符绑定 */
    public function testWhereRawWithBindings(): void
    {
        $sql = $this->createBuilder()
            ->whereRaw('`created_at` >= ? AND `created_at` < ?', ['2024-01-01', '2024-02-01'])
            ->toSql();

        $this->assertStringContainsString("(`created_at` >= '2024-01-01' AND `created_at` < '2024-02-01')", $sql);
    }

    /** @test when() 条件为真时执行回调 */
    public function testWhenAppliesCallbackWhenTruthy(): void
    {
        $sql = $this->createBuilder()
            ->when(true, function ($q) {
                return $q->where(['enabled' => 1]);
            })
            ->toSql();

        $this->assertStringContainsString('`enabled` = 1', $sql);
    }

    /** @test when() 条件为假时执行 default 回调 */
    public function testWhenUsesDefaultWhenFalsy(): void
    {
        $sql = $this->createBuilder()
            ->when(false, function ($q) {
                return $q->where(['enabled' => 1]);
            }, function ($q) {
                return $q->where(['enabled' => 0]);
            })
            ->toSql();

        $this->assertStringContainsString('`enabled` = 0', $sql);
        $this->assertStringNotContainsString('`enabled` = 1', $sql);
    }

    /** @test getConditions() 返回已拼接的 AND 条件数组 */
    public function testGetConditions(): void
    {
        $conditions = $this->createBuilder()
            ->where(['a' => 1, 'b' => 2])
            ->getConditions();

        $this->assertSame(['`a` = 1 AND `b` = 2'], $conditions);
    }

    // =========================================================================
    // GROUP BY / HAVING / ORDER BY / LIMIT
    // =========================================================================

    /** @test groupBy() / having() / orderBy() / limit() / offset() 组合 */
    public function testGroupByHavingOrderByLimitOffset(): void
    {
        $sql = $this->createBuilder()
            ->select(['user_id', 'count() as cnt'])
            ->groupBy(['user_id'])
            ->having(['cnt' => ['cnt', '>', 0]])
            ->orderBy('cnt', 'DESC')
            ->limit(10)
            ->offset(20)
            ->toSql();

        $this->assertStringContainsString('GROUP BY user_id', $sql);
        $this->assertStringContainsString('HAVING `cnt` > 0', $sql);
        $this->assertStringContainsString('ORDER BY `cnt` DESC', $sql);
        $this->assertStringContainsString('LIMIT 10 OFFSET 20', $sql);
    }

    /** @test orderBy() 支持 ['field' => 'ASC'] 数组写法 */
    public function testOrderByArray(): void
    {
        $orderBy = $this->createBuilder()
            ->orderBy(['id' => 'DESC', 'name' => 'ASC'])
            ->getBuildOrderBy();

        $this->assertSame('ORDER BY `id` DESC, `name` ASC', $orderBy);
    }

    /**
     * @test 别名字段在 WHERE 与 ORDER BY 都拆成 `alias`.`field`，分区优化仍能补上 log_date
     *       1717545600 = 2024-06-05（秒级时间戳）
     */
    public function testAliasFieldQuotedInWhereAndOrderByWithPartitionOptimization(): void
    {
        $sql = $this->createBuilder('client_monitor_log', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => [
                    'default' => ['log_time' => 'log_date'],
                ],
            ],
        ])
            ->alias('t')
            ->where([['t.log_time', '>=', 1717545600]])
            ->orderBy('t.log_time')
            ->toSql();

        $this->assertStringContainsString('`t`.`log_time` >= 1717545600', $sql);
        $this->assertStringContainsString('ORDER BY `t`.`log_time` ASC', $sql);
        $this->assertStringContainsString("`log_date` >= '2024-06-05'", $sql);
    }

    /** @test having() 等值写法对 alias.field 与 WHERE 使用同一套字段格式化 */
    public function testHavingEqualityUsesFormatField(): void
    {
        $sql = $this->createBuilder()
            ->having(['t.cnt' => 1])
            ->toSql();

        $this->assertStringContainsString('HAVING `t`.`cnt` = 1', $sql);
    }

    // =========================================================================
    // 全局条件
    // =========================================================================

    /** @test 默认自动附加 connection 中的 global_conditions */
    public function testGlobalConditionsIncludedByDefault(): void
    {
        $where = $this->createBuilder()->getBuildWhere();

        $this->assertStringContainsString('game_id = 1', $where);
    }

    /** @test withoutGlobalConditions() 跳过全局条件 */
    public function testWithoutGlobalConditions(): void
    {
        $where = $this->createBuilder()
            ->withoutGlobalConditions()
            ->getBuildWhere();

        $this->assertStringNotContainsString('game_id = 1', $where);
    }

    /** @test withGlobalConditions() 重新启用全局条件 */
    public function testWithGlobalConditionsReEnablesGlobalWhere(): void
    {
        $where = $this->createBuilder()
            ->withoutGlobalConditions()
            ->withGlobalConditions()
            ->getBuildWhere();

        $this->assertStringContainsString('game_id = 1', $where);
    }

    /**
     * @test 使用 from() 覆盖物理表（如 CTE 别名）时不附加全局条件
     *       避免子查询/派生表误加 game_id 等字段
     */
    public function testGlobalConditionsSkippedWhenFromIsSet(): void
    {
        $where = $this->createBuilder()
            ->from('sub_table')
            ->getBuildWhere();

        $this->assertStringNotContainsString('game_id = 1', $where);
    }

    // =========================================================================
    // 时间分区优化（TimePartitionOptimizationTrait）
    // =========================================================================

    /**
     * @test 查询时间戳字段时自动追加对应日期字段条件，实现分区裁剪
     *       1717545600 = 2024-06-05（秒级时间戳）
     */
    public function testTimePartitionOptimizationAddsDateCondition(): void
    {
        $builder = $this->createBuilder('client_monitor_log', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => [
                    'default' => ['log_time' => 'log_date'],
                    'tables' => [
                        'client_monitor_log' => [
                            'log_time' => 'log_date',
                        ],
                    ],
                ],
            ],
        ]);

        $sql = $builder
            ->where([['log_time', '>=', 1717545600]])
            ->toSql();

        $this->assertStringContainsString('`log_time` >= 1717545600', $sql);
        $this->assertStringContainsString("`log_date` >= '2024-06-05'", $sql);
    }

    /** @test 多次调用 toSql() 不会重复追加日期优化条件 */
    public function testTimePartitionOptimizationNotDuplicatedOnRepeatedToSql(): void
    {
        $builder = $this->createBuilder('client_monitor_log', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => [
                    'default' => ['log_time' => 'log_date'],
                ],
            ],
        ])->where([['log_time', '>=', 1717545600]]);

        $first = $builder->toSql();
        $second = $builder->toSql();

        $this->assertSame($first, $second);
        $this->assertSame(1, substr_count($first, "`log_date` >= '2024-06-05'"));
    }

    /**
     * @test BETWEEN 时间戳条件自动追加日期 BETWEEN 分区条件
     *       1717545600=2024-06-05，1717632000=2024-06-06
     */
    public function testTimePartitionOptimizationBetweenAddsDateRange(): void
    {
        $sql = $this->createBuilder('client_monitor_log', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => [
                    'default' => ['log_time' => 'log_date'],
                    'tables' => ['client_monitor_log' => ['log_time' => 'log_date']],
                ],
            ],
        ])
            ->whereBetween('log_time', 1717545600, 1717632000)
            ->toSql();

        $this->assertStringContainsString('`log_time` BETWEEN 1717545600 AND 1717632000', $sql);
        $this->assertStringContainsString("`log_date` BETWEEN '2024-06-05' AND '2024-06-06'", $sql);
    }

    /**
     * @test 毫秒时间戳表配置：server_monitor_log 使用 timestamp_type=milliseconds
     *       1717545600000ms = 2024-06-05
     */
    public function testTimePartitionOptimizationWithMillisecondsTimestamp(): void
    {
        $sql = $this->createBuilder('server_monitor_log', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => [
                    'default' => ['log_time' => 'log_date'],
                    'tables' => [
                        'server_monitor_log' => [
                            'log_time' => 'log_date',
                            'timestamp_type' => 'milliseconds',
                        ],
                    ],
                ],
            ],
        ])
            ->where([['log_time', '>=', 1717545600000]])
            ->toSql();

        $this->assertStringContainsString('`log_time` >= 1717545600000', $sql);
        $this->assertStringContainsString("`log_date` >= '2024-06-05'", $sql);
    }

    /** @test 特定表映射：user_events 的 event_time → event_date */
    public function testTimePartitionOptimizationTableSpecificMapping(): void
    {
        $sql = $this->createBuilder('user_events', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => [
                    'default' => ['log_time' => 'log_date'],
                    'tables' => [
                        'user_events' => [
                            'event_time' => 'event_date',
                        ],
                    ],
                ],
            ],
        ])
            ->where([['event_time', '>=', 1717545600]])
            ->toSql();

        $this->assertStringContainsString("`event_date` >= '2024-06-05'", $sql);
        $this->assertStringNotContainsString('log_date', $sql);
    }

    /** @test 无表级配置时使用 field_mapping.default */
    public function testTimePartitionOptimizationUsesDefaultMapping(): void
    {
        $sql = $this->createBuilder('generic_log', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => [
                    'default' => ['log_time' => 'log_date'],
                ],
            ],
        ])
            ->where([['log_time', '>', 1717545600]])
            ->toSql();

        $this->assertStringContainsString('`log_time` > 1717545600', $sql);
        $this->assertStringContainsString("`log_date` >= '2024-06-05'", $sql);
    }

    /** @test > 映射为 log_date >=，< 映射为 log_date <= */
    public function testTimePartitionOptimizationOperatorMapping(): void
    {
        $config = [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => ['default' => ['log_time' => 'log_date']],
            ],
        ];

        $lessThanSql = $this->createBuilder('test_table', $config)
            ->where([['log_time', '<', 1717545600]])
            ->toSql();
        $this->assertStringContainsString("`log_date` <= '2024-06-05'", $lessThanSql);

        $equalSql = $this->createBuilder('test_table', $config)
            ->where([['log_time', '=', 1717545600]])
            ->toSql();
        $this->assertStringContainsString("`log_date` = '2024-06-05'", $equalSql);
    }

    /** @test 13 位毫秒时间戳在 seconds 配置下自动按毫秒转换 */
    public function testTimePartitionOptimizationAutoDetectsMillisecondTimestamp(): void
    {
        $sql = $this->createBuilder('test_table', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => ['default' => ['log_time' => 'log_date']],
            ],
        ])
            ->where([['log_time', '>=', 1717545600000]])
            ->toSql();

        $this->assertStringContainsString("`log_date` >= '2024-06-05'", $sql);
    }

    /** @test 优化未启用时不追加日期条件 */
    public function testTimePartitionOptimizationSkippedWhenDisabled(): void
    {
        $sql = $this->createBuilder('test_table', [
            'time_partition_optimization' => [
                'enabled' => false,
                'field_mapping' => ['default' => ['log_time' => 'log_date']],
            ],
        ])
            ->where([['log_time', '>=', 1717545600]])
            ->toSql();

        $this->assertStringContainsString('`log_time` >= 1717545600', $sql);
        $this->assertStringNotContainsString('log_date', $sql);
    }

    /** @test 不支持的操作符（如 !=）不生成日期优化条件 */
    public function testTimePartitionOptimizationSkipsUnsupportedOperator(): void
    {
        $sql = $this->createBuilder('test_table', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => ['default' => ['log_time' => 'log_date']],
            ],
        ])
            ->where([['log_time', '!=', 1717545600]])
            ->toSql();

        $this->assertStringContainsString('`log_time` != 1717545600', $sql);
        $this->assertStringNotContainsString('log_date', $sql);
    }

    /** @test 非法时间戳（超出范围）不追加日期条件 */
    public function testTimePartitionOptimizationSkipsInvalidTimestamp(): void
    {
        $sql = $this->createBuilder('test_table', [
            'time_partition_optimization' => [
                'enabled' => true,
                'timestamp_type' => 'seconds',
                'field_mapping' => ['default' => ['log_time' => 'log_date']],
            ],
        ])
            ->where([['log_time', '>=', 99999999999999]])
            ->toSql();

        $this->assertStringContainsString('`log_time` >= 99999999999999', $sql);
        $this->assertStringNotContainsString('log_date', $sql);
    }

    // =========================================================================
    // 构建器状态管理
    // =========================================================================

    /** @test fresh() 清空 where/limit/order 等，恢复全局条件默认开启 */
    public function testFreshResetsBuilderState(): void
    {
        $builder = $this->createBuilder()
            ->select(['id'])
            ->where(['id' => 1])
            ->orderBy('id')
            ->limit(5)
            ->withoutGlobalConditions()
            ->fresh();

        $sql = $builder->toSql();

        $this->assertStringContainsString('SELECT *', $sql);
        $this->assertStringNotContainsString('`id` = 1', $sql);
        $this->assertStringNotContainsString('ORDER BY', $sql);
        $this->assertStringNotContainsString('LIMIT', $sql);
        $this->assertStringContainsString('game_id = 1', $builder->getBuildWhere());
    }

    /** @test newQuery() 共用 connection，但不继承当前 where/limit 等条件 */
    public function testNewQuerySharesConnectionButResetsConditions(): void
    {
        $connection = $this->createConnection();
        $builder = $this->createBuilder('test_table', [], $connection)
            ->where(['id' => 1])
            ->limit(10);

        $newQuery = $builder->newQuery();

        $this->assertNotSame($builder, $newQuery);
        $this->assertSame($connection, $newQuery->getConnection());
        $this->assertStringNotContainsString('`id` = 1', $newQuery->toSql());
    }

    /** @test getConnection() 返回关联的 ClickHouseConnection */
    public function testGetConnection(): void
    {
        $connection = $this->createConnection();
        $builder = $this->createBuilder('test_table', [], $connection);

        $this->assertSame($connection, $builder->getConnection());
    }

    // =========================================================================
    // 值格式化与条件解析
    // =========================================================================

    /** @test parseArrayCondition() 格式不合法时抛出异常 */
    public function testParseArrayConditionThrowsOnInvalidFormat(): void
    {
        $builder = $this->createBuilder();

        $this->expectException(ClickHouseQueryException::class);
        $builder->parseArrayCondition(['only_two']);
    }

    /** @test parseArrayCondition() 不支持的操作符抛出异常 */
    public function testParseArrayConditionThrowsOnUnsupportedOperator(): void
    {
        $builder = $this->createBuilder();

        $this->expectException(ClickHouseQueryException::class);
        $builder->parseArrayCondition(['id', 'REGEXP', '.*']);
    }

    /** @test quoteValue() 转义字符串单引号，数值原样返回 */
    public function testQuoteValueEscapesString(): void
    {
        $builder = $this->createBuilder();

        $this->assertSame("'it\\'s ok'", $builder->quoteValue("it's ok"));
        $this->assertSame(123, $builder->quoteValue(123));
    }

    /** @test formatValue() 处理 null / bool / string */
    public function testFormatValue(): void
    {
        $builder = $this->createBuilder();

        $this->assertSame('NULL', $builder->formatValue(null));
        $this->assertSame('1', $builder->formatValue(true));
        $this->assertSame('0', $builder->formatValue(false));
        $this->assertSame("'abc'", $builder->formatValue('abc'));
    }

    // =========================================================================
    // 查询执行（get / first / count / execute）
    // =========================================================================

    /** @test get() 构建 SQL 并通过 connection 执行 */
    public function testGetExecutesBuiltSql(): void
    {
        MockConnectionAdapter::$resultsQueue = [[['id' => 1]]];

        $result = $this->createBuilder()
            ->select(['id'])
            ->where(['id' => 1])
            ->get();

        $this->assertSame([['id' => 1]], $result);
        $this->assertCount(1, MockConnectionAdapter::$executedSql);
        $this->assertStringContainsString('SELECT id', MockConnectionAdapter::$executedSql[0]);
    }

    /**
     * @test first() 临时 LIMIT 1 查询，执行后恢复原 limit 设置
     */
    public function testFirstUsesLimitOneAndRestoresLimit(): void
    {
        MockConnectionAdapter::$resultsQueue = [[['id' => 1], ['id' => 2]]];

        $builder = $this->createBuilder()->select(['id'])->limit(50);
        $row = $builder->first();

        $this->assertSame(['id' => 1], $row);
        $this->assertStringContainsString('LIMIT 1', MockConnectionAdapter::$executedSql[0]);
        $this->assertStringContainsString('LIMIT 50', $builder->toSql());
    }

    /** @test first() 无结果时返回空数组 */
    public function testFirstReturnsEmptyArrayWhenNoRows(): void
    {
        MockConnectionAdapter::$resultsQueue = [[]];

        $row = $this->createBuilder()->first();

        $this->assertSame([], $row);
    }

    /**
     * @test count() 临时替换 SELECT 为 count()，执行后恢复 fields / orderBy
     */
    public function testCountRestoresSelectFields(): void
    {
        MockConnectionAdapter::$resultsQueue = [[['count' => 99]]];

        $builder = $this->createBuilder()->select(['id', 'name']);
        $total = $builder->count();

        $this->assertSame(99, $total);
        $this->assertStringContainsString('count()', MockConnectionAdapter::$executedSql[0]);
        $this->assertStringContainsString('SELECT id, name', $builder->toSql());
        $this->assertStringNotContainsString('count()', strtolower($builder->toSql()));
    }

    /** @test count($column) 支持 count(distinct ...) */
    public function testCountWithColumn(): void
    {
        MockConnectionAdapter::$resultsQueue = [[['count' => 3]]];

        $total = $this->createBuilder()->count('distinct user_id');

        $this->assertSame(3, $total);
        $this->assertStringContainsString('count(distinct user_id)', MockConnectionAdapter::$executedSql[0]);
    }

    /** @test execute() 直接执行原始 SQL，委托给 connection */
    public function testExecuteDelegatesToConnection(): void
    {
        MockConnectionAdapter::$resultsQueue = [[['ping' => 1]]];

        $result = $this->createBuilder()->execute('SELECT ping');

        $this->assertSame([['ping' => 1]], $result);
        $this->assertSame(['SELECT ping'], MockConnectionAdapter::$executedSql);
    }

    // =========================================================================
    // 分页（PaginationTrait）
    // =========================================================================

    /**
     * @test paginate() 先 count 再 limit/offset 取数据，返回标准分页结构
     *       resultsQueue[0]=count 结果，resultsQueue[1]=数据行
     */
    public function testPaginate(): void
    {
        MockConnectionAdapter::$resultsQueue = [
            [['count' => 25]],
            [['id' => 11], ['id' => 12]],
        ];

        $result = $this->createBuilder()
            ->select(['id'])
            ->orderBy('id')
            ->paginate(10, false, 2);

        $this->assertSame(25, $result['total']);
        $this->assertSame(10, $result['per_page']);
        $this->assertSame(2, $result['current_page']);
        $this->assertSame(3, $result['last_page']);
        $this->assertCount(2, $result['data']);
        $this->assertStringContainsString('LIMIT 10 OFFSET 10', MockConnectionAdapter::$executedSql[1]);
    }

    /** @test paginate() 未传 page 时从 adapter.getCurrentPage() 读取 */
    public function testPaginateUsesAdapterCurrentPageWhenPageIsNull(): void
    {
        MockConnectionAdapter::$currentPage = 3;
        MockConnectionAdapter::$resultsQueue = [
            [['count' => 0]],
            [],
        ];

        $result = $this->createBuilder()->paginate(10, false);

        $this->assertSame(3, $result['current_page']);
    }

    /** @test countField() 指定分页 total 的 COUNT 字段 */
    public function testCountFieldAffectsPaginateTotal(): void
    {
        MockConnectionAdapter::$resultsQueue = [
            [['count' => 7]],
            [],
        ];

        $result = $this->createBuilder()
            ->countField('distinct user_id')
            ->paginate(10, false, 1);

        $this->assertSame(7, $result['total']);
        $this->assertStringContainsString('count(distinct user_id)', MockConnectionAdapter::$executedSql[0]);
    }

    /** @test getPageData() is_export=1 时跳过 count，直接返回全部数据 */
    public function testGetPageDataExportModeReturnsAllRows(): void
    {
        MockConnectionAdapter::$resultsQueue = [[['id' => 1], ['id' => 2]]];

        $data = $this->createBuilder()
            ->getPageData(['is_export' => 1]);

        $this->assertCount(2, $data);
        $this->assertArrayNotHasKey('total', $data);
    }

    /** @test getPageData() 无 params 时从 adapter.getRequestParams() 读取 page/pageSize */
    public function testGetPageDataUsesRequestParamsFromAdapter(): void
    {
        MockConnectionAdapter::$requestParams = ['page' => 2, 'pageSize' => 5];
        MockConnectionAdapter::$resultsQueue = [
            [['count' => 12]],
            [['id' => 6]],
        ];

        $result = $this->createBuilder()->getPageData();

        $this->assertSame(12, $result['total']);
        $this->assertSame(5, $result['per_page']);
        $this->assertSame(2, $result['current_page']);
    }

    /**
     * @test paginate(wrapCountWithClause=true) 使用 WITH CTE 包装计数查询
     */
    public function testPaginateWithWrapCountWithClause(): void
    {
        MockConnectionAdapter::$resultsQueue = [
            [['count' => 15]],
            [['id' => 1]],
        ];

        $result = $this->createBuilder()
            ->select(['id'])
            ->where(['status' => 1])
            ->paginate(10, true, 1);

        $this->assertSame(15, $result['total']);
        $this->assertStringContainsString('WITH _ckqb_pagination_count_ AS', MockConnectionAdapter::$executedSql[0]);
    }

    /**
     * @test GROUP BY 分页计数对分组结果包子查询再 count
     */
    public function testPaginateWithGroupByUsesAggregateSubqueryCount(): void
    {
        MockConnectionAdapter::$resultsQueue = [
            [['count' => 3]],
            [['user_id' => 1, 'cnt' => 5]],
        ];

        $result = $this->createBuilder()
            ->select(['user_id', 'count() as cnt'])
            ->groupBy(['user_id'])
            ->paginate(10, false, 1);

        $this->assertSame(3, $result['total']);
        $this->assertStringContainsString('_ckqb_pagination_aggregate_', MockConnectionAdapter::$executedSql[0]);
    }

    /** @test HAVING 场景同样走聚合子查询计数 */
    public function testPaginateWithHavingUsesAggregateSubqueryCount(): void
    {
        MockConnectionAdapter::$resultsQueue = [
            [['count' => 2]],
            [],
        ];

        $this->createBuilder()
            ->select(['user_id', 'count() as cnt'])
            ->groupBy(['user_id'])
            ->having(['cnt' => ['cnt', '>', 0]])
            ->paginate(10, false, 1);

        $this->assertStringContainsString('_ckqb_pagination_aggregate_', MockConnectionAdapter::$executedSql[0]);
    }

    /** @test total=0 时 last_page 为 1 */
    public function testPaginateLastPageIsOneWhenTotalIsZero(): void
    {
        MockConnectionAdapter::$resultsQueue = [
            [['count' => 0]],
            [],
        ];

        $result = $this->createBuilder()->paginate(10, false, 1);

        $this->assertSame(0, $result['total']);
        $this->assertSame(1, $result['last_page']);
    }

    /** @test getPageData() 支持传入 countField 参数 */
    public function testGetPageDataWithCountField(): void
    {
        MockConnectionAdapter::$resultsQueue = [
            [['count' => 8]],
            [['id' => 1]],
        ];

        $result = $this->createBuilder()->getPageData(
            ['page' => 1, 'pageSize' => 10],
            true,
            'distinct user_id'
        );

        $this->assertSame(8, $result['total']);
        $this->assertStringContainsString('count(distinct user_id)', MockConnectionAdapter::$executedSql[0]);
    }

    /** @test formatInValues() 空数组抛出异常 */
    public function testFormatInValuesThrowsOnEmptyArray(): void
    {
        $builder = $this->createBuilder();

        $this->expectException(ClickHouseQueryException::class);
        $builder->formatInValues([]);
    }

    /** @test formatValue() 支持数组嵌套 */
    public function testFormatValueWithArray(): void
    {
        $builder = $this->createBuilder();

        $this->assertSame('(1,2)', $builder->formatValue([1, 2]));
    }
}
