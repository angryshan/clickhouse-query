-- `2001_log_metadata`.admin_inner_account_dict definition

CREATE DICTIONARY `2001_log_metadata`.admin_inner_account_dict
(

    `player_id` UInt64,

    `ignore_monitor` UInt8,

    `account` String
)
PRIMARY KEY player_id
SOURCE(MYSQL(HOST '192.168.240.180' PORT 3306 USER 'write' PASSWORD '[HIDDEN]' DB 'xm2_center' QUERY 'SELECT player_id,
\nignore_monitor,
\naccount FROM `tx_inner_account` where status = 0' INVALIDATE_QUERY 'SELECT MAX(update_time) FROM `tx_inner_account`'))
LIFETIME(MIN 300 MAX 600)
LAYOUT(HASHED_ARRAY());


-- `2001_log_metadata`.client_monitor_log definition

CREATE TABLE `2001_log_metadata`.client_monitor_log
(

    `create_time` UInt32 DEFAULT toUnixTimestamp(now()) COMMENT '入库创建时间',

    `log_date` Date DEFAULT toDate(log_time) COMMENT '日期',

    `log_date_time` DateTime DEFAULT toDateTime(log_time,
 'Asia/Shanghai') COMMENT '日期时间',

    `log_time` UInt64 COMMENT '时间日期',

    `ip` String COMMENT 'ip地址',

    `game_id` UInt16 COMMENT '游戏标识',

    `file_version` UInt64 COMMENT '资源版本号',

    `code_version` UInt64 COMMENT '代码版本号',

    `mini_game_version` String COMMENT '小程序版本号',

    `channel_id` String COMMENT '渠道ID',

    `package_id` UInt32 COMMENT '包号',

    `log_type` String COMMENT '日志类型',

    `group_id` UInt32 COMMENT '渠道专服ID (group_id)',

    `server_id` UInt64 COMMENT '服务器ID (server_id)',

    `server_num` UInt32 COMMENT '区服编号 (server_num)',

    `account` String COMMENT '玩家账号',

    `user_id` String COMMENT '联运用户ID',

    `channel_uid` String COMMENT '渠道用户ID',

    `login_token` String COMMENT '登陆TOKEN',

    `player_id` UInt64 COMMENT '玩家ID',

    `type_id` UInt32 COMMENT '类型ID',

    `site` String COMMENT '报错行列',

    `source` String COMMENT '错误来源',

    `stack` String COMMENT '错误堆栈',

    `stack_md5` String COMMENT '错误堆栈md5',

    `stage` String COMMENT '报错阶段',

    `error_json` String COMMENT '完整json',

    `error_type` String COMMENT '报错类型',

    `is_first_login` UInt8 COMMENT '是否第一次登陆',

    `load_step` UInt8 COMMENT '加载步骤',

    `is_roll` UInt8 COMMENT '是否滚服',

    `first_pay_time` UInt64 DEFAULT 0 COMMENT '用户首充时间（秒）',

    `reg_time` UInt64 DEFAULT 0 COMMENT '用户注册时间（秒）',

    `last_login_time` UInt64 DEFAULT 0 COMMENT '最后登录时间（秒）',

    `first_pay_money` UInt32 DEFAULT 0 COMMENT '首次充值金额',

    `first_pay_real_money` UInt32 DEFAULT 0 COMMENT '首次充值真实金额',

    `sum_money` UInt32 DEFAULT 0 COMMENT '累积充值金额',

    `sum_real_money` UInt32 DEFAULT 0 COMMENT '累积充值真实金额',

    `player_name` String COMMENT '玩家名称',

    `player_level` UInt32 DEFAULT 0 COMMENT '玩家等级',

    `sub_log_type` UInt32 DEFAULT 0 COMMENT '日志子类型',

    `player_tran_level` UInt32 DEFAULT 0 COMMENT '玩家转生等级',

    `player_vip_level` UInt32 DEFAULT 0 COMMENT '玩家vip等级',

    `icon_id` UInt32 DEFAULT 0 COMMENT '头像id',

    `icon_frame_id` UInt32 DEFAULT 0 COMMENT '头像框id',

    `common_value_1` UInt64 DEFAULT 0 COMMENT '通用数值字段1',

    `common_value_2` UInt64 DEFAULT 0 COMMENT '通用数值字段2',

    `common_value_3` UInt64 DEFAULT 0 COMMENT '通用数值字段3',

    `common_value_4` UInt64 DEFAULT 0 COMMENT '通用数值字段4',

    `common_string_1` String DEFAULT 0 COMMENT '通用字符串字段1',

    `is_flag` UInt8 DEFAULT 0 COMMENT '标记是否',

    `status` UInt16 DEFAULT 0 COMMENT '状态',

    `share_time` UInt8 DEFAULT 0 COMMENT '分享时间',

    `player_create_time` UInt64 DEFAULT 0 COMMENT '玩家注册时间'
)
ENGINE = MergeTree
PARTITION BY toYYYYMMDD(log_date)
ORDER BY (game_id,
 log_type,
 log_time,
 group_id,
 server_id)
SETTINGS index_granularity = 8192;


-- `2001_log_metadata`.game_mod_log definition

CREATE TABLE `2001_log_metadata`.game_mod_log
(

    `create_time` UInt32 DEFAULT toUnixTimestamp(now()) COMMENT '入库创建时间',

    `log_date` Date DEFAULT toDate(log_time / 1000) COMMENT '日期',

    `log_date_time` DateTime DEFAULT toDateTime(log_time / 1000,
 'Asia/Shanghai') COMMENT '日期时间',

    `log_time` UInt64 COMMENT '时间日期',

    `ip` String COMMENT 'ip地址',

    `game_id` UInt16 COMMENT '游戏标识',

    `version` UInt64 COMMENT '服务端版本号',

    `log_type` String COMMENT '日志类型',

    `platform` UInt32 COMMENT '渠道专服id',

    `group` UInt32 COMMENT '区服编号 (server_num)',

    `server_type` UInt8 DEFAULT 1 COMMENT '服类型',

    `server_id` UInt64 COMMENT 'server_id',

    `node_id` UInt64 COMMENT '节点id',

    `race_id` UInt32 COMMENT '所在赛场id',

    `guild_id` UInt64 COMMENT '战盟id',

    `guild_name` String COMMENT '战盟名称',

    `start_time` UInt64 COMMENT '活动开始时间',

    `end_time` UInt64 COMMENT '活动结束时间',

    `online_player_count` UInt16 COMMENT '在线玩家数',

    `offline_player_count` UInt16 COMMENT '离线玩家数',

    `guild_player_count` UInt16 COMMENT '公会总人数',

    `guild_player_fight` UInt64 COMMENT '公会总参与战力',

    `sub_log_type` UInt16 COMMENT '子类型',

    `boss_id` UInt32 COMMENT 'boss ID',

    `boss_type` UInt32 DEFAULT 0 COMMENT 'boss类型',

    `player_count` UInt16 COMMENT '参与玩家数',

    `guild_level` UInt32 COMMENT '战盟等级',

    `guild_fight` UInt64 COMMENT '战盟战力',

    `leader_id` UInt64 COMMENT '盟主玩家id',

    `guild_pre_pos` UInt8 COMMENT '变动前职位',

    `guild_pos` UInt8 COMMENT '变动后职位',

    `guild_notice` String COMMENT '战盟公告',

    `operate_player_id` UInt64 COMMENT '操作者玩家id',

    `player_id` UInt64 COMMENT '玩家id',

    `price` Int32 DEFAULT 0 COMMENT '砍价物品最终售价',

    `source_id` UInt32 DEFAULT 0 COMMENT '变动原因',

    `change_pre_level` UInt32 DEFAULT 0 COMMENT '变动前等级',

    `current_level` UInt32 DEFAULT 0 COMMENT '变动后等级',

    `change_pre_value` UInt32 DEFAULT 0 COMMENT '变动前值',

    `current_value` UInt32 DEFAULT 0 COMMENT '变动后值',

    `change_value` Int64 DEFAULT 0 COMMENT '变动值',

    `max_level` UInt32 DEFAULT 0 COMMENT '历史最高值等级',

    `guarantee_value` UInt32 DEFAULT 0 COMMENT '保底值',

    `item_id` UInt64 DEFAULT 0 COMMENT '道具ID',

    `count` UInt32 DEFAULT 0 COMMENT '消耗数量',

    `progress` UInt32 DEFAULT 0 COMMENT '最终进度值',

    `rank` UInt8 DEFAULT 0 COMMENT '最终排名值',

    `cost_time` UInt32 DEFAULT 0 COMMENT '耗时(秒)',

    `map_id` UInt32 DEFAULT 0 COMMENT '地图ID',

    `version_config` String COMMENT '配置文件svn版本',

    `version_svn` String COMMENT '服务端代码脚本svn版本',

    `player_name` String COMMENT '玩家名',

    `player_vip_level` UInt32 DEFAULT 0 COMMENT '玩家vip等级',

    `icon_id` UInt32 DEFAULT 0 COMMENT '头像id',

    `icon_frame_id` UInt32 DEFAULT 0 COMMENT '头像框id',

    `world_num` UInt32 DEFAULT 0 COMMENT '当前世界NUM (第几世界)',

    `list_time` UInt64 DEFAULT 0 COMMENT '上榜时间戳',

    `extra_args` String DEFAULT 0 COMMENT '额外参数',

    `player_server_num` UInt32 DEFAULT 0 COMMENT '玩家单服group',

    `player_server_id` UInt64 DEFAULT 0 COMMENT '玩家当前单服ID',

    `before_level` UInt32 DEFAULT 0 COMMENT '变动前等级',

    `before_value` UInt64 DEFAULT 0 COMMENT '变动前值',

    `uuid` String COMMENT 'uuid',

    `open_id` UInt64 DEFAULT 0 COMMENT '功能ID',

    `player_list` String COMMENT '角色列表',

    `sub_value` UInt64 DEFAULT 0 COMMENT '子值',

    `rival_id` UInt64 DEFAULT 0 COMMENT '对手ID',

    `rival_fight` UInt64 DEFAULT 0 COMMENT '对手战力',

    `extra_value` UInt64 DEFAULT 0 COMMENT '扩展值',

    `ret` UInt16 DEFAULT 0 COMMENT '结果',

    `rival_ids` String COMMENT '对手列表',

    `is_hosting` UInt8 DEFAULT 0 COMMENT '玩家是否托管状态',

    `player_create_server_id` UInt64 DEFAULT 0 COMMENT '玩家创角单服ID',

    `common_value_1` UInt64 DEFAULT 0 COMMENT '通用数值字段1',

    `common_value_2` UInt64 DEFAULT 0 COMMENT '通用数值字段2',

    `common_value_3` UInt64 DEFAULT 0 COMMENT '通用数值字段3',

    `common_value_4` UInt64 DEFAULT 0 COMMENT '通用数值字段4',

    `common_value_5` UInt64 DEFAULT 0 COMMENT '通用数值字段5'
)
ENGINE = MergeTree
PARTITION BY toYYYYMMDD(log_date)
ORDER BY (game_id,
 log_type,
 log_time,
 platform,
 node_id,
 server_id)
SETTINGS index_granularity = 8192;


-- `2001_log_metadata`.player_action_log definition

CREATE TABLE `2001_log_metadata`.player_action_log
(

    `create_time` UInt32 DEFAULT toUnixTimestamp(now()) COMMENT '入库时间',

    `log_date` Date DEFAULT toDate(log_time / 1000) COMMENT '日期',

    `log_date_time` DateTime DEFAULT toDateTime(log_time / 1000,
 'Asia/Shanghai') COMMENT '日期时间',

    `log_time` UInt64 COMMENT '时间日期',

    `ip` String COMMENT 'ip地址',

    `game_id` UInt16 COMMENT '游戏标识',

    `node_id` UInt64 COMMENT '节点id',

    `package_id` UInt32 COMMENT '包号',

    `version` UInt64 COMMENT '服务端版本号',

    `platform` UInt32 COMMENT '渠道专服ID (group_id)',

    `group` UInt32 COMMENT '区服编号 (server_num)',

    `server_id` UInt64 COMMENT 'server_id',

    `account` String COMMENT '账号',

    `player_id` UInt64 COMMENT '玩家id',

    `player_name` String COMMENT '玩家名称',

    `player_level` UInt32 DEFAULT 1 COMMENT '玩家等级',

    `player_vip_level` UInt32 DEFAULT 0 COMMENT '玩家vip等级',

    `player_tran_level` UInt32 DEFAULT 0 COMMENT '玩家转生等级',

    `player_fight` UInt64 DEFAULT 0 COMMENT '玩家战力',

    `player_create_server_id` UInt64 COMMENT '玩家创建时的server_id,
\r\n\r\n用于后期区分子母服',

    `icon_id` UInt32 DEFAULT 0 COMMENT '头像id',

    `icon_frame_id` UInt32 DEFAULT 0 COMMENT '头像框id',

    `player_login_session` String COMMENT '玩家登陆session，作用：可根据该字段查玩家这次登陆的所有行为',

    `player_create_time` UInt64 DEFAULT 0 COMMENT '玩家注册时间',

    `player_charge_money` UInt32 COMMENT '玩家充值金额',

    `player_is_outline` UInt8 DEFAULT 0 COMMENT '日志记录时，玩家是否离线；0：在线 1：离线',

    `season_id` UInt64 COMMENT '赛季ID',

    `log_type` String COMMENT '日志类型',

    `sub_log_type` UInt32 DEFAULT 0 COMMENT '日志子类型',

    `change_type` UInt16 COMMENT '变动类型',

    `online_time` UInt64 DEFAULT 0 COMMENT '在线时长 (毫秒)',

    `source_id` UInt64 DEFAULT 0 COMMENT '来源id',

    `before_value` UInt64 DEFAULT 0 COMMENT '变动前值',

    `change_value` UInt64 DEFAULT 0 COMMENT '变动值',

    `current_value` UInt64 DEFAULT 0 COMMENT '当前值',

    `current_uuid_value` UInt64 DEFAULT 0 COMMENT '当前组值',

    `before_level` UInt32 DEFAULT 0 COMMENT '变动前等级',

    `change_level` UInt32 DEFAULT 0 COMMENT '变动等级',

    `current_level` UInt32 DEFAULT 0 COMMENT '当前等级',

    `before_exp` UInt64 DEFAULT 0 COMMENT '变动前经验',

    `change_exp` UInt64 DEFAULT 0 COMMENT '变动经验',

    `current_exp` UInt64 DEFAULT 0 COMMENT '当前经验',

    `item_id` UInt64 DEFAULT 0 COMMENT '道具id',

    `item_uid` UInt64 DEFAULT 0 COMMENT '道具uid',

    `to_player_id` UInt64 DEFAULT 0 COMMENT '对象玩家id',

    `title` String DEFAULT '' COMMENT '标题',

    `content` String DEFAULT '' COMMENT '内容',

    `rewards` String COMMENT '道具奖励参数',

    `costs` String COMMENT '道具消耗参数',

    `extra_args` String DEFAULT '' COMMENT '额外参数',

    `order_id` String COMMENT '订单ID',

    `money` UInt32 COMMENT '金额',

    `product_id` UInt32 COMMENT '商品id',

    `is_test` UInt8 DEFAULT 0 COMMENT '是否测试',

    `stage_id` UInt32 COMMENT '阶段id 可以作为通用id字段',

    `status` UInt16 COMMENT '通用状态字段',

    `parent_id` UInt32 COMMENT '父级id',

    `parent_status` UInt16 COMMENT '父级状态',

    `is_flag` UInt8 COMMENT '一个标志位,
\r\n\r\n用来指示某个条件是否满足(值为0或1)',

    `map_id` UInt64 COMMENT 'MapId',

    `ret` UInt16 COMMENT '结果',

    `rank` UInt16 COMMENT '排名',

    `mail_id` UInt32 DEFAULT 0 COMMENT '邮件id',

    `role_id` UInt32 COMMENT '角色id',

    `hero_id` UInt32 COMMENT '英雄id',

    `hero_pre_id` UInt32 COMMENT '旧英雄id',

    `scene_id` UInt32 COMMENT '场景id',

    `mon_id` UInt32 COMMENT '怪点',

    `race_id` UInt32 COMMENT '所在赛场id',

    `guild_id` UInt64 COMMENT '战盟id',

    `guild_name` String COMMENT '战盟名称',

    `start_time` UInt64 COMMENT '活动开始时间',

    `end_time` UInt64 COMMENT '活动结束时间',

    `boss_id` UInt32 COMMENT 'boss的编号',

    `current_count` UInt32 COMMENT '当前次数',

    `current_day_count` UInt32 COMMENT '当日次数',

    `current_total_count` UInt32 COMMENT '当前总次数',

    `step` UInt16 COMMENT '步骤',

    `player_count` UInt16 COMMENT '怪点玩家数',

    `mon_level` UInt32 COMMENT '怪点等级',

    `mon_fight` UInt64 COMMENT '怪点配置验证战力',

    `afk_time` UInt32 COMMENT '挂机时长',

    `new_item_id` UInt64 COMMENT '新道具ID',

    `kill_mon_count` UInt32 COMMENT '累积杀怪数',

    `kill_player_count` UInt32 COMMENT '累积杀人数',

    `boss_type` UInt16 DEFAULT 0 COMMENT 'boss类型',

    `cost_time` UInt64 DEFAULT 0 COMMENT '耗时',

    `record_uuid` String DEFAULT '' COMMENT '通用记录id,
\r\n\r\n 如邮件uuid',

    `server_cross_id` UInt64 DEFAULT 0 COMMENT '服务器所在跨服ID',

    `common_value_1` UInt64 DEFAULT 0 COMMENT '通用字段1',

    `common_value_2` UInt64 DEFAULT 0 COMMENT '通用字段2',

    `common_value_3` UInt64 DEFAULT 0 COMMENT '通用字段3',

    `common_value_4` UInt64 DEFAULT 0 COMMENT '通用字段4',

    `common_value_5` UInt64 DEFAULT 0 COMMENT '通用字段5',

    `pos` String COMMENT '坐标',

    `is_hosting` UInt8 DEFAULT 0 COMMENT '玩家是否托管状态',

    `server_open_time` UInt64 DEFAULT 0 COMMENT '区服的开服时间',

    `error_code` UInt32 DEFAULT 0 COMMENT '服务端错误码',

    `sub_value` UInt64 DEFAULT 0 COMMENT '子值',

    `open_id` UInt64 DEFAULT 0 COMMENT '功能ID',

    `cost_value` UInt32 DEFAULT 0 COMMENT '消耗值',

    `ratio_value` UInt32 DEFAULT 0 COMMENT '比例值,
 去除小数点,
 12.34% = 1234',

    `rival_id` UInt64 DEFAULT 0 COMMENT '对手ID',

    `rival_fight` UInt64 DEFAULT 0 COMMENT '对手战力',

    `rank_value` UInt16 DEFAULT 0 COMMENT '排名',

    `team_id` String COMMENT '队伍ID',

    `common_value_a` UInt64 COMMENT '通用数值字段a',

    `common_value_b` UInt64 COMMENT '通用数值字段b',

    `common_value_c` UInt64 COMMENT '通用数值字段c',

    `extra_value` UInt64 DEFAULT 0 COMMENT '扩展值',

    `version_config` UInt64 DEFAULT 0 COMMENT '配置文件svn版本',

    `version_svn` UInt64 DEFAULT 0 COMMENT '服务端代码脚本svn版本',

    INDEX player_bloom_filter player_id TYPE bloom_filter GRANULARITY 3,

    INDEX account_bloom_filter account TYPE bloom_filter GRANULARITY 4
)
ENGINE = MergeTree
PARTITION BY toYYYYMMDD(log_date)
ORDER BY (game_id,
 log_type,
 log_time,
 platform,
 server_id,
 player_create_server_id)
SETTINGS index_granularity = 8192;


-- `2001_log_metadata`.player_daily_base definition

CREATE TABLE `2001_log_metadata`.player_daily_base
(

    `log_date` Date COMMENT '日期',

    `game_id` UInt16 COMMENT '游戏标识',

    `platform` UInt32 COMMENT '渠道专服ID (group_id)',

    `player_create_server_id` UInt64 COMMENT '玩家创建时的server_id,
\n用于后期区分子母服',

    `account` String COMMENT '用户唯一标识',

    `player_id` UInt64 COMMENT '玩家id',

    `player_create_time` UInt64 COMMENT '玩家注册时间',

    `server_open_time` UInt64 DEFAULT 0 COMMENT '区服的开服时间',

    `server_id` AggregateFunction(argMax,
 UInt64,
 UInt64) COMMENT '服务器ID',

    `player_name` AggregateFunction(argMax,
 String,
 UInt64) COMMENT '玩家名称',

    `player_level` AggregateFunction(argMax,
 UInt32,
 UInt64) COMMENT '玩家等级',

    `player_tran_level` AggregateFunction(argMax,
 UInt32,
 UInt64) COMMENT '玩家转生等级',

    `player_vip_level` AggregateFunction(argMax,
 UInt32,
 UInt64) COMMENT '玩家vip等级',

    `player_fight` AggregateFunction(argMax,
 UInt64,
 UInt64) COMMENT '玩家战力',

    `player_charge_money` AggregateFunction(argMax,
 UInt32,
 UInt64) COMMENT '玩家累积充值金额(历史总充值)',

    `guild_id` AggregateFunction(argMax,
 UInt64,
 UInt64) COMMENT '帮派id',

    `current_premium_currency` AggregateFunction(argMaxIf,
 UInt64,
 UInt64,
 UInt8) COMMENT '当前仙玉剩余数量',

    `current_basic_currency` AggregateFunction(argMaxIf,
 UInt64,
 UInt64,
 UInt8) COMMENT '当前铜币剩余数量',

    `login_count` AggregateFunction(countIf,
 UInt8) COMMENT '玩家当天登录次数',

    `online_time` AggregateFunction(sumIf,
 UInt64,
 UInt8) COMMENT '玩家当天在线时长(毫秒)',

    `last_login_time` AggregateFunction(maxIf,
 UInt64,
 UInt8) COMMENT '最后登录时间',

    `last_logout_time` AggregateFunction(maxIf,
 UInt64,
 UInt8) COMMENT '最后登出或离线时间',

    `last_upgrade_time` AggregateFunction(maxIf,
 UInt64,
 UInt8) COMMENT '最后升级时间',

    `last_fight_change_time` AggregateFunction(maxIf,
 UInt64,
 UInt8) COMMENT '战力值变化最后时间',

    `last_charge_time` AggregateFunction(maxIf,
 UInt64,
 UInt8) COMMENT '最后充值时间',

    `daily_player_charge_money` AggregateFunction(sumIf,
 UInt32,
 UInt8) COMMENT '玩家当天累积充值金额',

    `max_log_time` AggregateFunction(max,
 UInt64) COMMENT '最新时间',

    `create_ip` AggregateFunction(argMaxIf,
 String,
 UInt64,
 UInt8) COMMENT '创角时ip',

    `latest_ip` AggregateFunction(argMax,
 String,
 UInt64) COMMENT '最新日志记录的ip',

    `latest_package_id` AggregateFunction(argMaxIf,
 UInt32,
 UInt64,
 UInt8) COMMENT '最新包id',

    PROJECTION prj_server_id_optimize
    (
        SELECT 
            game_id,

            log_date,

            server_open_time,

            platform,

            player_create_server_id,

            account,

            player_id,

            any(player_create_time) AS player_create_time,

            argMaxMerge(server_id) AS server_id,

            argMaxMerge(player_name) AS player_name,

            argMaxMerge(player_level) AS player_level,

            argMaxMerge(player_tran_level) AS player_tran_level,

            argMaxMerge(player_vip_level) AS player_vip_level,

            argMaxMerge(player_fight) AS player_fight,

            argMaxMerge(player_charge_money) AS player_charge_money,

            argMaxMerge(guild_id) AS guild_id,

            argMaxIfMerge(current_premium_currency) AS current_premium_currency,

            argMaxIfMerge(current_basic_currency) AS current_basic_currency,

            countIfMerge(login_count) AS login_count,

            sumIfMerge(online_time) AS online_time,

            maxIfMerge(last_login_time) AS last_login_time,

            maxIfMerge(last_logout_time) AS last_logout_time,

            maxIfMerge(last_upgrade_time) AS last_upgrade_time,

            maxIfMerge(last_fight_change_time) AS last_fight_change_time,

            maxIfMerge(last_charge_time) AS last_charge_time,

            sumIfMerge(daily_player_charge_money) AS daily_player_charge_money,

            argMaxMerge(latest_ip) AS latest_ip,

            argMaxIfMerge(create_ip) AS create_ip,

            argMaxIfMerge(latest_package_id) AS latest_package_id
        GROUP BY 
            game_id,

            log_date,

            server_open_time,

            platform,

            player_create_server_id,

            account,

            player_id
    )
)
ENGINE = AggregatingMergeTree
PARTITION BY toYYYYMMDD(log_date)
ORDER BY (game_id,
 log_date,
 server_open_time,
 platform,
 player_create_server_id,
 account,
 player_id)
SETTINGS index_granularity = 8192,
 deduplicate_merge_projection_mode = 'rebuild';


-- `2001_log_metadata`.player_stat_log definition

CREATE TABLE `2001_log_metadata`.player_stat_log
(

    `create_time` UInt64 DEFAULT toUnixTimestamp(now()) COMMENT '入库时间',

    `log_date` Date DEFAULT toDate(report_time) COMMENT '日期',

    `log_type` String DEFAULT '' COMMENT '日志类型',

    `report_time` UInt64 COMMENT '统计日期',

    `game_id` UInt16 DEFAULT 0 COMMENT '游戏标识',

    `group_id` UInt16 DEFAULT 0 COMMENT '渠道组id',

    `server_id` UInt64 DEFAULT 0 COMMENT '区服id(母服)',

    `server_num` UInt32 DEFAULT 0 COMMENT '区服编号(母服)',

    `player_create_server_id` UInt64 DEFAULT 0 COMMENT '初始服id(子服)',

    `server_open_time` UInt64 DEFAULT 0 COMMENT '开服时间',

    `open_days` UInt32 DEFAULT 0 COMMENT '开服第N天',

    `open_register_days` UInt32 DEFAULT 0 COMMENT '开服第N天注册',

    `account` String DEFAULT '' COMMENT '玩家账号',

    `player_id` UInt64 DEFAULT 0 COMMENT '玩家id',

    `is_inner` UInt8 DEFAULT 0 COMMENT '是否内部玩家1是0否',

    `player_create_time` UInt64 DEFAULT 0 COMMENT '玩家注册时间',

    `guild_id` UInt64 DEFAULT 0 COMMENT '帮派ID',

    `player_name` String DEFAULT '' COMMENT '玩家名称',

    `player_level` UInt32 DEFAULT 1 COMMENT '玩家等级',

    `player_vip_level` UInt32 DEFAULT 0 COMMENT '玩家vip等级',

    `player_fight` UInt64 DEFAULT 0 COMMENT '玩家战力',

    `player_charge_money` UInt32 DEFAULT 0 COMMENT '玩家累积充值金额',

    `player_first_charge_time` UInt64 DEFAULT 0 COMMENT '玩家首充时间',

    `player_first_charge_money` UInt64 DEFAULT 0 COMMENT '玩家首充金额',

    `player_open_day_charge_money` UInt64 DEFAULT 0 COMMENT '玩家开服首日充值金额',

    `player_day_charge_money` UInt32 DEFAULT 0 COMMENT '当日充值金额',

    `player_diamond` UInt64 DEFAULT 0 COMMENT '玩家剩余钻石',

    `player_bind_diamond` UInt64 DEFAULT 0 COMMENT '玩家剩余绑钻',

    `player_gold` UInt64 DEFAULT 0 COMMENT '玩家剩余金币',

    `is_roll` UInt8 DEFAULT 0 COMMENT '是否滚服',

    `login_times` UInt8 DEFAULT 0 COMMENT '当天登录次数',

    `game_mod_open_time` String COMMENT '玩法开启时间',

    `max_active_score` UInt32 DEFAULT 0 COMMENT '历史最高活跃积分',

    `map_explore` String DEFAULT 0 COMMENT '地图探索',

    `player_day_online_time` UInt64 DEFAULT 0 COMMENT '当天在线时长(秒)'
)
ENGINE = ReplacingMergeTree(create_time)
PARTITION BY toYYYYMMDD(fromUnixTimestamp(report_time))
ORDER BY (game_id,
 log_type,
 report_time,
 group_id,
 player_create_server_id,
 player_id)
SETTINGS index_granularity = 8192;


-- `2001_log_metadata`.server_monitor_log definition

CREATE TABLE `2001_log_metadata`.server_monitor_log
(

    `create_time` UInt32 DEFAULT toUnixTimestamp(now()) COMMENT '入库创建时间',

    `log_date` Date DEFAULT toDate(log_time / 1000) COMMENT '日期',

    `log_date_time` DateTime DEFAULT toDateTime(log_time / 1000,
 'Asia/Shanghai') COMMENT '日期时间',

    `log_time` UInt64 COMMENT '时间日期',

    `ip` String COMMENT 'ip地址',

    `game_id` UInt16 COMMENT '游戏标识',

    `node_id` UInt64 COMMENT '节点id',

    `platform` UInt32 COMMENT '渠道专服id',

    `version` UInt64 COMMENT '服务端版本号',

    `log_type` String COMMENT '日志类型',

    `group` UInt32 COMMENT '区服编号 (server_num)',

    `server_type` UInt32 COMMENT '服务类型',

    `server_id` UInt64 COMMENT 'server_id',

    `stack` String COMMENT '堆栈报错信息',

    `stack_md5` String COMMENT '堆栈md5',

    `text` String COMMENT '报错信息',

    `cpu` Float32,

    `rss` UInt32,

    `rss_per` Float32,

    `heap` UInt32,

    `heap_total` UInt32,

    `heap_per` Float32,

    `native_contexts` UInt32,

    `detached_contexts` UInt32,

    `ext` UInt32,

    `files` String COMMENT '文件地址',

    `bg_md5` String COMMENT '后台md5',

    `local_md5` String COMMENT '远程md5',

    `hotupdate_type` String COMMENT '热更类型',

    `command` String COMMENT '指令',

    `player_id` UInt64 COMMENT '被操作玩家id',

    `code` UInt32 COMMENT '错误码',

    `version_config` String COMMENT '配置文件svn版本',

    `version_svn` String COMMENT '服务端代码脚本svn版本',

    `player_count` UInt16 DEFAULT 0 COMMENT '玩家数'
)
ENGINE = MergeTree
PARTITION BY toYYYYMMDD(log_date)
ORDER BY (game_id,
 log_type,
 log_time,
 node_id,
 server_id)
SETTINGS index_granularity = 8192;