<?php

declare(strict_types=1);

namespace mon\thinkORM\concern;

/**
 * 自动时间戳
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
trait AutoTime
{
    /**
     * 是否自动写入时间戳
     *
     * @var boolean
     */
    protected $autoWriteTimestamp = false;

    /**
     * 创建时间字段 false表示关闭
     *
     * @var string
     */
    protected $createTime = 'create_time';

    /**
     * 更新时间字段 false表示关闭
     *
     * @var string
     */
    protected $updateTime = 'update_time';

    /**
     * 自动写入时间格式，空则写入时间戳
     *
     * @var string|null
     */
    protected $autoTimeFormat = 'Y-m-d H:i:s';

    /**
     * 是否需要自动写入时间字段
     *
     * @param  boolean $auto
     * @return static
     */
    public function isAutoWriteTimestamp(bool $auto)
    {
        $this->autoWriteTimestamp = $auto;

        return $this;
    }

    /**
     * 获取自动写入时间戳数据
     *
     * @param boolean $insert   操作类型，true为新增，false为更新
     * @return array
     */
    public function getAutoTimeData(bool $insert = false): array
    {
        if (!$this->autoWriteTimestamp) {
            return [];
        }
        $result = [];
        $time = $this->getAutoTimeValue();
        if ($insert && $this->createTime) {
            $result[$this->createTime] = $time;
        }
        if ($this->updateTime) {
            $result[$this->updateTime] = $time;
        }

        return $result;
    }

    /**
     * 获取时间字段值
     *
     * @param  mixed   $value   时间戳
     * @return mixed
     */
    protected function getAutoTimeValue($value = null)
    {
        $time = $value ?: time();
        if (!empty($this->autoTimeFormat)) {
            $time = date($this->autoTimeFormat, $time);
        }

        return $time;
    }
}
