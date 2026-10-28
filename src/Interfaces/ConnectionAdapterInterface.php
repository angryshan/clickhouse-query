<?php

// src/Interfaces/ConnectionAdapterInterface.php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Interfaces;

/**
 * 数据库连接适配器接口
 * @package TxAdmin\ClickHouseQuery\Interfaces
 * @author angryshan
 */
interface ConnectionAdapterInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function query(string $sql): array;

    public function getConfig(string $key, mixed $default = null): mixed;

    /**
     * 获取当前请求的参数
     *
     * @return array<string, mixed>
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
