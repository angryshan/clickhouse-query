<?php

namespace ClickHouseQueryTest;

use PHPUnit\Framework\TestCase;
use ClickHouseQueryTest\Services\TestService;

class ClickHouseQueryBuilderTest extends TestCase
{
    public function testSelectToSql()
    {
        $service = new TestService();
        $sql = $service->getTestSql();
        $this->assertIsString($sql);
        $this->assertStringContainsString('select', strtolower($sql));
        $this->assertStringContainsString('id', strtolower($sql));
        $this->assertStringContainsString('name', strtolower($sql));
    }
}


