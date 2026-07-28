<?php


$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


if ($uri === '/' || $uri === '/index.php') {
    $uri = '/home';
}

$file = __DIR__ . '/../pages' . $uri . '.php';


if (file_exists($file)) {
    require $file;
} else {
    http_response_code(404);
    require __DIR__ . '/../pages/404.php';
}