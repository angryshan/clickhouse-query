<?php

declare(strict_types=1);

namespace ClickHouseQuery\Database\QueryBuilder;

use ClickHouseQuery\Database\Connection\ClickHouseConnection;
use ClickHouseQuery\Database\QueryBuilder\Traits\WhereBuilderTrait;
use ClickHouseQuery\Database\QueryBuilder\Traits\WithFromBuilderTrait;
use Exception;

/**
 * 构建ClickHouse查询
 * @package ClickHouseQuery\Database\QueryBuilder
 * @author angryshan
 */
class ClickHouseQueryBuilder
{
    use WhereBuilderTrait;
    use WithFromBuilderTrait;

    private array $fields = [];
    private array $groupBy = [];
    private array $orderBy = [];
    private int $limit = 0;
    private int $offset = 0;
    private array $havingConditions = [];

    private string $table;
    private ClickHouseConnection $connection;

    /**
     * 是否使用WHERE全局条件 默认为true
     */
    private bool $useGlobalConditions = true;

    /**
     * 初始化查询构建器
     *
     * @param string $table 表名
     */
    public function __construct(string $table, array $config = [])
    {
        $this->table = $table;
        $this->connection = new ClickHouseConnection($config);
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
                // 等值查询: ['field' => value]
                $this->havingConditions[] = "`{$field}` = " . $this->quoteValue($value);
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
                $this->orderBy[] = "`{$field}` " . strtoupper($dir);
            }
        } else {
            // 处理字符串格式：'field', 'ASC'
            $this->orderBy[] = "`{$column}` " . strtoupper($direction);
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
     * 执行查询并获取结果
     *
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 查询结果
     * @throws Exception
     */
    public function get(bool $useConnectionControl = false): array
    {
        $sql = $this->buildQuery();
        $result = $this->connection->execute($sql, $useConnectionControl);
        $this->reset();
        return $result;
    }

    /**
     * 获取查询结果的第一条记录
     *
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 第一条记录或空数组
     * @throws Exception
     */
    public function first(bool $useConnectionControl = false): array
    {
        $sql = $this->buildQuery();
        $result = $this->connection->execute($sql, $useConnectionControl);
        $this->reset();

        // 如果结果为空，返回空数组
        if (empty($result)) {
            return [];
        }

        // 返回第一条记录
        return $result[0] ?? [];
    }

    /**
     * 执行 COUNT 查询
     *
     * @param string $column 要统计的字段，默认为 空
     * @param bool $useConnectionControl 是否使用连接控制
     * @return int 统计结果
     * @throws Exception
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
        return isset($result[0]['count']) ? (int)array_sum(array_column($result, 'count')) : 0;
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
     * @throws Exception
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
     * 获取分页数据
     *
     * @param array $params 请求参数数组
     * @param bool $useWithClause 是否使用WITH子句优化计数查询
     * @param string $countField 计数字段
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 分页结果
     * @throws Exception
     */
    public function getPageData(
        array $params = [],
        bool $useWithClause = true,
        string $countField = '',
        bool $useConnectionControl = false
    ): array {
        // 如果没有提供参数，尝试从请求中获取
        if (empty($params)) {
            $params = $this->getRequestParams();
        }

        // 解析分页参数
        $isExport = (bool)($params['is_export'] ?? false);
        $page = max(1, (int)($params['page'] ?? 1));
        $pageSize = max(1, (int)($params['pageSize'] ?? 10));

        // 导出模式 - 返回所有数据
        if ($isExport) {
            return $this->get($useConnectionControl);
        }

        // 计算总记录数
        if ($useWithClause) {
            // 使用 WITH 子句优化计数查询
            $countQuery = new self($this->table);
            $countQuery = $countQuery->with('allData', function (&$query) {
                $query = clone $this;
                $query->orderBy = []; // 计数查询不需要排序
            });
            $total = $countQuery->from('allData')->count($countField);
        } else {
            // 标准计数查询
            $countQuery = clone $this;
            $total = $countQuery->count($countField);
        }

        // 获取当前页数据
        $data = $this->limit($pageSize)
            ->offset(($page - 1) * $pageSize)
            ->get($useConnectionControl);

        // 计算最后一页
        $lastPage = $total > 0 ? ceil($total / $pageSize) : 1;

        // 返回标准化的分页结果
        return [
            'total' => $total,
            'per_page' => $pageSize,
            'current_page' => $page,
            'last_page' => $lastPage,
            'data' => $data,
        ];
    }

    /**
     * 获取请求参数
     *
     * @return array 请求参数
     */
    private function getRequestParams(): array
    {
        return $this->connection->getAdapter()->getRequestParams();
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
        $conditions = [];
        if ($this->useGlobalConditions && empty($this->with)) {
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
     * 重置查询构建器状态
     */
    private function reset(): void
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
        $this->resetWithFrom();
    }


} 