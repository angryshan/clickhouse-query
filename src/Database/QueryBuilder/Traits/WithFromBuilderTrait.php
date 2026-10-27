<?php

declare(strict_types=1);

namespace ClickHouseQuery\Database\QueryBuilder\Traits;

/**
 * WITH FROM 构建器
 * @package ClickHouseQuery\Database\QueryBuilder\Traits
 * @author angryshan
 */
trait WithFromBuilderTrait
{
    private string $from = '';
    private array $with = [];
    private string $alias = '';

    /**
     * 设置 FROM 子句
     */
    public function from(string $table): self
    {
        $this->from = $table;
        return $this;
    }

    /**
     * 设置表别名
     *
     * 别名会附加在 FROM 子句的表名（或 CTE 名称）之后：FROM table AS alias
     *
     * @param string $alias 表别名
     * @return self
     */
    public function alias(string $alias): self
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * 添加 WITH 子句
     */
    public function with(string $alias, callable $callback): self
    {
        // 创建新的查询构建器实例（共用 connection）
        $subQuery = new static($this->table, $this->config, $this->connection);
        
        // 执行回调，构建子查询
        $callback($subQuery);
        
        // 添加到 WITH 数组
        $this->with[$alias] = $subQuery->buildQuery();
        
        return $this;
    }

    /**
     * 构建 WITH 部分
     */
    protected function buildWith(): string
    {
        if (empty($this->with)) {
            return '';
        }

        $withClauses = [];
        foreach ($this->with as $alias => $query) {
            $withClauses[] = "{$alias} AS ({$query})";
        }

        return 'WITH ' . implode(', ', $withClauses);
    }

    /**
     * 构建 FROM 部分
     */
    protected function buildFromClause(string $defaultTable = ''): string
    {
        $from = $this->from ?: $defaultTable;
        $sql = 'FROM ' . $from;
        if (!empty($this->alias)) {
            $sql .= ' AS ' . $this->alias;
        }
        return $sql;
    }

     /**
     * 构建FROM部分
     */
    protected function buildFrom(): string
    {
        return $this->buildFromClause($this->table);
    }

    /**
     * 获取自定义 FROM（CTE 别名或覆盖物理表）
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * 获取当前表别名
     */
    public function getAlias(): string
    {
        return $this->alias;
    }

    /**
     * 重置状态
     */
    protected function resetWithFrom(): void
    {
        $this->from = '';
        $this->with = [];
        $this->alias = '';
    }
} 