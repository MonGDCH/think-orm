<?php

declare(strict_types=1);

namespace mon\thinkORM;

use think\Facade;

/**
 * 接管think\facade\Db
 * 
 * @method static \think\db\Query master() 从主服务器读取数据
 * @method static \think\db\Query readMaster(bool $all = false) 后续从主服务器读取数据
 * @method static \think\db\Query table(string $table) 指定数据表（含前缀）
 * @method static \think\db\Query name(string $name) 指定数据表（不含前缀）
 * @method static \think\db\Query where(mixed $field, string $op = null, mixed $condition = null) 查询条件
 * @method static \think\db\Query whereOr($field, $op = null, $condition = null) 指定OR查询条件
 * @method static \think\db\Query whereExp(string $field, string $condition, array $bind = []) 字段表达式查询
 * @method static \think\db\Query join(mixed $join, mixed $condition = null, string $type = 'INNER') JOIN查询
 * @method static \think\db\Query view(mixed $join, mixed $field = null, mixed $on = null, string $type = 'INNER') 视图查询
 * @method static \think\db\Query field(mixed $field, boolean $except = false) 指定查询字段
 * @method static \think\db\Query fieldRaw(string $field, array $bind = []) 指定查询字段
 * @method static \think\db\Query union(mixed $union, boolean $all = false) UNION查询
 * @method static \think\db\Query limit(mixed $offset, integer $length = null) 查询LIMIT
 * @method static \think\db\Query order(mixed $field, string $order = null) 查询ORDER
 * @method static \think\db\Query group($group) 指定group查询
 * @method static \think\db\Query having(string $having) 指定having查询
 * @method static \think\db\Query distinct(bool $distinct = true) 指定distinct查询
 * @method static \think\db\Query force(string $force) 指定强制索引
 * @method static \think\db\Query replace(bool $replace = true) 设置是否REPLACE
 * @method static \think\db\Query partition($partition) 设置当前查询所在的分区
 * @method static \think\db\Query extra($extra) 设置extra
 * @method static \think\db\Query duplicate($duplicate) 设置DUPLICATE
 * @method static \think\db\Query cache(mixed $key = null , integer $expire = null) 设置查询缓存
 * @method static \think\db\Query procedure(bool $procedure = true) 是否存储过程调用
 * @method static \think\db\Query inc(string $field, float $step = 1) 字段值增长
 * @method static \think\db\Query dec(string $field, float $step = 1) 字段值减少
 * @method static \think\db\Query lock($lock = false) 指定查询lock
 * @method static \think\db\Query alias($alias) 指定数据表别名
 * @method static \think\db\Query json(array $json = [], bool $assoc = false) 设置JSON字段信息
 * 
 * @method static string getLastSql() 获取最近一次查询的sql语句
 * @method static string buildSql(bool $sub = true) 创建子查询SQL
 * @method static mixed value(string $field) 获取某个字段的值
 * @method static array column(string $field, string $key = '') 获取某个列的值
 * @method static mixed find(mixed $data = null) 查询单个记录
 * @method static mixed select(mixed $data = null) 查询多个记录
 * @method static integer insert(array $data = [], boolean $getLastInsID = false) 插入一条记录
 * @method static integer insertGetId(array $data) 插入一条记录并返回自增ID
 * @method static integer insertAll(array $dataSet = [], integer $limit = 0) 插入多条记录
 * @method static integer update(array $data) 更新记录
 * @method static integer delete(mixed $data = null) 删除记录
 * @method static boolean chunk(integer $count, callable $callback, string $column = null) 分块获取数据
 * @method static \Generator cursor(mixed $data = null) 使用游标查找记录
 * @method static mixed query(string $sql, array $bind = [], boolean $master = false, bool $pdo = false) SQL查询
 * @method static integer execute(string $sql, array $bind = [], boolean $fetch = false, boolean $getLastInsID = false, string $sequence = null) SQL执行
 * @method static \think\Paginator paginate(integer $listRows = 15, mixed $simple = null, array $config = []) 分页查询
 * @method static mixed transaction(callable $callback) 执行数据库事务
 * @method static void startTrans() 启动事务
 * @method static void commit() 用于非自动提交状态下面的查询提交
 * @method static void rollback() 事务回滚
 * @method static boolean batchQuery(array $sqlArray) 批处理执行SQL语句
 * @method static mixed getLastInsID(string $sequence = null) 获取最近插入的ID
 * 
 * @method static void setConfig(array $config) 初始化配置参数
 * @method static void setCache(\Psr\SimpleCache\CacheInterface $cache) 设置缓存对象
 * @method static void setLog(\Psr\Log\LoggerInterface $log) 设置日志对象
 * @method static void log(string $log, string $type = 'sql') 记录SQL日志
 * @method static array getDbLog(bool $clear = false) 获得查询日志（没有设置日志对象使用）
 * @method static mixed getConfig(string $name = '', $default = null) 获取配置参数
 * @method static \think\db\PDOConnection connect(string $name = null, bool $force = false) 创建/切换数据库连接查询
 * @method static \think\db\Raw raw(string $value) 使用表达式设置数据
 * @method static void listen(callable $callback) 监听SQL执行
 * @method static array getListen() 获取监听SQL执行
 * @method static array getInstance() 获取所有连接实列
 * @method static void event(string $event, callable $callback) 注册回调方法
 * @method static void trigger(string $event, $params = null) 触发事件
 * @method static \PDO getPdo() 获取PDO对象
 * @method static string|array getPk() 获取主键
 */
class Db extends Facade
{
    /**
     * 获取当前Facade对应类名（或者已经绑定的容器对象标识）
     * @access protected
     * @return string
     */
    protected static function getFacadeClass()
    {
        return \mon\thinkORM\extend\DbManager::class;
    }
}
