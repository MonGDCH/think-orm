<?php

declare(strict_types=1);

namespace mon\thinkORM\contract;

use mon\thinkORM\Dao;

/**
 * Dao对象Query类接口
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
interface DaoQuery
{
    /**
     * 指定Dao对象
     *
     * @param Dao $dao Dao对象实例
     * @return DaoQuery 当前实例自身
     */
    public function dao(Dao $dao): DaoQuery;

    /**
     * 获取当前查询Dao对象
     *
     * @return Dao|null
     */
    public function getDao();

    /**
     * 允许操作的字段，存在Dao对象时有效
     *
     * @param array $field
     * @return DaoQuery
     */
    public function allowField(array $field): DaoQuery;

    /**
     * 判断是否为更新查询
     *
     * @return boolean
     */
    public function isUpdate(): bool;

    /**
     * 保存记录 自动判断insert或者update
     *
     * @param array     $data           数据
     * @param boolean   $forceInsert    是否强制insert
     * @param boolean   $getLastInsID   insert操作返回新增的ID
     * @return integer  影响行数或自增ID
     */
    public function save(array $data = [], bool $forceInsert = false, bool $getLastInsID = false);

    /**
     * Dao对象saveAll批量写入数据方法支持
     *
     * @param array     $data   操作数据
     * @param integer   $limit  每次写入数据限制
     * @return integer 影响行数
     */
    public function saveAll(array $data, int $limit = 0): int;

    /**
     * Dao对象get获取单条数据方法支持
     *
     * @param  boolean  $format 是否加工处理数据
     * @param  array $where where条件
     * @return array
     */
    public function get(bool $format = false, array $where = []): array;

    /**
     * Dao对象all获取多条数据方法支持
     *
     * @param  boolean  $format 是否加工处理数据
     * @param  array $where where条件
     * @return array
     */
    public function all(bool $format = false, array $where = []): array;
}
