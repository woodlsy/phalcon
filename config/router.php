<?php

$router = $di->getRouter();

$router->add(
    "/:module/:controller/:action/:params\.json",
    [
        "module"     => 1,
        "controller" => 2,
        "action"     => 3,
        "params"     => 4,
    ]
    );

$router->setDefaultModule("index");

$router->handle();
