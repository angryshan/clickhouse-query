# WHERE 条件构建说明

## 1. 基础用法

### 1.1 数组条件格式
```php
$query->where([
    ['status', '=', 1],
    ['created_at', '>=', '2024-01-01'],
    ['type', 'IN', [1, 2, 3]]
]);

// 等值查询简写:
$query->where([
    'status' => 1,
    'type' => 2
]);
```

### 1.2 ClickHouse 函数
```php
$query->where([
    ['create_time', '>=', 'toDateTime(\'2024-01-01\')'],
    ['date', '=', 'toDate(create_time)'],
    ['count', '>', 'count(*)']
]);
```

## 2. 高级用法

### 2.1 OR 条件
```php
$query->where([
    ['status', '=', 1]
])->orWhere([
    ['type', 'IN', [2, 3]],
    'level' => 10
]);
```

### 2.2 NULL 条件
```php
$query->whereNull('deleted_at');
$query->whereNotNull('updated_at');
```

### 2.3 IN 条件
```php
$query->whereIn('status', [1, 2, 3]);
$query->whereNotIn('type', ['A', 'B']);
```

### 2.4 BETWEEN 条件
```php
$query->whereBetween('created_at', '2024-01-01', '2024-12-31');

// 使用数组格式
$query->where([
    ['created_at', 'BETWEEN', ['2024-01-01', '2024-12-31']]
]);
```

### 2.5 条件组
```php
$query->whereGroup(function($query) {
    $query->where([
        ['status', '=', 1],
        ['type', 'IN', [2, 3]]
    ]);
}, 'OR');
```

### 2.6 复杂条件组合
```php
$query->whereGroup(function($query) {
    $query->where([
        ['platform', '=', 1]
    ])->orWhere([
        ['platform', '=', 2]
    ]);
}, 'OR')->where([
    ['status', '=', 1],
    ['level', '>', 10]
]);
// WHERE (platform = 1 OR platform = 2) OR (status = 1 AND level > 10)
```

### 2.7 原始 SQL 条件

```php
// 基本用法
$query->whereRaw('date >= toDate(now()) - 7');

// 带参数绑定
$query->whereRaw('player_level > ? AND vip_level >= ?', [10, 3]);

// 与其他条件组合
$query->where(['status' => 1])
      ->whereRaw('toDate(created_at) >= today() - 30');
```

> 注意: whereRaw 方法允许您使用 ClickHouse 特有的函数和表达式，参数通过问号占位符绑定。

### 2.8 条件判断

```php
// 当条件为真时，添加查询条件
$query->when($isActive, function($query) {
    $query->where(['status' => 1]);
});

// 带默认回调
$query->when($userType === 'admin', 
    // 当条件为真时执行
    function($query) {
        $query->whereIn('permission', ['read', 'write', 'admin']);
    }, 
    // 当条件为假时执行
    function($query) {
        $query->where(['permission' => 'read']);
    }
);

// 结合请求参数
$query->when(
    !empty($request['date_range']),
    function($query, $value) use ($request) {
        $query->whereBetween('created_at', $request['date_range'][0], $request['date_range'][1]);
    }
);
```

> 注意: when 方法提供了一种简洁的方式来根据条件添加查询约束，避免了复杂的 if-else 语句。

## 3. 支持的操作符

- 比较操作符: `=`, `>`, `<`, `>=`, `<=`, `!=`, `<>`
- 包含操作符: `IN`, `NOT IN`
- 范围操作符: `BETWEEN`
- 模糊匹配: `LIKE`

## 4. 全局条件

### 4.1 默认行为
默认情况下,全局条件(如 game_id)会自动添加到所有查询中。

### 4.2 控制全局条件
```php
// 不使用全局条件
$query->withoutGlobalConditions()
    ->select(['*'])
    ->where([
        ['game_id', '=', 1001] 
    ])
    ->get();

// 使用全局条件(默认)
$query->withGlobalConditions()
    ->select(['*'])
    ->where([
        ['log_type', '=', 'ERROR'] 
    ])
    ->get();
```

> 注意: withoutGlobalConditions() 只对当前查询有效,执行完查询后会自动重置状态

## 5. 注意事项

1. 条件值不能为 NULL,请使用 whereNull/whereNotNull
2. IN 条件的值数组不能为空
3. 字符串值会自动添加引号和转义
4. 日期时间值使用标准格式
5. ClickHouse 函数表达式会保持原样
6. 复杂条件建议使用条件组来组织
7. BETWEEN 操作符需要两个值，可以使用数组格式 `['field', 'BETWEEN', [value1, value2]]`
8. 使用 whereRaw 时注意 SQL 注入风险，优先使用参数绑定
9. when 方法可以简化条件判断逻辑，提高代码可读性
