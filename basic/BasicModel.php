<?php

namespace woodlsy\phalcon\basic;

use woodlsy\phalcon\library\Helper;
use woodlsy\phalcon\library\Log;
use Phalcon\DI;
use Phalcon\Mvc\Model;
use Phalcon\Db;
use Exception;
use woodlsy\phalcon\library\Redis;

abstract class BasicModel extends Model
{
    protected $_targetTable = null;

    protected $lastSql = null;

    //当前来连接的数据源
    protected $_targetDb = 'master';

    protected $admin = null;

    // 是否强制转换字段类型
    protected $isCast = false;
    protected $castDiffTime = 0.1;
    protected $columnsRedisTtl = 600;

    protected $createAtFieldName = 'create_at';
    protected $updateAtFieldName = 'update_at';
    protected $createByFieldName = 'create_by';
    protected $updateByFieldName = 'update_by';

    /**
     * 初始化
     *
     * @author yls
     * @throws Exception
     */
    public function initialize()
    {
        //初始化数据库
        if (!empty($this->_targetDb)) {
            if (!isset(DI::getDefault()->get('config')->db->{$this->_targetDb})) {
                Log::write('sql', "数据库｛{$this->_targetDb}｝连接不存在", 'error');
                throw new Exception('数据库连接失败');
            }
        }
        $this->setTargetTable($this->_targetTable);
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
    protected function loadPrefix($string = null) : string
    {
        $db_prefix = null;
        if (!$db_prefix) {
            $dbConfig  = $this->getReadConnection()->getDescriptor();
            $db_prefix = isset($dbConfig['prefix']) ? $dbConfig['prefix'] : null;
        }
        if (!$string || !is_string($string)) {
            $preg_name = str_replace('\\', '_', get_class($this));
            $string    = preg_replace('/^(([^_]_?)*Models_?)/i', '', $preg_name);
            $string    = '{{' . strtolower($string) . '}}';
        }

        return preg_replace('/{{(.+?)}}/', $db_prefix . '\\1', $string);
    }

    /**
     * 处理where条件，转化为sql
     * array(
     *     'id' => [1,2,3],               // id in (1,2,3)
     *     'status' => ['in', [1,2]],     // status in (1,2)
     *     'status' => ['not in', [1,2]],     // status not in (1,2)
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
    protected function dealWhere($where) : array
    {
        if (empty($where))
            return ['where' => '1=1', 'params' => []];
        if (!is_array($where))
            return ['where' => $where, 'params' => []];

        $fields = $val = [];

        foreach ($where as $key => $value) {
            if (empty($key) || is_numeric($key))
                continue;
            //            if (is_string($value[0])) $value[0] = trim(strtolower($value[0]));
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
                } elseif ($value[0] == 'not in') {
                    $w = [];
                    for ($i = 1; $i <= count($value[1]); $i++) {
                        $w[] = '?';
                    }
                    $fields[] = "`{$key}` not in (" . implode(',', $w) . ')';
                    foreach ($value[1] as $v) {
                        $val[] = $v;
                    }
                } elseif ($value['0'] == 'or') {
                    $childWhere  = [];
                    $childParams = [];
                    foreach ($value as $k => $v) {
                        if ($k === 0)
                            continue;
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
     */
    abstract public function attribute();

    /**
     * 处理需要查询的字段
     *
     * @author yls
     * @param string|array $fields
     * @return string
     */
    protected function dealFields($fields = '') : string
    {
        $defaultFields = $this->attribute();
        if ((empty($fields) || (is_string($fields) && '*' === trim($fields))) && !empty($defaultFields)) {
            $fields = array_keys($defaultFields);
        }
        if (is_array($fields)) {
            $selectFields = [];
            foreach ($fields as $key => $value) {
                if (is_numeric($key)) {
                    $selectFields[] = '`' . $value . '`';
                } else {
                    $selectFields[] = '`' . $key . '` as ' . $value;
                }
            }
            return implode(',', $selectFields);
        }
        return $fields;
    }

    /**
     * 处理新增数据
     *
     * @author yls
     * @param array $data
     * @return array
     */
    protected function dealInsertData(array $data) : array
    {
        $formatInsertData = $this->_getFormatInsertData($data);
        return ['val' => $formatInsertData['fieldStr'] . ' VALUES ' . $formatInsertData['valueStr'], 'params' => $formatInsertData['params']];
    }

    /**
     * 格式化处理要新增的数据
     *
     * @author yls
     * @param array $data
     * @return array
     */
    private function _getFormatInsertData(array $data) : array
    {
        $value = $fieldArr = $fieldPlaceholderArr = [];
        foreach ($data as $key => $val) {
            $fieldArr[]            = $key;
            $fieldPlaceholderArr[] = '?';
            $value[]               = $val;
        }
        $fieldStr = '(`' . implode('`,`', $fieldArr) . '`)';
        $valueStr = '(' . implode(',', $fieldPlaceholderArr) . ')';
        return ['fieldStr' => $fieldStr, 'valueStr' => $valueStr, 'params' => $value];
    }

    /**
     * 批量处理要新增的数据
     *
     * @author yls
     * @param array $data
     * @return array
     */
    public function dealInsertDataBatch(array $data) : array
    {
        $str      = '';
        $valueStr = $params = [];
        foreach ($data as $value) {
            $formatInsertData = $this->_getFormatInsertData($value);
            if ('' === $str) {
                $str = $formatInsertData['fieldStr'] . ' VALUES ';
            }
            $valueStr[] = $formatInsertData['valueStr'];
            $params     = array_merge($params, $formatInsertData['params']);
        }
        $str .= implode(',', $valueStr);
        return ['val' => $str, 'params' => $params];
    }

    /**
     * 处理更新数据
     * $data = [
     *    num => ['+', 1],
     * ];
     *
     * @author yls
     * @param array $data
     * @return array
     */
    protected function dealUpdateData(array $data) : array
    {
        $value = $fieldArr = [];
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                if (in_array($val[0], ['+', '-'])) {
                    $fieldArr[] = '`' . $key . '`=' . '`' . $key . '` ' . $val[0] . ' ?';
                    $value[]    = $val[1];
                }
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
     * @return int
     */
    public function insertData(array $data) : int
    {
        $data = $this->_dealInsertFields($data);
        $data = $this->dealInsertData($data);
        $sql  = "INSERT INTO {$this->_targetTable} " . $data['val'];

        return $this->execute($sql, $data['params']);
    }

    /**
     * 批量新增
     *
     * @author yls
     * @param array $data
     * @return int
     */
    public function insertDataBatch(array $data) : int
    {
        $data = $this->_dealInsertFields($data);
        $data = $this->dealInsertDataBatch($data);
        $sql  = "INSERT INTO {$this->_targetTable} " . $data['val'];

        return $this->execute($sql, $data['params']);
    }

    /**
     * 处理待新增的数据字段
     *
     * @author yls
     * @param array $data
     * @return array
     */
    private function _dealInsertFields(array $data) : array
    {
        if (!is_array(current($data)) || !isset($data[0])) {
            $fields = $this->attribute();
            if (isset($fields[$this->createAtFieldName]) && !isset($data[$this->createAtFieldName])) {
                $data[$this->createAtFieldName] = date('Y-m-d H:i:s');
            }
            if (isset($fields[$this->updateAtFieldName]) && !isset($data[$this->updateAtFieldName])) {
                $data[$this->updateAtFieldName] = date('Y-m-d H:i:s');
            }
            if (isset($fields[$this->createByFieldName]) && !isset($data[$this->createByFieldName]) && !empty($this->admin)) {
                $data[$this->createByFieldName] = $this->admin['id'];
            }
            if (isset($fields[$this->updateByFieldName]) && !isset($data[$this->updateByFieldName]) && !empty($this->admin)) {
                $data[$this->updateByFieldName] = $this->admin['id'];
            }
            return $data;
        }
        foreach ($data as $key => $value) {
            $data[$key] = $this->_dealInsertFields($value);
        }
        return $data;
    }

    /**
     * 更新数据
     *
     * @author yls
     * @param array $data
     * @param       $where
     * @param bool  $updateDate
     * @return int
     */
    public function updateData(array $data, $where, bool $updateDate = true) : int
    {
        $fields = $this->attribute();
        if (isset($fields[$this->updateAtFieldName]) && !isset($data[$this->updateAtFieldName]) && true === $updateDate)
            $data[$this->updateAtFieldName] = date('Y-m-d H:i:s');
        if (isset($fields[$this->updateByFieldName]) && !isset($data[$this->updateByFieldName]) && !empty($this->admin) && true === $updateDate)
            $data[$this->updateByFieldName] = $this->admin['id'];
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
     * @return int
     */
    public function deleteData(array $where) : int
    {
        $fields = $this->attribute();
        $data = [];
        if (isset($fields['is_deleted'])) {
            $data['is_deleted'] = 1;
        }
        if (isset($fields['deleted_at'])) {
            $data['deleted_at'] =  Helper::now();
        }
        if (empty($data)) {
            return 0;
        }
        return $this->updateData($data, $where);
    }

    /**
     * 物理删除数据
     *
     * @author yls
     * @param array $where
     * @return int
     */
    public function delData(array $where) : int
    {
        $whereSql = $this->dealWhere($where);
        $sql      = "DELETE FROM {$this->_targetTable} where " . $whereSql['where'];
        return $this->execute($sql, $whereSql['params']);
    }

    /**
     * 获取单条数据(通过主键)
     *
     * @param int  $id
     * @param null $fields
     * @return array|mixed
     */
    public function getById(int $id, $fields = NULL)
    {
        $fields = $this->dealFields($fields);
        $sql    = "select {$fields} from {$this->_targetTable} where `id` = ?";
        $params = [$id];
        $data   = $this->getRows($sql, $params);
        return $data[0] ?? array();
    }

    /**
     * 获取单条数据
     *
     * @param        $where
     * @param string|array $fields
     * @param string $orderBy
     * @param string $groupBy
     * @return array
     */
    public function getOne($where, $fields = "", string $orderBy = "", string $groupBy = '') : array
    {
        $data = $this->getList($where, $orderBy, 0, 1, $fields, $groupBy);
        return $data[0] ?? array();
    }

    /**
     * 获取列表(支持分页)
     *
     * @author yls
     * @param        $where
     * @param string $orderBy
     * @param null   $offset
     * @param null   $row
     * @param null   $fields
     * @param string $groupBy
     * @return array
     */
    public function getList($where, string $orderBy = '', $offset = NUll, $row = NUll, $fields = NUll, string $groupBy = '') : array
    {
        $whereSql = $this->dealWhere($where);
        $fields   = $this->dealFields($fields);
        if (!empty($orderBy)) {
            $orderBy = 'order by ' . $orderBy;
        }
        if (!empty($groupBy)) {
            $groupBy = 'group by ' . $groupBy;
        }

        $sql = "select {$fields} from {$this->_targetTable} where " . $whereSql['where'] . " {$groupBy} " . " {$orderBy}";
        if ($offset !== NULl && ($offset !== false && $offset >= 0 && $row > 0))
            $sql .= " limit {$offset},{$row}";
        return $this->getRows($sql, $whereSql['params']);
    }

    /**
     * 获取所有数据
     *
     * @author yls
     * @param        $where
     * @param null   $fields
     * @param string|null $orderBy
     * @param string $groupBy
     * @return array
     */
    public function getAll($where = [], $fields = NULL, string $orderBy = '', string $groupBy = '') : array
    {
        return $this->getList($where, $orderBy, null, null, $fields, $groupBy);
    }

    /**
     * 获取多条数据
     *
     * @author yls
     * @param string $sql
     * @param array  $params
     * @param bool   $isDeal 是否对数据进行处理
     * @return array
     */
    public function getRows(string $sql, array $params = [], bool $isDeal = true) : array
    {
        try {
            $rows = $this->readData($sql, $params);
            if ($rows && !empty($rows)) {
                return true === $isDeal ? $this->dealResult($rows) : $rows;
            } else {
                return [];
            }
        } catch (Exception $e) {
            Log::write('sql', $e->getMessage(), 'error');
            return [];
        }
    }

    /**
     * 获取总条数
     *
     * @author yls
     * @param        $where
     * @param array|string  $fields
     * @param string $groupBy
     * @return array|int
     */
    public function getCount($where, $fields = [], string $groupBy = '')
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
        $row = $this->getOne($where, $fieldStr, '', $groupBy);
        if (empty($row))
            return 0;
        return empty($fields) ? (int) $row['count_num'] : $row;
    }

    /**
     * 获取和
     *
     * @author yls
     * @param       $where
     * @param array|string $fields
     * @return array
     */
    public function getSum($where, $fields) : array
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
        return $this->getOne($where, implode(',', $fieldStr));
    }

    /**
     * 执行sql（只读）
     *
     * @author yls
     * @param string     $sql
     * @param array|null $bindParams
     * @return array
     */
    protected function readData(string $sql, array $bindParams = null) : array
    {
        $sql = $this->loadPrefix($sql);

        //执行SQL
        try {
            $startTime     = microtime(true);
            $this->lastSql = $sql;
            $connection    = $this->getReadConnection();
            $result        = $connection->query($sql, $bindParams);
            $result->setFetchMode(Db::FETCH_ASSOC);
            $endTime  = microtime(true);
            $diffTime = round(($endTime - $startTime), 3);
            $readData = $result->fetchAll();
            if (true === DI::getDefault()->get('config')->pSql) {
                Log::write('read', "【{$diffTime}】" . 'SQL:' . $sql . ' VALUE:' . json_encode($bindParams, JSON_UNESCAPED_UNICODE), 'sql');
            }
            return $readData;
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
     * @return int     新增时返回新增的ID
     */
    protected function execute(string $sql, array $params) : int
    {
        $sql = $this->loadPrefix($sql);

        try {
            $startTime     = microtime(true);
            $this->lastSql = $sql;
            $this->getWriteConnection()->execute($sql, $params);
            $endTime  = microtime(true);
            $diffTime = round(($endTime - $startTime), 3);
            if (true === DI::getDefault()->get('config')->pSql) {
                Log::write('write', "【{$diffTime}】" . 'SQL:' . $sql . ' VALUE:' . json_encode($params, JSON_UNESCAPED_UNICODE), 'sql');
            }
            return $this->getWriteConnection()->lastInsertId() > 0 ? $this->getWriteConnection()->lastInsertId() : $this->getWriteConnection()->affectedRows();
        } catch (Exception $e) {
            Log::write('sql', 'SQL:' . $sql . ' VALUE:' . json_encode($params, JSON_UNESCAPED_UNICODE), 'error');
            Log::write('sql', $e->getMessage(), 'error');
            return 0;
        }
    }

    /**
     * 对结果进行过滤处理
     *
     * @author yls
     * @param array $row
     * @return array
     */
        protected function dealResult(array $row) : array
    {
        if (empty($row)) {
            return $row;
        }

        $this->isCast = (bool) DI::getDefault()->get('config')->isCast;
        if (true === $this->isCast) {
            $startTime     = microtime(true);
            $row = $this->castType($row);
            $endTime  = microtime(true);
            $diffTime = round(($endTime - $startTime), 3);
            if ($diffTime > $this->castDiffTime) {
                Log::write('cast', "【{$diffTime}】".$this->lastSql, 'sql');
            }
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
     *  处理空时间
     *
     * @author yls
     * @param $val
     */
    protected function dealResultTime($val):string
    {
        if ('0000-00-00 00:00:00' === $val || '1990-01-01 00:00:00' === $val || '1990-01-01' === $val ||
            '0001-01-01' === $val || '0001-01-01 00:00:00' === $val
        ) {
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
    public function getLastSql() : ?string
    {
        return $this->lastSql;
    }

    /**
     * 强制转换类型
     *
     * @author yls
     * @param array $data
     * @return array
     */
    public function castType(array $data) : array
    {

        if (empty($data)) {
            return $data;
        }
        $key = 'COLUMNS_' . $this->_targetTable;
        try {
            if (!Redis::getInstance()->exists($key)) {
                $sql    = 'SHOW FULL COLUMNS FROM ' . $this->_targetTable;
                $fields = $this->getRows($sql, [], false);
                Redis::getInstance()->setex($key, $this->columnsRedisTtl, Helper::jsonEncode($fields));
            }
            $fields = Helper::jsonDecode(Redis::getInstance()->get($key));
        }catch (Exception $e) {
            Log::write('redis', $e->getMessage(), 'error');
            $sql    = 'SHOW FULL COLUMNS FROM ' . $this->_targetTable;
            $fields = $this->getRows($sql, [], false);
        }


        $dataFieldsName = is_array(current($data)) ? array_keys(current($data)) : array_keys($data);
        $fieldMap = [];
        foreach ($fields as $field) {
            if (
                in_array($field['Field'], $dataFieldsName) &&
                (0 === strpos($field['Type'], 'int(') ||
                0 === strpos($field['Type'], 'tinyint(') ||
                0 === strpos($field['Type'], 'bigint(') ||
                0 === strpos($field['Type'], 'mediumint(') ||
                0 === strpos($field['Type'], 'smallint('))
            ) {

                $fieldMap[$field['Field']] = true;
                break;
            }
        }
        if (empty($fieldMap)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_array($value) && is_numeric($key)) {
                $data[$key] = $this->castType($value);
            } elseif (isset($fieldMap[$key])) {
                $data[$key] = (int) $value;
            }
        }
        return $data;
    }
}