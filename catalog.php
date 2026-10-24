<?php
declare(strict_types=1);
require_once __DIR__ . '/app/bootstrap.php';
use QualPacote\Database;
use QualPacote\PlanRepository;
use QualPacote\RecommendationEngine;
$repo = new PlanRepository(Database::connection());
$operators = $repo->operators(true);
$operator = max(0, (int) ($_GET['operator'] ?? 0));
$category = ($_GET['category'] ?? '') === 'router' ? 'router' : 'mobile';
$query = trim((string) ($_GET['q'] ?? ''));
$budget = max(0, (float) ($_GET['max_price'] ?? 0));
$plans = array_values(array_filter($repo->activePlans($operator ? [$operator] : []), static fn ($plan) => ($plan['rules']['category'] ?? 'mobile') === $category
    && ($query === '' || mb_stripos($plan['name'], $query) !== false)
    && ($budget <= 0 || $plan['price_kz'] <= $budget)
    && (empty($plan['rules']['offer_ends_at']) || $plan['rules']['offer_ends_at'] >= date('Y-m-d'))));
?>
<!doctype html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Catálogo de ofertas — QualPacote</title><link rel="stylesheet" href="/assets/app.css?v=4"></head><body>
<header class="site-header"><div class="container header-inner"><a class="brand" href="/">Qual<span>Pacote</span></a><a href="/">Comparar pelo meu orçamento</a></div></header>
<main><section class="results-section"><div class="container"><div class="section-heading"><div><p class="eyebrow">Informação para decidir</p><h1>Consultar ofertas</h1><p>Ofertas publicadas com fonte e data. Este catálogo ainda pode não incluir todas as ofertas das operadoras.</p></div></div>
<form class="catalog-tools" method="get"><label>Procurar<input name="q" value="<?= e($query) ?>" placeholder="Nome do plano"></label><label>Utilização<select name="category"><option value="mobile"<?= selected($category,'mobile') ?>>Telemóvel</option><option value="router"<?= selected($category,'router') ?>>MiFi / router</option></select></label><label>Operadora<select name="operator"><option value="0">Todas</option><?php foreach ($operators as $row): ?><option value="<?= (int) $row['id'] ?>"<?= selected($operator,$row['id']) ?>><?= e($row['name']) ?></option><?php endforeach; ?></select></label><label>Preço máximo (Kz)<input type="number" name="max_price" min="0" value="<?= $budget ? e($budget) : '' ?>"></label><button class="primary-btn">Procurar</button></form>
<p><?= count($plans) ?> ofertas encontradas.</p><div class="results-list">
<?php foreach ($plans as $plan): ?>
<article class="result-card"><div class="result-rank"><span><?= e($plan['operator_name']) ?></span><strong><?= e(money($plan['price_kz'])) ?></strong></div><h2><?= e($plan['name']) ?></h2><p>Validade: <?= (int) $plan['validity_days'] ?> dias · Verificado em <?= e(date_ao($plan['source_checked_at'])) ?>.</p>
<?php if (max(strtotime($plan['source_checked_at']), !empty($plan['source_observed_at']) ? strtotime($plan['source_observed_at']) : 0) < strtotime('-7 days')): ?><p class="notice warning">Precisa de nova verificação. Confirme as condições antes de comprar.</p><?php endif; ?>
<?php if (!empty($plan['source_observed_at'])): ?><p>Fonte consultada sem alterações desde a revisão em <?= e(date_ao($plan['source_observed_at'])) ?>.</p><?php endif; ?>
<ul><?php foreach ($plan['benefits'] as $benefit): ?><li><?= e(number_format((float) $benefit['quantity'], 2, ',', '.') . ' ' . $benefit['unit']) ?><?= !empty($benefit['label']) ? ' — ' . e(preg_replace('/^(?:SHARED:[^|]+\||DAILY_(?:CAP|RELEASE):[^|]+\||FUP:)/i', '', $benefit['label'])) : '' ?><?= ($benefit['network_scope'] ?? 'ALL') === 'ONNET' ? ' · Mesma rede' : (($benefit['network_scope'] ?? 'ALL') === 'OFFNET' ? ' · Outras redes' : '') ?><?= !empty($benefit['start_time']) ? ' · Das ' . e(substr($benefit['start_time'],0,5)) . ' às ' . e(substr($benefit['end_time'],0,5)) : '' ?><?= !empty($benefit['app_scope']) ? ' · Apenas ' . e($benefit['app_scope']) : '' ?></li><?php endforeach; ?></ul>
<?php if (!empty($plan['rules']['requires_family'])): ?><p>Requer plano <?= e($plan['rules']['requires_family']) ?> activo.</p><?php endif; ?>
<?php if ($category === 'router'): ?><p>Requer equipamento e SIM compatíveis já disponíveis. Equipamento não incluído no preço.</p><?php endif; ?>
<?php if (!empty($plan['notes'])): ?><p><?= nl2br(e(trim(preg_replace('/\[(?:CATALOG|RULES):[^]]+\]|\[\/?REQUIREMENT\]/', '', $plan['notes'])))) ?></p><?php endif; ?>
<p>Activar: <?= e($plan['activation_code'] ?: 'Consultar fonte oficial') ?></p><a href="<?= e($plan['source_url']) ?>" target="_blank" rel="noopener noreferrer">Ver fonte oficial</a>
<p class="catalog-note">Minutos/SMS ou outros benefícios identificados como partilhados pertencem à mesma carteira; não são quantidades independentes.</p>
</article><?php endforeach; ?></div></div></section></main></body></html>
