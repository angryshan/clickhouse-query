<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Adapters;

use Hyperf\Context\ApplicationContext;
use Hyperf\DB\DB;
use Hyperf\HttpServer\Contract\RequestInterface;
use Hyperf\Paginator\Paginator;
use Psr\Container\ContainerInterface;
use RuntimeException;
use TxAdmin\ClickHouseQuery\Interfaces\ConnectionAdapterInterface;

/**
 * Hyperf 适配器（Hyperf 3.x / PHP 8.4+）
 * @package TxAdmin\ClickHouseQuery\Adapter
 * @author angryshan
 */
class HyperfAdapter implements ConnectionAdapterInterface
{
    /**
     * @var DB
     */
    private DB $connection;

    public function __construct(
        private readonly string $poolName = 'clickhouse'
    ) {
        $this->connection = DB::connection($this->poolName);
    }

    public function query(string $sql): array
    {
        return $this->connection->run(function (\PDO $pdo) use ($sql): array {
            $statement = $pdo->prepare($sql);
            $info = $statement->execute();

            if (!$info) {
                $errorInfo = $statement->errorInfo();
                throw new \RuntimeException($errorInfo[2] ?? 'ClickHouse query failed');
            }

            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        });
    }

    public function getConfig(string $key, mixed $default = null): mixed
    {
        return config("tx_clickhouse_query.{$key}", $default);
    }

    /**
     * 获取当前请求的参数
     * @return array
     */
    public function getRequestParams(): array
    {
        return $this->getContainer()->get(RequestInterface::class)->all();
    }

    /**
     * 解析当前页码（委托 Hyperf Paginator::resolveCurrentPage）
     */
    public function getCurrentPage(string $varPage = 'page', int $default = 1): int
    {
        if (class_exists(Paginator::class)) {
            $page = Paginator::resolveCurrentPage($varPage, $default);

            return $page >= 1 ? $page : $default;
        }

        $page = $this->getContainer()->get(RequestInterface::class)->input($varPage, $default);

        if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
            return (int) $page;
        }

        return $default;
    }

    private function getContainer(): ContainerInterface
    {
        return ApplicationContext::getContainer();
    }
}
