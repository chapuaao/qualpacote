<?php
declare(strict_types=1);
// This view is only included after admin/index.php has authenticated the admin.
if (empty($user)) { http_response_code(403); exit; }
$approval = static function (array $values): string {
    $votes = (int) $values['positive'] + (int) $values['negative'];
    return $votes > 0 ? number_format(100 * (int) $values['positive'] / $votes, 1, ',', '.') . '%' : '—';
};
?>
<div class="admin-heading"><div><p class="eyebrow">Utilização e opinião</p><h1>Estatísticas</h1><p>Visitantes, consultas e avaliação das recomendações.</p></div></div>
<section class="admin-panel">
<form class="statistics-filter" method="get" action="/admin/">
    <input type="hidden" name="page" value="statistics">
    <label>De<input type="date" name="from" value="<?= e($from) ?>" required></label>
    <label>Até<input type="date" name="to" value="<?= e($to) ?>" required></label>
    <button class="primary-btn small" type="submit">Filtrar</button>
</form>
<p class="statistics-note">Datas inclusivas, no fuso horário da aplicação. Visitantes são navegadores identificados por um cookie anónimo: apagar cookies ou usar outro dispositivo pode contar novamente. Os totais e votos são privados.</p>
</section>
<?php if ($usageError): ?><div class="notice error"><?= e($usageError) ?></div><?php endif; ?>
<?php if ($usageStats): $summary = $usageStats['summary']; $totals = $usageStats['totals']; ?>
<section class="admin-panel">
<div class="panel-heading"><h2>Total acumulado</h2></div>
<p><strong><?= (int) $totals['visitors'] ?></strong> visitantes · <strong><?= (int) $totals['users'] ?></strong> visitantes que consultaram · <strong><?= (int) $totals['consultations'] ?></strong> consultas · <strong><?= (int) $totals['positive'] ?></strong> aprovações · <strong><?= (int) $totals['negative'] ?></strong> reprovações · <strong><?= e($approval($totals)) ?></strong> de aprovação.</p>
<?php if ((int) $usageStats['legacy'] > 0): ?><p class="statistics-note"><?= (int) $usageStats['legacy'] ?> consultas sem identificação de visitante, anteriores à migração ou realizadas com o registo detalhado indisponível. Estão incluídas nas consultas; os visitantes históricos não podem ser reconstruídos.</p><?php endif; ?>
</section>
<div class="stats-grid usage-stats">
<?php foreach (['visitors'=>'visitantes no período','users'=>'visitantes que consultaram','consultations'=>'consultas realizadas','positive'=>'aprovações','negative'=>'reprovações'] as $key=>$label): ?>
<article><strong><?= (int) $summary[$key] ?></strong><span><?= e($label) ?></span></article>
<?php endforeach; ?>
<article><strong><?= e($approval($summary)) ?></strong><span>de aprovação entre os votos</span></article>
</div>
<section class="admin-panel">
<div class="panel-heading"><div><h2>Opinião por recomendação</h2><p>Votos ordenados pelo total recebido. A percentagem usa apenas resultados votados; “—” significa ausência de votos. Pacotes e saldo normal são separados, preservando a versão apresentada.</p></div></div>
<div class="table-wrap"><table class="admin-table"><thead><tr><th>Recomendação</th><th>Tipo</th><th>Apresentações</th><th>Gostei</th><th>Não gostei</th><th>Aprovação</th></tr></thead><tbody>
<?php foreach ($usageStats['items'] as $item): ?>
<tr><td><?= e($item['title']) ?><small><?= e($item['operator_name']) ?> · versão #<?= (int) $item['version_id'] ?></small></td><td><?= $item['kind'] === 'plan' ? 'Pacote' : 'Saldo normal' ?></td><td><?= (int) $item['impressions'] ?></td><td class="statistics-approval"><?= (int) $item['positive'] ?></td><td class="statistics-rejection"><?= (int) $item['negative'] ?></td><td><?= e($approval($item)) ?></td></tr>
<?php endforeach; ?>
<?php if ($usageStats['items'] === []): ?><tr><td colspan="6">Ainda não há resultados registados neste período.</td></tr><?php endif; ?>
</tbody></table></div>
<p class="statistics-note">Apresentações pela data da consulta; votos pela data em que a opinião foi registada ou alterada. Cada resultado mantém uma única opinião actual.</p>
</section>
<section class="admin-panel">
<div class="panel-heading"><h2>Evolução diária</h2></div>
<div class="table-wrap"><table class="admin-table"><thead><tr><th>Dia</th><th>Visitantes</th><th>Consultas</th><th>Gostei</th><th>Não gostei</th></tr></thead><tbody>
<?php foreach (array_reverse($usageStats['daily'], true) as $day=>$values): ?>
<tr><td><?= e(date_ao($day)) ?></td><td><?= (int) $values['visitors'] ?></td><td><?= (int) $values['consultations'] ?></td><td><?= (int) $values['positive'] ?></td><td><?= (int) $values['negative'] ?></td></tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
<section class="admin-panel">
<div class="panel-heading"><div><h2>Últimas consultas</h2><p>Até 100 consultas do período. Os votos nesta tabela são a opinião actual dos respectivos resultados.</p></div></div>
<div class="table-wrap"><table class="admin-table"><thead><tr><th>Consulta</th><th>Data e hora</th><th>Orçamento</th><th>Utilizações</th><th>Resultados</th><th>Gostei / Não gostei</th></tr></thead><tbody>
<?php foreach ($usageStats['searches'] as $search): ?>
<tr><td>#<?= (int) $search['id'] ?></td><td><?= e(date('d/m/Y H:i', strtotime($search['created_at']))) ?></td><td><?= e(money($search['budget_kz'])) ?></td><td><?= e(strtr($search['goal'], ['data'=>'Internet','voice'=>'Chamadas','sms'=>'SMS','social'=>'Redes sociais','+'=>' + '])) ?></td><td><?= (int) $search['result_count'] ?></td><td><?= (int) $search['positive'] ?> / <?= (int) $search['negative'] ?></td></tr>
<?php endforeach; ?>
<?php if ($usageStats['searches'] === []): ?><tr><td colspan="6">Ainda não há consultas neste período.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
<section class="admin-panel">
<div class="panel-heading"><div><h2>Últimas opiniões</h2><p>Até 100 opiniões registadas ou alteradas no período.</p></div></div>
<div class="table-wrap"><table class="admin-table"><thead><tr><th>Data e hora</th><th>Consulta</th><th>Recomendação</th><th>Opinião</th></tr></thead><tbody>
<?php foreach ($usageStats['history'] as $opinion): ?>
<tr><td><?= e(date('d/m/Y H:i', strtotime($opinion['voted_at']))) ?></td><td>#<?= (int) $opinion['search_event_id'] ?></td><td><?= e($opinion['title']) ?><small><?= e($opinion['operator_name']) ?> · <?= $opinion['kind'] === 'plan' ? 'Pacote' : 'Saldo normal' ?></small></td><td><?= (int) $opinion['vote'] === 1 ? 'Gostei' : 'Não gostei' ?></td></tr>
<?php endforeach; ?>
<?php if ($usageStats['history'] === []): ?><tr><td colspan="4">Ainda não há opiniões neste período.</td></tr><?php endif; ?>
</tbody></table></div>
</section>
<?php endif; ?>
