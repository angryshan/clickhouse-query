<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQueryTest\Database;

use TxAdmin\ClickHouseQuery\Database\Connection\ClickHouseConnection;
use TxAdmin\ClickHouseQuery\Database\QueryBuilder\ClickHouseQueryBuilder;
use TxAdmin\ClickHouseQueryTest\Mocks\MockConnectionAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Database 模块测试基类
 *
 * 提供 Mock 适配器、默认配置，以及 Connection / QueryBuilder 工厂方法。
 */
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        // 每个用例前重置 Mock 状态，避免用例间互相污染
        MockConnectionAdapter::reset();
    }

    /**
     * 默认测试配置（可通过 $override 覆盖单项）
     */
    protected function baseConfig(array $override = []): array
    {
        return array_merge([
            'pool_name' => 'clickhouse',
            'adapter' => MockConnectionAdapter::class,
            'global_conditions' => [
                'game_id' => 1,
            ],
            'time_partition_optimization' => [
                'enabled' => false,
                'field_mapping' => [],
                'timestamp_type' => 'seconds',
            ],
        ], $override);
    }

    /**
     * 创建带 Mock 适配器的 ClickHouseConnection
     */
    protected function createConnection(array $config = []): ClickHouseConnection
    {
        return new ClickHouseConnection($this->baseConfig($config));
    }

    /**
     * 创建 ClickHouseQueryBuilder
     *
     * @param string $table 表名
     * @param array $config 额外配置（合并进 baseConfig）
     * @param ClickHouseConnection|null $connection 传入则复用连接（newQuery 场景）
     */
    protected function createBuilder(string $table = 'test_table', array $config = [], ?ClickHouseConnection $connection = null): ClickHouseQueryBuilder
    {
        $mergedConfig = $this->baseConfig($config);

        if ($connection !== null) {
            return new ClickHouseQueryBuilder($table, $mergedConfig, $connection);
        }

        return new ClickHouseQueryBuilder($table, $mergedConfig);
    }
}
