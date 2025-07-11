<?php

declare(strict_types=1);

namespace ClickHouseQuery\Database\QueryBuilder\Traits;

use ClickHouseQuery\Exceptions\ClickHouseQueryException;
/**
 * 条件查询
 * @package ClickHouseQuery\Database\QueryBuilder\Traits
 * @author angryshan
 */
trait WhereBuilderTrait
{
    private array $conditions = [
        'AND' => [],
        'OR' => []
    ];

    /**
     * 获取支持的操作符
     */
    private function getSupportedOperators(): array
    {
        return [
            '=', '>', '<', '>=', '<=', '!=', '<>',
            'in', 'not in', 'between', 'like'
        ];
    }

    /**
     * 设置查询条件
     */
    public function where(array $conditions): self
    {
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                // 数组格式: ['field', 'operator', 'value']
                $this->conditions['AND'][] = $this->parseArrayCondition($value);
            } else {
                // 等值查询: ['field' => value]
                $this->conditions['AND'][] = "`{$field}` = " . $this->quoteValue($value);
            }
        }
        return $this;
    }

    /**
     * OR 条件查询
     */
    public function orWhere(array $conditions): self
    {
        foreach ($conditions as $field => $value) {
            if (is_array($value)) {
                $this->conditions['OR'][] = $this->parseArrayCondition($value);
            } else {
                // 等值查询: ['field' => value]
                $this->conditions['OR'][] = "`{$field}` = " . $this->quoteValue($value);
            }
        }
        return $this;
    }

    /**
     * NULL 条件
     */
    public function whereNull(string $column): self
    {
        $this->conditions['AND'][] = "`{$column}` IS NULL";
        return $this;
    }

    /**
     * NOT NULL 条件
     */
    public function whereNotNull(string $column): self
    {
        $this->conditions['AND'][] = "`{$column}` IS NOT NULL";
        return $this;
    }
    
    /**
     * IN 条件
     */
    public function whereIn(string $column, array $values): self
    {
        $this->conditions['AND'][] = sprintf("`%s` IN (%s)", $column, $this->formatInValues($values));
        return $this;
    }

    /**
     * NOT IN 条件
     */
    public function whereNotIn(string $column, array $values): self
    {
        $this->conditions['AND'][] = sprintf("`%s` NOT IN (%s)", $column, $this->formatInValues($values));
        return $this;
    }

    /**
     * BETWEEN 条件
     */
    public function whereBetween(string $column, $start, $end): self
    {
        $this->conditions['AND'][] = sprintf(
            "`%s` BETWEEN %s AND %s",
            $column,
            $this->formatValue($start),
            $this->formatValue($end)
        );
        return $this;
    }

    /**
     * 条件组
     */
    public function whereGroup(callable $callback, string $type = 'AND'): self
    {
        // 创建一个新的条件数组
        $builder = new class() {
            use WhereBuilderTrait;
        };
        $callback($builder);
        $groupConditions = $builder->getConditions();
        
        if (!empty($groupConditions)) {
            $this->conditions[$type][] = '(' . implode(' OR ', $groupConditions) . ')';
        }
        
        return $this;
    }

    /**
     * 获取构建好的条件数组
     */
    public function getConditions(): array
    {
        // 过滤掉空值条件
        $andConditions = array_filter($this->conditions['AND']);
        $orConditions = array_filter($this->conditions['OR']);
        
        // 如果只有 AND 条件
        if (!empty($andConditions) && empty($orConditions)) {
            // AND 条件直接用 AND 连接,不需要括号
            return [implode(' AND ', $andConditions)];
        }
        
        // 如果只有 OR 条件
        if (empty($andConditions) && !empty($orConditions)) {
            if (count($orConditions) > 1) {
                return ['(' . implode(' OR ', $orConditions) . ')'];
            }
            return [reset($orConditions)];
        }
        
        // 如果同时有 AND 和 OR 条件
        if (!empty($andConditions) && !empty($orConditions)) {
            $andPart = count($andConditions) > 1 
                ? '(' . implode(' AND ', $andConditions) . ')'
                : reset($andConditions);
            
            $orPart = count($orConditions) > 1
                ? '(' . implode(' OR ', $orConditions) . ')'
                : reset($orConditions);
            
            return ['(' . $andPart . ' OR ' . $orPart . ')'];
        }
        
        return [];
    }

    /**
     * 解析数组条件
     */
    public function parseArrayCondition(array $condition): string
    {
        // 验证数组格式
        if (count($condition) !== 3) {
            throw new ClickHouseQueryException(
                '条件数组格式错误,应为: [field, operator, value]'
            );
        }

        [$field, $operator, $value] = $condition;

        // 验证字段名
        if (!is_string($field) || empty($field)) {
            throw new ClickHouseQueryException('字段名必须是非空字符串');
        }

        // 验证操作符
        if (!is_string($operator) || empty($operator)) {
            throw new ClickHouseQueryException('操作符必须是非空字符串');
        }

        // 转换操作符为小写以统一比较
        $operator = strtolower($operator);
        
        // 验证操作符是否支持
        if (!in_array($operator, $this->getSupportedOperators())) {
            throw new ClickHouseQueryException(
                sprintf('不支持的操作符: %s', $operator)
            );
        }

        // 处理 IN/NOT IN 条件
        if (in_array($operator, ['in', 'not in'])) {
            if (!is_array($value)) {
                throw new ClickHouseQueryException('IN/NOT IN 操作符的值必须是数组');
            }
            if (empty($value)) {
                throw new ClickHouseQueryException('IN/NOT IN 操作符的值数组不能为空');
            }
            return sprintf("`%s` %s (%s)", $field, strtoupper($operator), $this->formatInValues($value));
        }

        // 处理 BETWEEN 条件
        if ($operator === 'between') {
            if (!is_array($value) || count($value) !== 2) {
                throw new ClickHouseQueryException('BETWEEN 操作符需要包含两个值的数组');
            }
            if ($value[0] === null || $value[1] === null) {
                throw new ClickHouseQueryException('BETWEEN 操作符的值不能为 NULL');
            }
            return sprintf(
                "`%s` BETWEEN %s AND %s",
                $field,
                $this->formatValue($value[0]),
                $this->formatValue($value[1])
            );
        }

        // 验证普通条件的值
        if ($value === null) {
            throw new ClickHouseQueryException('条件值不能为 NULL,请使用 whereNull/whereNotNull');
        }

        return "`{$field}` {$operator} " . $this->quoteValue($value);
    }

    /**
     * 格式化 IN 条件的值
     */
    public function formatInValues(array $values): string 
    {
        if (empty($values)) {
            throw new ClickHouseQueryException('IN 条件的值不能为空数组');
        }
        
        return implode(',', array_map(function ($value) {
            if ($value === null) {
                throw new ClickHouseQueryException('IN 条件的值不能为 NULL');
            }
            return is_numeric($value) ? $value : "'" . str_replace("'", "\'", $value) . "'";
        }, $values));
    }

    /**
     * 格式化值
     */
    public function formatValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return '(' . implode(',', array_map([$this, 'formatValue'], $value)) . ')';
        }

        if (is_string($value)) {
            return "'" . str_replace("'", "\'", $value) . "'";
        }

        return (string)$value;
    }

    /**
     * 为值添加引号并转义
     * 
     * @param mixed $value 需要处理的值
     * @return mixed 处理后的值
     * 
     * @example
     *   quoteValue('abc')      // 返回: 'abc'
     *   quoteValue("a'bc")     // 返回: 'a\'bc'
     *   quoteValue(123)        // 返回: 123
     */
    public function quoteValue($value)
    {
        if (is_string($value)) {
            return "'" . str_replace("'", "\'", $value) . "'";
        }
        return $value;
    }

    /**
     * 添加原始 WHERE 条件
     *
     * @param string $condition 原始 SQL 条件语句
     * @param array $values 绑定参数数组
     * @return self
     */
    public function whereRaw(string $condition, array $values = []): self
    {
        // 处理绑定参数
        if (!empty($values)) {
            foreach ($values as $value) {
                $condition = preg_replace('/\?/', $this->formatValue($value), $condition, 1);
            }
        }

        $this->conditions['AND'][] = "({$condition})";
        return $this;
    }

    /**
     * 当条件满足时，才会执行回调函数中的查询条件
     * @param mixed         $value
     * @param callable      $callback
     * @param callable|null $default
     * @return $this|mixed
     */
    public function when($value, callable $callback, callable $default = null)
    {
        if ($value) {
            return $callback($this, $value) ?: $this;
        }
        if ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }
} 