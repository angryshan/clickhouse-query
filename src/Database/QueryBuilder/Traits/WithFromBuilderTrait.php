<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Database\QueryBuilder\Traits;

/**
 * WITH FROM 构建器
 * @package TxAdmin\ClickHouseQuery\Database\QueryBuilder\Traits
 * @author angryshan
 */
trait WithFromBuilderTrait
{
    private string $from = '';
    private array $with = [];

    /**
     * 设置 FROM 子句
     */
    public function from(string $table): self
    {
        $this->from = $table;
        return $this;
    }

    /**
     * 添加 WITH 子句
     */
    public function with(string $alias, callable $callback): self
    {
        // 创建新的查询构建器实例
        $subQuery = new static($this->table);
        
        // 执行回调，构建子查询
        $callback($subQuery);
        
        // 添加到 WITH 数组
        $this->with[$alias] = $subQuery->toSql();
        
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
        return 'FROM ' . $from;
    }

     /**
     * 构建FROM部分
     */
    protected function buildFrom(): string
    {
        return $this->buildFromClause($this->table);
    }

    /**
     * 重置状态
     */
    protected function resetWithFrom(): void
    {
        $this->from = '';
        $this->with = [];
    }
} 