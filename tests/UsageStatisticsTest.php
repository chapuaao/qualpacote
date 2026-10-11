<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/PlanRepository.php';
require_once dirname(__DIR__) . '/app/UsageRepository.php';
require_once dirname(__DIR__) . '/app/UsageSession.php';
require_once dirname(__DIR__) . '/app/Csrf.php';

use QualPacote\Csrf;
use QualPacote\UsageRepository;
use QualPacote\UsageSession;

date_default_timezone_set('Africa/Luanda');
function usage_expect(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function usage_rejects(callable $action, string $message): void
{
    try { $action(); } catch (InvalidArgumentException) { return; }
    throw new RuntimeException($message);
}

// No production tables are touched: this suite creates and drops its own DB.
$dsn = getenv('QUALPACOTE_TEST_DSN');
if (!$dsn) {
    fwrite(STDERR, "Defina QUALPACOTE_TEST_DSN para um servidor MySQL/MariaDB de testes (sem dbname).\n");
    exit(1);
}
if (!str_starts_with($dsn, 'mysql:') || str_contains(strtolower($dsn), 'dbname')) {
    fwrite(STDERR, "QUALPACOTE_TEST_DSN deve ser mysql:host=...;port=... sem dbname.\n");
    exit(1);
}
$db = new PDO($dsn, getenv('QUALPACOTE_TEST_USER') ?: 'root', getenv('QUALPACOTE_TEST_PASSWORD') ?: '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);
$name = 'qualpacote_usage_test_' . bin2hex(random_bytes(6));
$db->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $db->exec("USE `$name`");
    $schema = file_get_contents(dirname(__DIR__) . '/database/schema.sql');
    $migration = file_get_contents(dirname(__DIR__) . '/database/migrations/20261011_usage_statistics.sql');
    // Validate upgrading a pre-statistics installation, and repeated imports.
    $db->exec(substr($schema, 0, strpos($schema, '-- Idempotente:')));
    $db->exec($migration);
    $db->exec($migration);
    $db->exec($schema);
    $repo = new UsageRepository($db);
    $today = date('Y-m-d');
    $visitor = hash('sha256', 'first-browser');
    $other = hash('sha256', 'second-browser');
    $repo->recordVisit($visitor);
    $repo->recordVisit($visitor);
    $repo->recordVisit($other);
    $empty = $repo->statistics($today, $today);
    usage_expect((int) $empty['summary']['visitors'] === 2 && (int) $empty['summary']['consultations'] === 0, 'Navegadores repetidos não duplicam visitantes; período sem consultas.');
    $profile = ['budget_kz'=>500,'services'=>['voice'],'priority'=>'voice','duration_days'=>7,'schedule'=>'day','call_scope'=>'mixed'];
    $results = [
        ['kind'=>'plan','plan'=>['id'=>1,'version_id'=>2],'tariff'=>null,'operator_name'=>'Rede A','title'=>'Pacote teste'],
        ['kind'=>'balance','plan'=>null,'tariff'=>['id'=>1,'version_id'=>2],'operator_name'=>'Rede A','title'=>'Saldo normal'],
    ];
    $request = hash('sha256', 'request');
    $tokens = $repo->recordConsultation($visitor, $request, $profile, $results, 'compare=1');
    usage_expect(count($tokens) === 2, 'Pacote e saldo com IDs coincidentes têm identidades separadas.');
    usage_expect($tokens === $repo->recordConsultation($visitor, $request, $profile, $results, 'compare=1'), 'Repetir consulta preserva os tokens e não duplica registos.');
    $plan = $tokens['plan:1:2']['vote_token'];
    $balance = $tokens['balance:1:2']['vote_token'];
    usage_rejects(fn () => $repo->vote($other, $plan, 1), 'Outro visitante não pode votar nesta recomendação.');
    usage_rejects(fn () => $repo->vote($visitor, str_repeat('0',64), 1), 'Recomendação inventada rejeitada.');
    usage_rejects(fn () => $repo->vote($visitor, $plan, 0), 'Voto fora de aprovar/reprovar rejeitado.');
    usage_expect($repo->vote($visitor, $plan, 1) === '/?compare=1#resultados', 'Votação sem JS volta à consulta.');
    $repo->vote($visitor, $plan, 1);
    $repo->vote($visitor, $balance, -1);
    $stats = $repo->statistics($today, $today);
    usage_expect((int) $stats['summary']['consultations'] === 1 && (int) $stats['summary']['users'] === 1, 'Consultas e utilizadores são métricas distintas.');
    usage_expect((int) $stats['summary']['positive'] === 1 && (int) $stats['summary']['negative'] === 1, 'Clicar duas vezes mantém um voto.');
    $repo->vote($visitor, $plan, -1);
    $stats = $repo->statistics($today, $today);
    usage_expect((int) $stats['summary']['positive'] === 0 && (int) $stats['summary']['negative'] === 2, 'Alterar opinião substitui a anterior.');
    usage_expect($stats['daily'][$today]['negative'] === 2 && count($stats['history']) === 2, 'Evolução e histórico coincidem com os votos actuais.');
    $repo->recordConsultation($visitor, hash('sha256', 'another-click'), $profile, [], 'compare=1');
    $stats = $repo->statistics($today, $today);
    usage_expect((int) $stats['summary']['consultations'] === 2 && (int) $stats['summary']['users'] === 1, 'Novo clique conta, mesmo sem resultados.');
    usage_expect((int) $stats['searches'][0]['result_count'] === 0, 'Histórico inclui consultas sem resultados.');
    (new QualPacote\PlanRepository($db))->logSearch($profile, 0);
    $stats = $repo->statistics($today, $today);
    usage_expect((int) $stats['legacy'] === 1 && (int) $stats['totals']['consultations'] === 3, 'Consultas antigas preservadas sem inventar visitantes.');
    $old = date('Y-m-d', strtotime('-2 days'));
    $db->exec("UPDATE search_events SET created_at='$old 23:59:59'");
    $stats = $repo->statistics($today, $today);
    usage_expect((int) $stats['summary']['consultations'] === 0 && (int) $stats['summary']['negative'] === 2, 'Votos são filtrados pela sua data, independentemente da consulta.');
    usage_expect(count($stats['items']) === 2 && (int) $stats['items'][0]['impressions'] === 0, 'Votos tardios continuam no ranking.');
    usage_rejects(fn () => UsageRepository::period('2026-02-30','2026-03-01'), 'Data inválida rejeitada.');
    usage_rejects(fn () => UsageRepository::period('2026-10-12','2026-10-11'), 'Intervalo invertido rejeitado.');
    usage_rejects(fn () => UsageRepository::period('2024-01-01','2026-01-01'), 'Intervalo excessivo rejeitado.');
    usage_expect(UsageRepository::period('2026-10-11','2026-10-11')[1] === '2026-10-12 00:00:00', 'Fim inclusivo até ao dia seguinte.');

    $_SESSION = [];
    $form = UsageSession::formToken();
    $key = UsageSession::requestToken($form, $profile);
    usage_expect($key === UsageSession::requestToken($form, $profile), 'Actualizar os resultados mantém a consulta.');
    usage_expect($key === UsageSession::requestToken('forged', $profile), 'Token não emitido não inventa nova consulta.');
    usage_expect($key !== UsageSession::requestToken(UsageSession::formToken(), $profile), 'Novo clique no formulário conta uma nova consulta.');
    usage_expect(!Csrf::validate('forged') && Csrf::validate(Csrf::token()), 'CSRF exige token da sessão.');
    require_once __DIR__ . '/UsageHttpTest.php';
    usage_http_checks($db, $name, $dsn);
    fwrite(STDOUT, "OK: UsageStatisticsTest (MySQL/MariaDB)\n");
} finally {
    $db->exec("DROP DATABASE `$name`");
}
