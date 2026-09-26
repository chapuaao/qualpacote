<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/RecommendationEngine.php';

use QualPacote\RecommendationEngine;

function expect_intelligence(bool $condition, string $message): void
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
        'operator_id' => 1,
        'name' => 'Dividido',
        'operator_name' => 'Rede A',
        'price_kz' => 2000,
        'validity_days' => 30,
        'benefits' => [
            ['type' => 'VOICE', 'quantity' => 100, 'unit' => 'MIN', 'network_scope' => 'ALL', 'start_time' => '07:00:00', 'end_time' => '23:59:00'],
            ['type' => 'VOICE', 'quantity' => 500, 'unit' => 'MIN', 'network_scope' => 'ALL', 'start_time' => '00:00:00', 'end_time' => '06:59:00'],
        ],
    ],
    [
        'id' => 2,
        'operator_id' => 2,
        'name' => '24 horas',
        'operator_name' => 'Rede B',
        'price_kz' => 2000,
        'validity_days' => 30,
        'benefits' => [
            ['type' => 'VOICE', 'quantity' => 320, 'unit' => 'MIN', 'network_scope' => 'ALL'],
        ],
    ],
];

$results = $engine->recommend($plans, [
    'budget_kz' => 2000,
    'services' => ['voice'],
    'voice_mode' => 'max',
    'duration_days' => 30,
    'schedule' => 'day',
    'call_scope' => 'mixed',
]);

expect_intelligence($results[0]['plan']['id'] === 2, 'plano 24 horas deve vencer no uso diurno');
$split = array_values(array_filter($results, static fn ($row) => $row['kind'] === 'plan' && $row['plan']['id'] === 1))[0];
expect_intelligence(abs($split['capacity']['voice'] - 100) < 0.01, '500 minutos nocturnos devem ficar exactamente fora do cálculo diurno');
expect_intelligence(str_contains(implode(' ', $split['restrictions']), '500 min'), 'a explicação deve quantificar os 500 minutos fora do horário');
expect_intelligence(str_contains(implode(' ', $split['restrictions']), 'provável'), 'o contexto diurno deve explicar que a madrugada tende a ser pouco útil');

$shared = [[
    'id' => 3,
    'operator_id' => 1,
    'name' => 'Carteira partilhada',
    'operator_name' => 'Rede A',
    'price_kz' => 1000,
    'validity_days' => 30,
    'benefits' => [
        ['type' => 'VOICE', 'quantity' => 100, 'unit' => 'MIN', 'network_scope' => 'ALL', 'label' => 'SHARED:u|Minutos/SMS partilhados'],
        ['type' => 'SMS', 'quantity' => 100, 'unit' => 'SMS', 'network_scope' => 'ALL', 'label' => 'SHARED:u|Minutos/SMS partilhados'],
    ],
]];
$sharedResult = $engine->recommend($shared, [
    'budget_kz' => 1000,
    'services' => ['voice', 'sms'],
    'voice_mode' => 'max',
    'sms_mode' => 'max',
    'priority' => 'balanced',
    'duration_days' => 30,
]);
expect_intelligence(abs($sharedResult[0]['capacity']['voice'] + $sharedResult[0]['capacity']['sms'] - 100) < 0.01, 'carteira partilhada não pode ser duplicada');

$conditional = [[
    'id' => 4,
    'operator_id' => 3,
    'name' => 'Aditivo',
    'operator_name' => 'Rede M',
    'price_kz' => 100,
    'validity_days' => 1,
    'notes' => "[REQUIREMENT]\nRequer FLEX activo.\n[/REQUIREMENT]",
    'benefits' => [['type' => 'DATA', 'quantity' => 1000, 'unit' => 'MB', 'network_scope' => 'ALL']],
]];
$conditionalResult = $engine->recommend($conditional, [
    'budget_kz' => 100,
    'services' => ['data'],
    'data_mode' => 'max',
    'duration_days' => 1,
]);
expect_intelligence($conditionalResult[0]['conditional'] === true, 'requisito prévio deve ser reconhecido');
expect_intelligence(str_contains(implode(' ', $conditionalResult[0]['restrictions']), 'FLEX activo'), 'condição prévia deve ficar visível');

$tariffs = [[
    'id' => 10,
    'operator_id' => 1,
    'operator_name' => 'Rede A',
    'name' => 'Base',
    'is_default' => 1,
    'rates' => [
        ['service_type' => 'VOICE', 'unit' => 'MIN', 'price_kz_per_unit' => 20, 'network_scope' => 'ALL'],
        ['service_type' => 'SMS', 'unit' => 'SMS', 'price_kz_per_unit' => 5, 'network_scope' => 'ALL'],
        ['service_type' => 'DATA', 'unit' => 'MB', 'price_kz_per_unit' => 1, 'network_scope' => 'ALL'],
    ],
]];
$balanceResult = $engine->recommend([], [
    'budget_kz' => 500,
    'services' => ['voice'],
    'voice_mode' => 'max',
    'duration_days' => 0,
], $tariffs);
expect_intelligence(abs($balanceResult[0]['capacity']['voice'] - 25) < 0.01, '500 Kz a 20 Kz/min devem render 25 minutos');

fwrite(STDOUT, "OK: RestrictionIntelligenceTest\n");
