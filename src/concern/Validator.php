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
        return Container::instance()->get($this->validate);
    }
}
