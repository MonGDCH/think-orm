# mon-think-orm

封装 think-orm，扩展自定义查询类及 DB 类，优化业务操作。

- 优化 IDE 提示（含 `_ide_helper.php` 静态注解支持）
- 增加 `Dao` 对象，简化 ORM 操作，内置验证器、批量更新和批量增量操作，支持修改器、获取器等功能
- 支持 `workerman` 等常驻进程环境（长连接心跳、SQL 日志内存回收）
- 支持 `mongdch/mon-http` 环境下的数据库中间件（SQL 日志清理、事务安全校验）

## 环境要求

- PHP >= 8.0
- topthink/think-orm >= 3.0
- mongdch/mon-util、mon-log、mon-cache

> 注意：本扩展依赖 think-orm 3.x 的 `parseUpdateData(): bool` 行为，请勿与 think-orm 2.x 混用。

## 安装

```bash
composer require mongdch/think-orm
```

## 快速开始

### 手动注册（非 Gaia 框架环境）

```php
use mon\thinkORM\Orm;

// 读取 config/database.php 配置并初始化（支持传入自定义配置数组）
Orm::register(false, require __DIR__ . '/database.php');
```

`register()` 签名：

```php
Orm::register(
    bool $longLink = false,                  // 是否开启长连接心跳（依赖 workerman/Timer）
    array $config = [],                      // 数据库配置，为空则读取 mon\env\Config 的 database 节点
    ?LoggerInterface $log = null,            // SQL 日志驱动，默认 mon\log\Logger
    ?CacheInterface $cache = null,           // 查询缓存驱动，默认 support\cache\CacheService
    int $timer = 50                          // 长连接心跳间隔（秒）
);
```

### Gaia 框架

安装后执行发布命令生成配置文件：

```bash
php gaia vendor:publish mon\thinkORM
```

## 数据库配置

配置结构基于 think-orm（`config/database.php`）：

```php
return [
    // 默认数据库连接标识
    'default' => 'mysql',
    // 数据库连接信息
    'connections' => [
        'mysql' => [
            'type'            => 'mysql',    // 数据库类型：mysql/sqlite/pgsql/sqlsrv/mongo/oracle
            'hostname'        => '127.0.0.1',
            'database'        => '',         // 数据库名（SQLite 为文件路径）
            'username'        => '',
            'password'        => '',
            'hostport'        => 3306,
            'charset'         => 'utf8mb4',
            'prefix'          => '',         // 表前缀
            'deploy'          => 0,          // 0 集中式 1 分布式
            'rw_separate'     => false,      // 读写分离（主从有效）
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,       // 严格检查字段是否存在
            'break_reconnect' => true,       // 断线重连，常驻进程建议开启
            'trigger_sql'     => false,      // SQL 监听，常驻进程长期运行建议关闭，防止爆内存
            'query'           => \mon\thinkORM\extend\Query::class, // 自定义查询类，必须保留以支持 Dao
        ],
    ],
];
```

多连接配置时，可在 Dao 中通过 `$connection` 属性指定连接节点。

## 定义 Dao

继承 `mon\thinkORM\Dao` 并指定表名即可使用：

```php
use mon\thinkORM\Dao;
use mon\util\Instance;

class LogDao extends Dao
{
    use Instance; // 可选：单例支持

    /**
     * 操作表名（必填）
     * @var string
     */
    protected $table = 'admin';

    /**
     * 数据库连接配置节点（可选，默认 default 连接）
     * @var string
     */
    protected $connection = '';

    /**
     * 自动写入时间戳（默认关闭）
     * 开启后 insert 写入 create_time + update_time，update 写入 update_time
     * @var boolean
     */
    protected $autoWriteTimestamp = true;

    /**
     * 时间字段名与格式（可覆盖默认值）
     */
    protected $createTime    = 'create_time';
    protected $updateTime    = 'update_time';
    protected $autoTimeFormat = 'Y-m-d H:i:s'; // 置为 null 则写入时间戳

    /**
     * 新增操作自动完成字段
     * 支持两种写法：'字段名'（配合下方 setXxxAttr 修改器）或 '字段名' => 默认值
     * @var array
     */
    protected $insert = ['status' => 1];

    /**
     * 更新操作自动完成字段
     * @var array
     */
    protected $update = ['update_uid' => 0];

    /**
     * 查询后追加字段（配合 getXxxAttr 获取器）
     * @var array
     */
    protected $append = ['level_text'];

    /**
     * 只读字段，更新操作中强制剔除
     * @var array
     */
    protected $readonly = ['create_time'];

    // 修改器：写入 status 字段时触发（仅对配置在 $insert/$update 中的字段生效）
    protected function setStatusAttr($value, $data)
    {
        return (int) $value;
    }

    // 获取器：查询结果加工（format 读取时触发）
    protected function getLevelTextAttr($value, $data)
    {
        return [1 => '普通', 2 => 'VIP'][$data['level']] ?? '未知';
    }

    // 查询场景（配合 scope() 使用）
    protected function scopeStatus($query, $status = 1)
    {
        return $query->where('status', $status);
    }
}
```

> 注意：修改器（`setXxxAttr`）只对配置在 `$insert` / `$update` 自动完成列表中的字段生效，未列入的普通字段不会经过修改器处理。

## Dao 基础用法

```php
$dao = LogDao::instance();

// 查询：get 单条，all 多条；$format 为 true 时经过获取器/追加字段加工
$one   = $dao->get();                        // 单条原始数据
$oneF  = $dao->get(true, ['id' => 1]);       // 单条 + 获取器加工
$list  = $dao->all(true, ['status' => 1]);   // 多条 + 加工

// 链式查询（__call 代理到 Query 对象，完整支持 think-orm 查询语法）
$data = $dao->where('level', '>', 1)->order('id', 'desc')->limit(10)->all(true);
$data = $dao->scope('status', 2)->all();     // 查询场景，也支持传入闭包

// 保存：无 where 且数据不含主键时 insert；有 where 或含主键时 update
$dao->save(['name' => 'tom']);                        // insert
$dao->save(['id' => 1, 'name' => 'tom']);             // update（主键自动转 where）
$dao->where('id', 1)->save(['name' => 'tom']);        // update
$dao->save(['name' => 'tom'], true, true);            // 强制 insert 并返回自增ID

// 批量写入（自动应用 insert 自动完成 + 自动时间戳）
$dao->saveAll([
    ['name' => 'a'],
    ['name' => 'b'],
], 100); // 第二个参数为分批写入大小，0 表示一次写入

// 字段白名单（写入时只保留允许字段，消费一次后自动重置）
$dao->allowField(['name', 'level'])->save($input);

// 删除
$dao->where('id', 1)->delete();

// 原生 SQL
$dao->query('SELECT * FROM admin WHERE id = ?', [1]);        // 查询
$dao->execute('UPDATE admin SET level = 2 WHERE id = ?', [1]); // 写入

// 事务（支持嵌套）
$dao->transaction(function ($query) use ($dao) {
    $dao->save(['name' => 'a']);
    $dao->where('id', 1)->save(['level' => 2]);
});

// 获取错误信息（由业务逻辑写入，getError 读取后自动清空）
$error = $dao->getError();
```

### 通过 Query 对象操作（绑定 Dao）

自定义查询类 `mon\thinkORM\extend\Query` 实现了 `DaoQuery` 接口，在未定义 Dao 的场景也可直接使用：

```php
use mon\thinkORM\Db;

// 以下方式自动识别 insert / update（数据含主键时转 where）
Db::table('admin')->where('id', 1)->save(['level' => 2]);
Db::table('admin')->save(['name' => 'tom']); // insert

// 绑定 Dao 后可用 get / all / saveAll
Db::table('admin')->get();
```

> `allowField()` 在查询对象未绑定 Dao 时会被静默忽略。

## 批量更新与批量增量

基于 `CASE WHEN` 语句实现，单条 SQL 更新多行（默认按 `id` 主键，500 条一批）：

```php
$dao = LogDao::instance();

// 批量更新字段值
$dao->batchUpdate([
    ['id' => 1, 'deadline' => 9],
    ['id' => 2, 'deadline' => 50],
]);

// 批量增量（默认累加，支持 + - * /）
$dao->batchIncrement([
    ['id' => 1, 'score' => 10],   // score += 10
    ['id' => 2, 'stock' => -1],   // stock -= 1
], ['score' => '+', 'stock' => '-']);

// 完整签名
// batchUpdate(array $updateData, string $key = 'id', int $batchSize = 500, string $where = '', string $table = '')
// batchIncrement(array $data, array $operators = [], string $key = 'id', int $batchSize = 500, string $where = '', string $table = '')
```

> 安全提示：`$where` 参数为原生 SQL 拼接（如 `'status = 1'`），请勿传入用户输入；主键值必须为服务端可信数据（本扩展会对其做类型校验）。字段值已经过 `PDO::quote` 转义处理。

## 验证器

`Dao` 内置验证器支持，基于 `mon\util\Validate`。验证器不会自动执行，需在业务侧显式调用：

```php
class LogDao extends Dao
{
    protected $table = 'admin';

    // 可自定义验证器类，默认 mon\util\Validate
    // protected $validate = \app\validate\AdminValidate::class;
}

// 使用
$validate = LogDao::instance()->validate();
$result = $validate->data($input)->rule([
    'name' => 'require|length:1,20',
])->check();
```

## 常驻进程支持（workerman 等）

```php
// onWorkerStart 中注册
Orm::register(true); // 开启长连接心跳，默认 50 秒 ping 一次所有连接
```

心跳机制：

- 定期对所有连接执行 `SELECT 1`（Oracle 为 `SELECT 1 FROM DUAL`，Mongo 为 ping 命令）防止连接被服务端断开
- 定期清空 SQL 日志内存，防止 `trigger_sql` 开启时爆内存

## mon-http 中间件

在 `mongdch/mon-http` 环境下注册 `ORMMiddleware`，可在每次请求结束后：

- 清理本次请求产生的 SQL 日志
- 检查并回滚未提交的事务（常驻进程下防止事务状态污染连接池）

```php
use mon\thinkORM\ORMMiddleware;

// 加入中间件列表即可
$middleware[] = ORMMiddleware::class;
```

## Db 门面

`mon\thinkORM\Db` 接管了 `think\facade\Db`，支持完整的 think-orm 查询语法与扩展方法：

```php
use mon\thinkORM\Db;

Db::setConfig($config);                 // 初始化配置
Db::connect('mysql2')->table('user')->select(); // 多连接切换
Db::startTrans();                       // 事务
Db::getLastSql();                       // 调试
Db::getDbLog(true);                     // 获取并清空 SQL 日志（未注入日志驱动时）
Db::raw('COUNT(*)');                    // 表达式
```

## IDE 提示

对于 Laravel/IDE 等无法识别 `__callStatic` 的场景，可引入 `src/_ide_helper.php`（仅为 `think\facade\Db` 提供静态注解，无运行时逻辑），或直接依赖 `mon\thinkORM\Db` 与 `Dao` 头部的方法注解获得提示。

## 已知注意事项

1. **think-orm 版本**：仅支持 think-orm 3.x，2.x 下 `Query::isUpdate()` 会产生类型错误。
2. **`allow` 状态消费**：`allowField()` 设置的白名单在 `save()`/`saveAll()` 执行后立即重置；若写入过程抛出异常则不会重置，单例复用 Dao 时请注意重新设置。
3. **`resultset_type`**：`Dao::get()`/`all()` 按数组结果设计，请保持默认 `resultset_type` 配置（array），配置为 collection 时返回值类型会变化。
4. **心跳依赖**：`Orm::register(true)` / `Orm::heart()` 依赖 `workerman\Timer`，非 workerman 环境请传 `false` 并自行维护连接保活。
5. **验证器非自动**：`validate()` 仅提供验证器实例，不会在写入前自动校验。
6. **日志依赖**：`Orm::register()` 未显式传入 `$log` 时会回退到 `mon\log\Logger` 的 default 通道，请确保已初始化 mon-log，或显式传入实现了 `Psr\Log\LoggerInterface` 的日志对象。

## License

MIT
