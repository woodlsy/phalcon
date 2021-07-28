<?php
/*
 * Modified: prepend directory path of current file, because of this file own different ENV under between Apache and command line.
 * NOTE: please remove this comment.
 */

use Phalcon\Config;

$config = [
    'open_modules'     => true, // 是否开启多模块，true 是 false 否
    'modules'          => [
        'index',
    ],
    'exception'        => woodlsy\phalcon\library\DiyException::class,
    'yar_service'      => false,
    'limit_request'    => true, // 限制频繁请求 true 是 false 否
    'csrf'             => true, // 是否开启csrf true是 false 否
    'csrf_key_name'    => 'tokenKey',
    'csrf_key_value'   => 'tokenValue',
    'logsPath'         => '/data/logs/' . APP_NAME . '/',
    'viewsDir'         => APP_PATH . '/views/',
    'debug'            => true,
    'suffix'           => '', // url后缀
    'pSql'             => false, // 打印sql
    'isCast'           => false, // 强制转换数据类型 开启强制转换则必须配置好redis
    'example-redis'    => [
        'host'     => '127.0.0.1',
        'port'     => '6379',
        'password' => '123456', //密码 默认为空
        'prefix'   => 'app_', //KEY的前缀 默认 空
    ],
    'example-db'       => [
        'master' => [
            'adapter'  => 'mysql',
            'host'     => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'port'     => '3306',
            'dbname'   => 'user',
            'prefix'   => 'pr_',
            'charset'  => 'utf8',
        ],
    ],
    'example-rabbitMQ' => [
        'host'     => '127.0.0.1',
        'port'     => '5672',
        'vhost'    => '/',  // '/' 默认的虚拟主机
        'login'    => 'guest',
        'password' => 'guest',
    ]
];

if (file_exists(APP_PATH . '/config/config.php')) {
    $appConfig = require_once APP_PATH . '/config/config.php';
    $config    = array_merge($config, $appConfig);
}

return new Config($config);
