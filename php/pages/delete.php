<?php
require_once __DIR__ . '/../requests/requests.php';
$id = $_GET['id'] ?? null;
if (!$id) {
    die('Série não informada.');
}
$url_api = getenv("ULR_API");
$request = new SendRequest("$url_api/series/" . rawurlencode($id), 'DELETE');
$response = $request->send();
if ($response['status_code'] !== 200) {
    echo 'Erro ao excluir série: ' . htmlspecialchars($response['response']['message'] ?? 'Erro desconhecido');
    exit;
}
header('Location: /home');
exit;