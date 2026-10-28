<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQueryTest\Mocks;

use TxAdmin\ClickHouseQuery\Interfaces\ConnectionAdapterInterface;

/**
 * 单元测试用连接适配器
 *
 * 实现 ConnectionAdapterInterface，无需真实 ClickHouse 或 TP/Hyperf 环境。
 * 通过静态属性记录执行过的 SQL、预设返回队列，供断言使用。
 */
class MockConnectionAdapter implements ConnectionAdapterInterface
{
    /** @var string[] 已执行的 SQL 列表（按顺序） */
    public static array $executedSql = [];

    /** @var array<int, array> 待返回结果队列，每次 query 消费队首一项 */
    public static array $resultsQueue = [];

    /** @var array 模拟 getConfig 的配置树（支持点号键） */
    public static array $config = [];

    /** @var array 模拟 HTTP 请求参数（getPageData 等场景） */
    public static array $requestParams = [];

    /** @var int 模拟当前页码（paginate 未显式传 page 时使用） */
    public static int $currentPage = 1;

    /** @var int|null 模拟 system.processes 中的 running_queries 数量 */
    public static ?int $runningQueriesCount = 0;

    public function __construct(string $poolName = 'clickhouse')
    {
    }

    /** 重置所有静态状态，应在每个测试 setUp 中调用 */
    public static function reset(): void
    {
        self::$executedSql = [];
        self::$resultsQueue = [];
        self::$config = [];
        self::$requestParams = [];
        self::$currentPage = 1;
        self::$runningQueriesCount = 0;
    }

    public function query(string $sql): array
    {
        self::$executedSql[] = $sql;

        // 连接控制：查询当前运行进程数
        if ($sql === 'SELECT COUNT(*) AS running_queries FROM system.processes') {
            return [['running_queries' => self::$runningQueriesCount]];
        }

        if (self::$resultsQueue !== []) {
            return array_shift(self::$resultsQueue);
        }

        return [];
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$config;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function getRequestParams(): array
    {
        return self::$requestParams;
    }

    public function getCurrentPage(string $varPage = 'page', int $default = 1): int
    {
        if (isset(self::$requestParams[$varPage])) {
            $page = (int) self::$requestParams[$varPage];

            return $page >= 1 ? $page : $default;
        }

        return self::$currentPage >= 1 ? self::$currentPage : $default;
    }
}
