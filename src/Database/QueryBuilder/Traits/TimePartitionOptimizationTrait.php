<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Database\QueryBuilder\Traits;

/**
 * 时间分区自动优化 Trait
 *
 * 功能：当查询时间戳字段时，自动添加对应的日期字段条件
 * 目的：实现 ClickHouse 分区裁剪，提升查询性能 100-1000 倍
 *
 * 使用场景：
 * - 表按日期分区：PARTITION BY toYYYYMMDD(log_date)
 * - 查询时使用时间戳字段：WHERE log_time >= xxx
 * - 自动添加日期条件：AND log_date >= 'YYYY-MM-DD'
 *
 * @package TxAdmin\ClickHouseQuery\Database\QueryBuilder\Traits
 * @author angryshan
 */
trait TimePartitionOptimizationTrait
{
    /**
     * 应用时间分区优化（在 buildWhere 时调用）
     * 遍历所有条件，为时间字段添加对应的日期字段条件
     */
    protected function applyTimePartitionOptimization(): void
    {
        // 检查是否启用时间分区优化
        if (!$this->isTimePartitionOptimizationEnabled()) {
            return;
        }

        // 获取时间字段映射配置
        $mapping = $this->getTimeFieldMapping();
        if (empty($mapping)) {
            return;
        }

        // 遍历所有 AND 条件（字符串格式）
        foreach ($this->conditions['AND'] as $condition) {
            // 兼容字符串格式（当前所有 where 方法都是字符串）
            $conditionStr = is_array($condition) && isset($condition['sql']) ? $condition['sql'] : $condition;

            if (!is_string($conditionStr)) {
                continue;
            }

            // 从字符串中解析条件信息
            $parsed = $this->parseConditionString($conditionStr);
            if ($parsed === null) {
                continue;
            }

            $field = $parsed['field'];
            $operator = $parsed['operator'];
            $value = $parsed['value'];

            // 检查当前字段是否是需要优化的时间字段
            if (!isset($mapping[$field])) {
                continue;
            }

            $dateField = $mapping[$field];

            // 处理 BETWEEN 操作符
            if ($operator === 'BETWEEN' && is_array($value) && count($value) === 2) {
                // 转换开始和结束时间戳为日期
                $startDate = $this->convertTimestampToDate($value[0]);
                $endDate = $this->convertTimestampToDate($value[1]);

                if ($startDate === null || $endDate === null) {
                    continue;
                }

                // 添加 BETWEEN 日期条件
                $dateCondition = "`{$dateField}` BETWEEN '{$startDate}' AND '{$endDate}'";
                $this->conditions['AND'][] = $dateCondition;
                continue;
            }

            // 转换时间戳为日期（普通操作符）
            $dateValue = $this->convertTimestampToDate($value);
            if ($dateValue === null) {
                continue;
            }

            // 根据操作符添加对应的日期条件
            $dateOperator = $this->mapOperatorForDate($operator);
            if ($dateOperator === null) {
                continue;
            }

            // 添加日期条件（统一使用字符串格式，与其他 where 方法一致）
            $dateCondition = "`{$dateField}` {$dateOperator} '{$dateValue}'";
            $this->conditions['AND'][] = $dateCondition;
        }
    }

    /**
     * 从条件字符串中解析字段、操作符和值
     *
     * 支持两种字段格式：
     * - 普通字段：`log_time` >= 1234567890
     * - 带别名前缀：`t`.`log_time` >= 1234567890
     *
     * @param string $conditionStr 条件字符串
     * @return null|array 返回 ['field' => ..., 'operator' => ..., 'value' => ...] 或 null
     */
    protected function parseConditionString(string $conditionStr): ?array
    {
        // 字段名模式：匹配 `field` 或 `alias`.`field`
        // `field` → 捕获 field
        // `alias`.`field` → 捕获 field（丢弃 alias 部分）
        $fieldPattern = '(?:`([^`]+)`\.)?`([^`]+)`';

        // 优先匹配 BETWEEN 操作符（支持数字和带引号的数字）
        $betweenPattern = '/^\(?\s*' . $fieldPattern . '\s+BETWEEN\s+\'?(\d+)\'?\s+AND\s+\'?(\d+)\'?\s*\)?$/i';
        if (preg_match($betweenPattern, trim($conditionStr), $matches)) {
            // $matches[1] = alias（可选）, $matches[2] = field, $matches[3] = start, $matches[4] = end
            return [
                'field' => $matches[2],
                'operator' => 'BETWEEN',
                'value' => [$matches[3], $matches[4]],
            ];
        }

        // 匹配普通操作符: =, >, <, >=, <=, !=, <>
        // 支持括号包围的条件: (condition)
        $pattern = '/^\(?\s*' . $fieldPattern . '\s*(>=|<=|<>|!=|=|>|<)\s*(.+?)\s*\)?$/';

        if (!preg_match($pattern, trim($conditionStr), $matches)) {
            return null;
        }

        // $matches[1] = alias（可选）, $matches[2] = field, $matches[3] = operator, $matches[4] = value
        $field = $matches[2];
        $operator = $matches[3];
        $valueStr = trim($matches[4]);

        // 解析值（去除引号和尾部括号）
        $valueStr = rtrim($valueStr, ')');

        if (preg_match("/^'(.+)'$/", $valueStr, $valueMatches)) {
            // 字符串值
            $value = $valueMatches[1];
        } elseif (is_numeric($valueStr)) {
            // 数值
            $value = $valueStr;
        } else {
            // 其他格式暂不支持
            return null;
        }

        return [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];
    }

    /**
     * 获取时间分区优化配置
     *
     * @param string $key 配置键（支持点号分隔的多级键）
     * @param mixed $default 默认值
     * @return mixed
     */
    protected function getOptimizationConfig(string $key, mixed $default = null): mixed
    {
        return $this->connection->getTimePartitionOptimization()[$key] ?? $default;
    }

    /**
     * 检查是否启用时间分区优化
     *
     * @return bool
     */
    protected function isTimePartitionOptimizationEnabled(): bool
    {
        return (bool) $this->getOptimizationConfig('enabled', false);
    }

    /**
     * 获取时间字段映射配置（支持多表）
     *
     * @return array
     */
    protected function getTimeFieldMapping(): array
    {
        $fieldMapping = $this->getOptimizationConfig('field_mapping', []);

        if (empty($fieldMapping)) {
            return [];
        }

        // 新版配置格式：支持 default 和 tables
        $defaultMapping = $fieldMapping['default'] ?? [];
        $tablesMapping = $fieldMapping['tables'] ?? [];

        // 如果有特定表的配置，使用特定表配置（覆盖通用配置）
        if (isset($tablesMapping[$this->table])) {
            $tableMapping = $tablesMapping[$this->table];
            // ✅ 排除非映射字段（如 timestamp_type）
            unset($tableMapping['timestamp_type']);
            return array_merge($defaultMapping, $tableMapping);
        }

        // 否则使用通用配置
        return $defaultMapping;
    }

    /**
     * 获取时间戳类型配置（支持按表配置）
     *
     * @return string
     */
    protected function getTimestampType(): string
    {
        // 1. 尝试获取特定表的配置
        $tableTimestampType = $this->getOptimizationConfig("field_mapping.tables.{$this->table}.timestamp_type", null);
        if ($tableTimestampType !== null) {
            return $tableTimestampType;
        }

        // 2. 获取全局配置
        return $this->getOptimizationConfig('timestamp_type', 'seconds');
    }

    /**
     * 检查是否已存在某字段的条件
     *
     * @param string $field 字段名
     * @return bool
     */
    protected function hasConditionForField(string $field): bool
    {
        if (!isset($this->conditions['AND'])) {
            return false;
        }

        foreach ($this->conditions['AND'] as $condition) {
            // 统一处理为字符串格式
            $conditionStr = is_array($condition) && isset($condition['sql']) ? $condition['sql'] : $condition;

            if (!is_string($conditionStr)) {
                continue;
            }

            // 使用更精确的正则匹配，避免子串误判
            // 匹配: `field` 或 `alias`.`field` 后面跟着操作符
            if (preg_match('/(?:`[^`]+`\\.)?`' . preg_quote($field, '/') . '`\\s*(>=|<=|<>|!=|=|>|<|IN|BETWEEN|IS|LIKE)/i', $conditionStr)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 将时间戳转换为日期字符串
     *
     * @param mixed $timestamp 时间戳（秒或毫秒）
     * @return null|string 日期字符串 (YYYY-MM-DD) 或 null
     */
    protected function convertTimestampToDate(mixed $timestamp): ?string
    {
        if (!is_numeric($timestamp)) {
            return null;
        }

        // 转换为整数
        $timestamp = (int) $timestamp;

        // 获取时间戳类型配置
        $timestampType = $this->getTimestampType();

        // 自动检测：如果是毫秒时间戳（13位，> 10000000000），转换为秒
        if ($timestampType === 'milliseconds' || $timestamp > 10000000000) {
            $timestamp = intdiv($timestamp, 1000);
        }

        // 验证时间戳范围（1970-01-01 到 2100-01-01）
        if ($timestamp < 0 || $timestamp > 4102444800) {
            return null;
        }

        return date('Y-m-d', $timestamp);
    }

    /**
     * 映射时间操作符到日期操作符
     *
     * 规则：
     * - log_time > 1234567890 → log_date >= '2024-06-05' (当天可能有数据)
     * - log_time >= 1234567890 → log_date >= '2024-06-05'
     * - log_time < 1234567890 → log_date <= '2024-06-05' (当天可能有数据)
     * - log_time <= 1234567890 → log_date <= '2024-06-05'
     * - log_time = 1234567890 → log_date = '2024-06-05'
     *
     * @param string $operator 原始操作符
     * @return null|string 日期操作符或 null
     */
    protected function mapOperatorForDate(string $operator): ?string
    {
        $operator = strtolower(trim($operator));

        $mapping = [
            '>' => '>=',   // log_time > 1234567890 → log_date >= '2024-06-05'
            '>=' => '>=',  // log_time >= 1234567890 → log_date >= '2024-06-05'
            '<' => '<=',   // log_time < 1234567890 → log_date <= '2024-06-05'
            '<=' => '<=',  // log_time <= 1234567890 → log_date <= '2024-06-05'
            '=' => '=',    // log_time = 1234567890 → log_date = '2024-06-05'
        ];

        return $mapping[$operator] ?? null;
    }
}
