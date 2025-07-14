<?php
namespace ClickHouseQuery\Test\Services;

use ClickHouseQuery\Database\QueryBuilder\ClickHouseQueryBuilder;

abstract class AbstractService
{
    protected string $table;

    protected function query(): ClickHouseQueryBuilder
    {
        return new ClickHouseQueryBuilder($this->table);
    }

} 