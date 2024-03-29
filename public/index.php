<?php

use Phalcon\Debug;
use Phalcon\Mvc\Application;
use Phalcon\Di\FactoryDefault;
use woodlsy\phalcon\library\Helper;
use woodlsy\phalcon\library\Log;
use woodlsy\phalcon\library\Redis;

error_reporting(E_ALL);

defined('APP_DIR') or define('APP_DIR', 'application');
defined('BASE_PATH') or define('BASE_PATH', dirname(__DIR__) . '/../../..');
defined('APP_PATH') or define('APP_PATH', dirname(__DIR__) . '/../../../' . APP_DIR);
define('WOODLSY_PHALCON_PATH', dirname(__DIR__));
defined('APP_NAME') or define('APP_NAME', 'app');
defined('RUN_TYPE') or define('RUN_TYPE', 'cgi');
date_default_timezone_set('Asia/Shanghai');

require BASE_PATH . '/vendor/autoload.php';

$di = new FactoryDefault();

/**
 * Read services
 */
include WOODLSY_PHALCON_PATH . '/config/services.php';

/**
 * Handle routes
 */
include WOODLSY_PHALCON_PATH . '/config/router.php';

/**
 * Include Autoloader
 */
include WOODLSY_PHALCON_PATH . '/config/loader.php';

Log::setTriggerError();

if (true === (bool) $config->debug) {
    $debug = new Debug();
    $debug->listen();
}

try {
    // 创建应用
    $application = new Application($di);

    if (true === (bool) $config->open_modules) {
        $moduels = [];
        foreach ($config->modules as $moduleName) {
            $moduels[$moduleName] = [
                "className" => APP_DIR . "\\modules\\{$moduleName}\\Module",
                "path"      => APP_PATH . "/modules/{$moduleName}/Module.php",
            ];
        }
        // 注册模块
        $application->registerModules($moduels);
    }

    if (true === (bool) $config->yar_service) {
        $service = new Yar_Server(new \woodlsy\phalcon\library\YarService($application));
        $service->handle();
    } else {
        // 处理请求
        $response = $application->handle();
        $response->send();
    }
} catch (Exception $e) {
    if (get_class($e) === $config->exception) {
        if (true === $application->config->limit_request && $application->request->isPost()) {
            $moduleName     = $application->router->getModuleName();
            $controllerName = $application->router->getControllerName();
            $actionName     = $application->router->getActionName();
            $key            = session_id() . '_controller_lock_' . $moduleName . '_' . $controllerName . '_' . $actionName;
            Redis::getInstance()->del($key);
        }

        header('Content-type: application/json');
        echo Helper::jsonEncode(['code' => $e->getCode() ? : 1, 'msg' => $e->getMessage()]);
    } else {
        if (true === (bool) $config->debug) {
            echo $debug->onUncaughtException($e);
        } else {
            echo '系统错误，请联系管理员';
            Log::write('system', $e->getMessage() . '|' . $e->getFile() . '|' . $e->getLine());
        }
    }
}