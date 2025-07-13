<?php

require dirname(__DIR__) . '/src/Router.php';

header_remove('X-Powered-By');

$router = new SzepeViktor\WordPress\Dot404\Router();
$router->handle();
