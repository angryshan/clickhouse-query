<?php

declare(strict_types=1);

namespace ClickHouseQuery\Database\Connection;

use ClickHouseQuery\Exceptions\ClickHouseQueryException;
use ClickHouseQuery\Interfaces\ConnectionAdapterInterface;
use ClickHouseQuery\Database\Factories\AdapterFactory;
use Throwable;

/**
 * ClickHouse数据库连接
 * @package ClickHouseQuery\Database\Connection
 * @author angryshan
 */
class ClickHouseConnection
{
    /**
     * 数据库连接池名称
     */
    private string $poolName = 'clickhouse';

    /**
     * 最大并发查询数
     * 当正在执行的查询数量达到此值时，新的查询将等待
     */
    private int $maxRunningProcesses = 5;

    /**
     * 最大等待尝试次数
     * 当等待次数超过此值时，将抛出超时异常
     */
    private int $maxWaitAttempts = 60;

    /**
     * 最小等待时间（微秒）
     * 每次等待的最小时间为 0.5 秒
     */
    private int $waitMinMicroseconds = 500000;

    /**
     * 最大等待时间（微秒）
     * 每次等待的最大时间为 1 秒
     */
    private int $waitMaxMicroseconds = 1000000;

    private string $globalWhere = '';
    private ConnectionAdapterInterface $adapter;

    /**
     * 初始化数据库连接和全局条件
     */
    public function __construct(array $config = [])
    {
        // 设置连接池名称
        $this->poolName = $config['pool_name'] ?? config('clickhouse.pool_name', 'clickhouse');
        
        // 创建适配器
        $adapterFactory = new AdapterFactory();
        $this->adapter = $adapterFactory->create($config, $this->poolName);
        
        // 从适配器获取配置
        $this->maxRunningProcesses = $config['max_running_processes'] ?? 
            $this->adapter->getConfig('connection_control.max_running_processes', 5);
            
        $this->maxWaitAttempts = $config['max_wait_attempts'] ?? 
            $this->adapter->getConfig('connection_control.max_wait_attempts', 60);
            
        $this->waitMinMicroseconds = $config['wait_min_microseconds'] ?? 
            $this->adapter->getConfig('connection_control.wait_min_microseconds', 500000);
            
        $this->waitMaxMicroseconds = $config['wait_max_microseconds'] ?? 
            $this->adapter->getConfig('connection_control.wait_max_microseconds', 1000000);
        
        $this->initGlobalConditions();
    }
    
    /**
     * 执行SQL查询
     *
     * @param string $sql SQL语句
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 查询结果
     * @throws ClickHouseQueryException
     */
    public function execute(string $sql, bool $useConnectionControl = false): array
    {
        try {
            if ($useConnectionControl) {
                $this->waitForAvailableConnection();
            }
            return $this->adapter->query($sql);
        } catch (Throwable $e) {
            throw new ClickHouseQueryException($e->getMessage());
        }
    }
    
    /**
     * 直接执行SQL查询
     * @throws ClickHouseQueryException
     */
    public function query(string $sql)
    {
        try {
            return $this->adapter->query($sql);
        } catch (Throwable $e) {
            throw new ClickHouseQueryException($e->getMessage());
        }
    }

    /**
     * 获取全局WHERE条件
     */
    public function getGlobalWhere(): string
    {
        return $this->globalWhere;
    }

    /**
     * 获取适配器
     */
    public function getAdapter(): ConnectionAdapterInterface
    {
        return $this->adapter;
    }

    /**
     * 等待可用连接
     * @throws ClickHouseQueryException
     */
    private function waitForAvailableConnection(): void
    {
        $attempt = 0;

        while (++$attempt <= $this->maxWaitAttempts) {
            $runningQueries = $this->getRunningQueriesCount();

            if ($runningQueries === null || $runningQueries < $this->maxRunningProcesses) {
                return;
            }

            usleep(rand($this->waitMinMicroseconds, $this->waitMaxMicroseconds));
        }

        throw new ClickHouseQueryException('等待可用连接超时');
    }

    /**
     * 获取当前运行的查询数
     * @throws ClickHouseQueryException
     */
    private function getRunningQueriesCount(): ?int
    {
        try {
            $result = $this->adapter->query('SELECT COUNT(*) AS running_queries FROM system.processes');
            return $result[0]['running_queries'] ?? null;
        } catch (Throwable $e) {
            throw new ClickHouseQueryException($e->getMessage());
        }
    }

    /**
     * 初始化全局查询条件
     */
    private function initGlobalConditions(): void
    {
        $conditions = [];

        // 全局条件从配置中读取
        $globalConditions = $this->adapter->getConfig('global_conditions', []);
        foreach ($globalConditions as $field => $value) {
            $conditions[] = "{$field} = {$value}";
        }

        $this->globalWhere = implode(' AND ', $conditions);
    }
} 