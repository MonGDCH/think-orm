<?php

declare(strict_types=1);

namespace mon\thinkORM\extend;

use RuntimeException;
use mon\thinkORM\Dao;
use mon\thinkORM\contract\DaoQuery;

/**
 * 自定义扩展的查询类
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
class Query extends \think\db\Query implements DaoQuery
{
    /**
     * Dao对象
     *
     * @var Dao
     */
    protected $dao;

    /**
     * 指定Dao对象
     *
     * @param Dao $dao Dao对象实例
     * @return Query 当前实例自身
     */
    public function dao(Dao $dao): Query
    {
        $this->dao = $dao;
        return $this;
    }

    /**
     * 获取当前查询Dao对象
     *
     * @return Dao|null
     */
    public function getDao()
    {
        return $this->dao;
    }

    /**
     * 允许操作的字段，存在Dao对象时有效
     *
     * @param array $field
     * @return Query
     */
    public function allowField(array $field): Query
    {
        if ($this->getDao()) {
            $this->getDao()->allowField($field);
        }

        return $this;
    }

    /**
     * 判断是否为更新查询
     *
     * @return boolean
     */
    public function isUpdate(): bool
    {
        $isUpdate = false;
        if (!empty($this->options['where'])) {
            $isUpdate = true;
        } else {
            $isUpdate = $this->parseUpdateData($this->options['data']);
        }

        return $isUpdate;
    }

    /**
     * 保存记录 自动判断insert或者update
     * 重载save方法，支持Dao对象
     *
     * @param array     $data           数据
     * @param boolean   $forceInsert    是否强制insert
     * @param boolean   $getLastInsID   insert操作返回新增的ID
     * @return integer  影响行数或自增ID
     */
    public function save(array $data = [], bool $forceInsert = false, bool $getLastInsID = false)
    {
        $this->options['data'] = array_merge($this->options['data'] ?? [], $data);
        // 存在Dao对象时，使用Dao对象操作
        if ($this->getDao()) {
            return $this->getDao()->save($this->options['data'], $forceInsert, $getLastInsID, $this);
        }

        if ($forceInsert) {
            return $this->insert($data, $getLastInsID);
        }

        return $this->isUpdate() ? $this->update() : $this->insert([], $getLastInsID);
    }

    /**
     * Dao对象saveAll批量写入数据方法支持
     *
     * @param array     $data   操作数据
     * @param integer   $limit  每次写入数据限制
     * @return integer 影响行数
     */
    public function saveAll(array $data, int $limit = 0): int
    {
        if (!$this->getDao()) {
            throw new RuntimeException('查询对象未绑定Dao对象');
        }

        return call_user_func_array([$this->getDao(), 'saveAll'], [$data, $limit, $this]);
    }

    /**
     * Dao对象get获取单条数据方法支持
     *
     * @param  array $where where条件
     * @param  boolean  $format 是否加工处理数据
     * @return array
     */
    public function get(bool $format = false, array $where = []): array
    {
        if (!$this->getDao()) {
            throw new RuntimeException('查询对象未绑定Dao对象');
        }

        return call_user_func_array([$this->getDao(), 'get'], [$format, $where, $this]);
    }

    /**
     * Dao对象all获取多条数据方法支持
     *
     * @param  array $where where条件
     * @param  boolean  $format 是否加工处理数据
     * @return array
     */
    public function all(bool $format = false, array $where = []): array
    {
        if (!$this->getDao()) {
            throw new RuntimeException('查询对象未绑定Dao对象');
        }

        return call_user_func_array([$this->getDao(), 'all'], [$format, $where, $this]);
    }
}
