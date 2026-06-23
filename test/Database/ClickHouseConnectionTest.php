<?php

declare(strict_types=1);

namespace ClickHouseQueryTest\Database;

use ClickHouseQuery\Exceptions\ClickHouseQueryException;
use ClickHouseQueryTest\Mocks\MockConnectionAdapter;

/**
 * ClickHouseConnection 公开方法单元测试
 *
 * @covers \ClickHouseQuery\Database\Connection\ClickHouseConnection
 */
class ClickHouseConnectionTest extends DatabaseTestCase
{
    // -------------------------------------------------------------------------
    // 配置读取
    // -------------------------------------------------------------------------

    /** @test 从 global_conditions 配置生成全局 WHERE 片段 */
    public function testGetGlobalWhereFromConfig(): void
    {
        $connection = $this->createConnection([
            'global_conditions' => [
                'game_id' => 100,
                'status' => 1,
            ],
        ]);

        $this->assertSame('game_id = 100 AND status = 1', $connection->getGlobalWhere());
    }

    /** @test 无全局条件时返回空字符串 */
    public function testGetGlobalWhereWhenEmpty(): void
    {
        $connection = $this->createConnection(['global_conditions' => []]);

        $this->assertSame('', $connection->getGlobalWhere());
    }

    /** @test getTimePartitionOptimization 返回构造时传入的配置 */
    public function testGetTimePartitionOptimization(): void
    {
        $optimization = [
            'enabled' => true,
            'timestamp_type' => 'milliseconds',
            'field_mapping' => ['default' => ['log_time' => 'log_date']],
        ];

        $connection = $this->createConnection([
            'time_partition_optimization' => $optimization,
        ]);

        $this->assertSame($optimization, $connection->getTimePartitionOptimization());
    }

    /** @test getAdapter 返回构造时创建的适配器实例 */
    public function testGetAdapter(): void
    {
        $connection = $this->createConnection();

        $this->assertInstanceOf(MockConnectionAdapter::class, $connection->getAdapter());
    }

    // -------------------------------------------------------------------------
    // SQL 执行
    // -------------------------------------------------------------------------

    /** @test query 直接委托适配器执行并返回结果 */
    public function testQueryReturnsAdapterResult(): void
    {
        MockConnectionAdapter::$resultsQueue = [[['id' => 1, 'name' => 'foo']]];

        $connection = $this->createConnection();
        $result = $connection->query('SELECT 1');

        $this->assertSame([['id' => 1, 'name' => 'foo']], $result);
        $this->assertSame(['SELECT 1'], MockConnectionAdapter::$executedSql);
    }

    /** @test execute 默认不启用连接控制，直接执行 SQL */
    public function testExecuteWithoutConnectionControl(): void
    {
        MockConnectionAdapter::$resultsQueue = [[['count' => 5]]];

        $connection = $this->createConnection();
        $result = $connection->execute('SELECT count()');

        $this->assertSame([['count' => 5]], $result);
        $this->assertCount(1, MockConnectionAdapter::$executedSql);
    }

    /**
     * @test 启用连接控制时，先查询 system.processes 判断并发数
     *       当前运行数低于上限则继续执行目标 SQL
     */
    public function testExecuteWithConnectionControlWaitsWhenUnderLimit(): void
    {
        MockConnectionAdapter::$runningQueriesCount = 2;
        MockConnectionAdapter::$resultsQueue = [[['ok' => true]]];

        $connection = $this->createConnection([
            'max_running_processes' => 5,
        ]);

        $result = $connection->execute('SELECT 1', true);

        $this->assertSame([['ok' => true]], $result);
        $this->assertContains('SELECT COUNT(*) AS running_queries FROM system.processes', MockConnectionAdapter::$executedSql);
    }

    /** @test 底层异常统一包装为 ClickHouseQueryException */
    public function testQueryWrapsExceptionAsClickHouseQueryException(): void
    {
        $adapter = new class('clickhouse') extends MockConnectionAdapter {
            public function query(string $sql)
            {
                throw new \RuntimeException('connection failed');
            }
        };

        $connection = $this->createConnection();
        // 通过反射替换适配器，模拟连接失败场景
        $reflection = new \ReflectionClass($connection);
        $property = $reflection->getProperty('adapter');
        $property->setAccessible(true);
        $property->setValue($connection, new $adapter('clickhouse'));

        $this->expectException(ClickHouseQueryException::class);
        $this->expectExceptionMessage('connection failed');

        $connection->query('SELECT 1');
    }

    /**
     * @test 连接数持续超限时抛出「等待可用连接超时」
     *       max_wait_attempts=1 避免测试长时间 usleep
     */
    public function testExecuteThrowsWhenConnectionWaitTimesOut(): void
    {
        MockConnectionAdapter::$runningQueriesCount = 10;

        $connection = $this->createConnection([
            'max_running_processes' => 1,
            'max_wait_attempts' => 1,
            'wait_min_microseconds' => 0,
            'wait_max_microseconds' => 0,
        ]);

        $this->expectException(ClickHouseQueryException::class);
        $this->expectExceptionMessage('等待可用连接超时');

        $connection->execute('SELECT 1', true);
    }

    /**
     * @test system.processes 查询无 running_queries 字段时视为有可用连接
     */
    public function testExecuteProceedsWhenRunningQueriesCountIsNull(): void
    {
        $adapter = new class('clickhouse') extends MockConnectionAdapter {
            public function query(string $sql)
            {
                self::$executedSql[] = $sql;

                if ($sql === 'SELECT COUNT(*) AS running_queries FROM system.processes') {
                    return [['other' => 1]];
                }

                return [['ok' => true]];
            }
        };

        $connection = $this->createConnection([
            'max_running_processes' => 1,
        ]);
        $reflection = new \ReflectionClass($connection);
        $property = $reflection->getProperty('adapter');
        $property->setAccessible(true);
        $property->setValue($connection, new $adapter('clickhouse'));

        $result = $connection->execute('SELECT 1', true);

        $this->assertSame([['ok' => true]], $result);
    }

    /** @test execute 底层异常同样包装为 ClickHouseQueryException */
    public function testExecuteWrapsExceptionAsClickHouseQueryException(): void
    {
        $adapter = new class('clickhouse') extends MockConnectionAdapter {
            public function query(string $sql)
            {
                throw new \RuntimeException('execute failed');
            }
        };

        $connection = $this->createConnection();
        $reflection = new \ReflectionClass($connection);
        $property = $reflection->getProperty('adapter');
        $property->setAccessible(true);
        $property->setValue($connection, new $adapter('clickhouse'));

        $this->expectException(ClickHouseQueryException::class);
        $this->expectExceptionMessage('execute failed');

        $connection->execute('SELECT 1');
    }
}
