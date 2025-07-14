<?php

namespace ClickHouseQuery\Factories;

use ClickHouseQuery\Exceptions\ClickHouseQueryException;
use ClickHouseQuery\Interfaces\ConnectionAdapterInterface;
use ClickHouseQuery\Adapters\ThinkPHPAdapter;
use ClickHouseQuery\Adapters\HyperfAdapter;

class AdapterFactory
{
    /**
     * 创建适配器实例
     */
    public function create(array $config, string $poolName): ConnectionAdapterInterface
    {
        // 1. 显式配置（最高优先级）
        $adapter = $this->resolveFromExplicitConfig($config, $poolName);
        if ($adapter) return $adapter;
        
        // 2. 自动检测（中等优先级）
        $adapter = $this->detectFrameworkAdapter($poolName);
        if ($adapter) return $adapter;
        
        // 3. 报错
        throw new ClickHouseQueryException('请在配置中指定框架数据库适配器(adapter)参数');
    }

    /**
     * 从显式配置中解析适配器
     */
    private function resolveFromExplicitConfig(array $config, string $poolName): ?ConnectionAdapterInterface
    {
        // 从配置中解析
        if (isset($config['adapter']) && class_exists($config['adapter'])) {
            $adapterClass = $config['adapter'];
            return new $adapterClass($poolName);
        }
        
        $configAdapter = config('clickhouse.adapter');
        if (!empty($configAdapter) && class_exists($configAdapter)) {
            return new $configAdapter($poolName);
        }
        
        return null;
    }

    /**
     * 自动检测适配器
     */
    private function detectFrameworkAdapter(string $poolName): ?ConnectionAdapterInterface
    {
        $detectedFramework = $this->detectFramework();
        
        switch ($detectedFramework) {
            case 'thinkphp':
                return new ThinkPHPAdapter($poolName);
            case 'hyperf':
                return new HyperfAdapter($poolName);
            default:
                return null;
        }
    }

    /**
     * 检测当前运行的框架类型
     */
    private function detectFramework(): ?string
    {
        // 检测ThinkPHP
        if (class_exists('\think\App') || class_exists('\Think\App')) {
            return 'thinkphp';
        }
        
        // 检测Hyperf
        if (class_exists('\Hyperf\Framework\ApplicationFactory') || 
            class_exists('\Hyperf\HttpServer\Server')) {
            return 'hyperf';
        }
        
        return null;
    }
}
