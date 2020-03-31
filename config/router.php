<?php

$router = $di->getRouter();

$suffix = $config->suffix;
if (true === (bool) $config->open_modules) {
    $router->add(
        "/:module/:controller/:action/:params" . (!empty($suffix) ? "\.{$suffix}" : $suffix),
        [
            "module"     => 1,
            "controller" => 2,
            "action"     => 3,
            "params"     => 4,
        ]
    );

    $router->setDefaultModule("index");
} else {
    $router->add(
        "/:controller/:action/:params" . (!empty($suffix) ? "\.{$suffix}" : $suffix),
        [
            "controller" => 1,
            "action"     => 2,
            "params"     => 3,
        ]
    );
}
$router->handle();
