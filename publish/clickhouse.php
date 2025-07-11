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

    // 适配器
    'adapter' => '', // \ClickHouseQuery\Adapters\HyperfAdapter::class   || \ClickHouseQuery\Adapters\ThinkPHPAdapter::class
]; 