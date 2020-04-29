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
 * Database connection is created based in the parameters defined in the configuration file
 */
foreach ($config->db->toArray() as $key => $val) {
    $class = 'Phalcon\Db\Adapter\Pdo\\' . $val['adapter'];
    $params = $val;

    if ($val['adapter'] == 'Postgresql') {
        unset($params['charset']);
    }

    $connection = new $class($params);
    $di->setShared($key, $connection);
}


// Start the session the first time when some component request the session service
$di->setShared(
    "session",
    function () {
        $session = new Session();

        $session->start();

        return $session;
    }
);

$di->set('crypt', function (){
    $crypt = new Phalcon\Crypt();
    $crypt->setKey($_SERVER['HTTP_HOST']); //salt
    return $crypt;
});

if (true !== (bool) $config->open_modules && 'cli' !== RUN_TYPE) {
    $di->set(
        "dispatcher",
        function () {
            $dispatcher = new Dispatcher();

            $dispatcher->setDefaultNamespace(APP_DIR . "\controllers");

            return $dispatcher;
        }
    );

    /**
     * Setting up the view component
     */
    $di->set(
        "view",
        function () use ($config) {
            $view = new View();

            // A trailing directory separator is required
            $view->setViewsDir($config->viewsDir);
//        $view->setLayoutsDir('layouts/');
//        $view->setLayout('index');

//         $view->registerEngines([".html"   => "Phalcon\\Mvc\\View\\Engine\\Php"]);
            return $view;
        },
        true
    );
}
