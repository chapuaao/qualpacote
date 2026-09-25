<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/RecommendationEngine.php';

use QualPacote\RecommendationEngine;

function expect_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FALHOU: {$message}\n");
        exit(1);
    }
}

$engine = new RecommendationEngine();

$plans = [
    [
        'id' => 1,
        'name' => 'Diurno',
        'operator_name' => 'Rede A',
        'price_kz' => 2000,
        'validity_days' => 30,
        'benefits' => [
            ['type' => 'VOICE', 'quantity' => 300, 'unit' => 'MIN', 'network_scope' => 'ALL', 'start_time' => '07:00:00', 'end_time' => '23:59:00'],
            ['type' => 'VOICE', 'quantity' => 300, 'unit' => 'MIN', 'network_scope' => 'ALL', 'start_time' => '00:00:00', 'end_time' => '06:59:00'],
            ['type' => 'DATA', 'quantity' => 2048, 'unit' => 'MB', 'network_scope' => 'ALL'],
        ],
    ],
    [
        'id' => 2,
        'name' => '24 horas',
        'operator_name' => 'Rede B',
        'price_kz' => 2000,
        'validity_days' => 30,
        'benefits' => [
            ['type' => 'VOICE', 'quantity' => 320, 'unit' => 'MIN', 'network_scope' => 'ALL'],
            ['type' => 'DATA', 'quantity' => 2048, 'unit' => 'MB', 'network_scope' => 'ALL'],
        ],
    ],
];

$dayProfile = [
    'budget_kz' => 2000,
    'goal' => 'voice',
    'usage_level' => 'normal',
    'duration_days' => 30,
    'schedule' => 'day',
    'call_scope' => 'mixed',
];

$results = $engine->recommend($plans, $dayProfile);
expect_true(count($results) === 2, 'deve devolver os dois planos');
expect_true($results[0]['plan']['id'] === 2, 'plano 24 horas deve vencer para utilização diurna');
expect_true($results[1]['restriction_loss_percent'] > 20, 'benefício nocturno deve perder valor para perfil diurno');

$nightProfile = $dayProfile;
$nightProfile['schedule'] = 'night';
$nightResults = $engine->recommend($plans, $nightProfile);
expect_true($nightResults[0]['plan']['id'] === 1, 'plano com benefício nocturno deve subir para utilização nocturna');

$shortPlan = [[
    'id' => 3,
    'name' => '7 dias',
    'operator_name' => 'Rede C',
    'price_kz' => 1000,
    'validity_days' => 7,
    'benefits' => [
        ['type' => 'DATA', 'quantity' => 4096, 'unit' => 'MB', 'network_scope' => 'ALL'],
    ],
]];

$coverageProfile = [
    'budget_kz' => 2000,
    'goal' => 'data',
    'usage_level' => 'normal',
    'duration_days' => 30,
    'schedule' => 'day',
    'call_scope' => 'mixed',
];

$coverage = $engine->recommend($shortPlan, $coverageProfile);
expect_true($coverage[0]['coverage_days'] === 14, 'orçamento deve permitir apenas dois ciclos de 7 dias');
expect_true($coverage[0]['coverage_percent'] < 50, 'validade curta deve reduzir cobertura');

fwrite(STDOUT, "OK: RecommendationEngineTest\n");
