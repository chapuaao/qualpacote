<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use QualPacote\Database;
use QualPacote\PlanRepository;
use QualPacote\RecommendationEngine;

$repo = new PlanRepository(Database::connection());
$operators = $repo->operators(true);

$selectedServices = array_values(array_unique(array_filter(
    array_map(static fn ($v) => strtolower(trim((string) $v)), (array) ($_GET['services'] ?? [])),
    static fn ($v) => in_array($v, ['data', 'voice', 'sms', 'social'], true)
)));

$profile = [
    'budget_kz' => $_GET['budget_kz'] ?? '',
    'services' => $selectedServices,
    'priority' => $_GET['priority'] ?? 'balanced',
    'duration_days' => $_GET['duration_days'] ?? '7',
    'schedule' => $_GET['schedule'] ?? 'day',
    'call_scope' => $_GET['call_scope'] ?? 'mixed',
    'data_mode' => $_GET['data_mode'] ?? 'max',
    'data_gb' => $_GET['data_gb'] ?? '',
    'voice_mode' => $_GET['voice_mode'] ?? 'max',
    'minutes' => $_GET['minutes'] ?? '',
    'calls' => $_GET['calls'] ?? '',
    'avg_call_minutes' => $_GET['avg_call_minutes'] ?? '3',
    'sms_mode' => $_GET['sms_mode'] ?? 'max',
    'sms_count' => $_GET['sms_count'] ?? '',
    'social_mode' => $_GET['social_mode'] ?? 'max',
    'social_gb' => $_GET['social_gb'] ?? '',
];

$selectedOperators = array_values(array_filter(array_map('intval', (array) ($_GET['operators'] ?? [])), static fn ($id) => $id > 0));
$searched = isset($_GET['compare']);
$results = [];
$error = null;

if ($searched) {
    $budget = (float) $profile['budget_kz'];
    if ($budget < 100) {
        $error = 'Informe quanto pretende carregar, a partir de 100 Kz.';
    } elseif ($selectedServices === []) {
        $error = 'Escolha pelo menos uma utilização: internet, chamadas, SMS ou redes sociais.';
    } else {
        $plans = $repo->activePlans($selectedOperators);
        $tariffs = $repo->activeTariffs($selectedOperators);
        $engine = new RecommendationEngine();
        $results = $engine->recommend($plans, $profile, $tariffs);
        $repo->logSearch($profile, count($results));
    }
}

$lastVerified = null;
try {
    $stats = $repo->dashboardStats();
    $lastVerified = $stats['last_checked'] ?? null;
} catch (Throwable) {
    $lastVerified = null;
}

function capacity_text(array $capacity, array $services): string
{
    $items = [];
    foreach ($services as $service) {
        $value = (float) ($capacity[$service] ?? 0);
        if ($value <= 0) continue;
        if (in_array($service, ['data', 'social'], true)) {
            $items[] = $value >= 1024
                ? number_format($value / 1024, 1, ',', '.') . ' GB ' . ($service === 'social' ? 'para redes sociais' : 'de internet')
                : number_format($value, 0, ',', '.') . ' MB ' . ($service === 'social' ? 'para redes sociais' : 'de internet');
        } elseif ($service === 'voice') {
            $items[] = number_format($value, 0, ',', '.') . ' min de chamadas';
        } elseif ($service === 'sms') {
            $items[] = number_format($value, 0, ',', '.') . ' SMS';
        }
    }
    return implode(' · ', $items);
}

function potential_label(string $service, float $value): string
{
    return match ($service) {
        'data' => $value >= 1024 ? number_format($value / 1024, 1, ',', '.') . ' GB de internet' : number_format($value, 0, ',', '.') . ' MB de internet',
        'social' => $value >= 1024 ? number_format($value / 1024, 1, ',', '.') . ' GB para redes sociais' : number_format($value, 0, ',', '.') . ' MB para redes sociais',
        'voice' => number_format($value, 0, ',', '.') . ' minutos de chamadas',
        'sms' => number_format($value, 0, ',', '.') . ' SMS',
        default => '',
    };
}

function service_label(string $service): string
{
    return match ($service) {
        'data' => 'Internet',
        'voice' => 'Chamadas',
        'sms' => 'SMS',
        'social' => 'Redes sociais',
        default => $service,
    };
}
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QualPacote — Faça o seu saldo render mais</title>
    <meta name="description" content="Compare saldo normal e pacotes móveis pelo que realmente consegue fazer com o valor que pretende carregar.">
    <link rel="stylesheet" href="/assets/app.css?v=2">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/">Qual<span>Pacote</span></a>
        <a class="admin-link" href="/admin/">Admin</a>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <p class="eyebrow">Faça o saldo render</p>
                <h1>Tenho este valor. O que me compensa carregar?</h1>
                <p class="lead">Diga quanto tem e o que precisa fazer. O QualPacote compara pacotes e saldo normal, considera validade e limitações, e mostra as opções que entregam mais valor para o seu objectivo.</p>
                <div class="hero-points">
                    <span>Pacotes</span><span>Saldo normal</span><span>Tarifas reais</span><span>Restrições consideradas</span>
                </div>
            </div>

            <form class="finder-card" method="get" action="/" data-intelligence-form>
                <input type="hidden" name="compare" value="1">

                <div class="field">
                    <label for="budget_kz">Quanto pretende carregar?</label>
                    <div class="money-input large-money">
                        <input id="budget_kz" name="budget_kz" type="number" min="100" step="50" inputmode="numeric" required value="<?= e($profile['budget_kz']) ?>" placeholder="Ex.: 2.000">
                        <span>Kz</span>
                    </div>
                </div>

                <div class="field">
                    <label>Para que quer usar este valor?</label>
                    <div class="choice-grid service-choice-grid">
                        <?php foreach (['data' => 'Internet', 'voice' => 'Chamadas', 'sms' => 'SMS', 'social' => 'Redes sociais'] as $value => $label): ?>
                            <label class="choice service-choice">
                                <input type="checkbox" name="services[]" value="<?= e($value) ?>"<?= checked(in_array($value, $selectedServices, true)) ?> data-service-toggle="<?= e($value) ?>">
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small>Escolha uma ou várias. A combinação é feita a partir do que marcar.</small>
                </div>

                <div class="intent-panels">
                    <section class="intent-panel" data-service-panel="data"<?= in_array('data', $selectedServices, true) ? '' : ' hidden' ?>>
                        <strong>Internet</strong>
                        <label class="intent-option"><input type="radio" name="data_mode" value="max"<?= checked($profile['data_mode'] === 'max') ?>> Quero o máximo de internet possível com este valor</label>
                        <label class="intent-option"><input type="radio" name="data_mode" value="minimum"<?= checked($profile['data_mode'] === 'minimum') ?>> Preciso de pelo menos</label>
                        <div class="inline-target" data-mode-target="data"<?= $profile['data_mode'] === 'minimum' ? '' : ' hidden' ?>><input name="data_gb" type="number" min="0" step="0.5" value="<?= e($profile['data_gb']) ?>" placeholder="Ex.: 5"><span>GB</span></div>
                    </section>

                    <section class="intent-panel" data-service-panel="voice"<?= in_array('voice', $selectedServices, true) ? '' : ' hidden' ?>>
                        <strong>Chamadas</strong>
                        <label class="intent-option"><input type="radio" name="voice_mode" value="max"<?= checked($profile['voice_mode'] === 'max') ?>> Quero falar o máximo possível</label>
                        <label class="intent-option"><input type="radio" name="voice_mode" value="minutes"<?= checked($profile['voice_mode'] === 'minutes') ?>> Preciso de pelo menos alguns minutos</label>
                        <div class="inline-target" data-mode-target="voice-minutes"<?= $profile['voice_mode'] === 'minutes' ? '' : ' hidden' ?>><input name="minutes" type="number" min="0" step="10" value="<?= e($profile['minutes']) ?>" placeholder="Ex.: 120"><span>min</span></div>
                        <label class="intent-option"><input type="radio" name="voice_mode" value="calls"<?= checked($profile['voice_mode'] === 'calls') ?>> Quero fazer pelo menos um número de chamadas</label>
                        <div class="call-target" data-mode-target="voice-calls"<?= $profile['voice_mode'] === 'calls' ? '' : ' hidden' ?>>
                            <label><input name="calls" type="number" min="1" step="1" value="<?= e($profile['calls']) ?>" placeholder="Ex.: 30"><span>chamadas</span></label>
                            <label><select name="avg_call_minutes"><option value="1"<?= selected($profile['avg_call_minutes'], '1') ?>>~1 min cada</option><option value="3"<?= selected($profile['avg_call_minutes'], '3') ?>>~3 min cada</option><option value="5"<?= selected($profile['avg_call_minutes'], '5') ?>>~5 min cada</option><option value="10"<?= selected($profile['avg_call_minutes'], '10') ?>>~10 min cada</option></select></label>
                        </div>
                    </section>

                    <section class="intent-panel" data-service-panel="sms"<?= in_array('sms', $selectedServices, true) ? '' : ' hidden' ?>>
                        <strong>SMS</strong>
                        <label class="intent-option"><input type="radio" name="sms_mode" value="max"<?= checked($profile['sms_mode'] === 'max') ?>> Quero enviar o máximo possível</label>
                        <label class="intent-option"><input type="radio" name="sms_mode" value="minimum"<?= checked($profile['sms_mode'] === 'minimum') ?>> Preciso de pelo menos</label>
                        <div class="inline-target" data-mode-target="sms"<?= $profile['sms_mode'] === 'minimum' ? '' : ' hidden' ?>><input name="sms_count" type="number" min="0" step="1" value="<?= e($profile['sms_count']) ?>" placeholder="Ex.: 50"><span>SMS</span></div>
                    </section>

                    <section class="intent-panel" data-service-panel="social"<?= in_array('social', $selectedServices, true) ? '' : ' hidden' ?>>
                        <strong>Redes sociais</strong>
                        <label class="intent-option"><input type="radio" name="social_mode" value="max"<?= checked($profile['social_mode'] === 'max') ?>> Quero o máximo de dados para redes sociais</label>
                        <label class="intent-option"><input type="radio" name="social_mode" value="minimum"<?= checked($profile['social_mode'] === 'minimum') ?>> Preciso de pelo menos</label>
                        <div class="inline-target" data-mode-target="social"<?= $profile['social_mode'] === 'minimum' ? '' : ' hidden' ?>><input name="social_gb" type="number" min="0" step="0.5" value="<?= e($profile['social_gb']) ?>" placeholder="Ex.: 2"><span>GB</span></div>
                    </section>
                </div>

                <div class="field" data-priority-field<?= count($selectedServices) > 1 ? '' : ' hidden' ?>>
                    <label for="priority">Se tiver de escolher, o que não pode faltar?</label>
                    <select id="priority" name="priority">
                        <option value="balanced"<?= selected($profile['priority'], 'balanced') ?>>Quero equilíbrio</option>
                        <option value="data"<?= selected($profile['priority'], 'data') ?>>Internet primeiro</option>
                        <option value="voice"<?= selected($profile['priority'], 'voice') ?>>Chamadas primeiro</option>
                        <option value="sms"<?= selected($profile['priority'], 'sms') ?>>SMS primeiro</option>
                        <option value="social"<?= selected($profile['priority'], 'social') ?>>Redes sociais primeiro</option>
                    </select>
                </div>

                <div class="field">
                    <label for="duration_days">Até quando precisa que este valor lhe sirva?</label>
                    <select id="duration_days" name="duration_days">
                        <option value="0"<?= selected($profile['duration_days'], '0') ?>>Quero usar até acabar, sem prazo do pacote</option>
                        <option value="1"<?= selected($profile['duration_days'], '1') ?>>Só hoje</option>
                        <option value="3"<?= selected($profile['duration_days'], '3') ?>>Pelo menos 3 dias</option>
                        <option value="7"<?= selected($profile['duration_days'], '7') ?>>Pelo menos 7 dias</option>
                        <option value="15"<?= selected($profile['duration_days'], '15') ?>>Pelo menos 15 dias</option>
                        <option value="30"<?= selected($profile['duration_days'], '30') ?>>Pelo menos 30 dias</option>
                        <option value="60"<?= selected($profile['duration_days'], '60') ?>>Pelo menos 60 dias</option>
                    </select>
                </div>

                <details class="fine-tune">
                    <summary>Mais detalhes, se forem importantes para si</summary>
                    <div class="details-fields">
                        <div class="two-cols">
                            <div class="field">
                                <label for="schedule">Quando costuma usar mais?</label>
                                <select id="schedule" name="schedule"><option value="day"<?= selected($profile['schedule'], 'day') ?>>Durante o dia</option><option value="mixed"<?= selected($profile['schedule'], 'mixed') ?>>Dia e noite</option><option value="night"<?= selected($profile['schedule'], 'night') ?>>Principalmente à noite/madrugada</option></select>
                            </div>
                            <div class="field" data-call-scope-field<?= in_array('voice', $selectedServices, true) ? '' : ' hidden' ?>>
                                <label for="call_scope">Para quem liga mais?</label>
                                <select id="call_scope" name="call_scope"><option value="mixed"<?= selected($profile['call_scope'], 'mixed') ?>>Para várias redes</option><option value="same"<?= selected($profile['call_scope'], 'same') ?>>Principalmente para a mesma rede</option><option value="other"<?= selected($profile['call_scope'], 'other') ?>>Principalmente para outras redes</option></select>
                            </div>
                        </div>
                        <div class="field">
                            <label>Que cartões pode usar?</label>
                            <div class="operator-options">
                                <?php foreach ($operators as $operator): ?>
                                    <label><input type="checkbox" name="operators[]" value="<?= (int) $operator['id'] ?>"<?= checked($selectedOperators === [] || in_array((int) $operator['id'], $selectedOperators, true)) ?>><span><?= e($operator['name']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                            <small>Se deixar todos marcados, comparamos todas as redes disponíveis.</small>
                        </div>
                    </div>
                </details>

                <button class="primary-btn" type="submit">Ver o que compensa mais</button>
            </form>
        </div>
    </section>

    <?php if ($searched): ?>
        <section class="results-section" id="resultados">
            <div class="container">
                <div class="section-heading">
                    <div><p class="eyebrow">Com <?= e(money($profile['budget_kz'])) ?></p><h2>Opções mais úteis para o que pediu</h2></div>
                    <?php if ($lastVerified): ?><p class="verified">Catálogo revisto até <?= e(date_ao($lastVerified)) ?></p><?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <div class="notice error"><?= e($error) ?></div>
                <?php elseif ($results === []): ?>
                    <div class="empty-state"><h3>Ainda não há uma opção comparável para estes critérios.</h3><p>Experimente mais operadoras, outro período ou reveja o orçamento.</p></div>
                <?php else: ?>
                    <div class="results-list">
                        <?php foreach ($results as $index => $result):
                            $plan = $result['plan'];
                            $tariff = $result['tariff'];
                            $sourceUrl = $result['kind'] === 'plan' ? ($plan['source_url'] ?? '') : ($tariff['source_url'] ?? '');
                            $validity = $result['kind'] === 'plan' ? (int) ($plan['validity_days'] ?? 0) : (($tariff['balance_validity_days'] ?? null) !== null ? (int) $tariff['balance_validity_days'] : 0);
                        ?>
                            <article class="result-card<?= $index === 0 ? ' top-result' : '' ?>">
                                <div class="result-rank">
                                    <span><?= $index === 0 ? 'Melhor opção para o que pediu' : 'Alternativa ' . ($index + 1) ?></span>
                                    <strong><?= e($result['operator_name']) ?></strong>
                                </div>
                                <div class="result-main">
                                    <div>
                                        <div class="result-type"><?= $result['kind'] === 'balance' ? 'Saldo normal' : 'Pacote' ?></div>
                                        <h3><?= e($result['title']) ?></h3>
                                        <p class="result-summary"><?= e($result['summary']) ?></p>
                                        <p class="capacity"><?= e(capacity_text($result['capacity'], $selectedServices)) ?></p>
                                    </div>
                                    <div class="result-price"><strong><?= e(money($result['effective_cost'])) ?></strong><span><?= $result['kind'] === 'plan' && $result['cycles'] > 1 ? $result['cycles'] . ' activaçōes para o período' : 'do seu orçamento' ?></span></div>
                                </div>

                                <div class="practical-facts">
                                    <span class="fact <?= $result['meets_requirements'] ? 'good' : 'warn' ?>"><?= $result['meets_requirements'] ? 'Cumpre os mínimos e o período' : 'Não cobre tudo o que pediu' ?></span>
                                    <?php if ($validity > 0): ?><span class="fact">Validade: <?= $validity ?> dias</span><?php else: ?><span class="fact">Sem validade de pacote informada</span><?php endif; ?>
                                    <?php if ($result['kind'] === 'plan' && $result['equivalent_balance_complete'] && $result['equivalent_balance_kz']): ?>
                                        <span class="fact strong">Equivale a ~<?= e(money($result['equivalent_balance_kz'])) ?> em saldo normal</span>
                                    <?php endif; ?>
                                </div>

                                <?php if ($result['kind'] === 'balance' && $result['balance_potentials'] !== []): ?>
                                    <div class="balance-intelligence">
                                        <strong>Se gastar todo este valor num único serviço:</strong>
                                        <div class="potential-grid">
                                            <?php foreach ($selectedServices as $service): $value = (float) ($result['balance_potentials'][$service] ?? 0); if ($value <= 0) continue; ?>
                                                <span><b><?= e(service_label($service)) ?></b><?= e(potential_label($service, $value)) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                        <?php if (count($selectedServices) > 1): ?><small>Na estimativa combinada acima, o mesmo saldo é repartido entre os usos seleccionados; o valor não é contado duas vezes.</small><?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($result['kind'] === 'plan' && $result['equivalent_balance_complete'] && $result['equivalent_balance_kz'] && $result['equivalent_balance_kz'] > $result['effective_cost']): ?>
                                    <div class="value-comparison"><strong>Vantagem sobre saldo normal</strong><span>Para comprar aproximadamente estes benefícios pela tarifa <?= e($result['equivalent_tariff_name']) ?> seriam necessários ~<?= e(money($result['equivalent_balance_kz'])) ?>.</span></div>
                                <?php endif; ?>

                                <?php if ($result['restrictions'] !== []): ?>
                                    <div class="restrictions"><strong>Atenção</strong><ul><?php foreach ($result['restrictions'] as $restriction): ?><li><?= e($restriction) ?></li><?php endforeach; ?></ul></div>
                                <?php endif; ?>

                                <div class="result-footer">
                                    <div>
                                        <?php if ($result['kind'] === 'plan' && !empty($plan['activation_code'])): ?><span class="activation">Activar: <strong><?= e($plan['activation_code']) ?></strong></span><?php endif; ?>
                                        <span><?= $result['kind'] === 'balance' ? 'Consumo pela tarifa normal' : 'Pacote pré-definido' ?></span>
                                    </div>
                                    <?php if ($sourceUrl): ?><a href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener noreferrer">Ver fonte</a><?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="catalog-note">Os valores dependem das tarifas e pacotes verificados no catálogo. As operadoras podem alterar condições; confirme antes de activar.</p>
            </div>
        </section>
    <?php endif; ?>

    <section class="how-it-works">
        <div class="container">
            <div class="section-heading compact"><div><p class="eyebrow">Uma comparação mais útil</p><h2>O saldo normal também entra na conta.</h2></div></div>
            <div class="feature-grid">
                <article><span>01</span><h3>O seu orçamento primeiro</h3><p>Comparamos apenas alternativas que cabem no valor que pretende carregar.</p></article>
                <article><span>02</span><h3>O que quer conseguir</h3><p>Pode maximizar internet, chamadas ou SMS, ou indicar mínimos que precisam ser cumpridos.</p></article>
                <article><span>03</span><h3>Pacote vs. saldo</h3><p>Quando a tarifa normal é conhecida, calculamos quanto o mesmo dinheiro rende sem pacote.</p></article>
                <article><span>04</span><h3>Limitações contam</h3><p>Validade, horário, rede e benefícios específicos reduzem o valor quando não servem ao seu uso.</p></article>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer"><div class="container"><strong>QualPacote</strong><span>Inteligência prática para fazer o saldo render.</span></div></footer>
<script src="/assets/app.js?v=2"></script>
</body>
</html>
