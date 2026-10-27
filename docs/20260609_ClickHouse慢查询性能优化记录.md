# 20260609 ClickHouse 慢查询性能优化记录

## 问题发现

2026-06-09，群里反馈 ClickHouse 存在慢查询，CPU 持续高压，截图如下：
![image](/images/9/1864/1780996351000-企业微信截图_17806473024565.png)
![image](/images/9/1864/1780996368000-企业微信截图_17806474182967.png)

- 单次查询耗时 2300ms+
- 读取行数 2400 万行
- 读取数据量 ~862 MB

---

## 排查过程

### 1. 确认慢查询

```
查询耗时:   2379ms / 2382ms
读取行数:   24,443,175 行
读取数据量: ~862 MB
返回行数:   136 行
```

### 2. EXPLAIN 定位根因

对慢查询执行 `EXPLAIN indexes = 1`，分区裁剪完全没有生效：

```
Partition:
  Condition: true         ← 无法利用分区键
  Parts: 890/890          ← 全部 890 个 parts 被扫描
  Granules: 49114/49114   ← 全部 4.9 万个 granules 被读取
```
![image](/images/9/1864/1780996421000-企业微信截图_17806491118996.png)

### 3. 原因分析

`player_action_log`、`server_monitor_log` 等表的分区键是 `log_date`（Date 类型），但查询只传了 `log_time`（UInt64 毫秒时间戳），ClickHouse 没法从时间戳推断分区范围，只能全表扫描。

内网对比实验（server_monitor_log，共 1225 个 parts）：

| 查询条件 | Parts | Granules | 结论 |
|---------|-------|----------|------|
| 只有 `log_time` | 1223/1223 | 1641/44668 | 全表扫描 |
| `log_time` + `game_id` | 1228/1228 | 1611/44674 | 分区无效，主键略有过滤 |
| `log_time` + `game_id` + `log_date` | 39/1225 | 72/44676 | 减少 95.6% |

`game_id` 这次效果不明显是因为库里只有 4001 一个游戏，索引没有区分度，多游戏场景下会有明显效果。

### 4. 全局条件 game_id 未生效

查询构造器有自动注入全局 `game_id` 的功能，但实际生成的 SQL 里没有这个条件。

原因是配置文件从 `clickhouse.php` 改名为 `tx_clickhouse_query.php` 时，`HyperfAdapter` 和 `ThinkPHPAdapter` 的 `getConfig()` 里读取的 key 前缀没有同步修改，导致读不到任何配置。

```php
// 修复前
return config("clickhouse.{$key}", $default);

// 修复后
return config("tx_clickhouse_query.{$key}", $default);
```

### 5. NOT IN 范围不可控

部分查询用 `NOT IN` 排除某个 `log_type`，但 `player_action_log` 是通用日志表，`log_type` 会随业务持续增加，`NOT IN` 的隐含扫描范围越来越大，ClickHouse 对 `NOT IN` 的稀疏索引利用也很差。

---

## 解决方案

### 1. 查询构造器自动注入分区条件（应急方案，xm4 已上线）

xm4 已上线，表结构无法直接变更分区键，新增 `TimePartitionOptimizationTrait` 作为过渡方案。在 `buildWhere()` 阶段检测到时间戳条件时自动注入对应的 `log_date` 条件：

```php
// 原有代码
$query->where([['log_time', 'BETWEEN', [1780283946000, 1780888746000]]]);

// 自动生成的 SQL
WHERE log_time BETWEEN 1780283946000 AND 1780888746000
  AND log_date BETWEEN '2026-05-24' AND '2026-05-31'
```

支持 `>=`、`<=`、`>`、`<`、`=`、`BETWEEN`，秒级/毫秒级按表配置：

```php
'tables' => [
    'client_monitor_log'  => ['log_time' => 'log_date', 'timestamp_type' => 'seconds'],
    'player_action_log'   => ['log_time' => 'log_date', 'timestamp_type' => 'milliseconds'],
    'server_monitor_log'  => ['log_time' => 'log_date', 'timestamp_type' => 'milliseconds'],
    'game_mod_log'        => ['log_time' => 'log_date', 'timestamp_type' => 'milliseconds'],
],
```

### 2. 修改分区键（根治方案，xm2/xm5/xm6 待上线）

xm2、xm5、xm6 尚未上线，直接将分区键从 `log_date` 改为 `log_time`，分区和查询字段一致，不再需要额外注入日期条件：

```sql
-- 改前
PARTITION BY toYYYYMMDD(log_date)

-- 改后
PARTITION BY toYYYYMMDD(toDateTime(log_time / 1000))  -- 毫秒级
-- 或
PARTITION BY toYYYYMMDD(toDateTime(log_time))          -- 秒级
```

上线后将 `time_partition_optimization.enabled` 设为 `false` 关闭 Trait 即可，不影响 xm4。

### 3. NOT IN 改为 IN

```php
// 改前
$query->whereNotIn('log_type', ['PlayerLoginLog']);

// 改后
$query->whereIn('log_type', ['PlayerChargeLog', 'PlayerRechargeLog']);
```

---

## 注意事项

1. 表按 `log_date` 分区，查询必须带 `log_date` 条件，只有 `log_time` 不会触发分区裁剪
2. `game_id` 是 ORDER BY 第一列，对主键索引有效，但不影响分区裁剪，两者要分清
3. `log_type` 用 `IN` 明确指定，不要用 `NOT IN`
4. 上线前用 `EXPLAIN indexes = 1` 检查，`Parts: X/Y` 里 X 远小于 Y 才算正常
5. 配置文件改名时，注意同步检查所有适配器里的 config key 前缀
