# ClickHouse查询构建器

## 功能

* 基于链式API的ClickHouse查询构建
* 表结构映射和字段管理
* 数据库连接管理和并发控制
* 统一的异常处理机制

## 环境要求

* php >= 7.4
* hyperf >= 2.0
* thinkphp >= 6.0

## 安装

```bash
composer require angryshan/clickhouse-query -vvv
```

## 配置文件

发布配置文件`config/clickhouse.php`

```bash
php bin/hyperf.php vendor:publish angryshan/clickhouse-query
php think vendor:publish
```

或者复制以下内容到`config/clickhouse.php`
```
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
    
    // 自定义适配器类
    'adapter' => \ClickHouseQuery\Adapters\ThinkPHPAdapter::class, // 或 \ClickHouseQuery\Adapters\HyperfAdapter::class
]; 
```

## 使用示例

### 基础查询

```php
use ClickHouseQuery\Database\QueryBuilder\ClickHouseQueryBuilder;
use ClickHouseQuery\Tables\AbstractTable;

// 定义表
class BusinessTable extends AbstractTable
{
    protected string $tableName = 'business_table';
}

// 创建查询构建器
$table = new BusinessTable();
$builder = new ClickHouseQueryBuilder($table->getTableName());

// 执行查询
try {
    $result = $builder
        ->select(['id', 'name'])
        ->where(['status' => 1])
        ->get();
        
    // 处理结果...
    
} catch (Exception $e) {
    // 异常处理
}
```

### 复杂查询

```php
$result = $builder
    ->select(['department', 'COUNT(*) as count'])
    ->where([
        'status' => 1,
        'created_at >' => '2024-01-01'
    ])
    ->groupBy(['department'])
    ->orderBy('count', 'DESC')
    ->limit(10)
    ->get();
```

## 注意事项

* 必须使用 try-catch 处理异常
* 查询执行后自动重置状态
* WHERE条件需使用数组格式
* 字段名需符合ClickHouse规范

## 驱动兼容性

ClickHouse支持两种主要的连接协议：

1. **HTTP协议** - 端口8123，最通用，适合跨平台应用
2. **Native TCP协议** - 端口9000，性能最佳，官方命令行客户端使用

本查询构建器**专为MySQL接口+PDO驱动方式**设计，通过以下框架配置使用：

```php
// ThinkPHP配置 config/database.php
'clickhouse' => [
    'type' => 'mysql', // 使用PDO驱动
    'hostname' => '127.0.0.1',
    'hostport' => '9000', 
    // ...其他配置
]

// Hyperf配置 config/db.php
'clickhouse' => [
    'driver' => 'pdo',
    'host' => '127.0.0.1',
    'port' => '9000', 
    // ...其他配置
]
```

框架兼容性说明：
- **ThinkPHP**: 使用`\ClickHouseQuery\Adapters\ThinkPHPAdapter`适配器
- **Hyperf**: 使用`\ClickHouseQuery\Adapters\HyperfAdapter`适配器

**适配器选择策略**:
1. 优先使用配置中显式指定的适配器
2. 如未指定，将尝试自动检测当前框架环境（仅开发环境）
3. 如无法检测，将抛出异常要求指定适配器

为确保生产环境的稳定性，**强烈建议**在配置中显式指定适配器。

如需使用HTTP/TCP原生协议，需要：

1. 创建自定义适配器类实现`ConnectionAdapterInterface`接口
2. 在配置中指定自定义适配器：
```php
// 方法一：在clickhouse.php配置文件中指定
return [
    // 其他配置...
    'adapter' => \Your\Custom\AdapterClass::class,
];

// 方法二：在初始化时指定
$connection = new ClickHouseConnection([
    'adapter' => \Your\Custom\AdapterClass::class,
]);
```

## 高级功能

### 条件构建

使用when方法进行条件查询：

```php
$result = $builder
    ->select(['id', 'name'])
    ->when($hasStatus, function($query) use ($status) {
        $query->where(['status' => $status]);
    })
    ->get();
```

### 分页查询

```php
$page = 1;
$pageSize = 20;
$result = $builder
    ->select(['id', 'name'])
    ->where(['status' => 1])
    ->getPageData($page, $pageSize);
```

### 异常处理

```php
try {
    $result = $builder->select(['id'])->get();
} catch (ClickHouseQueryException $e) {
    // 处理ClickHouse查询异常
    $errorMessage = $e->getMessage();
    $errorCode = $e->getCode();
    // 记录日志或其他处理...
} catch (\Exception $e) {
    // 处理其他异常
}
```

## 文档说明

更多详细信息请参考代码内的注释和类文档。

## 许可证

MIT 许可证（MIT）。有关更多信息，请参见[协议文件](LICENSE)。