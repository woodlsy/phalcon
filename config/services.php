<?php

use Phalcon\Mvc\View;
use Phalcon\Session\Adapter\Files as Session;
use Phalcon\Mvc\Dispatcher;

/**
 * Shared configuration service
 */
$di->setShared('config', function () {
    return include WOODLSY_PHALCON_PATH . "/config/config.php";
});

$config = $di->getConfig();

/**
 * Setting up the view component
 */
$di->set(
    "view",
    function () use ($config) {
        $view = new View();

        // A trailing directory separator is required
        $view->setViewsDir($config->application->viewsDir . '/' . $this->get('router')->getModuleName());
        $view->setLayoutsDir('layouts/');
        $view->setLayout('index');

//         $view->registerEngines([".html"   => "Phalcon\\Mvc\\View\\Engine\\Php"]);
        return $view;
    },
    true
);

/**
 * Database connection is created based in the parameters defined in the configuration file
 */
//主库
$di->setShared('dbMaster', function () {
    $config = $this->getConfig();

    $class  = 'Phalcon\Db\Adapter\Pdo\\' . $config->application->database->master->adapter;
    $params = $config->application->database->master->toArray();

    if ($config->application->database->master->adapter == 'Postgresql') {
        unset($params['charset']);
    }

    $connection = new $class($params);

    return $connection;
});

// Start the session the first time when some component request the session service
$di->setShared(
    "session",
    function () {
        $session = new Session();

        $session->start();

        return $session;
    }
);

if (true !== (bool) $config->open_modules) {
    /************ 单模块时开启 start *************/
    $di->set(
        "dispatcher",
        function () {
            $dispatcher = new Dispatcher();

            $dispatcher->setDefaultNamespace(APP_DIR . "\controllers");

            return $dispatcher;
        }
    );
    /************ 单模块时开启 end *************/
}
