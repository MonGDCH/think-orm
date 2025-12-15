<?php

declare(strict_types=1);

namespace mon\thinkORM;

use Throwable;
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
        $config = $config ?: Config::instance()->get('database', []);
        Db::setConfig($config);
        // 定义日志驱动
        $logger = $log ?: Logger::instance()->channel();
        Db::setLog($logger);
        // 定义缓存驱动
        $cacher = $cache ?: CacheService::instance()->getService()->store();
        Db::setCache($cacher);
        // 处理长链接
        if ($longLink) {
            self::heart($timer);
        }
    }

    /**
     * 保持数据库链接心跳
     *
     * @param integer $timer
     * @return void
     */
    public static function heart(int $timer = 50)
    {
        Timer::add($timer, function () {
            $instances = Db::getInstance();
            foreach ($instances as $connection) {
                try {
                    /** @var \think\db\PDOConnection $connection */
                    if ($connection->getConfig('type') == 'mongo') {
                        $command = new Command(['ping' => 1]);
                        /**  @var \think\db\connector\Mongo $connection */
                        $connection->command($command);
                        continue;
                    }
                    /**  @var \think\db\connector\Mysql $connection */
                    $connection->query('SELECT 1');
                } catch (Throwable $e) {
                }
            }
            // 清空内存中的日志，防止错误的配置导致爆内存
            Db::getDbLog(true);
        });
    }
}
