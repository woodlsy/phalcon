<?php

namespace woodlsy\phalcon\library;

use DateTime;
use Exception;

/**
 * Class Helper
 *
 * @author yls
 * @package library
 */
class Helper
{
    /******************************* 数组 ****************************************/

    /**
     * 从一个二维数组中获取某一个key键的值数组
     *
     * @author yls
     * @param array  $data
     * @param string $key
     * @return array
     */
    public static function getValueArray(array $data, string $key) : array
    {
        return array_column($data, $key);
    }

    /**
     * 转化为展示前端的键值对
     *
     * @author yls
     * @param array       $data
     * @param string|null $key
     * @param string|null $value
     * @return array
     */
    public static function showPair(array $data, string $key = null, string $value = null) : array
    {
        if (empty($data)) {
            return $data;
        }

        $arr = [];
        foreach ($data as $k => $v) {
            if (null === $key || null === $value) {
                $arr[] = ['key' => $k, 'value' => $v];
            } else {
                $arr[] = ['key' => $v[$key], 'value' => $v[$value]];
            }
        }
        return $arr;
    }

    /**
     * 把数组转换为key => value格式
     *
     * @author yls
     * @param array  $array
     * @param string $keyField
     * @param string $valueField
     * @return array
     */
    public static function getPairs(array $array, string $keyField, string $valueField) : array
    {
        if (is_array(current($array))) {
            return array_column($array, $valueField, $keyField);
        }
        return $array;
    }

    /**
     * 取数组其中一个字段的值来做索引
     *
     * @author yls
     * @param array  $arr
     * @param string $field
     * @return array
     */
    public static function getIndexArray(array $arr, string $field)
    {
        if (empty($arr)) {
            return $arr;
        }
        $data = [];
        foreach ($arr as $key => $val) {
            $data[($val[$field] ?? '')] = $val;
        }

        return $data;
    }

    /******************************* 字符串 ****************************************/

    /**
     * 获取随机字符串
     *
     * @author yls
     * @param int $length
     * @param int $type 1:$str1 2:$str2 3:$str1和$str2 4:$str3 5:$str1和$str3 6:$str2和$str3 7:$str1和$str2和$str3
     * @return bool|string
     */
    public static function randString(int $length, int $type = 7)
    {
        $str1 = 'QWERTYUIOPASDFGHJKLZXCVBNM';
        $str2 = '1234567890';
        $str3 = 'qwertyuiopasdfghjklzxcvbnm';
        $str  = '';
        if (self::hasBitwise(1, $type)) {
            $str .= $str1;
        }
        if (self::hasBitwise(2, $type)) {
            $str .= $str2;
        }
        if (self::hasBitwise(4, $type)) {
            $str .= $str3;
        }
        return substr(str_shuffle($str), mt_rand(0, strlen($str) - ($length + 1)), $length);
    }

    /**
     * 金额/百分比转化后给前端显示
     *
     * @author yls
     * @param int $money
     * @return string
     */
    public static function moneyToShow($money) : string
    {
        if (false !== strpos($money, '.')) {
            return (string) $money;
        }
        return sprintf("%.2f", $money / 100);
    }

    /**
     * 金额/百分比从前端获取转化后保存
     *
     * @author yls
     * @param string $money
     * @return int
     */
    public static function moneyToSave(string $money) : int
    {
        return (int) bcmul($money, '100');
    }

    /**
     * 根据区code，返回省市区code
     *
     * @author yls
     * @param string $districtCode
     * @return array
     */
    public static function getRegionCodeByDistrictCode(string $districtCode) : array
    {
        $provinceCode = substr($districtCode, 0, 2);
        $cityCode     = substr($districtCode, 0, 4);
        return ['province_code' => $provinceCode . '0000', 'city_code' => $cityCode . '00', 'district_code' => $districtCode];
    }

    /******************************* 时间 ****************************************/

    /**
     * 获取时间格式
     *
     * @author yls
     * @param string|null $format
     * @param string|null $time
     * @return string
     */
    public static function now(string $format = null, $time = null) : string
    {
        $format = $format ? : 'Y-m-d H:i:s';
        $time   = $time ? : time();
        return date($format, $time);
    }

    /**
     * 两个日期之间的天数差
     *
     * @author yls
     * @param string $startTime
     * @param string $endTime
     * @return int
     * @throws Exception
     */
    public static function diffOfDaysBetweenDates(string $startTime, string $endTime):int
    {
        $sTime = new DateTime($startTime);
        $eTime = new DateTime($endTime);
        return (int)$sTime->diff($eTime)->days;
    }

    /******************************* 号码 ****************************************/

    /**
     * 验证身份证号
     * 身份证验证规则：
     * 第十八位数字（校验码）的计算方法为：
     * 1.将前面的身份证号码17位数分别乘以不同的系数。从第一位到第十七位的系数分别为：7 9 10 5 8 4 2 1 6 3 7 9 10 5 8 4 2
     * 2.将这17位数字和系数相乘的结果相加与11进行相除。
     * 3.余数0 1 2 3 4 5 6 7 8 9 10这11个数字，其分别对应的最后一位身份证的号码为1 0 X 9 8 7 6 5 4 3 2。
     * 4.例如 余数为 0 , 则身份证最后一位就是1
     *       余数为 2 , 则身份证最后一位就是罗马数字X
     *       余数为 10 , 则身份证最后一位就是2
     *
     * @author yls
     * @param string $cardNo
     * @return bool
     */
    public static function checkIDCardNo(string $cardNo) : bool
    {
        if (strlen($cardNo) !== 15 && strlen($cardNo) !== 18) {
            return false;
        }
        $cardNoCoefficient = [7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2];
        $sum               = 0;
        foreach ($cardNoCoefficient as $key => $val) {
            $sum += $cardNo[$key] * $val;
        }
        $remainderArr = [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $lastArr      = [1, 0, 'X', 9, 8, 7, 6, 5, 4, 3, 2];
        $last         = '';
        foreach ($remainderArr as $k => $v) {
            if ($v === $sum % 11) {
                $last = (string) $lastArr[$k];
            }
        }
        if ($last !== substr($cardNo, -1)) {
            return false;
        }
        return true;
    }

    /**
     * 从身份证号码获取男女  1 男 2 女
     *
     * @author yls
     * @param string $cardNo
     * @return int
     */
    public static function getSexByIDCardNo(string $cardNo) : int
    {
        $n = substr($cardNo, -2, 1);
        if (false === $n) {
            return 0;
        }
        return true === self::isEven((int) $n) ? 2 : 1;
    }

    /**
     * 验证手机号码
     *
     * @author yls
     * @param string $mobile
     * @return bool
     */
    public static function checkMobile(string $mobile) : bool
    {
        if (preg_match('/^1[3456789]{1}\d{9}$/', $mobile)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 脱敏手机号
     *
     * @author yls
     * @param string $mobile
     * @return string
     */
    public static function enMobile(string $mobile) : string
    {
        return empty($mobile) ? '' : substr_replace($mobile, '****', 3, 4);
    }

    /**
     * 批量手机号脱敏
     *
     * @author yls
     * @param array $data
     * @return array
     */
    public static function enMobileBatch(array $data) : array
    {
        if (empty($data)) {
            return $data;
        }
        if (is_array(current($data))) {
            foreach ($data as &$value) {
                $value = self::enMobileBatch($value);
            }
        } else {
            if (isset($data['mobile'])) {
                $data['mobile'] = self::enMobile($data['mobile']);
            }
        }
        return $data;
    }

    /******************************* 其他 ****************************************/

    /**
     * 解析json
     *
     * @author yls
     * @param string $content
     * @param bool   $type
     * @return array|object
     */
    public static function jsonDecode(string $content, bool $type = true)
    {
        return json_decode($content, $type);
    }

    /**
     * 转为json格式
     *
     * @author yls
     * @param array|object $content
     * @return string
     */
    public static function jsonEncode($content) : string
    {
        return json_encode($content, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 位运算 判断$needle是否包含在$haystack中
     *
     * @author yls
     * @param int $needle 需要判断值
     * @param int $haystack 用户当前值
     * @return bool 是否存在
     */
    public static function hasBitwise(int $needle, int $haystack) : bool
    {
        return ($haystack & $needle) === $needle;
    }

    /**
     * 位运算 在$haystack中增加一个值$needle
     *
     * @author yls
     * @param int $needle
     * @param int $haystack
     * @return int 新值
     */
    public static function addBitwise(int $needle, int $haystack) : int
    {
        if (self::hasBitwise($needle, $haystack)) {
            return $haystack;
        }
        return ($needle | $haystack);
    }

    /**
     * 位运算 在$haystack中删除一个值$needle
     *
     * @author yls
     * @param int $needle
     * @param int $haystack
     * @return int 新值
     */
    public static function removeBitwise(int $needle, int $haystack) : int
    {
        if (!self::hasBitwise($needle, $haystack)) {
            return $haystack;
        }
        return $haystack ^ $needle;
    }

    /**
     * 是否是偶数 true是
     *
     * @author yls
     * @param int $num
     * @return bool
     */
    public static function isEven(int $num) : bool
    {
        return !boolval($num & 1);
    }

    /**
     * 数组递归成树形结构
     *
     * @author yls
     * @param array  $data
     * @param string $idName ID字段名
     * @param string $parentIdFieldName 父级字段名
     * @param int    $parentId 从第几级开始
     * @return array
     */
    public static function getTreeStructure(array $data, string $idName = 'id', string $parentIdFieldName = 'parent_id', int $parentId = 0) : array
    {
        if (empty($data)) {
            return [];
        }
        $arr = [];
        foreach ($data as $value) {
            if ($parentId === (int) $value[$parentIdFieldName]) {
                $children = self::getTreeStructure($data, $idName, $parentIdFieldName, $value[$idName]);
                if (!empty($children)) {
                    $value['children'] = $children;
                }
                $arr[] = $value;
            }
        }
        return $arr;
    }
}
