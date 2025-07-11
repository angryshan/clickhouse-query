<?php
// src/Interfaces/ConnectionAdapterInterface.php

declare(strict_types=1);

namespace ClickHouseQuery\Interfaces;

/**
 * 数据库连接适配器接口
 * @package ClickHouseQuery\Interfaces
 * @author angryshan
 */
interface ConnectionAdapterInterface
{
    public function query(string $sql);
    public function getConfig(string $key, $default = null);

    /**
     * 获取当前请求的参数
     * @return array
     */
    public function getRequestParams(): array;
}
