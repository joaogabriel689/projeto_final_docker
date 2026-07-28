<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhe da Série</title>
    <link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="page">
    <div class="top-actions">
        <a class="btn btn-secondary" href="/home">&larr; Voltar para a lista</a>
    </div>

    <?php
    require_once __DIR__ . '/../requests/requests.php';

    $id = $_GET['nome'] ?? null;

    if (!$id) {
        die('Série não informada.');
    }
    $request = new SendRequest('http://api:8000/series/' . rawurlencode($id), 'GET');
    $response = $request->send();
    $s = $response['response'] ?? [];
    ?>

    <div class="detail-card">
        <h1><?php echo htmlspecialchars($s['titulo'] ?? 'Série sem nome'); ?></h1>

        <div class="detail-meta">
            <span><?php echo htmlspecialchars((string)($s['ano_lancamento'] ?? 'Ano não especificado')); ?></span>
            <span><?php echo htmlspecialchars($s['genero'] ?? 'Gênero não especificado'); ?></span>
            <span><?php echo htmlspecialchars((string)($s['temporadas'] ?? '?')); ?> temporada(s)</span>
            <span><?php echo htmlspecialchars((string)($s['duracao'] ?? '?')); ?> min/ep.</span>
        </div>

        <p class="detail-description">
            <?php echo htmlspecialchars($s['descricao'] ?? 'Sem descrição'); ?>
        </p>

        <p style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: var(--text-muted); font-size: 0.9rem;">
            Direção: <?php echo htmlspecialchars($s['diretor'] ?? 'Diretor não especificado'); ?>
        </p>

        <div class="detail-actions">
            <a class="btn btn-secondary" href="/criar-editar?id=<?= htmlspecialchars($id) ?>">Editar</a>
            <a class="btn btn-danger" href="/delete?id=<?= htmlspecialchars($id) ?>" onclick="return confirm('Excluir esta série?');">Excluir</a>
        </div>
    </div>
</div>
</body>
</html>