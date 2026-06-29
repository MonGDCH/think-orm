<?php

declare(strict_types=1);

namespace mon\thinkORM\extend;

use think\db\ConnectionInterface;

/**
 * 自定义扩展的Db实体类
 * 
 * @method \think\db\Query master() 从主服务器读取数据
 * @method \think\db\Query readMaster(bool $all = false) 后续从主服务器读取数据
 * @method \think\db\Query table(string $table) 指定数据表（含前缀）
 * @method \think\db\Query name(string $name) 指定数据表（不含前缀）
 * @method \think\db\Query where(mixed $field, string $op = null, mixed $condition = null) 查询条件
 * @method \think\db\Query whereOr($field, $op = null, $condition = null) 指定OR查询条件
 * @method \think\db\Query whereExp(string $field, string $condition, array $bind = []) 字段表达式查询
 * @method \think\db\Query when(mixed $condition, mixed $query, mixed $otherwise = null) 条件查询
 * @method \think\db\Query join(mixed $join, mixed $condition = null, string $type = 'INNER') JOIN查询
 * @method \think\db\Query view(mixed $join, mixed $field = null, mixed $on = null, string $type = 'INNER') 视图查询
 * @method \think\db\Query field(mixed $field, boolean $except = false) 指定查询字段
 * @method \think\db\Query fieldRaw(string $field, array $bind = []) 指定查询字段
 * @method \think\db\Query union(mixed $union, boolean $all = false) UNION查询
 * @method \think\db\Query limit(mixed $offset, integer $length = null) 查询LIMIT
 * @method \think\db\Query order(mixed $field, string $order = null) 查询ORDER
 * @method \think\db\Query group($group) 指定group查询
 * @method \think\db\Query having(string $having) 指定having查询
 * @method \think\db\Query distinct(bool $distinct = true) 指定distinct查询
 * @method \think\db\Query force(string $force) 指定强制索引
 * @method \think\db\Query replace(bool $replace = true) 设置是否REPLACE
 * @method \think\db\Query partition($partition) 设置当前查询所在的分区
 * @method \think\db\Query extra($extra) 设置extra
 * @method \think\db\Query duplicate($duplicate) 设置DUPLICATE
 * @method \think\db\Query cache(mixed $key = null , integer $expire = null) 设置查询缓存
 * @method \think\db\Query procedure(bool $procedure = true) 是否存储过程调用
 * @method \think\db\Query inc(string $field, float $step = 1) 字段值增长
 * @method \think\db\Query dec(string $field, float $step = 1) 字段值减少
 * @method \think\db\Query lock($lock = false) 指定查询lock
 * @method \think\db\Query alias($alias) 指定数据表别名
 * @method \think\db\Query json(array $json = [], bool $assoc = false) 设置JSON字段信息
 * 
 * @method string getLastSql() 获取最近一次查询的sql语句
 * @method string buildSql(bool $sub = true) 创建子查询SQL
 * @method mixed value(string $field) 获取某个字段的值
 * @method array column(string $field, string $key = '') 获取某个列的值
 * @method mixed find(mixed $data = null) 查询单个记录
 * @method mixed select(mixed $data = null) 查询多个记录
 * @method integer insert(array $data, boolean $replace = false, boolean $getLastInsID = false, string $sequence = null) 插入一条记录
 * @method integer insertGetId(array $data, boolean $replace = false, string $sequence = null) 插入一条记录并返回自增ID
 * @method integer insertAll(array $dataSet, int $limit = 0) 插入多条记录
 * @method integer update(array $data) 更新记录
 * @method integer delete(mixed $data = null) 删除记录
 * @method boolean chunk(integer $count, callable $callback, string $column = null) 分块获取数据
 * @method \Generator cursor(mixed $data = null) 使用游标查找记录
 * @method mixed query(string $sql, array $bind = [], boolean $master = false, bool $pdo = false) SQL查询
 * @method integer execute(string $sql, array $bind = [], boolean $fetch = false, boolean $getLastInsID = false, string $sequence = null) SQL执行
 * @method \think\Paginator paginate(integer $listRows = 15, mixed $simple = null, array $config = []) 分页查询
 * @method mixed transaction(callable $callback) 执行数据库事务
 * @method void startTrans() 启动事务
 * @method void commit() 用于非自动提交状态下面的查询提交
 * @method void rollback() 事务回滚
 * @method boolean batchQuery(array $sqlArray) 批处理执行SQL语句
 * @method mixed getLastInsID(string $sequence = null) 获取最近插入的ID
 * 
 * @method void setConfig(array $config) 初始化配置参数
 * @method void setCache(\Psr\SimpleCache\CacheInterface $cache) 设置缓存对象
 * @method void setLog(\Psr\Log\LoggerInterface $log) 设置日志对象
 * @method void log(string $log, string $type = 'sql') 记录SQL日志
 * @method array getDbLog(bool $clear = false) 获得查询日志（没有设置日志对象使用）
 * @method mixed getConfig(string $name = '', $default = null) 获取配置参数
 * @method \think\db\PDOConnection connect(string $name = null, bool $force = false) 创建/切换数据库连接查询
 * @method \think\db\Raw raw(string $value) 使用表达式设置数据
 * @method void listen(callable $callback) 监听SQL执行
 * @method array getListen() 获取监听SQL执行
 * @method array getInstance() 获取所有连接实列
 * @method void event(string $event, callable $callback) 注册回调方法
 * @method void trigger(string $event, $params = null) 触发事件
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class DbManager extends \think\DbManager
{
    /**
     * 通过动态配置数组链接数据库
     *
     * @param array $config     配置信息
     * @param boolean $force    是否强制重连
     * @param string|null $name 配置名，不传则自动生成
     * @return ConnectionInterface
     */
    public function configConnect(array $config, bool $force = false, ?string $name = null): ConnectionInterface
    {
        $name = is_null($name) ? md5(serialize($config)) : $name;
        if (!$force && isset($this->instance[$name])) {
            return $this->instance[$name];
        }

        $type = !empty($config['type']) ? $config['type'] : 'mysql';
        if (false !== strpos($type, '\\')) {
            $class = $type;
        } else {
            $class = '\\think\\db\\connector\\' . ucfirst($type);
        }

        /** @var ConnectionInterface $connection */
        $connection = new $class($config);
        $connection->setDb($this);
        if ($this->cache) {
            $connection->setCache($this->cache);
        }

        $this->instance[$name] = $connection;
        return $this->instance[$name];
    }
}
