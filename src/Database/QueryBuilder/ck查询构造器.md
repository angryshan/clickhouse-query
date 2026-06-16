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

#### 组件层（拼 SQL）

```php
// 基本分页
$result = $query->limit(10)->offset(20)->get();

// 获取总数
$total = $query->count();

// 推荐：纯分页（调用方显式传入 page、是否 WITH 计数）
$pageData = $query->paginate(
    1,      // page
    10,     // perPage
    false,  // wrapCountWithClause：是否用 WITH 子查询包装计数
    ''      // countField
);
// 返回格式:
// [
//     'total' => 100,         // 总记录数
//     'per_page' => 10,       // 每页记录数
//     'current_page' => 1,    // 当前页码
//     'last_page' => 10,      // 最后一页页码
//     'data' => [...]         // 当前页数据
// ]

// CTE 查询（with + from）需包装计数
$pageData = $query
    ->with('Stats', function ($q) { /* ... */ })
    ->from('Stats')
    ->paginate(1, 10, true);
```

> `paginate()` 第三个参数 `wrapCountWithClause` 由**调用方决定**，组件不做自动判断。

#### 兼容入口 getPageData()（存量代码）

```php
// 从 HTTP 请求读取 page / pageSize（params 为空时）
$pageData = $query->getPageData();

// 自定义分页参数
$pageData = $query->getPageData([
    'page' => 2,
    'pageSize' => 15,
]);

// 强制 WITH 计数包装
$pageData = $query->getPageData([], true, 'id');

// 导出全部数据（返回裸数组，非分页结构）
$allData = $query->getPageData(['is_export' => true]);
```

> 新代码请用 `paginate()` 或 `AbstractService::paginate()`，勿再扩展 `getPageData()`。

#### 服务层（业务入口，推荐）

继承 `AbstractService` 后使用 `paginate()`，统一处理：请求参数、pageSize 上限、偏移校验。

```php
class LogService extends AbstractService
{
    public function list(array $where): array
    {
        $query = $this->query()->where($where)->select(['*']);

        // 列表：分页结构；is_export=true 时返回裸数组（与 getPageData 一致）
        return $this->paginate($query);
    }
}
```

返回约定：

| 场景 | 返回 |
|------|------|
| 列表 | `{total, per_page, current_page, last_page, data}` |
| `is_export=true` | 裸二维数组 `[...]` |

`$useWithClause` 三态：`null` 有 `from` 才 WITH 计数；`true` 强制 WITH；`false` 强制直接 count。


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

## 6. 时间分区自动优化

### 6.1 功能说明

当 ClickHouse 表按日期分区，但查询使用时间戳字段时，自动添加日期条件实现分区裁剪，提升性能 100-1000 倍。

### 6.2 适用场景

```sql
-- 表结构
CREATE TABLE logs (
    log_time UInt64,                        -- 时间戳
    log_date Date DEFAULT toDate(log_time), -- 日期
    ...
)
PARTITION BY toYYYYMMDD(log_date);  -- 按日期分区
```

### 6.3 自动优化示例

```php
// 原始查询
$result = $query->where([
    ['log_time', '>=', 1717516800]  // 查询时间戳
])->get();

// 自动生成 SQL
// SELECT * FROM logs 
// WHERE `log_time` >= 1717516800 
//   AND `log_date` >= '2024-06-05'  ✅ 自动添加
```

### 6.4 支持的操作符

```php
// 大于
$query->where([['log_time', '>', 1717516800]]);
// 自动添加: log_date >= '2024-06-05'

// 大于等于
$query->where([['log_time', '>=', 1717516800]]);
// 自动添加: log_date >= '2024-06-05'

// 小于
$query->where([['log_time', '<', 1717603200]]);
// 自动添加: log_date <= '2024-06-06'

// 小于等于
$query->where([['log_time', '<=', 1717603200]]);
// 自动添加: log_date <= '2024-06-06'

// 等于
$query->where([['log_time', '=', 1717560000]]);
// 自动添加: log_date = '2024-06-05'

// BETWEEN (范围查询)
$query->where([['log_time', 'BETWEEN', [1717516800, 1717603200]]]);
// 自动添加: log_date BETWEEN '2024-06-05' AND '2024-06-06'
```

### 6.5 配置说明

```php
// config/tx_clickhouse_query.php
'time_partition_optimization' => [
    'enabled' => true,  // 是否启用
    
    'field_mapping' => [
        // 通用配置
        'default' => [
            'log_time' => 'log_date',
            'create_time' => 'log_date',
        ],
        
        // 表特定配置
        'tables' => [
            'client_monitor_log' => [
                'log_time' => 'log_date',
                'timestamp_type' => 'seconds',  // 秒级
            ],
            'server_monitor_log' => [
                'log_time' => 'log_date',
                'timestamp_type' => 'milliseconds',  // 毫秒级
            ],
        ],
    ],
]
```

### 6.6 注意事项

1. ✅ 自动识别秒级/毫秒级时间戳
2. ✅ 避免重复添加（智能检测已有条件）
3. ✅ 支持括号、复杂条件
4. ✅ 支持 BETWEEN 范围查询（自动转换为 BETWEEN 日期条件）
5. ❌ 不支持 IN 操作符
6. ❌ 如果表已经用 `PARTITION BY toYYYYMMDD(fromUnixTimestamp(timestamp_field))`，则不需要此优化

## 7. 注意事项

1. 查询执行后会自动重置状态
2. 字符串值会自动添加引号和转义
3. 默认添加全局条件(game_id)
4. WITH 子句中的子查询会继承全局条件
5. 建议使用连接控制避免并发查询过多
6. 复杂查询建议先使用 toSql() 检查生成的SQL
7. withoutGlobalConditions() 只对当前查询有效
8. 分页：`paginate()` 为组件推荐入口；`getPageData()` 为兼容存量；业务侧推荐 `AbstractService::paginate()`
9. 导出：`is_export=true` 时 `getPageData()` 与 `AbstractService::paginate()` 均返回裸数组，非分页结构
10. 时间分区优化默认启用，可在配置中关闭