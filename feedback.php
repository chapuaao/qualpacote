<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use QualPacote\Csrf;
use QualPacote\Database;
use QualPacote\UsageRepository;

header('Cache-Control: no-store');
$json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$status = 200;
$message = 'Obrigado! A sua opinião ficou registada.';
$returnUrl = '/';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    $status = 405;
    $message = 'Utilize os botões apresentados nas recomendações.';
} elseif (!Csrf::validate(is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null)) {
    $status = 403;
    $message = 'A sessão expirou. Volte a consultar os pacotes.';
} elseif (!isset($_SESSION['_usage_visitor'])) {
    $status = 403;
    $message = 'Volte a consultar os pacotes antes de votar.';
} else {
    $vote = $_POST['vote'] ?? null;
    $token = $_POST['result'] ?? null;
    if (!is_string($vote) || !in_array($vote, ['1', '-1'], true) || !is_string($token)) {
        $status = 422;
        $message = 'Escolha Gostei ou Não gostei.';
    } else {
        try {
            $returnUrl = (new UsageRepository(Database::connection()))->vote((string) $_SESSION['_usage_visitor'], $token, (int) $vote);
        } catch (InvalidArgumentException $e) {
            $status = 422;
            $message = $e->getMessage();
        } catch (Throwable) {
            $status = 503;
            $message = 'Não foi possível guardar a opinião. Tente novamente.';
        }
    }
}

http_response_code($status);
if ($json) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $status === 200, 'message' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($status === 200) {
    header('Location: ' . $returnUrl, true, 303);
    exit;
}
?>
<!doctype html>
<html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Opinião — QualPacote</title><link rel="stylesheet" href="/assets/app.css?v=3"></head>
<body><main class="container"><h1>A sua opinião</h1><p><?= e($message) ?></p><a href="/">Voltar a consultar pacotes</a></main></body></html>
