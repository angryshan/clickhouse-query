# ClickHouse Query 测试示例文档

## 1. 概述

本文档介绍了 ClickHouse Query 库的测试示例，展示了如何使用该库进行 ClickHouse 数据库查询。示例采用了 Tables + Services 的设计模式，清晰分离了数据结构定义和业务逻辑。

## 2. 目录结构

```
test/
├── Tables/                 # 表结构定义
│   └── TestTable.php      # 测试表定义
└── Services/               # 业务服务层
    ├── AbstractService.php # 服务基类
    └── TestService.php     # 测试服务实现
```

## 3. 核心组件

### 3.1 表结构定义 (Tables)

表结构类负责定义数据表的基本信息，包括表名和字段。

**TestTable.php**:
```php
<?php

namespace ClickHouseQuery\Test\Tables;

use ClickHouseQuery\Tables\AbstractTable;

class TestTable extends AbstractTable
{
    // 定义表名常量
    const TABLE_NAME = 'test';
    
    // 可扩展：添加字段定义
    // const FIELD_ID = 'id';
    // const FIELD_NAME = 'name';
}
```

### 3.2 服务层 (Services)

服务层负责封装业务逻辑和数据操作。

**AbstractService.php**:
```php
<?php
namespace ClickHouseQuery\Test\Services;

use ClickHouseQuery\Database\QueryBuilder\ClickHouseQueryBuilder;

abstract class AbstractService
{
    // 当前操作的表名
    protected string $table;

    // 创建查询构建器
    protected function query(): ClickHouseQueryBuilder
    {
        return new ClickHouseQueryBuilder($this->table);
    }
}
```

**TestService.php**:
```php
<?php

namespace ClickHouseQuery\Test\Services;

use ClickHouseQuery\Test\Tables\TestTable;

class TestService extends AbstractService
{
    // 在构造函数中设置表名
    public function __construct()
    {
        $this->table = TestTable::TABLE_NAME;
    }

    // 获取测试数据的方法
    public function getTestData()
    {
        return $this->query()->select(['id', 'name'])->get();
    }
}
```

## 4. 使用示例

### 4.1 基本查询

```php
<?php

// 创建服务实例
$testService = new \ClickHouseQuery\Test\Services\TestService();

// 执行查询获取数据
try {
    $data = $testService->getTestData();
    print_r($data);
} catch (\Exception $e) {
    echo "查询出错: " . $e->getMessage();
}
```

### 4.2 自定义查询

```php
<?php

$testService = new \ClickHouseQuery\Test\Services\TestService();

// 扩展TestService添加自定义查询方法
class ExtendedTestService extends \ClickHouseQuery\Test\Services\TestService 
{
    // 按条件查询数据
    public function getFilteredData(int $minId, string $nameLike)
    {
        return $this->query()
            ->select(['id', 'name', 'created_at'])
            ->where([
                'id >' => $minId,
                'name LIKE' => "%{$nameLike}%"
            ])
            ->orderBy('id', 'DESC')
            ->limit(10)
            ->get();
    }
}

$extendedService = new ExtendedTestService();
$filteredData = $extendedService->getFilteredData(100, 'test');
```

## 5. 设计说明

### 5.1 Tables 与 Services 的分离

本示例采用了 `Tables + Services` 的设计模式，相当于传统 ORM 中 Model 的职责分离：

- **Tables**: 负责定义"数据结构"（表名、字段等）
- **Services**: 负责定义"业务逻辑"（如何查询、处理数据）

这种分离带来以下优势：

1. **关注点分离**：结构定义与业务逻辑清晰区分
2. **代码组织更清晰**：避免单一类过于臃肿
3. **更灵活的复用**：同一个表可被多个服务使用

### 5.2 查询构建器的使用

查询构建器提供了链式 API，使查询代码更加直观：

```php
$this->query()
    ->select(['field1', 'field2'])
    ->where(['condition' => 'value'])
    ->orderBy('field', 'DESC')
    ->limit(10)
    ->get();
```

## 6. 扩展建议

### 6.1 表结构扩展

可以在 TestTable 中添加更多表结构信息：

```php
class TestTable extends AbstractTable
{
    const TABLE_NAME = 'test';
    
    // 添加字段定义
    const FIELD_ID = 'id';
    const FIELD_NAME = 'name';
    const FIELD_CREATED_AT = 'created_at';
    
    // 添加字段类型映射
    public static array $fieldTypes = [
        self::FIELD_ID => 'UInt32',
        self::FIELD_NAME => 'String',
        self::FIELD_CREATED_AT => 'DateTime'
    ];
}
```

### 6.2 服务层扩展

可以在 TestService 中添加更多业务方法：

```php
class TestService extends AbstractService
{
    // 构造函数设置表名
    public function __construct()
    {
        $this->table = TestTable::TABLE_NAME;
    }
    
    // 基本查询
    public function getTestData()
    {
        return $this->query()->select(['id', 'name'])->get();
    }
    
    // 按ID查询
    public function findById(int $id)
    {
        return $this->query()
            ->select(['id', 'name', 'created_at'])
            ->where(['id' => $id])
            ->first();
    }
    
    // 统计查询
    public function countByDateRange(string $startDate, string $endDate)
    {
        return $this->query()
            ->where([
                ['created_at', '>=', $startDate],
                ['created_at', '<=', $endDate]
            ])
            ->count();
    }
}
```

## 7. 最佳实践

1. **常量定义字段名**：使用常量定义字段名，避免字符串硬编码
2. **异常处理**：总是使用 try-catch 包裹查询操作
3. **查询重用**：将常用查询封装为服务方法
4. **参数验证**：在服务方法中添加参数验证逻辑
5. **结果转换**：根据需要在服务层添加结果格式转换

## 8. 总结

本测试示例展示了 ClickHouse Query 库的基本用法，通过 Tables 和 Services 的分离，提供了一种清晰、可维护的数据访问方式。这种设计特别适合 ClickHouse 这样的分析型数据库，能够更好地组织复杂的查询逻辑和数据结构定义。
