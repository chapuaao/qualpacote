<?php
declare(strict_types=1);

use QualPacote\Env;

require_once __DIR__ . '/Env.php';

Env::load(dirname(__DIR__) . '/.env');

date_default_timezone_set(Env::get('APP_TIMEZONE', 'Africa/Luanda') ?? 'Africa/Luanda');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name(Env::get('APP_SESSION_NAME', 'qualpacote_session') ?? 'qualpacote_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => Env::bool('APP_HTTPS', false),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Csrf.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/PlanRepository.php';
require_once __DIR__ . '/RecommendationEngine.php';
require_once __DIR__ . '/helpers.php';
