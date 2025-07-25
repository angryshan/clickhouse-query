<?php

declare(strict_types=1);

namespace TxAdmin\ClickHouseQuery\Services;

use TxAdmin\ClickHouseQuery\Database\QueryBuilder\ClickHouseQueryBuilder;
use TxAdmin\ClickHouseQuery\Exceptions\ClickHouseQueryException;

/**
 * 查询服务抽象基类
 * 提供基础查询功能，业务逻辑应在子类中实现
 * 
 * @package TxAdmin\ClickHouseQuery\Services
 * @author angryshan
 */
abstract class AbstractService
{
    /**
     * 当前操作的表名
     */
    protected string $table;
    
    /**
     * 配置参数
     */
    protected array $config = [];

    /**
     * 创建查询构建器
     * 
     * @param array $extraConfig 额外的配置参数
     * @return ClickHouseQueryBuilder
     * @throws ClickHouseQueryException 如果表名未定义
     */
    protected function query(array $extraConfig = []): ClickHouseQueryBuilder
    {
        if (empty($this->table)) {
            throw new ClickHouseQueryException('表名未定义，请在子类中设置 $table 属性');
        }
        
        $config = array_merge($this->config, $extraConfig);
        return new ClickHouseQueryBuilder($this->table, $config);
    }
    
    /**
     * 设置配置参数
     * 
     * @param array $config 配置参数
     * @return self
     */
    public function setConfig(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }
} 