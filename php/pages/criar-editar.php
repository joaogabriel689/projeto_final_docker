<?php
require_once __DIR__ . '/../requests/requests.php';


$isEdit = isset($_GET['id']) && !empty($_GET['id']);
$serieId = $_GET['id'] ?? null;
$serie = null;
$errorMessage = null;


if ($isEdit && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $request = new SendRequest("http://api:8000/series/" . rawurlencode($serieId), 'GET');
    $response = $request->send();

    if ($response['status_code'] === 200) {
        $serie = $response['response'];
    } else {
        $errorMessage = 'Série não encontrada.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serieData = [
        'titulo'         => $_POST['titulo'] ?? '',
        'descricao'      => $_POST['descricao'] ?? '',
        'ano_lancamento' => $_POST['ano_lancamento'] !== '' ? (int)$_POST['ano_lancamento'] : null,
        'genero'         => $_POST['genero'] ?? '',
        'diretor'        => $_POST['diretor'] ?? '',
        'temporadas'     => $_POST['temporadas'] !== '' ? (int)$_POST['temporadas'] : null,
        'duracao'        => $_POST['duracao'] !== '' ? (int)$_POST['duracao'] : null,
    ];


    $method = $_POST['_method'] ?? 'POST';

    if ($method === 'PUT' && $isEdit) {
        $request = new SendRequest("http://api:8000/series/" . rawurlencode($serieId), 'PUT', $serieData);
        $successStatus = 200;
    } else {
        $request = new SendRequest('http://api:8000/series/', 'POST', $serieData);
        $successStatus = 200;
    }

    $response = $request->send();

    if ($response['status_code'] === $successStatus) {
        header('Location: /home');
        exit;
    } else {
        $detail = $response['response']['detail'] ?? 'Erro desconhecido';
        if (is_array($detail)) {
            $detail = json_encode($detail);
        }
        $errorMessage = 'Erro ao ' . ($isEdit ? 'editar' : 'criar') . ' série: '
            . htmlspecialchars((string)$detail);
        $serie = $serieData;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isEdit ? 'Editar Série' : 'Criar Série' ?></title>
<link rel="stylesheet" href="/style.css">
</head>
<body>
<div class="page">
    <div class="top-actions">
        <a class="btn btn-secondary" href="/home">&larr; Voltar para a lista</a>
    </div>

    <h1><?= $isEdit ? 'Editar Série' : 'Criar Série' ?></h1>

    <?php if (!empty($errorMessage)): ?>
        <div class="error-message"><?= $errorMessage ?></div>
    <?php endif; ?>

    <div class="form-card">
        <form method="POST" action="">
            <?php if ($isEdit): ?>
                <input type="hidden" name="_method" value="PUT">
                <input type="hidden" name="id" value="<?= htmlspecialchars($serieId) ?>">
            <?php endif; ?>

            <label for="titulo">Nome</label>
            <input type="text" id="titulo" name="titulo" value="<?= htmlspecialchars($serie['titulo'] ?? '') ?>" required>

            <label for="descricao">Descrição</label>
            <textarea id="descricao" name="descricao"><?= htmlspecialchars($serie['descricao'] ?? '') ?></textarea>

            <div class="form-row">
                <div>
                    <label for="ano_lancamento">Ano</label>
                    <input type="number" id="ano_lancamento" name="ano_lancamento" value="<?= htmlspecialchars($serie['ano_lancamento'] ?? '') ?>">
                </div>
                <div>
                    <label for="genero">Gênero</label>
                    <input type="text" id="genero" name="genero" value="<?= htmlspecialchars($serie['genero'] ?? '') ?>">
                </div>
            </div>

            <div class="form-row">
                <div>
                    <label for="diretor">Diretor</label>
                    <input type="text" id="diretor" name="diretor" value="<?= htmlspecialchars($serie['diretor'] ?? '') ?>">
                </div>
                <div>
                    <label for="temporadas">Temporadas</label>
                    <input type="number" id="temporadas" name="temporadas" value="<?= htmlspecialchars($serie['temporadas'] ?? '') ?>">
                </div>
            </div>

            <label for="duracao">Duração (min/episódio)</label>
            <input type="number" id="duracao" name="duracao" value="<?= htmlspecialchars($serie['duracao'] ?? '') ?>">

            <button type="submit" class="btn"><?= $isEdit ? 'Salvar Alterações' : 'Criar Série' ?></button>
        </form>
    </div>
</div>
</body>
</html>