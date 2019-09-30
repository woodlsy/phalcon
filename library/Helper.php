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
     * @param array $content
     * @return string
     */
    public static function jsonEncode(array $content) : string
    {
        return json_encode($content, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 金额/百分比转化后给前端显示
     *
     * @author yls
     * @param int $money
     * @return string
     */
    public static function moneyToShow(int $money) : string
    {
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
     * 把数组转换为key => value格式
     *
     * @author yls
     * @param array  $array
     * @param string $keyField
     * @param string $valueField
     * @return array
     */
    public static function arrayToKeyValue(array $array, string $keyField, string $valueField) : array
    {
        if (is_array(current($array))) {
            $arr = [];
            foreach ($array as $value) {
                $arr[$value[$keyField]] = $value[$valueField];
            }
            return $arr;
        }
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

}
