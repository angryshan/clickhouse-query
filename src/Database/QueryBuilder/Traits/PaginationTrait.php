<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Database\QueryBuilder\Traits;

use TxAdmin\ClickHouseQuery\Exceptions\ClickHouseQueryException;

/**
 * 分页查询（组件层：仅负责 limit/offset/count/返回结构）
 *
 * @package TxAdmin\ClickHouseQuery\Database\QueryBuilder\Traits
 * @author angryshan
 */
trait PaginationTrait
{
    /**
     * 分页 COUNT 字段（空字符串时使用 count()）
     */
    private string $paginationCountField = '';

    /**
     * 指定分页 total 的 COUNT 字段（链式，对标 count('distinct user_id')）
     */
    public function countField(string $field): self
    {
        $this->paginationCountField = $field;

        return $this;
    }

    /**
     * 分页查询（推荐，对标 Hyperf Builder::paginate）
     *
     * @param int $perPage 每页条数
     * @param bool $wrapCountWithClause 计数是否用 WITH 子查询包装（由调用方决定，组件不自动判断）
     * @param null|int $page 页码（从 1 开始）；null 时从当前 HTTP 请求读取（Adapter::getCurrentPage）
     * @param string $pageName 请求参数名，默认 page
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array{total:int,per_page:int,current_page:int,last_page:int,data:array}
     * @throws ClickHouseQueryException
     */
    public function paginate(
        int $perPage,
        bool $wrapCountWithClause,
        ?int $page = null,
        string $pageName = 'page',
        bool $useConnectionControl = false
    ): array {
        $page = max(1, $page ?? $this->connection->getAdapter()->getCurrentPage($pageName));
        $perPage = max(1, $perPage);

        $total = $this->countForPagination(
            $wrapCountWithClause,
            $useConnectionControl
        );

        $data = $this->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get($useConnectionControl);

        return $this->buildPaginationResult($total, $perPage, $page, $data);
    }

    /**
     * 获取分页数据（兼容旧调用）
     *
     * @deprecated 请使用 paginate($perPage, $wrapCountWithClause, $page)；后续版本将移除
     * @param array $params 请求参数数组
     * @param bool $useWithClause 默认 true（兼容历史行为）；true=WITH 包装计数，false=直接 count（CTE 场景须传 true）
     * @param string $countField 计数字段（非空时临时作用于本次分页，不污染构建器状态）
     * @param bool $useConnectionControl 是否使用连接控制
     * @return array 分页结果或导出时的裸数据列表
     * @throws ClickHouseQueryException
     */
    public function getPageData(
        array $params = [],
        bool $useWithClause = true,
        string $countField = '',
        bool $useConnectionControl = false
    ): array {
        if (empty($params)) {
            $params = $this->connection->getAdapter()->getRequestParams();
        }

        if (!empty($params['is_export'])) {
            return $this->get($useConnectionControl);
        }

        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, (int) ($params['pageSize'] ?? 10));

        $query = clone $this;
        if ($countField !== '') {
            $query->countField($countField);
        }

        return $query->paginate(
            $perPage,
            $useWithClause,
            $page,
            'page',
            $useConnectionControl
        );
    }

    /**
     * 重置分页链式选项（fresh() 时调用）
     */
    protected function resetPaginationOptions(): void
    {
        $this->paginationCountField = '';
    }

    /**
     * 克隆查询用于计数（排除不影响总数的子句）
     */
    private function cloneForCount(): self
    {
        $query = clone $this;
        $query->orderBy = [];
        $query->limit = 0;
        $query->offset = 0;

        return $query;
    }

    /**
     * 执行分页总数统计
     */
    private function countForPagination(
        bool $wrapCountWithClause,
        bool $useConnectionControl
    ): int {
        $countField = $this->paginationCountField;

        if ($wrapCountWithClause) {
            $cteAlias = $this->paginationCountCteAlias();
            $countQuery = $this->newQuery()->with($cteAlias, function (&$query) {
                $query = $this->cloneForCount();
            });

            return $countQuery->from($cteAlias)->count($countField, $useConnectionControl);
        }

        if ($this->needsAggregateSubqueryForPaginationCount()) {
            $subSql = $this->cloneForCount()->buildQuery();
            $alias = $this->paginationAggregateSubqueryAlias();

            return $this->newQuery()
                ->from("({$subSql}) AS {$alias}")
                ->count($countField, $useConnectionControl);
        }

        return $this->cloneForCount()->count($countField, $useConnectionControl);
    }

    /**
     * GROUP BY / HAVING 场景需对分组结果包子查询再 count（对标 Hyperf runPaginationCountQuery / TP count+group）
     */
    private function needsAggregateSubqueryForPaginationCount(): bool
    {
        return !empty($this->groupBy) || !empty($this->havingConditions);
    }

    /**
     * 分页分组计数子查询别名（组件内部保留，应用层勿用 _ckqb_ 前缀）
     */
    private function paginationAggregateSubqueryAlias(): string
    {
        return '_ckqb_pagination_aggregate_';
    }

    /**
     * 分页计数 CTE 别名（组件内部保留，应用层勿用 _ckqb_ 前缀）
     */
    private function paginationCountCteAlias(): string
    {
        return '_ckqb_pagination_count_';
    }

    /**
     * 构建标准分页返回结构
     */
    private function buildPaginationResult(int $total, int $pageSize, int $page, array $data): array
    {
        return [
            'total' => $total,
            'per_page' => $pageSize,
            'current_page' => $page,
            'last_page' => $total > 0 ? (int) ceil($total / $pageSize) : 1,
            'data' => $data,
        ];
    }
}
