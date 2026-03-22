<?php

require dirname(__DIR__) . '/src/Router.php';

header_remove('X-Powered-By');

$router = new class() extends SzepeViktor\WordPress\Dot404\Router {
    protected function is_permalink(): bool
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $target = $_SERVER['DOCUMENT_ROOT'] . $uri;

        return $uri !== '/' && !is_file($target);
    }
};
$router->disallow_non_ascii_slug();
$router->handle();
