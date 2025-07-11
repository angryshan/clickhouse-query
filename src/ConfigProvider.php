<?php

declare(strict_types=1);

namespace ClickHouseQuery;

/**
 * 配置提供者
 * @package ClickHouseQuery
 * @author angryshan
 */
class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                // 在这里定义依赖注入关系，如有需要
            ],
            'commands' => [
                // 在这里定义命令
            ],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'ClickHouse Query配置文件',
                    'source' => __DIR__ . '/../publish/clickhouse.php',
                    'destination' => BASE_PATH . '/config/autoload/clickhouse.php',
                ],
            ],
        ];
    }
} 