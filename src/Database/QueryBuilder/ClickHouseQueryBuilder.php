<?php

declare(strict_types=1);

namespace ClickHouseQuery\Database\QueryBuilder;

use ClickHouseQuery\Database\Connection\ClickHouseConnection;
use ClickHouseQuery\Database\QueryBuilder\Traits\PaginationTrait;
use ClickHouseQuery\Database\QueryBuilder\Traits\TimePartitionOptimizationTrait;
use ClickHouseQuery\Database\QueryBuilder\Traits\WhereBuilderTrait;
use ClickHouseQuery\Database\QueryBuilder\Traits\WithFromBuilderTrait;
use ClickHouseQuery\Exceptions\ClickHouseQueryException;

/**
 * 构建ClickHouse查询
 * @package ClickHouseQuery\Database\QueryBuilder
 * @author angryshan
 */
class ClickHouseQueryBuilder
{
    use WhereBuilderTrait;
    use WithFromBuilderTrait;
    use TimePartitionOptimizationTrait;
    use PaginationTrait;

    private array $fields = [];
    private array $groupBy = [];
    private array $orderBy = [];
    private int $limit = 0;
    private int $offset = 0;
    private array $havingConditions = [];

    private array $config = [];

    private string $table;
    private ClickHouseConnection $connection;

    /**
     * 是否使用WHERE全局条件 默认为true
     */
    private bool $useGlobalConditions = true;

    /**
     * 分区优化是否已执行（防止 toSql() 多次调用重复追加 conditions）
     */
    private bool $conditionsOptimized = false;

    /**
     * 初始化查询构建器
     *
     * @param string $table 表名
     * @param array $config 配置
     * @param ClickHouseConnection|null $connection 传入则共用连接（newQuery 场景）
     */
    public function __construct(string $table, array $config = [], ?ClickHouseConnection $connection = null)
    {
        $this->table = $table;
        $this->config = $config;
        $this->connection = $connection ?? new ClickHouseConnection($this->config);
    }

    public function getConnection(): ClickHouseConnection
    {
        return $this->connection;
    }

    /**
     * 创建同表、同配置的新查询实例（共用 connection，不含当前 where/limit/with 等条件）
     */
    public function newQuery(): self
    {
        return new self($this->table, $this->config, $this->connection);
    }

    /**
     * 设置查询字段
     *
     * @param array $fields 要查询的字段数组
     * @return self
     */
    public function select(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * 设置分组条件
     *
     * @param array $groups 分组字段数组
     * @return self
     */
    public function groupBy(array $groups): self
    {
        $this->groupBy = $groups;
        return $this;
    }
    
    /**
     * 添加 HAVING 子句
     *
     * @param array $conditions HAVING 条件数组
     * @return self
     */
    public function having(array $conditions): self
    {
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                // 数组格式: [['field', 'operator', 'value']]
                $this->havingConditions[] = $this->parseArrayCondition($value);
            } else {
                // 等值查询: ['field' => value]，alias.field 与 WHERE 一样拆段加反引号
                $this->havingConditions[] = $this->formatField((string)$field) . ' = ' . $this->quoteValue($value);
            }
        }

        return $this;
    }

    /**
     * 设置排序条件
     *
     * @param string|array $column 排序字段或排序条件数组
     * @param string $direction 排序方向(ASC|DESC)，当$column为字符串时使用
     * @return self
     */
    public function orderBy($column, string $direction = 'ASC'): self
    {
        // 处理数组格式：['field1' => 'ASC', 'field2' => 'DESC']
        if (is_array($column)) {
            foreach ($column as $field => $dir) {
                $this->orderBy[] = $this->formatField((string)$field) . ' ' . strtoupper((string)$dir);
            }
        } else {
            // 处理字符串格式：'field', 'ASC'；t.log_time → `t`.`log_time`
            $this->orderBy[] = $this->formatField((string)$column) . ' ' . strtoupper($direction);
        }
        return $this;
    }

    /**
     * 设置返回数量限制
     *
     * @param int $limit 限制数量
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * 设置偏移量
     *
     * @param int $offset 偏移数量
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * 执行查询并获取结果（执行后保留构建器状态，可继续链式调用）
     *
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 查询结果
     * @throws ClickHouseQueryException
     */
    public function get(bool $useConnectionControl = false): array
    {
        $sql = $this->buildQuery();

        return $this->connection->execute($sql, $useConnectionControl);
    }

    /**
     * 获取查询结果的第一条记录（临时 LIMIT 1 后恢复原 limit）
     *
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 第一条记录或空数组
     * @throws ClickHouseQueryException
     */
    public function first(bool $useConnectionControl = false): array
    {
        $originalLimit = $this->limit;
        $this->limit = 1;

        $sql = $this->buildQuery();
        $result = $this->connection->execute($sql, $useConnectionControl);

        $this->limit = $originalLimit;

        if (empty($result)) {
            return [];
        }

        // 返回第一条记录
        return $result[0] ?? [];
    }

    /**
     * 执行 COUNT 查询
     *
     * @param string $column 要统计的字段，默认为空时使用 count()
     * @param bool $useConnectionControl 是否使用连接控制
     * @return int 统计结果
     * @throws ClickHouseQueryException
     */
    public function count(string $column = '', bool $useConnectionControl = false): int
    {
        // 保存原有的字段设置
        $originalFields = $this->fields;
        $originalOrderBy = $this->orderBy;

        // 设置 COUNT 查询
        if (empty($column)) {
            $this->fields = ["count() as count"]; // 使用 count() 而不是 count(*)
        } else {
            $this->fields = ["count({$column}) as count"];
        }
        // 获取数量 无需排序
        $this->orderBy = [];
        // 执行查询
        $result = $this->get($useConnectionControl);

        // 恢复原有的字段设置
        $this->fields = $originalFields;
        $this->orderBy = $originalOrderBy;

        // 返回统计结果
        return (int)($result[0]['count'] ?? 0);
    }

    /**
     * 获取构建的SQL语句
     *
     * @return string SQL语句
     */
    public function toSql(): string
    {
        return $this->buildQuery();
    }

    /**
     * 执行原始SQL语句
     *
     * @param string $sql SQL语句
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 查询结果
     * @throws ClickHouseQueryException
     */
    public function execute(string $sql, bool $useConnectionControl = false): array
    {
        return $this->connection->execute($sql, $useConnectionControl);
    }

    /**
     * 禁用全局条件
     */
    public function withoutGlobalConditions(): self
    {
        $this->useGlobalConditions = false;
        return $this;
    }
    
    /**
     * 启用全局条件
     */
    public function withGlobalConditions(): self
    {
        $this->useGlobalConditions = true;
        return $this;
    }

    /**
     * 构建完整的SQL查询语句
     *
     * @return string 完整的SQL语句
     */
    private function buildQuery(): string
    {
        $parts = [
            'WITH' => $this->buildWith(),
            'SELECT' => $this->buildSelect(),
            'FROM' => $this->buildFrom(),
            'WHERE' => $this->buildWhere(),
            'GROUP BY' => $this->buildGroupBy(),
            'HAVING' => $this->buildHaving(),
            'ORDER BY' => $this->buildOrderBy(),
            'LIMIT' => $this->buildLimit(),
        ];

        return implode(' ', array_filter($parts, fn($part) => !empty($part)));
    }
    
    /**
     * 构建SELECT部分
     */
    private function buildSelect(): string
    {
        return empty($this->fields) ? 'SELECT *' : 'SELECT ' . implode(', ', $this->fields);
    }

    /**
     * 构建 WHERE 部分
     */
    private function buildWhere(): string
    {
        // 分区优化只执行一次，防止 toSql() 多次调用重复追加 log_date 条件
        if (!$this->conditionsOptimized) {
            $this->applyTimePartitionOptimization();
            $this->conditionsOptimized = true;
        }

        $conditions = [];
        if ($this->useGlobalConditions && empty($this->from)) {
            $globalWhere = $this->connection->getGlobalWhere();
            if (!empty($globalWhere)) {
                $conditions[] = $globalWhere;
            }
        }

        $whereConditions = $this->getConditions();

        $allConditions = array_merge($conditions, $whereConditions);
        return !empty($allConditions) ? 'WHERE ' . implode(' AND ', $allConditions) : '';
    }

    /**
     * 获取构建WHERE部分
     */
    public function getBuildWhere(): string
    {
        return $this->buildWhere();
    }

    /**
     * 构建GROUP BY部分
     */
    private function buildGroupBy(): string
    {
        return !empty($this->groupBy) ? 'GROUP BY ' . implode(', ', $this->groupBy) : '';
    }

    /**
     * 构建ORDER BY部分
     */
    private function buildOrderBy(): string
    {
        return !empty($this->orderBy) ? 'ORDER BY ' . implode(', ', $this->orderBy) : '';
    }

    /**
     * 获取构建ORDER BY部分
     */
    public function getBuildOrderBy(): string
    {
        return $this->buildOrderBy();
    }

    /**
     * 构建LIMIT和OFFSET部分
     */
    private function buildLimit(): string
    {
        if ($this->limit <= 0) {
            return '';
        }

        $sql = "LIMIT {$this->limit}";
        if ($this->offset > 0) {
            $sql .= " OFFSET {$this->offset}";
        }
        return $sql;
    }

    /**
     * 构建 HAVING 部分
     */
    private function buildHaving(): string
    {
        return !empty($this->havingConditions) ? 'HAVING ' . implode(' AND ', $this->havingConditions) : '';
    }

    /**
     * 清空当前构建器上的查询条件（同表新实例请用 newQuery()）
     */
    public function fresh(): self
    {
        $this->fields = [];
        $this->conditions = [
            'AND' => [],
            'OR' => []
        ];
        $this->havingConditions = [];
        $this->groupBy = [];
        $this->orderBy = [];
        $this->limit = 0;
        $this->offset = 0;
        $this->useGlobalConditions = true;
        $this->conditionsOptimized = false;
        $this->resetWithFrom();
        $this->resetPaginationOptions();

        return $this;
    }


} 