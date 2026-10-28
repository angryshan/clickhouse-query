<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Factories;

use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Server;
use think\App;
use TxAdmin\ClickHouseQuery\Adapters\HyperfAdapter;
use TxAdmin\ClickHouseQuery\Adapters\ThinkPHPAdapter;
use TxAdmin\ClickHouseQuery\Exceptions\ClickHouseQueryException;
use TxAdmin\ClickHouseQuery\Interfaces\ConnectionAdapterInterface;

class AdapterFactory
{
    /**
     * 创建适配器实例
     */
    public function create(array $config, string $poolName): ConnectionAdapterInterface
    {
        $adapter = $this->resolveFromExplicitConfig($config, $poolName);
        if ($adapter !== null) {
            return $adapter;
        }

        $adapter = $this->detectFrameworkAdapter($poolName);
        if ($adapter !== null) {
            return $adapter;
        }

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

        $configAdapter = config('tx_clickhouse_query.adapter');
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
        return match ($this->detectFramework()) {
            'thinkphp' => new ThinkPHPAdapter($poolName),
            'hyperf' => new HyperfAdapter($poolName),
            default => null,
        };
    }

    private function detectFramework(): ?string
    {
        if (class_exists(App::class)) {
            return 'thinkphp';
        }

        if (
            class_exists(ApplicationContext::class)
            || class_exists(Server::class)
        ) {
            return 'hyperf';
        }

        return null;
    }
}
