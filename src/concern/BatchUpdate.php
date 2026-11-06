<?php

declare(strict_types=1);

namespace mon\thinkORM\concern;

use mon\thinkORM\Db;
use InvalidArgumentException;

/**
 * 批量更新
 * 
 * @author Mon <985558837@qq.com>
 * @version 1.0.0
 */
trait BatchUpdate
{
    /**
     * 批量更新数据（默认基于ID）
     * 
     * @param array $updateData 待更新的数据（二维数组，每个元素包含id和要更新的字段）
     * @param string $key 主键字段名
     * @param integer $batchSize 每批处理的数量
     * @param string $where 额外WHERE条件，默认为空
     * @param string $table 表名，默认使用当前表名
     * @return integer 成功更新的总记录数
     */
    public function batchUpdate(array $updateData, string $key = 'id', int $batchSize = 500, string $where = '', string $table = ''): int
    {
        $table = $table ?: $this->getTable();
        $totalUpdated = 0;
        foreach (array_chunk($updateData, $batchSize) as $batch) {
            $cases = [];
            $ids = [];

            // 构建CASE WHEN子句
            foreach ($batch as $item) {
                $id = $item[$key] ?? null;
                $this->validateId($id, $key);
                $ids[] = $id;

                foreach ($item as $field => $value) {
                    if ($field === $key) continue;

                    if (!isset($cases[$field])) {
                        $cases[$field] = [];
                    }

                    // 处理不同类型的值
                    $escapedValue = $this->escapeValue($value);
                    $cases[$field][] = "WHEN `{$key}` = {$id} THEN {$escapedValue}";
                }
            }

            // 构建并执行SQL
            if (!empty($setClauses = $this->buildSetClauses($cases))) {
                $sql = "UPDATE `{$table}` SET " . implode(', ', $setClauses) . " WHERE `{$key}` IN (" . implode(',', $ids) . ")";
                if ($where) {
                    $sql .= " AND {$where}";
                }
                $updated = $this->execute($sql);

                $totalUpdated += $updated;
            }
        }

        return $totalUpdated;
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
     * @param array $data 增量数据（二维数组，每个元素包含id和要增量的字段）
     * @param string $key 主键字段名
     * @param array $operators 操作符映射（如 ['score' => '+' | '-' | '*' | '/']）
     * @param int $batchSize 每批处理的数量
     * @param string $where 额外WHERE条件，默认为空
     * @param string $table 表名，默认使用当前表名
     * @return int 成功更新的总记录数
     */
    public function batchIncrement(array $data, array $operators = [], string $key = 'id', int $batchSize = 500, string $where = '', string $table = ''): int
    {
        $table = $table ?: $this->getTable();
        // 设置操作符（默认为加法，按字段名映射）
        $defaultOperators = ['+', '-', '*', '/'];
        $totalUpdated = 0;
        foreach (array_chunk($data, $batchSize) as $batch) {
            $cases = [];
            $ids = [];

            // 构建增量更新的CASE WHEN子句
            foreach ($batch as $item) {
                $id = $item[$key] ?? null;
                $this->validateId($id, $key);
                $ids[] = $id;

                foreach ($item as $field => $value) {
                    if ($field === $key) continue;

                    if (!isset($cases[$field])) {
                        $cases[$field] = [];
                    }

                    // 获取字段对应的操作符（默认为+）
                    $operator = $operators[$field] ?? '+';

                    // 验证操作符有效性
                    if (!in_array($operator, $defaultOperators)) {
                        throw new InvalidArgumentException("无效的操作符: {$operator}");
                    }

                    $escapedValue = $this->escapeValue($value);
                    $cases[$field][] = "WHEN `{$key}` = {$id} THEN `{$field}` {$operator} {$escapedValue}";
                }
            }

            // 执行批量更新
            if (!empty($setClauses = $this->buildSetClauses($cases))) {
                $sql = "UPDATE `{$table}` SET " . implode(', ', $setClauses) . " WHERE `{$key}` IN (" . implode(',', $ids) . ")";
                if ($where) {
                    $sql .= " AND {$where}";
                }
                $updated = $this->execute($sql);

                $totalUpdated += $updated;
            }
        }

        return $totalUpdated;
    }

    /**
     * 构建SET子句
     *
     * @param array $cases
     * @return array
     */
    protected function buildSetClauses(array $cases): array
    {
        $setClauses = [];
        foreach ($cases as $field => $whenClauses) {
            $elseClause = "ELSE `{$field}`";
            $setClauses[] = "`{$field}` = CASE " . implode(' ', $whenClauses) . " {$elseClause} END";
        }

        return $setClauses;
    }

    /**
     * 安全转义值
     *
     * @param mixed $value
     * @throws InvalidArgumentException 不支持的数据类型
     * @return string
     */
    protected function escapeValue($value): string
    {
        if (is_null($value)) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (is_string($value)) {
            return $this->getQuotePdo()->quote($value);
        }

        throw new InvalidArgumentException('批量更新不支持的数据类型: ' . gettype($value));
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

    /**
     * 获取用于转义的PDO对象（遵循Dao绑定的连接配置）
     *
     * @return \PDO
     */
    protected function getQuotePdo(): \PDO
    {
        if (method_exists($this, 'getConnection') && $this->getConnection()) {
            /** @var \think\db\PDOConnection $connection */
            $connection = Db::connect($this->getConnection());
            return $connection->getPdo();
        }

        return Db::getPdo();
    }
}
