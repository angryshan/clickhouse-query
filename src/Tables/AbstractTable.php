<?php

declare(strict_types=1);

namespace ClickHouseQuery\Tables;

use ClickHouseQuery\Interfaces\TableInterface;

/**
 * 抽象表类
 * @package ClickHouseQuery\Tables
 * @author angryshan
 */
abstract class AbstractTable implements TableInterface
{
    protected string $tableName;

    public function getTableName(): string
    {
        return $this->tableName;
    }
} 