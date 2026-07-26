<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Séries</title>
</head>
<body>
    <h1>Lista de Séries</h1>
    <?php
    require_once __DIR__ . '/../requests/requests.php';
    $request = new SendRequest('http://api:8000/');
    $response = $request->send();
    ?>

    <ul>
        <?php if ($response['status_code'] === 200 && is_array($response['response'])): ?>
            <?php foreach ($response['response'] as $serie): ?>
                <?php 
                    // Tenta pegar id / ID / id_serie ou usa string vazia caso não exista
                    $id = $serie['id'] ?? $serie['id_serie'] ?? '';
                    
                    // Tenta pegar name / nome / title / titulo ou usa 'Sem nome'
                    $nome = $serie['name'] ?? $serie['nome'] ?? $serie['title'] ?? $serie['titulo'] ?? 'Série sem nome';
                ?>
                <li>
                    <a href="/series/<?php echo htmlspecialchars((string)$id); ?>">
                        <?php echo htmlspecialchars((string)$nome); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li>Erro ao buscar séries: <?php echo htmlspecialchars($response['error'] ?? 'Erro desconhecido'); ?></li>
        <?php endif; ?>
    </ul>
</body>
</html>