<?php
declare(strict_types=1);

require_once __DIR__ . '/app/bootstrap.php';

use QualPacote\Database;
use QualPacote\PlanRepository;
use QualPacote\RecommendationEngine;

$repo = new PlanRepository(Database::connection());
$operators = $repo->operators(true);

$profile = [
    'budget_kz' => $_GET['budget_kz'] ?? '',
    'goal' => $_GET['goal'] ?? 'balanced',
    'usage_level' => $_GET['usage_level'] ?? 'normal',
    'duration_days' => $_GET['duration_days'] ?? '30',
    'schedule' => $_GET['schedule'] ?? 'day',
    'call_scope' => $_GET['call_scope'] ?? 'mixed',
    'data_gb' => $_GET['data_gb'] ?? '',
    'minutes' => $_GET['minutes'] ?? '',
];

$selectedOperators = array_values(array_filter(array_map(
    'intval',
    (array) ($_GET['operators'] ?? [])
), static fn ($id) => $id > 0));

$searched = isset($_GET['compare']);
$results = [];
$error = null;

if ($searched) {
    $budget = (float) $profile['budget_kz'];
    if ($budget < 100) {
        $error = 'Informe um orçamento a partir de 100 Kz.';
    } else {
        $plans = $repo->activePlans($selectedOperators);
        $engine = new RecommendationEngine();
        $results = $engine->recommend($plans, $profile);
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

function benefit_text(array $effective): string
{
    $items = [];
    if (($effective['data_mb'] ?? 0) > 0) {
        $mb = (float) $effective['data_mb'];
        $items[] = $mb >= 1024
            ? number_format($mb / 1024, 1, ',', '.') . ' GB úteis'
            : number_format($mb, 0, ',', '.') . ' MB úteis';
    }
    if (($effective['voice_min'] ?? 0) > 0) {
        $items[] = number_format((float) $effective['voice_min'], 0, ',', '.') . ' min úteis';
    }
    if (($effective['sms'] ?? 0) > 0) {
        $items[] = number_format((float) $effective['sms'], 0, ',', '.') . ' SMS';
    }

    return implode(' · ', $items);
}
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QualPacote — Encontre o pacote certo para o seu saldo</title>
    <meta name="description" content="Compare pacotes móveis pelo que realmente consegue usar. Informe o orçamento e o tipo de consumo.">
    <link rel="stylesheet" href="/assets/app.css?v=1">
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
                <p class="eyebrow">Antes de carregar, compare.</p>
                <h1>Descubra o pacote que rende mais para si.</h1>
                <p class="lead">Informe quanto quer gastar e como usa o telefone. Comparamos preço, validade, horários e benefícios que realmente consegue aproveitar.</p>
            </div>

            <form class="finder-card" method="get" action="/">
                <input type="hidden" name="compare" value="1">

                <div class="field">
                    <label for="budget_kz">Quanto quer gastar?</label>
                    <div class="money-input">
                        <input id="budget_kz" name="budget_kz" type="number" min="100" step="50" inputmode="numeric" required value="<?= e($profile['budget_kz']) ?>" placeholder="Ex.: 5000">
                        <span>Kz</span>
                    </div>
                </div>

                <div class="field">
                    <label>O que mais precisa?</label>
                    <div class="choice-grid">
                        <?php foreach ([
                            'balanced' => 'Internet + chamadas',
                            'data' => 'Internet',
                            'voice' => 'Chamadas',
                            'social' => 'Redes sociais',
                        ] as $value => $label): ?>
                            <label class="choice">
                                <input type="radio" name="goal" value="<?= e($value) ?>"<?= checked($profile['goal'] === $value) ?>>
                                <span><?= e($label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="two-cols">
                    <div class="field">
                        <label for="usage_level">Quanto costuma usar?</label>
                        <select id="usage_level" name="usage_level">
                            <option value="light"<?= selected($profile['usage_level'], 'light') ?>>Pouco</option>
                            <option value="normal"<?= selected($profile['usage_level'], 'normal') ?>>Normal</option>
                            <option value="heavy"<?= selected($profile['usage_level'], 'heavy') ?>>Muito</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="duration_days">Precisa que dure</label>
                        <select id="duration_days" name="duration_days">
                            <option value="1"<?= selected($profile['duration_days'], '1') ?>>1 dia</option>
                            <option value="7"<?= selected($profile['duration_days'], '7') ?>>7 dias</option>
                            <option value="30"<?= selected($profile['duration_days'], '30') ?>>30 dias</option>
                        </select>
                    </div>
                </div>

                <div class="two-cols">
                    <div class="field">
                        <label for="schedule">Quando usa mais?</label>
                        <select id="schedule" name="schedule">
                            <option value="day"<?= selected($profile['schedule'], 'day') ?>>Durante o dia</option>
                            <option value="mixed"<?= selected($profile['schedule'], 'mixed') ?>>Dia e noite</option>
                            <option value="night"<?= selected($profile['schedule'], 'night') ?>>Madrugada</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="call_scope">Para quem liga?</label>
                        <select id="call_scope" name="call_scope">
                            <option value="mixed"<?= selected($profile['call_scope'], 'mixed') ?>>Várias redes</option>
                            <option value="same"<?= selected($profile['call_scope'], 'same') ?>>Principalmente a mesma rede</option>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label>Que cartões pode usar?</label>
                    <div class="operator-options">
                        <?php foreach ($operators as $operator): ?>
                            <label>
                                <input type="checkbox" name="operators[]" value="<?= (int) $operator['id'] ?>"<?= checked($selectedOperators === [] || in_array((int) $operator['id'], $selectedOperators, true)) ?>>
                                <span><?= e($operator['name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <small>Se deixar todos marcados, comparamos todas as redes disponíveis.</small>
                </div>

                <details class="fine-tune">
                    <summary>Afinar a comparação</summary>
                    <div class="two-cols details-fields">
                        <div class="field">
                            <label for="data_gb">Internet aproximada</label>
                            <div class="money-input">
                                <input id="data_gb" name="data_gb" type="number" min="0" step="0.5" value="<?= e($profile['data_gb']) ?>" placeholder="Ex.: 5">
                                <span>GB</span>
                            </div>
                        </div>
                        <div class="field">
                            <label for="minutes">Chamadas aproximadas</label>
                            <div class="money-input">
                                <input id="minutes" name="minutes" type="number" min="0" step="10" value="<?= e($profile['minutes']) ?>" placeholder="Ex.: 300">
                                <span>min</span>
                            </div>
                        </div>
                    </div>
                </details>

                <button class="primary-btn" type="submit">Ver melhores opções</button>
            </form>
        </div>
    </section>

    <?php if ($searched): ?>
        <section class="results-section">
            <div class="container">
                <div class="section-heading">
                    <div>
                        <p class="eyebrow">Resultado</p>
                        <h2>Opções que melhor encaixam no seu uso</h2>
                    </div>
                    <?php if ($lastVerified): ?>
                        <p class="verified">Catálogo verificado até <?= e(date_ao($lastVerified)) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($error): ?>
                    <div class="notice error"><?= e($error) ?></div>
                <?php elseif ($results === []): ?>
                    <div class="empty-state">
                        <h3>Não encontrámos uma opção dentro desse orçamento.</h3>
                        <p>Aumente o orçamento, reduza o período ou seleccione mais operadoras.</p>
                    </div>
                <?php else: ?>
                    <div class="results-list">
                        <?php foreach ($results as $index => $result): $plan = $result['plan']; ?>
                            <article class="result-card<?= $index === 0 ? ' top-result' : '' ?>">
                                <div class="result-rank">
                                    <span><?= $index === 0 ? 'Melhor encaixe' : '#' . ($index + 1) ?></span>
                                    <strong><?= e($plan['operator_name']) ?></strong>
                                </div>

                                <div class="result-main">
                                    <div>
                                        <h3><?= e($plan['name']) ?></h3>
                                        <p class="result-summary"><?= e($result['summary']) ?></p>
                                        <p class="capacity"><?= e(benefit_text($result['effective'])) ?></p>
                                    </div>
                                    <div class="result-price">
                                        <strong><?= e(money($result['effective_cost'])) ?></strong>
                                        <span>para o período</span>
                                    </div>
                                </div>

                                <div class="result-metrics">
                                    <div><strong><?= (int) $result['fit_percent'] ?>%</strong><span>do perfil atendido</span></div>
                                    <div><strong><?= (int) $result['coverage_days'] ?> dias</strong><span>de cobertura</span></div>
                                    <div><strong><?= (int) $result['restriction_loss_percent'] ?>%</strong><span>impacto estimado das restrições</span></div>
                                </div>

                                <?php if ($result['restrictions'] !== []): ?>
                                    <div class="restrictions">
                                        <strong>Atenção</strong>
                                        <ul>
                                            <?php foreach ($result['restrictions'] as $restriction): ?>
                                                <li><?= e($restriction) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>

                                <div class="result-footer">
                                    <div>
                                        <?php if (!empty($plan['activation_code'])): ?>
                                            <span class="activation">Activar: <strong><?= e($plan['activation_code']) ?></strong></span>
                                        <?php endif; ?>
                                        <span>Validade: <?= (int) $plan['validity_days'] ?> dias</span>
                                    </div>
                                    <?php if (!empty($plan['source_url'])): ?>
                                        <a href="<?= e($plan['source_url']) ?>" target="_blank" rel="noopener noreferrer">Ver fonte</a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <p class="catalog-note">As operadoras podem alterar preços e condições. Confirme o valor e o código antes de activar.</p>
            </div>
        </section>
    <?php endif; ?>

    <section class="how-it-works">
        <div class="container">
            <div class="section-heading compact">
                <div>
                    <p class="eyebrow">Comparação prática</p>
                    <h2>O número anunciado nem sempre é o que consegue usar.</h2>
                </div>
            </div>
            <div class="feature-grid">
                <article><span>01</span><h3>Validade</h3><p>Um pacote barato pode sair caro se tiver de ser renovado várias vezes no período que precisa.</p></article>
                <article><span>02</span><h3>Horários</h3><p>Benefícios de madrugada contam menos para quem usa o telefone principalmente durante o dia.</p></article>
                <article><span>03</span><h3>Rede</h3><p>Minutos para a mesma rede não têm o mesmo valor para quem liga frequentemente para outras redes.</p></article>
                <article><span>04</span><h3>Uso real</h3><p>A melhor opção é a que atende o que precisa dentro do seu orçamento, não a que mostra o maior número.</p></article>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        <strong>QualPacote</strong>
        <span>Compare antes de carregar.</span>
    </div>
</footer>

<script src="/assets/app.js?v=1"></script>
</body>
</html>
