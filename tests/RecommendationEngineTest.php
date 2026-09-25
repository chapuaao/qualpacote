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
        'operator_id' => 1,
        'name' => 'Diurno + nocturno',
        'operator_name' => 'Rede A',
        'price_kz' => 2000,
        'validity_days' => 30,
        'benefits' => [
            ['type' => 'VOICE', 'quantity' => 100, 'unit' => 'MIN', 'network_scope' => 'ALL', 'start_time' => '07:00:00', 'end_time' => '23:59:00'],
            ['type' => 'VOICE', 'quantity' => 500, 'unit' => 'MIN', 'network_scope' => 'ALL', 'start_time' => '00:00:00', 'end_time' => '06:59:00'],
            ['type' => 'DATA', 'quantity' => 2048, 'unit' => 'MB', 'network_scope' => 'ALL'],
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
            ['type' => 'DATA', 'quantity' => 2048, 'unit' => 'MB', 'network_scope' => 'ALL'],
        ],
    ],
];

$dayProfile = [
    'budget_kz' => 2000,
    'services' => ['voice'],
    'voice_mode' => 'max',
    'duration_days' => 30,
    'schedule' => 'day',
    'call_scope' => 'mixed',
];
$results = $engine->recommend($plans, $dayProfile);
expect_true(count($results) === 2, 'deve devolver os dois planos');
expect_true($results[0]['plan']['id'] === 2, 'plano 24 horas deve vencer para uso diurno');
expect_true(count($results[1]['restrictions']) > 0, 'benefício nocturno deve ser assinalado como restrição');

$nightProfile = $dayProfile;
$nightProfile['schedule'] = 'night';
$nightResults = $engine->recommend($plans, $nightProfile);
expect_true($nightResults[0]['plan']['id'] === 1, 'plano com benefício nocturno deve subir para utilização nocturna');

$shortPlan = [[
    'id' => 3,
    'operator_id' => 3,
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
    'services' => ['data'],
    'data_mode' => 'max',
    'duration_days' => 30,
    'schedule' => 'day',
    'call_scope' => 'mixed',
];
$coverage = $engine->recommend($shortPlan, $coverageProfile);
expect_true($coverage[0]['coverage_days'] === 14, 'orçamento deve permitir apenas dois ciclos de 7 dias');
expect_true($coverage[0]['meets_requirements'] === false, 'validade insuficiente deve falhar o período solicitado');

$maxPlans = [
    [
        'id' => 4, 'operator_id' => 1, 'name' => '6 GB', 'operator_name' => 'Rede A',
        'price_kz' => 5000, 'validity_days' => 30,
        'benefits' => [['type' => 'DATA', 'quantity' => 6144, 'unit' => 'MB', 'network_scope' => 'ALL']],
    ],
    [
        'id' => 5, 'operator_id' => 1, 'name' => '13 GB', 'operator_name' => 'Rede A',
        'price_kz' => 10000, 'validity_days' => 30,
        'benefits' => [['type' => 'DATA', 'quantity' => 13312, 'unit' => 'MB', 'network_scope' => 'ALL']],
    ],
];
$maxData = $engine->recommend($maxPlans, [
    'budget_kz' => 10000,
    'services' => ['data'],
    'data_mode' => 'max',
    'duration_days' => 30,
]);
expect_true($maxData[0]['plan']['id'] === 5, 'quando o objectivo é máximo de dados, 13 GB deve superar 6 GB');

$tariffs = [[
    'id' => 10,
    'operator_id' => 1,
    'operator_name' => 'Rede A',
    'name' => 'Pré-pago',
    'is_default' => 1,
    'balance_validity_days' => null,
    'rates' => [
        ['service_type' => 'VOICE', 'unit' => 'MIN', 'price_kz_per_unit' => 20, 'network_scope' => 'ALL'],
        ['service_type' => 'SMS', 'unit' => 'SMS', 'price_kz_per_unit' => 5, 'network_scope' => 'ALL'],
        ['service_type' => 'DATA', 'unit' => 'MB', 'price_kz_per_unit' => 1, 'network_scope' => 'ALL'],
    ],
]];
$balanceOnly = $engine->recommend([], [
    'budget_kz' => 500,
    'services' => ['voice'],
    'voice_mode' => 'max',
    'duration_days' => 0,
], $tariffs);
expect_true(count($balanceOnly) === 1, 'saldo normal deve ser uma opção real');
expect_true(abs($balanceOnly[0]['capacity']['voice'] - 25) < 0.001, '500 Kz a 20 Kz/min devem render 25 minutos');
expect_true(abs($balanceOnly[0]['balance_potentials']['voice'] - 25) < 0.001, 'potencial de saldo deve usar o mesmo orçamento uma única vez');

$mixedBalance = $engine->recommend([], [
    'budget_kz' => 500,
    'services' => ['voice', 'data'],
    'priority' => 'voice',
    'voice_mode' => 'max',
    'data_mode' => 'max',
    'duration_days' => 0,
], $tariffs);
$spentEquivalent = ($mixedBalance[0]['capacity']['voice'] * 20) + ($mixedBalance[0]['capacity']['data'] * 1);
expect_true(abs($spentEquivalent - 500) < 0.01, 'saldo partilhado não pode duplicar os mesmos 500 Kz entre serviços');

$minimumPlan = [[
    'id' => 6, 'operator_id' => 1, 'name' => '100 minutos + dados', 'operator_name' => 'Rede A',
    'price_kz' => 2000, 'validity_days' => 7,
    'benefits' => [
        ['type' => 'VOICE', 'quantity' => 100, 'unit' => 'MIN', 'network_scope' => 'ALL'],
        ['type' => 'DATA', 'quantity' => 1024, 'unit' => 'MB', 'network_scope' => 'ALL'],
    ],
]];
$minimumResult = $engine->recommend($minimumPlan, [
    'budget_kz' => 2000,
    'services' => ['voice', 'data'],
    'voice_mode' => 'minutes',
    'minutes' => 100,
    'data_mode' => 'max',
    'priority' => 'data',
    'duration_days' => 7,
]);
expect_true($minimumResult[0]['meets_requirements'] === true, 'plano deve cumprir mínimo explícito de 100 minutos');

$equivalent = $engine->recommend([[
    'id' => 7, 'operator_id' => 1, 'name' => 'Pacote valorizado', 'operator_name' => 'Rede A',
    'price_kz' => 500, 'validity_days' => 7,
    'benefits' => [
        ['type' => 'VOICE', 'quantity' => 50, 'unit' => 'MIN', 'network_scope' => 'ALL'],
        ['type' => 'SMS', 'quantity' => 20, 'unit' => 'SMS', 'network_scope' => 'ALL'],
    ],
]], [
    'budget_kz' => 500,
    'services' => ['voice', 'sms'],
    'voice_mode' => 'max',
    'sms_mode' => 'max',
    'duration_days' => 7,
], $tariffs);
$planResult = array_values(array_filter($equivalent, static fn ($r) => $r['kind'] === 'plan'))[0];
expect_true($planResult['equivalent_balance_complete'] === true, 'deve calcular equivalente quando todas as tarifas base são conhecidas');
expect_true(abs($planResult['equivalent_balance_kz'] - 1100) < 0.01, '50 min x 20 + 20 SMS x 5 = 1100 Kz de saldo normal');

fwrite(STDOUT, "OK: RecommendationEngineTest\n");
