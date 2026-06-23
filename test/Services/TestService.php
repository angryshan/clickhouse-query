<?php

namespace ClickHouseQueryTest\Services;

use ClickHouseQuery\Services\AbstractService;
use ClickHouseQueryTest\Tables\TestTable;

class TestService extends AbstractService
{
    public function __construct()
    {
        $this->table = TestTable::TABLE_NAME;
    }

    public function getTestSql(): string
    {
        return $this->query()->select(['id', 'name'])->toSql();
    }

} 