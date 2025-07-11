<?php

declare(strict_types=1);

namespace ClickHouseQuery\Interfaces;

/**
 * 表接口
 * @package ClickHouseQuery\Interfaces
 * @author angryshan
 */
interface TableInterface
{
    /**
     * 获取表名
     */
    public function getTableName(): string;
} 