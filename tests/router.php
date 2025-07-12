<?php

require dirname(__DIR__) . '/src/Router.php';

$router = new SzepeViktor\WordPress\Dot404\Router();
$router->handle();
