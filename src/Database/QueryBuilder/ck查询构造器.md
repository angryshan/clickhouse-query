# ClickHouse 查询构造器

## 1. 基础查询

### 1.1 查询全部
```php
$result = $query->get();
```

### 1.2 指定字段
```php
$result = $query->select(['id', 'name', 'status'])->get();
```

### 1.3 条件查询
```php
// 数组格式(推荐)
$result = $query->where([
    ['status', '=', 1],
    ['created_at', '>=', '2024-01-01']
])->get();

// 等值查询简写
$result = $query->where([
    'status' => 1,
    'type' => 2
])->get();
```

### 1.4 获取第一条
```php
$first = $query->first();
```

> 注意: 推荐使用数组格式的条件写法,更清晰且支持所有操作符

## 2. 高级查询

### 2.1 分组统计
```php
$result = $query->select([
    'type',
    'COUNT(*) as count'
])
->groupBy(['type'])
->get();
```

### 2.2 排序
```php
// 单字段排序
$result = $query->orderBy('created_at', 'DESC')->get();

// 多字段排序（数组方式）
$result = $query->orderBy([
    'level' => 'DESC',
    'created_at' => 'ASC'
])->get();

// 多字段排序（链式调用）
$result = $query
    ->orderBy('level', 'DESC')
    ->orderBy('created_at', 'ASC')
    ->get();
```

### 2.3 分页
```php
// 基本分页
$result = $query->limit(10)->offset(20)->get();

// 获取总数
$total = $query->count();

// 高级分页（自动处理分页参数）
$pageData = $query->getPageData();
// 返回格式:
// [
//     'total' => 100,         // 总记录数
//     'per_page' => 10,       // 每页记录数
//     'current_page' => 1,    // 当前页码
//     'last_page' => 10,      // 最后一页页码
//     'data' => [...]         // 当前页数据
// ]

// 自定义分页参数
$pageData = $query->getPageData([
    'page' => 2,
    'pageSize' => 15
]);

// 优化计数查询
$pageData = $query->getPageData([], true, 'id');

// 导出全部数据（忽略分页）
$allData = $query->getPageData(['is_export' => true]);
```

### 2.4 统计
```php
$count = $query->count();

$count = $query->count('distinct user_id');

$count = $query->where([
    ['status', '=', 1]
])->count();
```

### 2.5 HAVING 子句

```php
// 基础用法
$result = $query
    ->select(['department', 'COUNT(*) as count'])
    ->groupBy(['department'])
    ->having(['count' => 10])
    ->get();

// 复杂条件
$result = $query
    ->select(['department', 'COUNT(*) as count', 'AVG(salary) as avg_salary'])
    ->groupBy(['department'])
    ->having([
        ['count', '>', 10],
        ['avg_salary', '>=', 5000]
    ])
    ->get();
```

> 注意: HAVING 子句通常与 GROUP BY 一起使用，用于过滤分组后的结果。条件格式与 WHERE 子句相同。

## 3. WITH 子句

### 3.1 基础用法
```php
$result = $query->with('Stats', function($query) {
    $query->select(['server_id', 'COUNT(*) as count'])
        ->groupBy(['server_id']);
})
->select(['server_id', 'count'])
->from('Stats')
->get();
```

### 3.2 多个 WITH 子句
```php
$result = $query
    ->with('DailyStats', function($query) {
        $query->select(['date', 'COUNT(*) as count'])
            ->groupBy(['date']);
    })
    ->with('ServerStats', function($query) {
        $query->select(['server_id', 'SUM(amount) as total'])
            ->groupBy(['server_id']);
    })
    ->from('DailyStats')
    ->get();
```

## 4. 全局条件控制

### 4.1 默认行为
```php
// 全局条件(如 game_id)会自动添加
$result = $query->select(['*'])->get();
```

### 4.2 控制全局条件
```php
// 临时关闭全局条件
$result = $query
    ->withoutGlobalConditions()
    ->select(['*'])
    ->get();

// 恢复使用全局条件
$result = $query
    ->withGlobalConditions()
    ->select(['*'])
    ->get();
```

### 4.3 使用场景
1. 需要查询其他游戏数据时
2. 统计多个游戏的数据时
3. 系统管理后台查询时
4. 需要自定义 game_id 条件时

## 5. 其他功能

### 5.1 获取SQL
```php
$sql = $query->toSql();
```

### 5.2 执行原生SQL
```php
$result = $query->execute('SELECT * FROM table WHERE id = 1');
```

### 5.3 连接控制
```php
// 使用连接控制(并发限制)
$result = $query->get(true);

// 在其他方法中使用连接控制
$first = $query->first(true);
$count = $query->count('', true);
$pageData = $query->getPageData([], true, '', true);
```

### 5.4 自定义配置
```php
// 在创建查询构建器时指定配置
$query = new ClickHouseQueryBuilder('table_name', [
    'pool_name' => 'custom_clickhouse',
    'adapter' => \ClickHouseQuery\Adapters\ThinkPHPAdapter::class,
    'max_running_processes' => 10
]);
```

## 6. 注意事项

1. 查询执行后会自动重置状态
2. 字符串值会自动添加引号和转义
3. 默认添加全局条件(game_id)
4. WITH 子句中的子查询会继承全局条件
5. 建议使用连接控制避免并发查询过多
6. 复杂查询建议先使用 toSql() 检查生成的SQL
7. withoutGlobalConditions() 只对当前查询有效
8. getPageData() 方法会自动处理分页参数和计算总记录数
9. 导出功能可通过 getPageData(['is_export' => true]) 实现