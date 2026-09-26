<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute este script pela linha de comandos.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

use QualPacote\Database;
use QualPacote\Env;

$email = mb_strtolower(trim(Env::get('ADMIN_EMAIL', '') ?? ''));
$password = Env::get('ADMIN_PASSWORD', '') ?? '';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Defina ADMIN_EMAIL válido no ficheiro .env.\n");
    exit(1);
}

if (strlen($password) < 12 || $password === 'change_me_now') {
    fwrite(STDERR, "Defina ADMIN_PASSWORD com pelo menos 12 caracteres no ficheiro .env.\n");
    exit(1);
}

$db = Database::connection();
$hash = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare(
    'INSERT INTO admins (email, name, password_hash, active, created_at)
     VALUES (?, ?, ?, 1, NOW())
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), active = 1'
);
$stmt->execute([$email, 'Administrador', $hash]);

fwrite(STDOUT, "Administrador configurado: {$email}\n");
