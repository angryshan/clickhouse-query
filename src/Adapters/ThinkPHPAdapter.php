<?php
// src/Adapter/ThinkPHPAdapter.php

declare(strict_types=1);

namespace ClickHouseQuery\Adapters;

use Exception;
use think\db\ConnectionInterface;
use think\facade\Db;
use ClickHouseQuery\Interfaces\ConnectionAdapterInterface;

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
    private $connection;
    
    /**
     * @var string
     */
    private $poolName;
    
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
        return $this->connection->query($sql);
    }
    
    /**
     * 获取配置值
     */
    public function getConfig(string $key, $default = null)
    {
        // 适配ThinkPHP的配置系统
        return config("database.connections.{$this->poolName}.{$key}", $default);
    }

    /**
     * 获取当前请求的参数
     * @return array
     */
    public function getRequestParams(): array
    {
        if (function_exists('request')) {
            $request = request();
            if (method_exists($request, 'param')) {
                return $request->param();
            }
        }
        return [];
    }
}
