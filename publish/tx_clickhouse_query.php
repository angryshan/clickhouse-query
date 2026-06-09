<?php

return [
    // 连接池名称
    'pool_name' => 'clickhouse',
    
    // 连接控制
    'connection_control' => [
        // 最大并发查询数
        'max_running_processes' => 5,
        
        // 最大等待尝试次数
        'max_wait_attempts' => 60,
        
        // 最小等待时间（微秒）
        'wait_min_microseconds' => 500000,
        
        // 最大等待时间（微秒）
        'wait_max_microseconds' => 1000000,
    ],
    
    // 全局查询条件
    'global_conditions' => [
        // 示例: 'game_id' => 1, 
    ],

    // 适配器 示例: \ClickHouseQuery\Adapters\HyperfAdapter::class
    'adapter' => '',
    
    // 时间分区自动优化配置
    'time_partition_optimization' => [
        // 是否启用自动优化
        'enabled' => true,
        
        // 时间戳类型：'seconds' 或 'milliseconds'
        'timestamp_type' => 'seconds',
        
        // 字段映射配置
        'field_mapping' => [
            // 通用配置（适用于所有表，大部分表用这个就够了）
            'default' => [
                'log_time' => 'log_date',       // 日志时间
                'create_time' => 'log_date',    // 创建时间
            ],
            
            // 特定表配置（按表名配置，会覆盖通用配置）
            'tables' => [
                'client_monitor_log' => [
                    'log_time' => 'log_date',
                    'timestamp_type' => 'seconds',  // 该表使用秒级时间戳
                ],
                'server_monitor_log' => [
                    'log_time' => 'log_date',
                    'timestamp_type' => 'milliseconds',  // 该表使用毫秒级时间戳
                ],
                'user_events' => [
                    'event_time' => 'event_date',
                    'create_time' => 'event_date',
                    // 不指定 timestamp_type，使用全局配置或自动检测
                ],
            ],
        ],
    ],
]; 