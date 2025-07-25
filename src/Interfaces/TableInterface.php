<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Interfaces;

/**
 * 表接口
 * @package TxAdmin\ClickHouseQuery\Interfaces
 * @author angryshan
 */
interface TableInterface
{
    /**
     * 获取表名
     */
    public function getTableName(): string;
} 