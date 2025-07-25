<?php

namespace TxAdmin\ClickHouseQuery\Test\Services;

use TxAdmin\ClickHouseQuery\Services\AbstractService;
use TxAdmin\ClickHouseQuery\Test\Tables\TestTable;

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