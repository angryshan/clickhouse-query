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
    private $connection;
    
    /**
     * @var string
     */
    private $poolName;
    
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
        return config("clickhouse.{$key}", $default);
    }

    /**
     * 获取当前请求的参数
     * @return array
     */
    public function getRequestParams(): array
    {
        if (class_exists('\Hyperf\HttpServer\Contract\RequestInterface')) {
            $container = \Hyperf\Utils\ApplicationContext::getContainer();
            if ($container->has(\Hyperf\HttpServer\Contract\RequestInterface::class)) {
                $request = $container->get(\Hyperf\HttpServer\Contract\RequestInterface::class);
                return $request->all();
            }
        }
        return [];
    }
}
