<?php

declare(strict_types=1);

namespace mon\thinkORM\concern;

use mon\util\Validate;
use mon\util\Container;

/**
 * 验证器
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
trait Validator
{
    /**
     * 验证器驱动，默认为内置的Validate验证器
     *
     * @var string
     */
    protected $validate = Validate::class;

    /**
     * 获取实例化验证器
     *
     * @return Validate
     */
    public function validate(): Validate
    {
        /**
         * 常驻进程下容器默认缓存实例，而验证器带有 error/errorKey 等可变状态，
         * 复用会导致上一请求的校验错误残留，因此每次获取新实例
         */
        return Container::instance()->get($this->validate, [], true);
    }
}
