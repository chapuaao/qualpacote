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
$nightProfile['schedule'] = 'late_night';
$nightResults = $engine->recommend($plans, $nightProfile);
expect_true($nightResults[0]['plan']['id'] === 1, 'plano com benefício nocturno deve subir para utilização nocturna');

$shortPlan = [[
    'id' => 3,
    'operator_id' => 3,
    'name' => '7 dias',
    'rules' => ['repeat_policy'=>'sequential'],
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

// Regression: general internet and additional social traffic share one finite pool.
$oneGb = [['id'=>90,'operator_id'=>1,'name'=>'Uma carteira','price_kz'=>500,'validity_days'=>7,
    'benefits'=>[['type'=>'DATA','quantity'=>1024,'unit'=>'MB','network_scope'=>'ALL']]]];
$additional = $engine->recommend($oneGb, ['budget_kz'=>500,'services'=>['data','social'],'data_mode'=>'minimum','data_gb'=>1,'social_mode'=>'minimum','social_gb'=>1,'duration_days'=>7]);
expect_true(!$additional[0]['meets_requirements'], '1 GB não satisfaz 1 GB geral mais 1 GB adicional social');
expect_true($additional[0]['capacity']['data'] + $additional[0]['capacity']['social'] <= 1024, 'carteira geral deve ser conservada');
$onlySocial = $engine->recommend($oneGb, ['budget_kz'=>500,'services'=>['social'],'social_mode'=>'minimum','social_gb'=>1,'duration_days'=>7]);
expect_true($onlySocial[0]['meets_requirements'], 'dados gerais podem servir integralmente às redes sociais quando são o único uso');

$oneGb[0]['rules'] = ['family'=>'dados','repeat_policy'=>'parallel','combination_group'=>'dados'];
$repeat = $engine->recommend($oneGb, ['budget_kz'=>1500,'services'=>['data'],'duration_days'=>7]);
expect_true($repeat[0]['cycles'] === 3 && $repeat[0]['capacity']['data'] === 3072.0, 'compras paralelas confirmadas devem maximizar o orçamento');
expect_true($repeat[0]['components'][0]['schedule'] === [['day'=>1,'count'=>3]], 'três compras no mesmo dia preservam a validade individual');

$minimumRepeat = $engine->recommend($oneGb,['budget_kz'=>5000,'services'=>['data'],'data_mode'=>'minimum','data_gb'=>2.5,'duration_days'=>7]);
expect_true($minimumRepeat[0]['cycles']===3 && $minimumRepeat[0]['effective_cost']===1500.0, 'mínimo deve comprar três pacotes, sem gastar todo o orçamento');

$restricted = $oneGb; $restricted[0]['rules'] = []; $restricted[0]['benefits'] = [['type'=>'SOCIAL','quantity'=>1024,'unit'=>'MB','app_scope'=>'WhatsApp, Facebook']];
$apps = $engine->recommend($restricted, ['budget_kz'=>500,'services'=>['social'],'social_apps'=>['youtube'],'duration_days'=>7]);
expect_true($apps[0]['capacity']['social'] === 0.0 && !$apps[0]['meets_requirements'], 'WhatsApp não satisfaz YouTube');
$apps = $engine->recommend($restricted, ['budget_kz'=>500,'services'=>['social'],'social_apps'=>['whatsapp'],'duration_days'=>7]);
expect_true($apps[0]['capacity']['social'] === 1024.0, 'aplicação confirmada permite a carteira social');

$smsPlan = $oneGb; $smsPlan[0]['rules'] = []; $smsPlan[0]['benefits'] = [['type'=>'SMS','quantity'=>20,'unit'=>'SMS','network_scope'=>'ONNET']];
$wrongNetwork = $engine->recommend($smsPlan, ['budget_kz'=>500,'services'=>['sms'],'sms_scope'=>'other','duration_days'=>7]);
expect_true($wrongNetwork[0]['capacity']['sms'] === 0.0, 'SMS on-net não pode ser usado para outras redes');
$unknownDuration = $engine->recommend([], ['budget_kz'=>500,'services'=>['voice'],'duration_days'=>7], $tariffs);
expect_true(!$unknownDuration[0]['meets_requirements'] && $unknownDuration[0]['coverage_days'] === 0, 'validade desconhecida não garante sete dias');

$nightProfile['schedule'] = 'night';
$evening = $engine->recommend($plans, $nightProfile);
expect_true($evening[0]['plan']['id'] === 2, 'noite às 21h não deve ser interpretada como madrugada às 02h');

$base = ['id'=>100,'operator_id'=>1,'name'=>'Base','operator_name'=>'Rede A','price_kz'=>500,'validity_days'=>7,
    'rules'=>['family'=>'base','combination_group'=>'base','repeat_policy'=>'sequential'],
    'benefits'=>[['type'=>'VOICE','quantity'=>50,'unit'=>'MIN','network_scope'=>'ALL']]];
$addon = ['id'=>101,'operator_id'=>1,'name'=>'Dados adicionais','operator_name'=>'Rede A','price_kz'=>100,'validity_days'=>7,
    'rules'=>['family'=>'dados','requires_family'=>'base','combination_group'=>'base','repeat_policy'=>'unknown'],
    'benefits'=>[['type'=>'DATA','quantity'=>1024,'unit'=>'MB','network_scope'=>'ALL']]];
$need = ['budget_kz'=>600,'services'=>['data','voice'],'data_mode'=>'minimum','data_gb'=>1,'voice_mode'=>'minutes','minutes'=>50,'duration_days'=>7];
$combined = $engine->recommend([$base,$addon], $need);
expect_true(!empty($combined[0]['is_combination']) && $combined[0]['effective_cost'] === 600.0 && $combined[0]['meets_requirements'], 'base mais aditivo deve incluir o custo completo e cumprir os mínimos');
$noBase = $engine->recommend([$base,$addon], array_replace($need, ['budget_kz'=>100,'services'=>['data']]));
expect_true($noBase[0]['conditional'] && !$noBase[0]['meets_requirements'], 'aditivo sem base não pode parecer suficiente');
$hasBase = $engine->recommend([$base,$addon], array_replace($need, ['budget_kz'=>100,'services'=>['data'],'active_plan_ids'=>[100]]));
expect_true(!$hasBase[0]['conditional'] && $hasBase[0]['meets_requirements'], 'plano activo confirmado permite o aditivo sem recompra da base');
$shortBase = $base; $shortBase['rules']['repeat_policy'] = 'unknown'; $shortBase['validity_days'] = 1;
$longAddon = $addon;
$periodDependency = $engine->recommend([$shortBase,$longAddon], array_replace($need, ['services'=>['data']]));
foreach ($periodDependency as $row) if (!empty($row['is_combination'])) expect_true(!$row['meets_requirements'], 'Base sem renovação não garante dependência durante todo o período');
$addon['rules']['combination_group']='outra';
$incompatible = $engine->recommend([$base,$addon], $need);
expect_true(!array_filter($incompatible, static fn ($row)=>!empty($row['is_combination'])), 'compatibilidade desconhecida não pode ser inventada');
$addon['rules']['combination_group']='base'; $addon['operator_id']=2;
$cross = $engine->recommend([$base,$addon], $need);
expect_true(!array_filter($cross, static fn ($row)=>!empty($row['is_combination'])), 'não misturar cartões de operadoras diferentes');

$router = $oneGb; $router[0]['rules']=['category'=>'router'];
expect_true($engine->recommend($router,['budget_kz'=>500,'services'=>['data'],'duration_days'=>7]) === [], 'router deve ficar fora do telemóvel');
$routerResult = $engine->recommend($router,['budget_kz'=>500,'services'=>['data'],'duration_days'=>7,'category'=>'router']);
expect_true(!$routerResult[0]['meets_requirements'], 'equipamento não confirmado impede promessa de compra completa');
$routerResult = $engine->recommend($router,['budget_kz'=>500,'services'=>['data'],'duration_days'=>7,'category'=>'router','router_ready'=>true]);
expect_true($routerResult[0]['meets_requirements'], 'SIM e equipamento já disponíveis permitem comparar só o pacote');

$base['rules']['allows_balance']=true;
$remainingTariff = $tariffs; $remainingTariff[0]['balance_validity_days']=7;
$withBalance = $engine->recommend([$base], array_replace($need,['budget_kz'=>1524]), $remainingTariff);
$complete = array_filter($withBalance, static fn ($row)=>!empty($row['is_combination']) && $row['meets_requirements']);
expect_true($complete !== [], 'pacote mais saldo extra deve completar dados sem duplicar orçamento');
foreach ($withBalance as $row) expect_true($row['effective_cost'] <= 1524, 'todas as compras devem caber no orçamento');

$on = $base; $on['id']=110; $on['rules']=['family'=>'on','combination_group'=>'net']; $on['validity_days']=30; $on['benefits'][0]['network_scope']='ONNET';
$off = $base; $off['id']=111; $off['rules']=['family'=>'off','combination_group'=>'net']; $off['validity_days']=7; $off['benefits'][0]['network_scope']='OFFNET';
$networkPeriod = $engine->recommend([$on,$off],['budget_kz'=>1000,'services'=>['voice'],'voice_mode'=>'minutes','minutes'=>50,'call_scope'=>'mixed','duration_days'=>30]);
foreach ($networkPeriod as $row) if (!empty($row['is_combination'])) expect_true(!$row['meets_requirements'] && $row['coverage_days']===7, 'carteira de outra rede com 7 dias não pode garantir 30 dias');
$addon['operator_id']=1; $addon['source_checked_at']=date('Y-m-d',strtotime('-31 days'));
$oldCombination = $engine->recommend([$base,$addon], $need);
foreach ($oldCombination as $row) if (!empty($row['is_combination'])) expect_true(!$row['meets_requirements'], 'resolver a dependência não pode eliminar uma pendência de actualidade');
$sharedCalls = $base;
$sharedCalls['rules'] = ['voice_step_seconds'=>60];
$sharedCalls['benefits'] = [
    ['type'=>'VOICE','quantity'=>10,'unit'=>'MIN','network_scope'=>'ALL','label'=>'SHARED:units|MIN/SMS'],
    ['type'=>'SMS','quantity'=>10,'unit'=>'SMS','network_scope'=>'ALL','label'=>'SHARED:units|MIN/SMS'],
];
$rounded = $engine->recommend([$sharedCalls], ['budget_kz'=>500,'services'=>['voice','sms'],'voice_mode'=>'calls','calls'=>5,'avg_call_minutes'=>0.5,'sms_mode'=>'minimum','sms_count'=>5,'duration_days'=>7]);
expect_true($rounded[0]['meets_requirements'] && $rounded[0]['capacity']['voice'] === 2.5 && $rounded[0]['capacity']['sms'] === 5.0, 'Cinco chamadas de 30 segundos cobram cinco unidades; as restantes cinco servem para SMS');
fwrite(STDOUT, "OK: RecommendationEngineTest\n");
