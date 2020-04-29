<?php

use Phalcon\Di\FactoryDefault\Cli as CliDI;
use Phalcon\Cli\Console as ConsoleApp;
use Phalcon\Loader;

error_reporting(E_ALL);

defined('APP_DIR') or define('APP_DIR', 'application');
defined('BASE_PATH') or define('BASE_PATH', dirname(__DIR__) . '/../..');
defined('APP_PATH') or define('APP_PATH', dirname(__DIR__) . '/../../' . APP_DIR);
define('WOODLSY_PHALCON_PATH', dirname(__DIR__).'/phalcon');
defined('APP_NAME') or define('APP_NAME', 'app');
defined('RUN_TYPE') or define('RUN_TYPE', 'cli');
date_default_timezone_set('Asia/Shanghai');

require BASE_PATH . '/vendor/autoload.php';

// 使用CLI工厂类作为默认的服务容器
$di = new CliDI();
try {
    /**
     * Read services
     */
    include WOODLSY_PHALCON_PATH . '/config/services.php';

    /**
     * 注册类自动加载器
     */
    $loader = new Loader();

    $loader->registerDirs(
        [
            BASE_PATH . '/tasks',
        ]
    );
    $moduleNamespaces = [
        APP_DIR => APP_PATH,
    ];
    $loader->registerNamespaces($moduleNamespaces);
    $loader->register();


    // 创建console应用
    $console = new ConsoleApp();

    $console->setDI($di);

    $di->setShared("console", $console);

    /**
     * 处理console应用参数
     */
    $arguments = [];

    foreach ($argv as $k => $arg) {
        if ($k == 1) {
            $arguments["task"] = $arg;
        } elseif ($k == 2) {
            $arguments["action"] = $arg;
        } elseif ($k >= 3) {
            $arguments["params"][] = $arg;
        }
    }


    // 处理参数
    $console->handle($arguments);
} catch (Exception $e) {
    echo $e->getMessage().' '.$e->getFile().' '.$e->getLine()."\n";
}