<?php

declare(strict_types=1);

namespace mon\thinkORM\concern;

use mon\thinkORM\extend\Query;

/**
 * 自动完成
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
trait AutoAttr
{
    /**
     * 更新操作自动完成字段配置
     *
     * @var array
     */
    protected $update = [];

    /**
     * 新增操作自动完成字段配置
     *
     * @var array
     */
    protected $insert = [];

    /**
     * 查询后字段完成的数据
     *
     * @var array
     */
    protected $append = [];

    /**
     * 只读字段，不允许修改
     *
     * @var array
     */
    protected $readonly = [];

    /**
     * 获取更新操作自动完成字段
     *
     * @return array
     */
    public function getUpdate(): array
    {
        return $this->update;
    }

    /**
     * 获取新增操作自动完成字段
     * 
     * @return array
     */
    public function getInsert(): array
    {
        return $this->insert;
    }

    /**
     * 获取查询后自动完成字段
     *
     * @return array
     */
    public function getAppend(): array
    {
        return $this->append;
    }

    /**
     * 获取只读字段
     *
     * @return array
     */
    public function getReadonly(): array
    {
        return $this->readonly;
    }

    /**
     * 更新数据
     *
     * @param  array $data  操作数据
     * @param  Query $query 查询对象实例
     * @return integer      影响行数
     */
    protected function updateData(array $data, ?Query $query = null)
    {
        // 过滤只读字段
        if (!empty($this->readonly)) {
            foreach ($this->readonly as $field) {
                if (array_key_exists($field, $data)) {
                    unset($data[$field]);
                }
            }
        }
        // 自动完成
        $updateData = array_merge($this->autoCompleteData($this->update, $data), $this->getAutoTimeData(false));
        // 获取查询对象实例
        if (!$query) {
            $query = $this->db();
        }

        return $query->data($updateData)->update();
    }

    /**
     * 新增数据
     *
     * @param  array   $data            操作数据
     * @param  boolean $getLastInsID    是否返回自增ID
     * @param  Query   $query           查询对象实例
     * @return integer|string           影响行数或自增主机ID
     */
    protected function insertData(array $data, bool $getLastInsID = false, ?Query $query = null)
    {
        // 自动完成
        $insertData = array_merge($this->autoCompleteData($this->insert, $data), $this->getAutoTimeData(true));
        // 获取查询对象实例
        if (!$query) {
            $query = $this->db();
        }

        return $query->insert($insertData, $getLastInsID);
    }

    /**
     * 数据自动完成
     *
     * @param  array  $auto 自动补全的字段
     * @param  array  $data 数据数据源
     * @return array
     */
    protected function autoCompleteData(array $auto = [], array $data = []): array
    {
        $result = $data;
        // 处理补全数据
        foreach ($auto as $field => $value) {
            if (is_integer($field)) {
                $field = $value;
                $value = null;
            }
            // 处理数据字段
            if (array_key_exists($field, $data)) {
                $value = $data[$field];
            }
            $result[$field] = $this->setAttr($field, $value, $data);
        }

        return $result;
    }

    /**
     * 设置器，设置修改操作数据
     *
     * @param string $name  属性名
     * @param mixed  $value 属性值
     * @param array  $data  元数据
     * @return mixed
     */
    protected function setAttr(string $name, $value = null, array $data = [])
    {
        // 检测设置器是否存在
        $method = 'set' . $this->parseAttrName($name) . 'Attr';
        if (method_exists($this, $method)) {
            $value = $this->{$method}($value, $data);
        }
        return $value;
    }

    /**
     * 获取器, 修改获取数据
     *
     * @param  string $name  属性名
     * @param  mixed  $value 属性值
     * @param  array  $data  元数据
     * @return mixed
     */
    protected function getAttr(string $name, $value = null, array $data = [])
    {
        // 检测设置器是否存在
        $method = 'get' . $this->parseAttrName($name) . 'Attr';
        if (method_exists($this, $method)) {
            $value = $this->{$method}($value, $data);
        }

        return $value;
    }

    /**
     * 整理获取Dao数据
     *
     * @param array $data   数据集
     * @return array
     */
    protected function getDaoData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $result[$key] = $this->getAttr($key, $value, $data);
        }
        if ($this->getAppend()) {
            foreach ($this->getAppend() as $field => $val) {
                if (is_integer($field)) {
                    $field = $val;
                    $val = null;
                }
                $result[$field] = $this->getAttr($field, $val, $data);
            }
        }

        return $result;
    }

    /**
     * 检测命名, 转换下划线命名规则为驼峰法命名规则
     *
     * @param  string $name 字段名称
     * @return string
     */
    protected function parseAttrName(string $name): string
    {
        $name = preg_replace_callback('/_([a-zA-Z])/', function ($match) {
            return strtoupper($match[1]);
        }, $name);

        return ucfirst($name);
    }
}
