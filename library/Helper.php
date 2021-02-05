<?php

namespace woodlsy\phalcon\library;

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
        if (empty($data)) {
            return [];
        }
        if (!is_array(current($data))) {
            return [];
        }
        $arr = [];
        foreach ($data as $value) {
            if (isset($value[$key])) {
                $arr[] = $value[$key];
            }
        }
        return $arr;
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
            $arr = [];
            foreach ($array as $value) {
                $arr[$value[$keyField]] = $value[$valueField];
            }
            return $arr;
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
        $str = '';
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
            return (string)$money;
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

    /**
     * 获取时间格式
     *
     * @author yls
     * @param string|null $format
     * @param string|null $time
     * @return string
     */
    public static function now(string $format = null, string $time = null) : string
    {
        if (null === $format) {
            $format = 'Y-m-d H:i:s';
        }
        if (null === $time) {
            $time = time();
        }
        return date($format, $time);
    }

    /******************************* 其他 ****************************************/

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
     * 脱敏手机号
     *
     * @author yls
     * @param string $mobile
     * @return string
     */
    public static function enMobile(string $mobile) : string
    {
        return empty($mobile) ? '' : substr_replace($mobile,'****',3,4);
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


}
