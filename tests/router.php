<?php

require dirname(__DIR__) . '/src/Router.php';

header_remove('X-Powered-By');

$router = new SzepeViktor\WordPress\Dot404\Router();
$router->disallow_non_ascii_slug();
$router->handle();
