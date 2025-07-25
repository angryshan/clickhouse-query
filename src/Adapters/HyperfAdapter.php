<?php
// src/Adapter/HyperfAdapter.php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Adapters;

use Hyperf\DB\DB;
use TxAdmin\ClickHouseQuery\Interfaces\ConnectionAdapterInterface;

/**
 * Hyperf适配器
 * @package TxAdmin\ClickHouseQuery\Adapter
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
        return config("clickhouse.{$key}", $default);
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
}
