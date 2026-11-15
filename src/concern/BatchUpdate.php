<?php

declare(strict_types=1);

namespace mon\thinkORM\concern;

use InvalidArgumentException;

/**
 * 批量更新
 *
 * 说明：本 trait 会拼接原生 SQL，表名、字段名走白名单校验，字段值一律使用参数绑定，
 * $where 参数只接受可信的静态条件片段（不支持绑定），禁止拼接用户输入。
 *
 * @author Mon <985558837@qq.com>
 * @version 1.1.0
 */
trait BatchUpdate
{
    /**
     * 批量更新数据（默认基于ID）
     * 
     * @param array $updateData 待更新的数据（二维数组，每个元素包含主键和要更新的字段）
     * @param string $key 主键字段名
     * @param integer $batchSize 每批处理的数量
     * @param string $where 额外WHERE条件（可信的静态片段），默认为空
     * @param string $table 表名，默认使用当前表名
     * @throws InvalidArgumentException 主键、字段名或字段值不合法
     * @return integer 成功更新的总记录数
     */
    public function batchUpdate(array $updateData, string $key = 'id', int $batchSize = 500, string $where = '', string $table = ''): int
    {
        return $this->batchWrite($updateData, [], $key, $batchSize, $where, $table);
    }

    /**
     * 批量增量更新数据（默认基于ID）
     * 
     * @example 
     * $data = [
     *     ['id' => 1, 'score' => 10],   // score += 10
     *     ['id' => 2, 'score' => 5],    // score += 5
     *     ['id' => 3, 'level' => 2],    // level += 2
     *     ['id' => 4, 'stock' => -1],   // stock -= 1
     * ];
     * // 指定操作符（可选，默认为+）
     * $operators = [
     *     'score' => '+',
     *     'level' => '+',
     *     'stock' => '-',
     * ];
     * $this->batchIncrement($data, $operators);
     * 
     * @param array $data 增量数据（二维数组，每个元素包含主键和要增量的字段）
     * @param array $operators 操作符映射（如 ['score' => '+' | '-' | '*' | '/']）
     * @param string $key 主键字段名
     * @param int $batchSize 每批处理的数量
     * @param string $where 额外WHERE条件（可信的静态片段），默认为空
     * @param string $table 表名，默认使用当前表名
     * @throws InvalidArgumentException 主键、字段名、操作符或字段值不合法
     * @return int 成功更新的总记录数
     */
    public function batchIncrement(array $data, array $operators = [], string $key = 'id', int $batchSize = 500, string $where = '', string $table = ''): int
    {
        return $this->batchWrite($data, $operators, $key, $batchSize, $where, $table);
    }

    /**
     * 批量写入实现
     *
     * @param array $data       待处理数据
     * @param array $operators  增量操作符映射，为空表示直接赋值
     * @param string $key       主键字段名
     * @param integer $batchSize 每批处理数量
     * @param string $where     额外WHERE条件
     * @param string $table     表名
     * @throws InvalidArgumentException
     * @return integer 成功更新的总记录数
     */
    protected function batchWrite(array $data, array $operators, string $key, int $batchSize, string $where, string $table): int
    {
        if (empty($data)) {
            return 0;
        }

        $table = $this->resolveBatchTable($table);
        $keyQuoted = '`' . $this->parseBatchIdentifier($key, '主键字段') . '`';
        $batchSize = max(1, $batchSize);
        $allowOperators = ['+', '-', '*', '/'];
        $totalUpdated = 0;

        foreach (array_chunk($data, $batchSize) as $batch) {
            $ids = [];
            $cases = [];
            $binds = [];

            foreach ($batch as $item) {
                if (!is_array($item)) {
                    throw new InvalidArgumentException('批量更新的数据项必须为数组');
                }
                $id = $item[$key] ?? null;
                $this->validateId($id, $key);
                $ids[] = $id;

                foreach ($this->prepareBatchData($item, $key) as $field => $value) {
                    if ($field === $key) {
                        continue;
                    }
                    $field = $this->parseBatchIdentifier((string) $field, '操作字段');
                    $this->validateBatchValue($value, $field);
                    // 构建赋值表达式，值统一使用参数绑定，避免SQL注入
                    $expression = '?';
                    if (!empty($operators)) {
                        $operator = $operators[$field] ?? '+';
                        if (!in_array($operator, $allowOperators, true)) {
                            throw new InvalidArgumentException("无效的操作符: {$operator}");
                        }
                        $expression = "`{$field}` {$operator} ?";
                    }
                    $cases[$field][] = "WHEN {$keyQuoted} = ? THEN {$expression}";
                    $binds[] = $id;
                    $binds[] = $value;
                }
            }

            if (empty($cases)) {
                continue;
            }

            $setClauses = [];
            foreach ($cases as $field => $whenClauses) {
                $setClauses[] = "`{$field}` = CASE " . implode(' ', $whenClauses) . " ELSE `{$field}` END";
            }

            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "UPDATE {$table} SET " . implode(', ', $setClauses) . " WHERE {$keyQuoted} IN ({$placeholders})";
            // 绑定值顺序必须与SQL中占位符顺序一致：先SET，后WHERE
            $binds = array_merge($binds, $ids);
            if ($where !== '') {
                $sql .= " AND ({$where})";
            }

            $totalUpdated += $this->execute($sql, $binds);
        }

        return $totalUpdated;
    }

    /**
     * 整理单条待更新数据：过滤只读字段、补全自动时间戳
     *
     * @param array $item 单条数据
     * @param string $key 主键字段名
     * @return array
     */
    protected function prepareBatchData(array $item, string $key): array
    {
        // 过滤只读字段，避免批量更新绕过 Dao 的只读保护
        if (method_exists($this, 'getReadonly')) {
            foreach ($this->getReadonly() as $field) {
                unset($item[$field]);
            }
        }

        // 补全自动写入的时间戳字段，保持与 save 更新行为一致
        if (method_exists($this, 'getAutoTimeData')) {
            $item = array_merge($item, $this->getAutoTimeData(false));
        }

        return $item;
    }

    /**
     * 校验并格式化表名，支持 db.table 形式，返回反引号包裹后的表名
     *
     * @param string $table 表名
     * @throws InvalidArgumentException
     * @return string
     */
    protected function resolveBatchTable(string $table): string
    {
        $table = $table !== '' ? $table : (string) $this->getTable();
        if ($table === '') {
            throw new InvalidArgumentException('批量更新的表名不能为空');
        }

        $parts = [];
        foreach (explode('.', $table) as $part) {
            $parts[] = '`' . $this->parseBatchIdentifier($part, '表名') . '`';
        }

        return implode('.', $parts);
    }

    /**
     * 校验标识符（表名、字段名），只允许字母、数字、下划线
     *
     * @param string $identifier 标识符
     * @param string $type 标识符类型，用于错误提示
     * @throws InvalidArgumentException
     * @return string
     */
    protected function parseBatchIdentifier(string $identifier, string $type = '字段'): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("批量更新的{$type}[{$identifier}]不合法，只允许字母、数字和下划线，且不能以数字开头");
        }

        return $identifier;
    }

    /**
     * 校验字段值类型，仅支持标量及 null（值通过参数绑定写入）
     *
     * @param mixed $value 字段值
     * @param string $field 字段名
     * @throws InvalidArgumentException
     * @return void
     */
    protected function validateBatchValue($value, string $field): void
    {
        if (is_null($value) || is_scalar($value)) {
            return;
        }

        throw new InvalidArgumentException("批量更新字段[{$field}]不支持的数据类型: " . gettype($value));
    }

    /**
     * 校验主键值，防止SQL注入（仅允许整数及整数字符串）
     *
     * @param mixed  $id   主键值
     * @param string $key  主键字段名
     * @throws InvalidArgumentException 非法主键值
     * @return void
     */
    protected function validateId($id, string $key): void
    {
        if (!is_int($id) && !(is_string($id) && preg_match('/^-?\d+$/', $id))) {
            throw new InvalidArgumentException("批量更新的主键[{$key}]必须为整数");
        }
    }
}
