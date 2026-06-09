<?php

declare(strict_types=1);

namespace ClickHouseQuery\Database\QueryBuilder\Traits;

/**
 * WHERE 条件去重优化
 *
 * 解决两类冗余问题：
 * 1. 完全重复的条件：`log_date` >= '2024-06-01' AND `log_date` >= '2024-06-01'
 * 2. 同字段范围冗余：`log_date` >= '2026-05-09' AND `log_date` >= '2026-05-10' → 只保留 >= '2026-05-10'
 *
 * 依赖：
 * - $conditions        由 WhereBuilderTrait 提供
 * - parseConditionString() 由 TimePartitionOptimizationTrait 提供
 *
 * @property array $conditions
 * @method array|null parseConditionString(string $conditionStr)
 */
trait WhereDeduplicationTrait
{
    /**
     * 对 AND / OR 条件组分别执行去重
     */
    protected function deduplicateConditions(): void
    {
        if (!empty($this->conditions['AND'])) {
            $this->conditions['AND'] = $this->optimizeConditionGroup($this->conditions['AND']);
        }

        if (!empty($this->conditions['OR'])) {
            $this->conditions['OR'] = $this->optimizeConditionGroup($this->conditions['OR']);
        }
    }

    /**
     * 对一组条件执行去重和范围合并
     */
    private function optimizeConditionGroup(array $conditions): array
    {
        // 第一步：去除完全相同的条件字符串
        $conditions = array_values(array_unique($conditions));

        if (count($conditions) <= 1) {
            return $conditions;
        }

        // 第二步：按字段分组，合并范围条件
        $rangeMap = [];   // 可解析的范围条件，按字段归组
        $others   = [];   // 无法解析或不参与合并的条件（原样保留）

        foreach ($conditions as $condition) {
            $parsed = $this->parseConditionString($condition);

            if ($parsed === null || !in_array($parsed['operator'], ['>=', '>', '<=', '<', '='])) {
                $others[] = $condition;
                continue;
            }

            $field = $parsed['field'];
            $op    = $parsed['operator'];
            $value = $parsed['value'];

            if (!isset($rangeMap[$field])) {
                $rangeMap[$field] = [];
            }

            switch ($op) {
                case '=':
                    // 等值条件直接覆盖，同字段多个等值只保留最后一个
                    $rangeMap[$field]['eq'] = $value;
                    break;

                case '>=':
                case '>':
                    // 下界取最大值（越大限制越严）
                    if (!isset($rangeMap[$field]['lower']) ||
                        $this->compareConditionValues($value, $rangeMap[$field]['lower']['value']) > 0) {
                        $rangeMap[$field]['lower'] = ['op' => $op, 'value' => $value];
                    }
                    break;

                case '<=':
                case '<':
                    // 上界取最小值（越小限制越严）
                    if (!isset($rangeMap[$field]['upper']) ||
                        $this->compareConditionValues($value, $rangeMap[$field]['upper']['value']) < 0) {
                        $rangeMap[$field]['upper'] = ['op' => $op, 'value' => $value];
                    }
                    break;
            }
        }

        // 第三步：将合并后的范围条件重新拼回字符串
        $result = $others;

        foreach ($rangeMap as $field => $range) {
            if (isset($range['eq'])) {
                // 有等值条件时，上下界条件都是冗余的
                $result[] = "`{$field}` = " . $this->quoteDedupeValue($range['eq']);
            } else {
                if (isset($range['lower'])) {
                    $result[] = "`{$field}` {$range['lower']['op']} " . $this->quoteDedupeValue($range['lower']['value']);
                }
                if (isset($range['upper'])) {
                    $result[] = "`{$field}` {$range['upper']['op']} " . $this->quoteDedupeValue($range['upper']['value']);
                }
            }
        }

        return $result;
    }

    /**
     * 比较两个条件值的大小
     * 日期字符串（YYYY-MM-DD）和数字都能正确比较
     */
    private function compareConditionValues($a, $b): int
    {
        if (is_numeric($a) && is_numeric($b)) {
            return $a <=> $b;
        }
        return strcmp((string)$a, (string)$b);
    }

    /**
     * 给值加引号（数字不加，字符串加单引号）
     */
    private function quoteDedupeValue($value): string
    {
        if (is_numeric($value)) {
            return (string)$value;
        }
        return "'{$value}'";
    }
}
