<?php
declare(strict_types = 1);

namespace woodlsy\phalcon\library;

class YarClient
{
    public static $content_timeout = 1000;

    /*
     * YAR_VERSION
        YAR_OPT_PACKAGER  打包方式
        YAR_OPT_PERSISTENT 设置为true，能够使HTTP保存活动状态，加快调用，但在并行中并不起作用
        YAR_OPT_TIMEOUT 超时
        YAR_OPT_CONNECT_TIMEOUT 连接超时
        YAR_OPT_HEADER //从2.0.4开始
     */
    /**
     * 串行调用
     *
     * @author yls
     * @param string $url
     * @param array  $params
     * @return mixed
     */
    public static function syn(string $url, array $params = [])
    {
        $client = new \Yar_Client($url);
        /* the following setopt is optinal */
        $client->SetOpt(YAR_OPT_CONNECT_TIMEOUT, self::$content_timeout);

        /* call remote service */
        return $client->run($params);
    }

    /**
     * 编排并行
     *
     * @author yls
     * @param string      $url
     * @param array       $params
     * @param string $callback
     */
    public static function call(string $url, array $params = [], string $callback = '\woodlsy\phalcon\library\YarClient::success_callback')
    {
        \Yar_Concurrent_Client::call($url, "run", $params, $callback, '\woodlsy\phalcon\library\YarClient::error_callback');
    }

    /**
     * 发起并行
     *
     * @author yls
     */
    public static function send()
    {
        \Yar_Concurrent_Client::loop();
    }

    /**
     * 回调错误记入日志
     *
     * @author yls
     * @param $type
     * @param $error
     * @param $callinfo
     */
    public static function error_callback($type, $error, $callinfo)
    {
        Log::write('callback', Helper::jsonEncode($error) . '|' . Helper::jsonEncode($callinfo), 'yar');
    }

    /**
     * 默认回调函数
     *
     * @author yls
     * @param $retval
     * @param $callinfo
     * @return bool
     */
    public static function success_callback($retval, $callinfo)
    {
        return true;
    }
}