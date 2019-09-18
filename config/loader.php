<?php

$loader = new \Phalcon\Loader();

/**
 * We're a registering a set of directories taken from the configuration file
 */
$moduleNamespaces = [
        APP_DIR => APP_PATH,
];
$loader->registerNamespaces($moduleNamespaces);
$loader->register();