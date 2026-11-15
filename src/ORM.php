<?php

declare(strict_types=1);

namespace mon\thinkORM;

use Throwable;
use RuntimeException;
use mon\env\Config;
use mon\log\Logger;
use mon\thinkORM\Db;
use Workerman\Timer;
use MongoDB\Driver\Command;
use Psr\Log\LoggerInterface;
use support\cache\CacheService;
use Psr\SimpleCache\CacheInterface;

/**
 * ORM注册使用工具
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class ORM
{
    /**
     * 心跳定时器ID，用于避免重复注册
     *
     * @var mixed
     */
    protected static $heartTimerId = null;

    /**
     * 日志驱动
     *
     * @var LoggerInterface|null
     */
    protected static $logger = null;

    /**
     * 心跳检测失败次数，按连接名称统计
     *
     * @var array
     */
    protected static $heartFailTimes = [];

    /**
     * 注册ORM
     *
     * @param boolean $longLink 是否长链接
     * @param array $config     数据库配置
     * @param LoggerInterface|null $log 记录日志驱动
     * @param CacheInterface|null $cache    缓存驱动
     * @param integer $timer    长链接轮询时间间隔
     * @return void
     */
    public static function register(bool $longLink = false, array $config = [], ?LoggerInterface $log = null, ?CacheInterface $cache = null, int $timer = 50)
    {
        // 定义配置
        $config = $config ?: self::defaultConfig();
        if (empty($config)) {
            throw new RuntimeException('数据库配置不能为空，请检查config/database.php或传入config参数');
        }
        Db::setConfig($config);
        // 定义日志驱动
        $logger = $log ?: Logger::instance()->channel();
        self::$logger = $logger;
        Db::setLog($logger);
        // 定义缓存驱动
        $cache = $cache ?: (class_exists(CacheService::class) ? CacheService::instance()->getService()->store() : null);
        if ($cache) {
            Db::setCache($cache);
        }
        // 处理长链接
        if ($longLink) {
            self::heart($timer);
        }
    }

    /**
     * 获取默认数据库配置
     *
     * @return array
     */
    protected static function defaultConfig(): array
    {
        // mon-env为可选依赖，未安装时由调用方显式传入配置
        if (!class_exists(Config::class)) {
            return [];
        }

        return (array) Config::instance()->get('database', []);
    }

    /**
     * 保持数据库链接心跳
     *
     * 注意：同一进程内重复调用只会注册一个定时器，避免心跳任务叠加
     *
     * @param integer $timer
     * @return void
     */
    public static function heart(int $timer = 50)
    {
        if (!is_null(self::$heartTimerId)) {
            return;
        }

        self::$heartTimerId = Timer::add($timer, function () {
            foreach (Db::getInstance() as $name => $connection) {
                try {
                    self::ping($connection);
                    // 恢复正常后重置失败计数
                    unset(self::$heartFailTimes[$name]);
                } catch (Throwable $e) {
                    self::heartFailed((string) $name, $connection, $e);
                }
            }
        });
    }

    /**
     * 检测单个连接是否存活
     *
     * @param mixed $connection 连接实例
     * @throws Throwable
     * @return void
     */
    protected static function ping($connection): void
    {
        $type = strtolower((string) $connection->getConfig('type'));

        if ($type == 'mongo') {
            $command = new Command(['ping' => 1]);
            /**  @var \think\db\connector\Mongo $connection */
            $connection->command($command);
            return;
        }

        /**  @var \think\db\connector\Mysql $connection */
        $sql = in_array($type, ['oracle', 'oci', 'oci8']) ? 'SELECT 1 AS ping FROM DUAL' : 'SELECT 1 AS ping';
        // 已建立过连接时直接使用PDO探活，避免心跳语句写入SQL日志
        $pdo = method_exists($connection, 'getPdo') ? $connection->getPdo() : false;
        if ($pdo instanceof \PDO) {
            $pdo->query($sql);
            return;
        }
        // 未建立连接的，走ORM通道触发连接初始化
        $connection->query($sql);
    }

    /**
     * 心跳检测失败处理
     *
     * @param string $name 连接名称
     * @param mixed $connection 连接实例
     * @param Throwable $e 异常对象
     * @return void
     */
    protected static function heartFailed(string $name, $connection, Throwable $e): void
    {
        $times = (self::$heartFailTimes[$name] ?? 0) + 1;
        self::$heartFailTimes[$name] = $times;

        // 关闭已失效的连接，下次查询时由break_reconnect机制重新建立连接
        try {
            if (method_exists($connection, 'close')) {
                $connection->close();
            }
        } catch (Throwable $ex) {
        }

        // 首次失败及每10次失败记录一次日志，避免持续故障时刷爆日志
        if ($times === 1 || $times % 10 === 0) {
            try {
                self::logger()->warning("数据库[database.{$name}]心跳检测失败({$times}次): " . $e->getMessage());
            } catch (Throwable $ex) {
            }
        }
    }

    /**
     * 获取日志驱动
     *
     * @return LoggerInterface
     */
    protected static function logger(): LoggerInterface
    {
        if (is_null(self::$logger)) {
            self::$logger = Logger::instance()->channel();
        }

        return self::$logger;
    }
}
