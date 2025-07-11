<?php

declare(strict_types=1);

namespace ClickHouseQuery\Database\Connection;

use Exception;
use RuntimeException;
use Throwable;
use ClickHouseQuery\Interfaces\ConnectionAdapterInterface;
use ClickHouseQuery\Adapters\HyperfAdapter;
use ClickHouseQuery\Adapters\ThinkPHPAdapter;

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
        // 配置选项覆盖默认值
        if (isset($config['pool_name'])) {
            $this->poolName = $config['pool_name'];
        } else {
            // 尝试从配置中读取
            $poolName = config('clickhouse.pool_name', 'clickhouse');
            $this->poolName = $poolName;
        }
        
        if (isset($config['max_running_processes'])) {
            $this->maxRunningProcesses = $config['max_running_processes'];
        } else {
            $this->maxRunningProcesses = config('clickhouse.max_running_processes', 5);
        }
        
        if (isset($config['max_wait_attempts'])) {
            $this->maxWaitAttempts = $config['max_wait_attempts'];
        } else {
            $this->maxWaitAttempts = config('clickhouse.max_wait_attempts', 60);
        }
        
        // 根据环境自动选择适配器
        $this->adapter = $this->createAdapter($config);
        
        $this->initGlobalConditions();
    }
    
    /**
     * 创建适配的数据库连接
     */
    private function createAdapter(array $config): ConnectionAdapterInterface
    {
        // 检查是否在ThinkPHP环境
        if (class_exists('\think\facade\Db')) {
            return new ThinkPHPAdapter($this->poolName);
        }
        
        // 检查是否在Hyperf环境
        if (class_exists('\Hyperf\DB\DB')) {
            return new HyperfAdapter($this->poolName);
        }
        
        // 如果配置中指定了适配器类
        if (isset($config['adapter']) && class_exists($config['adapter'])) {
            $adapterClass = $config['adapter'];
            return new $adapterClass($this->poolName);
        }
        
        throw new RuntimeException('无法确定适合的数据库适配器，请在配置中指定adapter参数');
    }
    
    /**
     * 执行SQL查询
     *
     * @param string $sql SQL语句
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 查询结果
     * @throws Exception
     */
    public function execute(string $sql, bool $useConnectionControl = false): array
    {
        try {
            if ($useConnectionControl) {
                $this->waitForAvailableConnection();
            }
            return $this->adapter->query($sql);
        } catch (Throwable $e) {
            throw new Exception($e->getMessage());
        }
    }
    
    /**
     * 直接执行SQL查询
     * @throws Exception
     */
    public function query(string $sql)
    {
        try {
            return $this->adapter->query($sql);
        } catch (Throwable $e) {
            throw new Exception($e->getMessage());
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
     * @throws Exception
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

        throw new RuntimeException('等待可用连接超时');
    }

    /**
     * 获取当前运行的查询数
     * @throws Exception
     */
    private function getRunningQueriesCount(): ?int
    {
        try {
            $result = $this->adapter->query('SELECT COUNT(*) AS running_queries FROM system.processes');
            return $result[0]['running_queries'] ?? null;
        } catch (Throwable $e) {
            throw new Exception($e->getMessage());
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