<?php
// src/Adapter/HyperfAdapter.php

declare(strict_types=1);

namespace ClickHouseQuery\Adapters;

use Hyperf\DB\DB;
use ClickHouseQuery\Interfaces\ConnectionAdapterInterface;

/**
 * Hyperf适配器
 * @package ClickHouseQuery\Adapter
 * @author angryshan
 */
class HyperfAdapter implements ConnectionAdapterInterface
{
    /**
     * @var DB
     */
    private DB $connection;
    
    /**
     * @var string
     */
    private string $poolName;
    
    public function __construct(string $poolName = 'clickhouse')
    {
        $this->poolName = $poolName;
        $this->connection = DB::connection($this->poolName);
    }
    
    /**
     * 执行查询
     */
    public function query(string $sql)
    {
        return $this->connection->query($sql);
    }
    
    /**
     * 获取配置值
     */
    public function getConfig(string $key, $default = null)
    {
        return config("tx_clickhouse_query.{$key}", $default);
    }

    /**
     * 获取当前请求的参数
     * @return array
     */
    public function getRequestParams(): array
    {
        $container = \Hyperf\Utils\ApplicationContext::getContainer();
        $request = $container->get(\Hyperf\HttpServer\Contract\RequestInterface::class);
        return $request->all();
    }

    /**
     * 解析当前页码（委托 Hyperf Paginator::resolveCurrentPage）
     */
    public function getCurrentPage(string $varPage = 'page', int $default = 1): int
    {
        if (class_exists(\Hyperf\Paginator\Paginator::class)) {
            $page = \Hyperf\Paginator\Paginator::resolveCurrentPage($varPage, $default);
            return $page >= 1 ? $page : $default;
        }

        $container = \Hyperf\Utils\ApplicationContext::getContainer();
        $request = $container->get(\Hyperf\HttpServer\Contract\RequestInterface::class);
        $page = $request->input($varPage, $default);

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return $default;
    }
}
