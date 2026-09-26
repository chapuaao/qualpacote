<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    require_once __DIR__ . '/app/bootstrap.php';
    \QualPacote\Database::connection()->query('SELECT 1');
    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (Throwable) {
    http_response_code(503);
    echo json_encode(['ok' => false]);
}
