<?php
// src/Adapter/ThinkPHPAdapter.php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Adapters;

use think\db\ConnectionInterface;
use think\facade\Db;
use TxAdmin\ClickHouseQuery\Interfaces\ConnectionAdapterInterface;

/**
 * ThinkPHP适配器
 * @package ClickHouseQuery\Adapter
 * @author angryshan
 */
class ThinkPHPAdapter implements ConnectionAdapterInterface
{
    /**
     * @var ConnectionInterface
     */
    private ConnectionInterface $connection;
    
    /**
     * @var string
     */
    private string $poolName;
    
    public function __construct(string $poolName = 'clickhouse')
    {
        $this->poolName = $poolName;
        $this->connection = Db::connect($this->poolName);
    }
    
    /**
     * 执行查询
     */
    public function query(string $sql)
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
    
    /**
     * 获取配置值
     */
    public function getConfig(string $key, $default = null)
    {
        // 适配ThinkPHP的配置系统
        return config("tx_clickhouse_query.{$key}", $default);
    }

    /**
     * 获取当前请求的参数
     * @return array
     */
    public function getRequestParams(): array
    {
        $request = request();
        return $request->param();
    }

    /**
     * 解析当前页码（委托 TP PaginatorService 注册的 currentPageResolver）
     */
    public function getCurrentPage(string $varPage = 'page', int $default = 1): int
    {
        if (class_exists(\think\Paginator::class)) {
            $page = \think\Paginator::getCurrentPage($varPage, $default);
            return $page >= 1 ? $page : $default;
        }

        $page = request()->param($varPage);

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return $default;
    }
}
