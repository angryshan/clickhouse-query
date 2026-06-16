<?php

declare(strict_types=1);

namespace ClickHouseQuery\Services;

use ClickHouseQuery\Database\QueryBuilder\ClickHouseQueryBuilder;
use ClickHouseQuery\Exceptions\ClickHouseQueryException;

/**
 * 查询服务抽象基类
 * 提供基础查询功能，业务逻辑应在子类中实现
 *
 * @package ClickHouseQuery\Services
 * @author angryshan
 */
abstract class AbstractService
{
    /**
     * 当前操作的表名
     */
    protected string $table;

    /**
     * 配置参数
     */
    protected array $config = [];

    /**
     * 每页最大条数，0 表示不限制
     */
    protected int $maxPageSize = 0;

    /**
     * 最大分页偏移量，0 表示不限制
     */
    protected int $maxPaginationOffset = 0;

    /**
     * 创建查询构建器
     *
     * @param array $extraConfig 额外的配置参数
     * @return ClickHouseQueryBuilder
     * @throws ClickHouseQueryException 如果表名未定义
     */
    protected function query(array $extraConfig = []): ClickHouseQueryBuilder
    {
        if (empty($this->table)) {
            throw new ClickHouseQueryException('表名未定义，请在子类中设置 $table 属性');
        }

        $config = array_merge($this->config, $extraConfig);
        return new ClickHouseQueryBuilder($this->table, $config);
    }

    /**
     * 应用层分页入口（推荐）
     *
     * @param ClickHouseQueryBuilder $query 已组装好条件的查询构建器
     * @param array $params 分页参数，空数组时从当前 HTTP 请求读取
     * @param bool|null $useWithClause 计数 WITH 策略，见 resolveWrapCountWithClause()
     * @param string $countField 计数字段
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 列表为分页结构 {total, per_page, current_page, last_page, data}；is_export 时为裸二维数组（同 getPageData）
     * @throws ClickHouseQueryException
     */
    protected function paginate(
        ClickHouseQueryBuilder $query,
        array $params = [],
        ?bool $useWithClause = null,
        string $countField = '',
        bool $useConnectionControl = false
    ): array {
        $params = $this->normalizePaginationParams(
            $this->resolvePaginationParams($query, $params)
        );

        if ($params['is_export']) {
            return $query->get($useConnectionControl);
        }

        $this->assertPaginationOffset((int)$params['page'], (int)$params['pageSize']);

        return $query->paginate(
            (int)$params['page'],
            (int)$params['pageSize'],
            $this->resolveWrapCountWithClause($query, $useWithClause),
            $countField,
            $useConnectionControl
        );
    }

    /**
     * 从请求或入参解析分页参数
     */
    protected function resolvePaginationParams(ClickHouseQueryBuilder $query, array $params = []): array
    {
        if (empty($params)) {
            $params = $query->getConnection()->getAdapter()->getRequestParams();
        }

        return [
            'is_export' => (bool)($params['is_export'] ?? false),
            'page' => max(1, (int)($params['page'] ?? 1)),
            'pageSize' => max(1, (int)($params['pageSize'] ?? 10)),
        ];
    }

    /**
     * 解析计数是否用 WITH 子查询包装（服务层策略，三态语义）
     *
     * | 传入值 | 行为 |
     * |--------|------|
     * | null（paginate 默认） | 自动：仅当查询设置了自定义 from（CTE 别名）时包装，否则直接 count |
     * | true | 强制 WITH 包装（等同历史 getPageData([], true)） |
     * | false | 强制直接 count，不包装（即使存在 from；CTE 场景勿用） |
     *
     * 与 getPageData($params, false) 的区别：旧兼容入口在 false 时仍会对有 from 的查询自动包装。
     */
    protected function resolveWrapCountWithClause(ClickHouseQueryBuilder $query, ?bool $useWithClause): bool
    {
        if ($useWithClause === true) {
            return true;
        }

        if ($useWithClause === false) {
            return false;
        }

        return $query->getFrom() !== '';
    }

    /**
     * 规范化分页参数（子类可覆盖以扩展业务规则）
     */
    protected function normalizePaginationParams(array $params): array
    {
        $maxPageSize = $this->getMaxPageSize();
        if ($maxPageSize > 0) {
            $params['pageSize'] = min((int)$params['pageSize'], $maxPageSize);
        }

        return $params;
    }

    /**
     * 分页偏移量校验，子类可覆盖自定义提示或规则
     *
     * @throws ClickHouseQueryException
     */
    protected function assertPaginationOffset(int $page, int $pageSize): void
    {
        $maxOffset = $this->getMaxPaginationOffset();
        if ($maxOffset <= 0) {
            return;
        }

        $offset = ($page - 1) * $pageSize;
        if ($offset > $maxOffset) {
            throw new ClickHouseQueryException(
                sprintf('查询偏移量过大（超过 %d 条记录），请缩小查询范围或使用更精确的筛选条件', $maxOffset)
            );
        }
    }

    protected function getMaxPageSize(): int
    {
        return $this->maxPageSize;
    }

    protected function getMaxPaginationOffset(): int
    {
        return $this->maxPaginationOffset;
    }

    /**
     * 设置配置参数
     *
     * @param array $config 配置参数
     * @return self
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
}
