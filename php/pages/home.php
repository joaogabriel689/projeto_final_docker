<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Séries</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="page">
    <h1>Lista de Séries
        <span class="subtitle">Catálogo completo das suas séries cadastradas</span>
    </h1>

    <div class="top-actions">
        <a class="btn" href="/criar-editar">+ Nova Série</a>
    </div>

    <?php
    require_once __DIR__ . '/../requests/requests.php';
    $request = new SendRequest('http://api:8000/');
    $response = $request->send();
    ?>

    <?php if ($response['status_code'] === 200 && is_array($response['response']) && count($response['response']) > 0): ?>
        <ul class="series-grid">
            <?php foreach ($response['response'] as $serie): ?>
                <?php
                    $id = $serie['id'] ?? $serie['id_serie'] ?? '';
                    $nome = $serie['titulo'] ?? $serie['nome'] ?? $serie['title'] ?? 'Série sem nome';
                ?>
                <li class="series-card">
                    <a href="/serie?nome=<?= urlencode((string)$nome) ?>">
                        <?php echo htmlspecialchars((string)$nome); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php elseif ($response['status_code'] === 200): ?>
        <div class="state-card">
            <h1>Nenhuma série cadastrada</h1>
            <p>Que tal <a href="/criar-editar">criar a primeira</a>?</p>
        </div>
    <?php else: ?>
        <div class="error-message">
            Erro ao buscar séries: <?php echo htmlspecialchars($response['error'] ?? 'Erro desconhecido'); ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>