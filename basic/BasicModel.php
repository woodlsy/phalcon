<?php

namespace woodlsy\phalcon\basic;

use woodlsy\phalcon\library\Log;
use woodlsy\phalcon\library\Helper;
use Phalcon\DI;
use Phalcon\Mvc\Model;
use Phalcon\Db;
use Exception;

abstract class BasicModel extends Model
{
    protected $_targetTable = null;

    protected $lastSql = null;

    //当前来连接的数据源
    protected $_targetDb = 'master';

    /**
     * 初始化
     *
     * @author yls
     * @throws Exception
     */
    public function initialize()
    {
        //初始化数据库
        if(!empty($this->_targetDb)){
            if(!isset(DI::getDefault()->get('config')->db->{$this->_targetDb})){
                Log::write('sql', "数据库｛{$this->_targetDb}｝连接不存在", 'error');
                throw new Exception('数据库连接失败');
            }
        }
        $this->setWriteConnectionService($this->_targetDb);
        $this->setReadConnectionService($this->_targetDb);
    }

    /**
     * 设置表名
     *
     * @param $table
     */
    public function setTargetTable($table)
    {
        $this->_targetTable = $table;
        $this->setSource($this->_targetTable);
    }

    /**
     * SQL重置前缀
     *
     * @param $string
     * @return string
     */
    private function loadPrefix($string = null) : string
    {
        static $db_prefix = null;
        if (!$db_prefix) {
            $dbConfig  = $this->getReadConnection()->getDescriptor();
            $db_prefix = isset($dbConfig['prefix']) ? $dbConfig['prefix'] : null;
        }
        if (!$string || !is_string($string)) {
            $preg_name = str_replace('\\', '_', get_class($this));
            $string    = preg_replace('/^(([^_]_?)*Models_?)/i', '', $preg_name);
            $string    = '{{' . strtolower($string) . '}}';
        }

        return preg_replace('/\{\{(.+?)\}\}/', $db_prefix . '\\1', $string);
    }

    /**
     * 处理where条件，转化为sql
     * array(
     *     'id' => [1,2,3],               // id in (1,2,3)
     *     'status' => ['in', [1,2]],     // status in (1,2)
     *     'name' => ['like', '%产品%'],   // name like '%产品%'
     *     'year' => ['between', [2015,2017]] // year between 2015 AND 2017
     *     'age' => ['>', 10]            // age > 10
     *     'start_time' =>['or', 'start_time'=>['>=', '2017-11-17 17:20:10'], 'start_time'=>['<=', '2017-11-17
     * 17:30:10']]
     *                                   // (start_time >= '2017-11-17 17:20:10') or (start_time <= '2017-11-17
     * 17:30:10')
     *     '_sql' => ['_sql', "(start_time >= '2017-11-17 17:20:10') or (start_time <= '2017-11-17 17:30:10')"],
     * //直接拼接后面的sql，若有多个 key 可随意写，但不能为数字
     * )
     * 默认 and 连接
     *
     * @param string|array $where
     * @return array
     */
    private function dealWhere($where) : array
    {
        if (empty($where)) return ['where' => '1=1', 'params' => []];
        if (!is_array($where)) return ['where' => $where, 'params' => []];

        $fields = $val = [];

        foreach ($where as $key => $value) {
            if (empty($key) || is_numeric($key)) continue;
            if (is_string($value[0])) $value[0] = trim(strtolower($value[0]));
            if (is_array($value)) {
                if (in_array($value[0], ['>', '>=', '<', '<=', 'like', '!=', '<>'])) {
                    $fields[] = "`{$key}` {$value[0]} ?";
                    $val[]    = $value[1];
                } elseif ($value[0] == 'between' && is_array($value[1])) {
                    $fields[] = $key . " between ? AND ?";
                    $val[]    = $value[1][0];
                    $val[]    = $value[1][1];
                } elseif ($value[0] == 'in') {
                    $w = [];
                    for ($i = 1; $i <= count($value[1]); $i++) {
                        $w[] = '?';
                    }
                    $fields[] = "`{$key}` in (" . implode(',', $w) . ')';
                    foreach ($value[1] as $v) {
                        $val[] = $v;
                    }
                } elseif ($value['0'] == 'or') {
                    $childWhere  = [];
                    $childParams = [];
                    foreach ($value as $k => $v) {
                        if ($k === 0) continue;
                        $tmp_Where    = $this->dealWhere([$k => $v]);
                        $childWhere[] = $tmp_Where['where'];
                        $childParams  = empty($childParams) ? $tmp_Where['params'] : array_merge($childParams, $tmp_Where['params']);
                    }
                    $fields[] = ' (' . implode(') OR (', $childWhere) . ') ';
                    $val      = array_merge($val, $childParams);
                } elseif ($value['0'] == '_sql') {
                    $fields[] = $value[1];
                } else {
                    $w = [];
                    for ($i = 1; $i <= count($value); $i++) {
                        $w[] = '?';
                    }
                    $fields[] = "`{$key}` in (" . implode(',', $w) . ')';
                    foreach ($value as $v) {
                        $val[] = $v;
                    }
                }
            } else {
                $fields[] = "`{$key}` = ?";
                $val[]    = $value;
            }
        }
        $whereSql = ' (' . implode(') AND (', $fields) . ') ';
        $paramSql = $val;

        return ['where' => $whereSql, 'params' => $paramSql];
    }

    /**
     * 表字段属性
     *
     * @return array
     */
    abstract public function attribute();

    /**
     * 处理需要查询的字段
     *
     * @author yls
     * @param string|array $fields
     * @return string
     */
    private function dealFields($fields = '') : string
    {
        $defaultFields = $this->attribute();
        if ((empty($fields) || (is_string($fields) && '*' === trim($fields))) && !empty($defaultFields)) {
            $fields = array_keys($defaultFields);
        }
        if (is_array($fields)) return '`' . implode('`,`', $fields) . '`';
        return $fields;
    }

    /**
     * 处理新增数据
     *
     * @author yls
     * @param array $data
     * @return array
     */
    private function dealInsertData(array $data) : array
    {
        $value = $fieldArr = $fieldPlaceholderArr = [];
        foreach ($data as $key => $val) {
            $fieldArr[]            = $key;
            $fieldPlaceholderArr[] = '?';
            $value[]               = $val;
        }
        $str = '(`' . implode('`,`', $fieldArr) . '`) VALUES (' . implode(',', $fieldPlaceholderArr) . ')';
        return ['val' => $str, 'params' => $value];
    }

    /**
     * 处理更新数据
     *
     * @author yls
     * @param array $data
     * @return array
     */
    private function dealUpdateData(array $data) : array
    {
        $value = $fieldArr = [];
        foreach ($data as $key => $val) {
            if (strpos($val, '+') || strpos($val, ' - ')) {
                $fieldArr[] = '`' . $key . '`=' . $val;
            } else {
                $fieldArr[] = '`' . $key . '`=?';
                $value[]    = $val;
            }
        }

        $str = implode(',', $fieldArr);
        return ['val' => $str, 'params' => $value];
    }

    /**
     * 添加数据
     *
     * @author yls
     * @param array $data
     * @return int|bool
     */
    public function insertData(array $data)
    {
        $fields = $this->attribute();
        if (isset($fields['create_at']) && !isset($data['create_at'])) $data['create_at'] = date('Y-m-d H:i:s');
        if (isset($fields['update_at']) && !isset($data['update_at'])) $data['update_at'] = date('Y-m-d H:i:s');
        if (isset($fields['create_by']) && !isset($data['create_by']) && !empty($this->admin)) $data['create_by'] = $this->admin['id'];
        if (isset($fields['update_by']) && !isset($data['update_by']) && !empty($this->admin)) $data['update_by'] = $this->admin['id'];
        $data   = $this->dealInsertData($data);
        $sql    = "INSERT INTO {$this->_targetTable} " . $data['val'];
        $result = $this->execute($sql, $data['params']);

        return $result;
    }

    /**
     * 更新数据
     *
     * @author yls
     * @param array $data
     * @param       $where
     * @return int|bool
     */
    public function updateData(array $data, $where)
    {
        $fields = $this->attribute();
        if (isset($fields['update_at']) && !isset($data['update_at'])) $data['update_at'] = date('Y-m-d H:i:s');
        if (isset($fields['update_by']) && !isset($data['update_by']) && !empty($this->admin)) $data['update_by'] = $this->admin['id'];
        $data     = $this->dealUpdateData($data);
        $whereSql = $this->dealWhere($where);
        $params   = array_merge($data['params'], $whereSql['params']);
        $sql      = "UPDATE {$this->_targetTable} set " . $data['val'] . ' where ' . $whereSql['where'];
        return $this->execute($sql, $params);
    }

    /**
     * 逻辑删除数据
     *
     * @author yls
     * @param array $where
     * @return int|bool
     */
    public function deleteData(array $where)
    {
        $fields = $this->attribute();
        $data   = ['is_deleted' => 1];
        if (isset($fields['deleted_at']) && !isset($data['deleted_at'])) $data['deleted_at'] = date('Y-m-d H:i:s');
        return $this->updateData($data, $where);
    }

    /**
     * 物理删除数据
     *
     * @author yls
     * @param array $where
     * @return int|bool
     */
    public function delData(array $where)
    {
        $whereSql = $this->dealWhere($where);
        $sql      = "DELETE FROM {$this->_targetTable} where " . $whereSql['where'];
        return $this->execute($sql, $whereSql['params']);
    }

    /**
     * 获取单条数据(通过主键)
     * @param int $id
     * @param null $fields
     * @return array|mixed
     */
    public function getById(int $id, $fields = NULL)
    {
        $fields = $this->dealFields($fields);
        $sql    = "select {$fields} from {$this->_targetTable} where `id` = ?";
        $params = [$id];
        $data   = $this->getRows($sql, $params);
        return isset($data[0]) ? $data[0] : array();
    }

    /**
     * 获取单条数据
     * @param $where
     * @param string $fields
     * @param string $orderBy
     * @return array
     */
    public function getOne($where, $fields = "", string $orderBy = "") : array
    {
        $data = $this->getList($where, $orderBy, null, null, $fields);
        return isset($data[0]) ? $data[0] : array();
    }

    /**
     * 获取列表(支持分页)
     * @param $where
     * @param string $orderBy
     * @param null $offset
     * @param null $row
     * @param null $fields
     * @return array|bool
     */
    public function getList($where, $orderBy = '', $offset = NUll, $row = NUll, $fields = NUll)
    {
        $whereSql = $this->dealWhere($where);
        $fields   = $this->dealFields($fields);
        if (!empty($orderBy)) $orderBy = 'order by ' . $orderBy;

        $sql = "select {$fields} from {$this->_targetTable} where " . $whereSql['where'] . " {$orderBy}";
        if ($offset !== NULl && ($offset !== false && $offset >= 0 && $row > 0)) $sql .= " limit {$offset},{$row}";
        return $this->getRows($sql, $whereSql['params']);
    }

    /**
     * 获取所有数据
     *
     * @author yls
     * @param        $where
     * @param null   $fields
     * @param string $orderBy
     * @return array|bool
     */
    public function getAll($where, $fields = NULL, $orderBy = NULL)
    {
        $whereSql = $this->dealWhere($where);
        $fields   = $this->dealFields($fields);
        if (!empty($orderBy)) $orderBy = 'order by ' . $orderBy;

        $sql = "select {$fields} from {$this->_targetTable} where " . $whereSql['where'] . " {$orderBy}";
        return $this->getRows($sql, $whereSql['params']);
    }

    /**
     * 获取多条数据
     *
     * @param string $sql
     * @param array  $params
     * @return array|bool
     */
    public function getRows($sql, $params = [])
    {
        try {
            $rows = $this->readData($sql, $params);
            if ($rows && !empty($rows)) {
                return $this->dealResult($rows);
            } else {
                return [];
            }
        } catch (Exception $e) {
            Log::write('sql', $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 获取总条数
     *
     * @author yls
     * @param       $where
     * @param array $fields
     * @return array|int
     */
    public function getCount($where, array $fields = [])
    {
        if (!empty($fields)) {
            $fieldStr = [];
            foreach ($fields as $val) {
                $tmp = explode(' ', $val);
                if (2 === count($tmp)) {
                    $fieldStr[] = "count({$tmp[0]} `{$tmp[1]}`) as {$tmp[1]}_num";
                } else {
                    $fieldStr[] = "count(`{$val}`) as {$val}_num";
                }
                $fieldStr = implode(',', $fieldStr);
            }
        } else {
            $fieldStr = 'count(*) as count_num';
        }
        $row = $this->getOne($where, $fieldStr);
        if (empty($row)) return 0;
        return empty($fields) ? (int) $row['count_num'] : $row;
    }

    /**
     * 获取和
     *
     * @author yls
     * @param       $where
     * @param array $fields
     * @return array
     */
    public function getSum($where, array $fields) : array
    {
        $fieldStr = [];
        foreach ($fields as $val) {
            $tmp = explode(' ', $val);
            if (count($tmp) > 1) {
                $fieldStr[] = "sum({$tmp[0]}) as {$tmp[1]}_sum";
            } else {
                $fieldStr[] = "sum(`{$val}`) as {$val}_sum";
            }
        }
        $row = $this->getOne($where, implode(',', $fieldStr));
        return $row;
    }

    /**
     * 执行sql（只读）
     *
     * @author yls
     * @param string     $sql
     * @param array|null $bindParams
     * @return array
     */
    private function readData(string $sql, array $bindParams = null)
    {
        $sql = $this->loadPrefix($sql);

        //执行SQL
        try {
            $this->lastSql = $sql;
            $connection = $this->getReadConnection();
            $result     = $connection->query($sql, $bindParams);
            $result->setFetchMode(Db::FETCH_ASSOC);
            return $result->fetchAll();
        } catch (Exception $e) {
            Log::write('sql', 'SQL:' . $sql . ' VALUE:' . json_encode($bindParams, JSON_UNESCAPED_UNICODE), 'error');
            Log::write('sql', $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * 执行sql(只写)
     *
     * @param string $sql
     * @param array  $params
     * @return boolean|int     新增时返回新增的ID
     */
    private function execute($sql, $params)
    {
        $sql = $this->loadPrefix($sql);
        try {
            $this->lastSql = $sql;
            $this->getWriteConnection()->execute($sql, $params);

            return $this->getWriteConnection()->lastInsertId() > 0 ? $this->getWriteConnection()->lastInsertId() : $this->getWriteConnection()->affectedRows();
        } catch (\Exception $e) {
            Log::write('sql', 'SQL:' . $sql . ' VALUE:' . json_encode($params, JSON_UNESCAPED_UNICODE), 'error');
            Log::write('sql', $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 对结果进行过滤处理
     *
     * @author yls
     * @param array $row
     * @return array
     */
    private function dealResult(array $row) : array
    {
        if (empty($row)) {
            return $row;
        }

        foreach ($row as $key => &$val) {
            if (is_array($val)) {
                foreach ($val as $k => &$v) {
                    $v = $this->dealResultTime($v);
                }
            } else {
                $val = $this->dealResultTime($val);
            }
        }
        unset($val, $v);
        return $row;
    }

    /**
     * 处理空时间
     *
     * @author yls
     * @param $val
     * @return string
     */
    private function dealResultTime($val)
    {
        if ('0000-00-00 00:00:00' == $val) {
            return '';
        } else {
            return $val;
        }
    }

    /**
     * 获取最后一条sql
     *
     * @author yls
     * @return string|null
     */
    public function getLastSql() :? string
    {
        return $this->lastSql;
    }
}