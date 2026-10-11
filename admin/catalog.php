<?php
use QualPacote\Csrf;
// Included only after the authenticated controller and CSRF validation.
if (!isset($user) || !$user) { http_response_code(404); exit; }
?>
<div class="admin-heading"><div><p class="eyebrow">Qualidade da informação</p><h1>Fontes e revisão</h1><p>Novas ofertas e alterações ficam pendentes até serem revistas. Uma falha de acesso não elimina uma pendência.</p></div></div>
<?php if (!$catalogAvailable): ?>
<div class="notice error">Importe database/migrations/20261011_catalog_intelligence.sql para activar a descoberta e revisão assistida.</div>
<?php elseif ($page === 'catalog-review' && $review):
$current = $review['plan_id'] ? $repo->findPlan((int) $review['plan_id']) : null;
$payload = $review['payload'];
?>
<section class="admin-panel">
<h2><?= e($review['name']) ?></h2>
<p>Estado: <?= e($review['state']) ?> · Evidência recolhida em <?= e($review['fetched_at']) ?>.</p>
<p><a href="<?= e($review['url']) ?>" target="_blank" rel="noopener noreferrer">Abrir fonte oficial</a> · <a href="/admin/?page=catalog-edit&id=<?= (int) $review['id'] ?>">Corrigir / completar proposta</a></p>
<?php foreach (json_decode($review['warnings_json'], true) ?: [] as $warning): ?><p class="notice warning"><?= e($warning) ?></p><?php endforeach; ?>
<div class="table-wrap"><table class="admin-table"><thead><tr><th>Informação</th><th>Publicada</th><th>Proposta</th></tr></thead><tbody>
<?php foreach (['name'=>'Plano','price_kz'=>'Preço (Kz)','validity_days'=>'Validade (dias)','activation_code'=>'Activação','source_checked_at'=>'Verificado em','benefits'=>'Benefícios e restrições','rules'=>'Elegibilidade e compatibilidade','notes'=>'Condições'] as $key=>$label):
$before = $current[$key] ?? null; $after = $payload[$key] ?? null;
if (is_array($before)) $before = json_encode($before, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
if (is_array($after)) $after = json_encode($after, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
?>
<tr><td><?= e($label) ?></td><td><pre class="review-value"><?= e($before ?? '—') ?></pre></td><td><pre class="review-value"><?= e($after ?? '—') ?></pre></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php if ($review['state'] === 'pending'): ?>
<form method="post"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="approve_catalog"><input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
<label class="inline-check"><input type="checkbox" name="confirmed" value="1" required> Revi a fonte, os benefícios, a elegibilidade e as restrições desta oferta.</label><button class="primary-btn" type="submit">Aprovar e publicar nova versão</button></form>
<?php endif; ?>
<form method="post" class="state-form"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="dismiss_catalog"><input type="hidden" name="id" value="<?= (int) $review['id'] ?>"><button class="secondary-btn small">Arquivar proposta</button></form>
<?php if (!empty($review['evidence_type'])): ?><p><a href="/admin/evidence.php?id=<?= (int) $review['snapshot_id'] ?>" target="_blank" rel="noopener">Abrir documento recolhido</a></p><?php endif; ?>
<details><summary>Evidência recolhida</summary><pre class="review-value"><?= e($review['evidence_text']) ?></pre></details>
</section>
<?php else: $health = $catalog->health(); $run = $health['run']; ?>
<div class="notice <?= !$run || !$run['finished_at'] || strtotime($run['finished_at']) < time() - 36 * 3600 || $run['state'] !== 'ok' ? 'warning' : 'success' ?>">
Última rotina: <?= $run ? e($run['finished_at'] ?? $run['started_at']) . ' (' . e($run['state']) . ')' : 'Ainda não executada' ?>. <?= (int) $health['pending'] ?> propostas pendentes · <?= (int) $health['errors'] ?> fontes com falha · <?= (int) $health['discovered'] ?> fontes descobertas por confirmar.
</div>
<section class="admin-panel"><h2>Propostas pendentes</h2><div class="table-wrap"><table class="admin-table"><thead><tr><th>Operadora</th><th>Oferta</th><th>Estado</th><th></th></tr></thead><tbody>
<?php foreach ($catalog->proposals() as $proposal): ?><tr><td><?= e($proposal['operator_name']) ?></td><td><?= e($proposal['name']) ?></td><td><?= e($proposal['state']) ?></td><td><a href="/admin/?page=catalog-review&id=<?= (int) $proposal['id'] ?>">Comparar e rever</a></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="admin-panel"><h2>Fontes e cobertura</h2><p>Uma fonte descoberta não é uma oferta confirmada. Fontes sem leitor automático conservam a evidência para preparação manual.</p>
<?php foreach ($catalog->sources() as $source): ?>
<details class="source-item"><summary><?= e($source['operator_name'] . ' — ' . $source['title']) ?> · <?= e($source['status']) ?><?= $source['last_error'] ? ' · FALHA' : '' ?></summary>
<p><a href="<?= e($source['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($source['url']) ?></a></p>
<p>Último acesso: <?= e($source['last_success_at'] ?? 'Nenhum') ?><?= $source['last_error'] ? ' · ' . e($source['last_error']) : '' ?></p>
<form method="post" class="form-grid"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="configure_source"><input type="hidden" name="id" value="<?= (int) $source['id'] ?>">
<label>Leitura<select name="adapter"><?php foreach (['manual'=>'Revisão manual','afrimix'=>'Afrimix','movinet'=>'Movinet','konekta4g'=>'Konekta 4G'] as $v=>$l): ?><option value="<?= e($v) ?>"<?= selected($source['adapter'],$v) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
<label>Estado<select name="status"><?php foreach (['discovered'=>'Descoberta','verified'=>'Confirmada','ignored'=>'Ignorada'] as $v=>$l): ?><option value="<?= e($v) ?>"<?= selected($source['status'],$v) ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
<label class="inline-check"><input type="checkbox" name="enabled" value="1"<?= checked((bool) $source['enabled']) ?>> Verificar automaticamente</label><button class="secondary-btn small">Guardar fonte</button></form>
</details><?php endforeach; ?></section>
<section class="admin-panel"><h2>Adicionar fonte oficial</h2><form method="post" class="form-grid"><input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="register_source">
<label>Operadora<select name="operator_id"><?php foreach ($operators as $operator): ?><option value="<?= (int) $operator['id'] ?>"><?= e($operator['name']) ?></option><?php endforeach; ?></select></label>
<label>Título<input name="title" required maxlength="180"></label><label>Endereço<input name="url" type="url" required></label><label>Tipo<select name="source_kind"><option value="offer">Oferta / condições</option><option value="discovery">Índice de ofertas</option><option value="document">Documento</option></select></label><button class="primary-btn">Adicionar à vigilância</button></form></section>
<?php endif; ?>
