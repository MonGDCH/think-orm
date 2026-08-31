<?php

declare(strict_types=1);

namespace mon\thinkORM;

use Closure;
use RuntimeException;
use mon\thinkORM\extend\Query;
use mon\thinkORM\concern\AutoAttr;
use mon\thinkORM\concern\AutoTime;
use mon\thinkORM\concern\Validator;
use mon\thinkORM\contract\DaoQuery;
use mon\thinkORM\concern\BatchUpdate;

/**
 * Db操作Dao对象
 * 
 * @method Query master() 从主服务器读取数据
 * @method Query readMaster(bool $all = false) 后续从主服务器读取数据
 * @method Query table(string $table) 指定数据表（含前缀）
 * @method Query name(string $name) 指定数据表（不含前缀）
 * @method Query page(int $page, int $listRows = null)  指定分页
 * @method Query where(mixed $field, string $op = null, mixed $condition = null) 查询条件
 * @method Query whereIn(mixed $field, array $condition) 指定IN查询条件
 * @method Query whereNotIn(mixed $field, array $condition) 指定NOT NOT IN查询条件
 * @method Query whereOr($field, $op = null, $condition = null) 指定OR查询条件
 * @method Query whereExp(string $field, string $condition, array $bind = []) 字段表达式查询
 * @method Query join(mixed $join, mixed $condition = null, string $type = 'INNER') JOIN查询
 * @method Query view(mixed $join, mixed $field = null, mixed $on = null, string $type = 'INNER') 视图查询
 * @method Query field(mixed $field, boolean $except = false) 指定查询字段
 * @method Query fieldRaw(string $field, array $bind = []) 指定查询字段
 * @method Query union(mixed $union, boolean $all = false) UNION查询
 * @method Query limit(mixed $offset, integer $length = null) 查询LIMIT
 * @method Query order(mixed $field, string $order = null) 查询ORDER
 * @method Query group($group) 指定group查询
 * @method Query having(string $having) 指定having查询
 * @method Query distinct(bool $distinct = true) 指定distinct查询
 * @method Query force(string $force) 指定强制索引
 * @method Query replace(bool $replace = true) 设置是否REPLACE
 * @method Query partition($partition) 设置当前查询所在的分区
 * @method Query extra($extra) 设置extra
 * @method Query duplicate($duplicate) 设置DUPLICATE
 * @method Query cache(mixed $key = null , integer $expire = null) 设置查询缓存
 * @method Query procedure(bool $procedure = true) 是否存储过程调用
 * @method Query inc(string $field, float $step = 1) 字段值增长
 * @method Query dec(string $field, float $step = 1) 字段值减少
 * @method Query lock($lock = false) 指定查询lock
 * @method Query alias($alias) 指定数据表别名
 * @method Query json(array $json = [], bool $assoc = false) 设置JSON字段信息
 * 
 * @method string getLastSql() 获取最近一次查询的sql语句
 * @method string buildSql(bool $sub = true) 创建子查询SQL
 * @method mixed value(string $field) 获取某个字段的值
 * @method array column(string $field, string $key = '') 获取某个列的值
 * @method mixed find(mixed $data = null) 查询单个记录
 * @method mixed select(mixed $data = null) 查询多个记录
 * @method integer insert(array $data, boolean $replace = false, boolean $getLastInsID = false, string $sequence = null) 插入一条记录
 * @method integer insertGetId(array $data, boolean $replace = false, string $sequence = null) 插入一条记录并返回自增ID
 * @method integer insertAll(array $dataSet) 插入多条记录
 * @method integer update(array $data) 更新记录
 * @method integer delete(mixed $data = null) 删除记录
 * @method boolean chunk(integer $count, callable $callback, string $column = null) 分块获取数据
 * @method \Generator cursor(mixed $data = null) 使用游标查找记录
 * @method mixed transaction(callable $callback) 执行数据库事务
 * @method void startTrans() 启动事务
 * @method void commit() 用于非自动提交状态下面的查询提交
 * @method void rollback() 事务回滚
 * @method boolean batchQuery(array $sqlArray) 批处理执行SQL语句
 * @method mixed getLastInsID(string $sequence = null) 获取最近插入的ID
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
abstract class Dao
{
    use Validator, AutoTime, AutoAttr, BatchUpdate;

    /**
     * 操作表名
     *
     * @var string
     */
    protected $table = '';

    /**
     * 链接配置节点
     *
     * @var string
     */
    protected $connection = '';

    /**
     * 只允许操作的字段
     *
     * @var array
     */
    protected $allow = [];

    /**
     * 错误信息
     *
     * @var mixed
     */
    protected $error = null;

    /**
     * 获取Dao操作表名
     *
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * 获取Dao操作链接配置节点
     *
     * @return string
     */
    public function getConnection(): string
    {
        return $this->connection;
    }

    /**
     * 获取错误信息
     *
     * @return mixed 错误信息
     */
    public function getError()
    {
        $error = $this->error;
        $this->error = null;
        return $error;
    }

    /**
     * 获取DB实例
     *
     * @throws RuntimeException
     * @return Query
     */
    public function db(): Query
    {
        if (!$this->getTable()) {
            throw new RuntimeException('Dao对象数据表名[table]属性必须');
        }
        if ($this->getConnection()) {
            $db = Db::connect($this->getConnection())->table($this->getTable());
        } else {
            $db = Db::table($this->getTable());
        }
        // 绑定Dao对象
        if (!is_a($db, DaoQuery::class)) {
            throw new RuntimeException('Query查询对象必须实现[' . DaoQuery::class . ']接口，可配置自定义查询类[' . Query::class . ']实现');
        }

        return $db->dao($this);
    }

    /**
     * 执行查询 返回数据集
     *
     * @param string  $sql    sql指令
     * @param array   $bind   参数绑定
     * @param boolean $master 主库读取
     * @return array
     */
    public function query(string $sql, array $bind = [], bool $master = false): array
    {
        if ($this->getConnection()) {
            return Db::connect($this->getConnection())->query($sql, $bind, $master);
        }

        return Db::query($sql, $bind, $master);
    }

    /**
     * 执行语句
     *
     * @param string $sql  sql指令
     * @param array  $bind 参数绑定
     * @return integer
     */
    public function execute(string $sql, array $bind = []): int
    {
        if ($this->getConnection()) {
            return Db::connect($this->getConnection())->execute($sql, $bind);
        }

        return Db::execute($sql, $bind);
    }

    /**
     * 设置查询场景, 相当于查询前置条件
     *
     * @param  string|Closure $name 场景名称或者闭包函数
     * @param  mixed $args          可变传参
     * @throws RuntimeException
     * @return Query                查询对象实例
     */
    public function scope($name, ...$args): Query
    {
        // 固定第一个参数为Db实例
        array_unshift($args, $this->db());
        if ($name instanceof Closure) {
            return call_user_func_array($name, (array) $args);
        }
        $method = 'scope' . ucfirst($name);
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], (array) $args);
        }
        throw new RuntimeException("查询场景不存[{$method}]");
    }

    /**
     * 允许新增或修改的字段
     *
     * @param array $field  只允许操作的字段列表
     * @return Dao
     */
    public function allowField(array $field): Dao
    {
        $this->allow = $field;
        return $this;
    }

    /**
     * 获取一条数据
     *
     * @param  Query    $query  查询对象实例
     * @param  array    $where  where条件
     * @param  boolean  $format 是否加工处理数据
     * @return array
     */
    public function get(bool $format = false, array $where = [], ?Query $query = null): array
    {
        // 获取查询对象实例
        if (!$query) {
            $query = $this->db();
        }
        $data = $query->where($where)->findOrEmpty();
        // 查询为空，直接返回空数组
        if (!$data) {
            return [];
        }

        return $format ? $this->getDaoData($data) : $data;
    }

    /**
     * 获取多条数据
     *
     * @param  boolean  $format 是否加工处理数据
     * @param  array    $where  where条件
     * @param  Query    $query  查询对象实例
     * @return array
     */
    public function all(bool $format = false, array $where = [], ?Query $query = null): array
    {
        // 获取查询对象实例
        if (!$query) {
            $query = $this->db();
        }
        $data = $query->where($where)->select()->toArray();
        if (!$data) {
            return $data;
        }

        $result = [];
        if ($format) {
            foreach ($data as $k => $value) {
                $result[$k] = $this->getDaoData($value);
            }
        }

        return $format ? $result : $data;
    }

    /**
     * 保存数据
     *
     * @param  array    $data           操作数据
     * @param boolean   $forceInsert    是否强制insert
     * @param  boolean  $getLastInsID   insert操作下是否返回自增ID
     * @param  Query    $query          查询对象实例
     * @return integer|string  影响行数或自增iD
     */
    public function save(array $data, bool $forceInsert = false, bool $getLastInsID = false, ?Query $query = null)
    {
        // 过滤允许操作的字段
        if (!empty($this->allow)) {
            $allowData = [];
            foreach ($this->allow as $field) {
                if (array_key_exists($field, $data)) {
                    $allowData[$field] = $data[$field];
                }
            }
            // 重置操作数据
            $data = $allowData;
            $this->allow = [];
        }
        // 获取查询对象实例
        if (!$query) {
            $query = $this->db();
        }

        if ($forceInsert) {
            return $this->insertData($data, $getLastInsID, $query);
        }

        return $query->isUpdate() ? $this->updateData($data, $query) : $this->insertData($data, $getLastInsID, $query);
    }

    /**
     * 批量写入数据
     *
     * @param array     $data   操作数据
     * @param integer   $limit  每次写入数据限制
     * @param mixed     $query  查询对象实例
     * @return integer 影响行数
     */
    public function saveAll(array $data, int $limit = 0, ?Query $query = null): int
    {
        $autoTimeData = $this->getAutoTimeData(true);
        foreach ($data as $k => $item) {
            // 过滤允许操作的字段
            if (!empty($this->allow)) {
                $allowData = [];
                foreach ($this->allow as $field) {
                    if (array_key_exists($field, $item)) {
                        $allowData[$field] = $item[$field];
                    }
                }
                // 重置操作数据
                $item = $allowData;
            }

            $data[$k] = array_merge($this->autoCompleteData($this->insert, $item), $autoTimeData);
        }
        $this->allow = [];
        // 获取查询对象实例
        if (!$query) {
            $query = $this->db();
        }

        return $query->insertAll($data, $limit);
    }

    /**
     * 动态调用
     * 
     * @param  string $method 回调方法
     * @param  mixed $args   参数
     * @return mixed
     */
    public function __call(string $method, $args)
    {
        return call_user_func_array([$this->db(), $method], (array) $args);
    }

    /**
     * 静态调用
     *
     * @param  string $method 回调方法
     * @param  mixed $args   参数
     * @return mixed
     */
    public static function __callStatic(string $method, $args)
    {
        return call_user_func_array([(new static())->db(), $method], (array) $args);
    }
}
