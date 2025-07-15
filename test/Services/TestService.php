<?php

namespace ClickHouseQuery\Test\Services;

use ClickHouseQuery\Services\AbstractService;
use ClickHouseQuery\Test\Tables\TestTable;

class TestService extends AbstractService
{
    public function __construct()
    {
        $this->table = TestTable::TABLE_NAME;
    }

    public function getTestData(): array
    {
        return $this->query()->select(['id', 'name'])->get();
    }

} 