<?php

declare(strict_types=1);

namespace mon\thinkORM;

use Closure;
use Throwable;
use RuntimeException;
use mon\http\Response;
use mon\http\interfaces\RequestInterface;
use mon\http\interfaces\MiddlewareInterface;

/**
 * mon-http中间件支持，处理事务安全验证
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class ORMMiddleware implements MiddlewareInterface
{
    /**
     * 中间件实现接口
     *
     * @param RequestInterface $request  请求实例
     * @param Closure $next 执行下一个中间件回调方法
     * @return Response
     */
    public function process(RequestInterface $request, Closure $next): Response
    {
        try {
            return $next($request);
        } finally {
            /**
             * 常驻进程下数据库连接会跨请求复用，必须保证业务异常时也执行事务校验，
             * 否则未提交事务及其计数会残留到该进程处理的下一个请求
             */
            $this->checkTpUncommittedTransaction();
        }
    }

    /**
     * 检查think-orm是否有未提交的事务
     *
     * 注意：单个连接回滚失败（常见于连接已断开）不会中断其它连接的清理，
     * 也不会掩盖本次请求的原始异常
     *
     * @throws RuntimeException 存在未提交的事务（已尝试回滚），或回滚失败
     * @return void
     */
    protected function checkTpUncommittedTransaction(): void
    {
        $rolledBack = false;
        $failures = [];

        /** @var \think\db\connector\Mysql $connection */
        foreach (Db::getInstance() as $name => $connection) {
            if (!method_exists($connection, 'getPdo')) {
                continue;
            }

            $pdo = $connection->getPdo();
            if (!$pdo instanceof \PDO || !$pdo->inTransaction()) {
                continue;
            }

            try {
                // 逐个回滚，兼容嵌套事务（只回滚一次会残留外层事务）
                $times = 0;
                while ($pdo instanceof \PDO && $pdo->inTransaction() && $times < 100) {
                    $connection->rollback();
                    $pdo = $connection->getPdo();
                    $times++;
                }
                $rolledBack = true;
            } catch (Throwable $e) {
                // 记录失败并关闭失效连接（事务计数随之归零），继续清理其它连接
                $failures[] = "database.[{$name}] " . get_class($e) . ': ' . $e->getMessage();
                try {
                    if (method_exists($connection, 'close')) {
                        $connection->close();
                    }
                } catch (Throwable $ex) {
                }
            }
        }

        if (!empty($failures)) {
            throw new RuntimeException('Uncommitted transaction rollback failed: ' . implode('; ', $failures));
        }

        if ($rolledBack) {
            throw new RuntimeException('Uncommitted transaction found and try to rollback');
        }
    }
}
