<?php
// src/Interfaces/ConnectionAdapterInterface.php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Interfaces;

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

    /**
     * 解析当前页码
     *
     * @param string $varPage 请求参数名，默认 page
     * @param int $default 无效或未传时的默认值
     */
    public function getCurrentPage(string $varPage = 'page', int $default = 1): int;
}
