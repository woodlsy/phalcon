<?php
namespace woodlsy\phalcon\library;

use Phalcon\DI;
use Exception;

/**
 * Class Redis
 *
 * @author yls
 * @package library
 */
class Redis
{
    public $obj;
    private static $_instance = null;

    /**
     * 声明实例
     *
     * @author yls
     * @param array $config
     * @return bool|\Redis
     */
    public static function getInstance(array $config=[])
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance->init($config);
    }

    /**
     * 初始化
     *
     * @author yls
     * @param $config
     * @return bool|\Redis
     */
    public function init($config)
    {
        $redisConfig = empty($config) ? DI::getDefault()->get('config')->redis->toArray() : $config;
        //Log::write('pack', 'redis配置：' . json_encode($redisConfig, JSON_UNESCAPED_UNICODE), 'redis_config');
        try {
            $this->obj = new \Redis();
            $this->obj->connect($redisConfig['host'], $redisConfig['port']);
            if (!empty($redisConfig['password'])) {
                $this->obj->auth($redisConfig['password']);
            }
//             $this->obj = new \RedisCluster(NULL, $redisConfig['default']['host']);

            if (!$this->obj) {
                trigger_error('redis|redis连接失败，host：'.json_encode($redisConfig['host'], JSON_UNESCAPED_UNICODE));
                throw new Exception('redis failed');
            }
        } catch(Exception $e){
            trigger_error('redis|'.$e->getMessage());
            throw new Exception('redis failed');
        }
        
        //设置前缀
        $this->obj->setOption(\Redis::OPT_PREFIX, $redisConfig['prefix']);

        return $this->obj;
    }

    /**
     * 声明实例 不带前缀
     *
     * @author yls
     * @param array $config
     * @return bool|\Redis
     */
    public static function getPrefix(array $config=[])
    {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance->initPrefix($config);
    }

    /**
     * 不带前缀
     *
     * @author yls
     * @param $config
     * @return bool|\Redis
     */
    public function initPrefix($config)
    {
        $redisConfig = empty($config) ? DI::getDefault()->get('config')->redis->toArray() : $config;
        //Log::write('pack', 'redis配置：' . json_encode($redisConfig, JSON_UNESCAPED_UNICODE), 'redis_config');
        try {
            $this->obj = new \Redis();
            $this->obj->connect($redisConfig['host'], $redisConfig['port']);
            if (!empty($redisConfig['password'])) {
                $this->obj->auth($redisConfig['password']);
            }
            //             $this->obj = new \RedisCluster(NULL, $redisConfig['default']['host']);

            if (!$this->obj) {
                trigger_error('redis|redis连接失败，host：'.json_encode($redisConfig['host'], JSON_UNESCAPED_UNICODE));
                throw new Exception('redis failed');
            }
        } catch(Exception $e){
            trigger_error('redis|'.$e->getMessage());
            throw new Exception('redis failed');
        }

        return $this->obj;
    }


}