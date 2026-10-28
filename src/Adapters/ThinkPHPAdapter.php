<?php

// src/Adapter/ThinkPHPAdapter.php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Adapters;

use RuntimeException;
use think\db\ConnectionInterface;
use think\facade\Db;
use think\Paginator;
use TxAdmin\ClickHouseQuery\Interfaces\ConnectionAdapterInterface;

/**
 * ThinkPHP 适配器（ThinkPHP 8.x / PHP 8.4+）
 * @package TxAdmin\ClickHouseQuery\Adapter
 * @author angryshan
 */
class ThinkPHPAdapter implements ConnectionAdapterInterface
{
    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $connection;

    public function __construct(
        private readonly string $poolName = 'clickhouse'
    ) {
        $this->connection = Db::connect($this->poolName);
    }

    public function query(string $sql): array
    {
        $pdo = $this->connection->connect();
        $statement = $pdo->prepare($sql);
        $info = $statement->execute();

        if (!$info) {
            $errorInfo = $statement->errorInfo();
            throw new \RuntimeException($errorInfo[2] ?? 'ClickHouse query failed');
        }

        return $statement->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return config("tx_clickhouse_query.{$key}", $default);
    }

    /**
     * 获取当前请求的参数
     * @return array
     */
    public function getRequestParams(): array
    {
        return request()->param();
    }

    /**
     * 解析当前页码（委托 TP PaginatorService 注册的 currentPageResolver）
     */
    public function getCurrentPage(string $varPage = 'page', int $default = 1): int
    {
        if (class_exists(Paginator::class)) {
            $page = Paginator::getCurrentPage($varPage, $default);

            return $page >= 1 ? $page : $default;
        }

        $page = request()->param($varPage);

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return $default;
    }
}
