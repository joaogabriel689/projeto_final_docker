<?php

// 1. Extrai apenas o caminho da URL (ignora query strings como ?id=1)
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// 2. Se for a raiz ou index.php, direciona para a 'home'
if ($uri === '/' || $uri === '/index.php') {
    $uri = '/home';
}

// 3. Monta o caminho absoluto até o arquivo da página
$file = __DIR__ . '/../pages' . $uri . '.php';

// 4. Checa se o arquivo existe de fato na pasta /pages
if (file_exists($file)) {
    require $file;
} else {
    // Se o arquivo não existir (ex: /pagina-que-nao-existe), aí sim carrega o 404
    http_response_code(404);
    require __DIR__ . '/../pages/404.php';
}