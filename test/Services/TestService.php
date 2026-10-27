<?php

namespace TxAdmin\ClickHouseQueryTest\Services;

use TxAdmin\ClickHouseQuery\Services\AbstractService;
use TxAdmin\ClickHouseQueryTest\Tables\TestTable;

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