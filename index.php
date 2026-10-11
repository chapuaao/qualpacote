<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';
header('Cache-Control: no-store');

use QualPacote\Csrf;
use QualPacote\UsageRepository;
use QualPacote\UsageSession;
use QualPacote\Database;
use QualPacote\PlanRepository;
use QualPacote\RecommendationEngine;

$repo = new PlanRepository(Database::connection());
$operators = $repo->operators(true);
$usage = new UsageRepository(Database::connection());
$visitor = UsageSession::visitorKey();
$usageAvailable = true;
$feedbackResults = [];
try {
    $usage->recordVisit($visitor);
} catch (Throwable) {
    // A pending migration must never prevent a public comparison.
    $usageAvailable = false;
}

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
    'social_apps' => (array) ($_GET['social_apps'] ?? []),
    'sms_scope' => $_GET['sms_scope'] ?? 'mixed',
    'category' => ($_GET['category'] ?? '') === 'router' ? 'router' : 'mobile',
    'router_ready' => !empty($_GET['router_ready']),
    'active_plan_ids' => (array) ($_GET['active_plan_ids'] ?? []),
];

$selectedOperators = array_values(array_filter(array_map('intval', (array) ($_GET['operators'] ?? [])), static fn ($id) => $id > 0));
$availablePlans = $repo->activePlans($selectedOperators);
$activeChoices = [];
foreach ($availablePlans as $existing) {
    if (empty($existing['rules']['family']) || !empty($existing['rules']['requires_family'])) continue;
    $needed = false;
    foreach ($availablePlans as $offer) {
        if ((int) $offer['operator_id'] !== (int) $existing['operator_id']) continue;
        if (($offer['rules']['requires_family'] ?? '') === $existing['rules']['family'] || in_array($existing['rules']['family'], $offer['rules']['incompatible_families'] ?? [], true)) $needed = true;
    }
    if (!$needed) continue;
    $familyKey = $existing['operator_id'] . ':' . $existing['rules']['family'];
    $activeChoices[$familyKey] ??= $existing;
    if (in_array((string) $existing['id'], array_map('strval',$profile['active_plan_ids']),true)) $activeChoices[$familyKey] = $existing;
}
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
        $plans = $availablePlans;
        $tariffs = $repo->activeTariffs($selectedOperators);
        $engine = new RecommendationEngine();
        $results = $engine->recommend($plans, $profile, $tariffs);
        $requestToken = UsageSession::requestToken($_GET['consultation'] ?? null, [$profile, $selectedOperators]);
        if ($usageAvailable) {
            try {
                $feedbackResults = $usage->recordConsultation($visitor, $requestToken, $profile, $results, (string) ($_SERVER['QUERY_STRING'] ?? ''));
            } catch (Throwable) {
                $usageAvailable = false;
            }
        }
        if (!$usageAvailable && !isset($_SESSION['_usage_legacy'][$requestToken])) {
            if ($repo->logSearch($profile, count($results)) !== null) {
                $_SESSION['_usage_legacy'][$requestToken] = true;
                $_SESSION['_usage_legacy'] = array_slice($_SESSION['_usage_legacy'], -100, null, true);
            }
        }
    }
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
    <link rel="stylesheet" href="/assets/app.css?v=5">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/">Qual<span>Pacote</span></a>
        <a class="admin-link" href="/catalog.php">Consultar catálogo</a><a class="admin-link" href="/admin/">Admin</a>
    </div>
</header>

<main>
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <p class="eyebrow">Faça o saldo render</p>
                <h1>Tenho este valor. O que me compensa carregar?</h1>
                <p class="lead">Escolha o orçamento e o que precisa. Compare pacotes e saldo normal.</p>
            </div>

            <form class="finder-card" method="get" action="/" data-intelligence-form>
                <input type="hidden" name="compare" value="1">
                <input type="hidden" name="consultation" value="<?= e(UsageSession::formToken()) ?>">

                <div class="field"><label for="category">Onde pretende usar?</label><select id="category" name="category"><option value="mobile"<?= selected($profile['category'], 'mobile') ?>>No telemóvel</option><option value="router"<?= selected($profile['category'], 'router') ?>>Num MiFi / router</option></select></div>
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
                </div>

                <div class="intent-panels">
                    <section class="intent-panel" data-service-panel="data"<?= in_array('data', $selectedServices, true) ? '' : ' hidden' ?>>
                        <strong>Internet</strong>
                        <label class="intent-option"><input type="radio" name="data_mode" value="max"<?= checked($profile['data_mode'] === 'max') ?>> O máximo de internet</label>
                        <label class="intent-option"><input type="radio" name="data_mode" value="minimum"<?= checked($profile['data_mode'] === 'minimum') ?>> Preciso de pelo menos</label>
                        <div class="inline-target" data-mode-target="data"<?= $profile['data_mode'] === 'minimum' ? '' : ' hidden' ?>><input name="data_gb" type="number" min="0" step="0.5" value="<?= e($profile['data_gb']) ?>" placeholder="Ex.: 5"><span>GB</span></div>
                    </section>

                    <section class="intent-panel" data-service-panel="voice"<?= in_array('voice', $selectedServices, true) ? '' : ' hidden' ?>>
                        <strong>Chamadas</strong>
                        <label class="intent-option"><input type="radio" name="voice_mode" value="max"<?= checked($profile['voice_mode'] === 'max') ?>> O máximo de chamadas</label>
                        <label class="intent-option"><input type="radio" name="voice_mode" value="minutes"<?= checked($profile['voice_mode'] === 'minutes') ?>> Pelo menos</label>
                        <div class="inline-target" data-mode-target="voice-minutes"<?= $profile['voice_mode'] === 'minutes' ? '' : ' hidden' ?>><input name="minutes" type="number" min="0" step="10" value="<?= e($profile['minutes']) ?>" placeholder="Ex.: 120"><span>min</span></div>
                        <label class="intent-option"><input type="radio" name="voice_mode" value="calls"<?= checked($profile['voice_mode'] === 'calls') ?>> Número de chamadas</label>
                        <div class="call-target" data-mode-target="voice-calls"<?= $profile['voice_mode'] === 'calls' ? '' : ' hidden' ?>>
                            <label><input name="calls" type="number" min="1" step="1" value="<?= e($profile['calls']) ?>" placeholder="Ex.: 30"><span>chamadas</span></label>
                            <label><select name="avg_call_minutes"><option value="0.5"<?= selected($profile['avg_call_minutes'], '0.5') ?>>~30 segundos cada</option><option value="1"<?= selected($profile['avg_call_minutes'], '1') ?>>~1 min cada</option><option value="3"<?= selected($profile['avg_call_minutes'], '3') ?>>~3 min cada</option><option value="5"<?= selected($profile['avg_call_minutes'], '5') ?>>~5 min cada</option><option value="10"<?= selected($profile['avg_call_minutes'], '10') ?>>~10 min cada</option></select></label>
                        </div>
                    </section>

                    <section class="intent-panel" data-service-panel="sms"<?= in_array('sms', $selectedServices, true) ? '' : ' hidden' ?>>
                        <strong>SMS</strong>
                        <label class="intent-option"><input type="radio" name="sms_mode" value="max"<?= checked($profile['sms_mode'] === 'max') ?>> O máximo de SMS</label>
                        <label class="intent-option"><input type="radio" name="sms_mode" value="minimum"<?= checked($profile['sms_mode'] === 'minimum') ?>> Preciso de pelo menos</label>
                        <div class="inline-target" data-mode-target="sms"<?= $profile['sms_mode'] === 'minimum' ? '' : ' hidden' ?>><input name="sms_count" type="number" min="0" step="1" value="<?= e($profile['sms_count']) ?>" placeholder="Ex.: 50"><span>SMS</span></div>
                    </section>

                    <section class="intent-panel" data-service-panel="social"<?= in_array('social', $selectedServices, true) ? '' : ' hidden' ?>>
                        <strong>Redes sociais</strong><small>Consumo adicional à internet, se também a seleccionar.</small><div class="operator-options"><?php foreach (['whatsapp'=>'WhatsApp','facebook'=>'Facebook','instagram'=>'Instagram','tiktok'=>'TikTok','youtube'=>'YouTube','x'=>'X','snapchat'=>'Snapchat','other'=>'Outras aplicações'] as $app=>$label): ?><label><input type="checkbox" name="social_apps[]" value="<?= e($app) ?>"<?= checked(in_array($app,$profile['social_apps'],true)) ?>><span><?= e($label) ?></span></label><?php endforeach; ?></div><small>Sem aplicações seleccionadas, comparamos apenas dados gerais.</small>
                        <label class="intent-option"><input type="radio" name="social_mode" value="max"<?= checked($profile['social_mode'] === 'max') ?>> O máximo de dados</label>
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
                        <option value="0"<?= selected($profile['duration_days'], '0') ?>>Não tenho um período mínimo</option>
                        <option value="1"<?= selected($profile['duration_days'], '1') ?>>Só hoje</option>
                        <option value="3"<?= selected($profile['duration_days'], '3') ?>>Pelo menos 3 dias</option>
                        <option value="7"<?= selected($profile['duration_days'], '7') ?>>Pelo menos 7 dias</option>
                        <option value="15"<?= selected($profile['duration_days'], '15') ?>>Pelo menos 15 dias</option>
                        <option value="30"<?= selected($profile['duration_days'], '30') ?>>Pelo menos 30 dias</option>
                        <option value="60"<?= selected($profile['duration_days'], '60') ?>>Pelo menos 60 dias</option>
                    </select>
                </div>

                <details class="fine-tune">
                    <summary>Filtros avançados</summary>
                    <div class="details-fields">
                        <div class="field">
                            <label>Quais redes preferes?</label>
                            <div class="operator-options">
                                <?php foreach ($operators as $operator): ?>
                                    <label><input type="checkbox" name="operators[]" value="<?= (int) $operator['id'] ?>"<?= checked($selectedOperators === [] || in_array((int) $operator['id'], $selectedOperators, true)) ?>><span><?= e($operator['name']) ?></span></label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php if ($activeChoices !== []): ?><div class="field" data-active-plans-field<?= $profile['category'] === 'router' ? ' hidden' : '' ?>><label>Plano já activo durante todo o período?</label><div class="operator-options"><?php foreach ($activeChoices as $existing): ?><label><input type="checkbox" name="active_plan_ids[]" value="<?= (int) $existing['id'] ?>"<?= checked(in_array((string) $existing['id'], array_map('strval',$profile['active_plan_ids']),true)) ?>> <span><?= e($existing['operator_name'] . ' — ' . strtoupper($existing['rules']['family'])) ?></span></label><?php endforeach; ?></div><small>Só confirma a elegibilidade; os benefícios existentes não entram nesta compra.</small></div><?php endif; ?>
                        <div class="field" data-sms-scope-field<?= in_array('sms', $selectedServices, true) ? '' : ' hidden' ?>><label for="sms_scope">Para quem envia SMS?</label><select id="sms_scope" name="sms_scope"><?php foreach (['mixed'=>'Várias redes','same'=>'Mesma rede','other'=>'Outras redes'] as $v=>$l): ?><option value="<?= e($v) ?>"<?= selected($profile['sms_scope'],$v) ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
                        <div class="two-cols">
                            <div class="field">
                                <label for="schedule">Quando costuma usar mais?</label>
                                <select id="schedule" name="schedule"><option value="day"<?= selected($profile['schedule'], 'day') ?>>Durante o dia (referência: 12h)</option><option value="mixed"<?= selected($profile['schedule'], 'mixed') ?>>Dia e noite</option><option value="night"<?= selected($profile['schedule'], 'night') ?>>À noite (referência: 21h)</option><option value="late_night"<?= selected($profile['schedule'], 'late_night') ?>>De madrugada (referência: 02h)</option></select>
                            </div>
                            <div class="field" data-call-scope-field<?= in_array('voice', $selectedServices, true) ? '' : ' hidden' ?>>
                                <label for="call_scope">Para quem liga mais?</label>
                                <select id="call_scope" name="call_scope"><option value="mixed"<?= selected($profile['call_scope'], 'mixed') ?>>Para várias redes</option><option value="same"<?= selected($profile['call_scope'], 'same') ?>>Para a mesma rede</option><option value="other"<?= selected($profile['call_scope'], 'other') ?>>Para outras redes</option></select>
                            </div>
                        </div>
                        <label class="inline-check" data-router-ready-field<?= $profile['category'] === 'router' ? '' : ' hidden' ?>><input type="checkbox" name="router_ready" value="1"<?= checked($profile['router_ready']) ?>> Já tenho SIM e MiFi/router compatíveis com a oferta</label>
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
                    <div><p class="eyebrow">Com <?= e(money($profile['budget_kz'])) ?></p><h2>Opções para si</h2></div>
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
                                    <span><?= $index === 0 ? '1ª opção' : 'Alternativa ' . ($index + 1) ?></span>
                                    <strong><?= e($result['operator_name']) ?></strong>
                                </div>
                                <div class="result-main">
                                    <div>
                                        <div class="result-type"><?= !empty($result['is_combination']) ? 'Combinação de compras' : ($result['kind'] === 'balance' ? 'Saldo normal' : 'Pacote') ?></div>
                                        <h3><?= e($result['title']) ?></h3>
                                        <p class="capacity"><?= e(capacity_text($result['capacity'], $selectedServices)) ?></p>
                                    </div>
                                    <div class="result-price"><strong><?= e(money($result['effective_cost'])) ?></strong><span><?= $result['kind'] === 'plan' && $result['cycles'] > 1 ? $result['cycles'] . ' activações planeadas' : 'do seu orçamento' ?></span></div>
                                </div>

                                <div class="practical-facts">
                                    <span class="fact">Sobra: <?= e(money($result['remaining_budget'])) ?></span>
                                    <span class="fact <?= $result['meets_requirements'] ? 'good' : 'warn' ?>"><?= $result['meets_requirements'] ? 'Cumpre o pedido' : 'Não cobre tudo' ?></span>
                                    <?php if (!empty($result['is_combination'])): ?><span class="fact">Validade por compra</span><?php elseif ($validity > 0): ?><span class="fact">Validade: <?= $validity ?> dias</span><?php else: ?><span class="fact">Validade por confirmar</span><?php endif; ?>
                                    <?php if ($result['kind'] === 'plan' && $result['equivalent_balance_complete'] && $result['equivalent_balance_kz']): ?>
                                        <span class="fact strong">Equivale a ~<?= e(money($result['equivalent_balance_kz'])) ?> em saldo normal</span>
                                    <?php endif; ?>
                                </div>

                                <?php
                                $essential = array_values(array_unique($result['conditional_requirements']));
                                $details = array_values(array_filter($result['restrictions'], static function ($notice) use ($essential) {
                                    if (str_contains($notice, 'precisa de nova verificação')) return false;
                                    foreach ($essential as $requirement) if (str_contains($notice, $requirement)) return false;
                                    return true;
                                }));
                                $purchaseSteps = !empty($result['is_combination']) || $result['cycles'] > 1;
                                ?>
                                <?php if ($essential !== []): ?><div class="restrictions essential-conditions"><strong>Antes de comprar</strong><ul><?php foreach ($essential as $condition): ?><li><?= e($condition) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
                                <?php if ($details !== []): ?><details class="result-details"><summary>Condições e limites</summary><ul><?php foreach ($details as $restriction): ?><li><?= e($restriction) ?></li><?php endforeach; ?></ul></details><?php endif; ?>

                                <?php if ($result['kind'] === 'balance' && count($selectedServices) > 1 && $result['balance_potentials'] !== []): ?>
                                    <details class="result-details"><summary>Se usar todo o saldo num só serviço</summary><div class="potential-grid">
                                        <?php foreach ($selectedServices as $service): $value = (float) ($result['balance_potentials'][$service] ?? 0); if ($value <= 0) continue; ?><span><b><?= e(service_label($service)) ?></b> <?= e(potential_label($service, $value)) ?></span><?php endforeach; ?>
                                    </div></details>
                                <?php endif; ?>

                                <?php if ($purchaseSteps): ?>
                                <div class="purchase-steps"><strong>Como comprar</strong><ol>
                                <?php foreach ($result['components'] as $part): $item = $part['plan'] ?? $part['tariff']; $channels = array_values(array_filter(array_map('trim', explode('|', (string) ($item['activation_code'] ?? ''))))); ?>
                                    <li><b><?= e($item['name']) ?></b> — <?= e(money($part['cost'])) ?>.
                                    <?php if (isset($part['plan'])): foreach ($part['schedule'] as $step): ?> Dia <?= (int) $step['day'] ?>: <?= (int) $step['count'] ?> compra(s).<?php endforeach; ?>
                                    Validade: <?= (int) $item['validity_days'] ?> dias por compra.
                                    <?php if ($channels !== []): ?> Activar: <strong><?= e(array_shift($channels)) ?></strong>.<?php endif; ?>
                                    <?php if ($channels !== []): ?><details class="activation-channels"><summary>Outros canais</summary><?= e(implode(' · ', $channels)) ?></details><?php endif; ?>
                                    <?php else: ?> Saldo para consumo directo.<?php endif; ?>
                                    <div class="source-meta">Verificado em <?= e(date_ao($item['source_checked_at'] ?? '')) ?>. <?php if (!empty($item['source_observed_at'])): ?>Fonte inalterada em <?= e(date_ao($item['source_observed_at'])) ?>.<?php endif; ?> <?php if (!empty($item['source_url'])): ?><a href="<?= e($item['source_url']) ?>" target="_blank" rel="noopener noreferrer">Fonte</a><?php endif; ?>
                                    <?php if (max(strtotime($item['source_checked_at'] ?? '') ?: 0, strtotime($item['source_observed_at'] ?? '') ?: 0) < strtotime('-7 days')): ?><strong class="source-warning">Confirmar condições</strong><?php endif; ?></div>
                                    </li>
                                <?php endforeach; ?></ol></div>
                                <?php else: $item = $plan ?? $tariff; $channels = array_values(array_filter(array_map('trim', explode('|', (string) ($item['activation_code'] ?? ''))))); ?>
                                <div class="result-footer">
                                    <?php if ($channels !== []): ?><span class="activation">Activar: <strong><?= e(array_shift($channels)) ?></strong></span><?php endif; ?>
                                    <span>Verificado em <?= e(date_ao($item['source_checked_at'] ?? '')) ?><?php if (!empty($item['source_observed_at'])): ?> · Fonte inalterada em <?= e(date_ao($item['source_observed_at'])) ?><?php endif; ?></span>
                                    <?php if ($sourceUrl): ?><a href="<?= e($sourceUrl) ?>" target="_blank" rel="noopener noreferrer">Fonte</a><?php endif; ?>
                                    <?php if (max(strtotime($item['source_checked_at'] ?? '') ?: 0, strtotime($item['source_observed_at'] ?? '') ?: 0) < strtotime('-7 days')): ?><strong class="source-warning">Confirmar condições</strong><?php endif; ?>
                                </div>
                                <?php if ($channels !== []): ?><details class="activation-channels"><summary>Outros canais de activação</summary><?= e(implode(' · ', $channels)) ?></details><?php endif; ?>
                                <?php endif; ?>
                                <?php $feedback = $feedbackResults[UsageRepository::resultKey($result)] ?? null; if ($feedback): ?>
                                    <form class="result-feedback" method="post" action="/feedback.php" data-feedback-form>
                                        <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                                        <input type="hidden" name="result" value="<?= e($feedback['vote_token']) ?>">
                                        <strong>Foi útil?</strong>
                                        <div class="feedback-actions">
                                            <button class="secondary-btn small" type="submit" name="vote" value="1" aria-pressed="<?= (int) $feedback['vote'] === 1 ? 'true' : 'false' ?>">Gostei</button>
                                            <button class="secondary-btn small" type="submit" name="vote" value="-1" aria-pressed="<?= (int) $feedback['vote'] === -1 ? 'true' : 'false' ?>">Não gostei</button>
                                        </div>
                                        <span class="feedback-status" role="status" aria-live="polite"><?= $feedback['vote'] !== null ? 'Obrigado! A sua opinião ficou registada.' : '' ?></span>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="catalog-note">Confirme as condições na operadora antes de activar.</p><details class="result-details"><summary>Sobre esta comparação</summary><p>Comparamos as ofertas disponíveis no catálogo. Combinações: até dois tipos de pacote, mais a base necessária, na mesma rede. Horários de referência e validades de cada carteira limitam a utilização.</p></details>
            </div>
        </section>
    <?php endif; ?>

</main>

<footer class="site-footer"><div class="container"><strong>QualPacote</strong><span>Inteligência prática para fazer o saldo render.</span></div></footer>
<script src="/assets/app.js?v=5"></script>
</body>
</html>
