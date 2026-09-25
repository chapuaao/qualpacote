<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use QualPacote\Auth;
use QualPacote\Csrf;
use QualPacote\Database;
use QualPacote\PlanRepository;

$repo = new PlanRepository(Database::connection());
$message = null;
$error = null;

if (isset($_GET['logout'])) {
    Auth::logout();
    header('Location: /admin/');
    exit;
}

$user = Auth::user();
if (!$user && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $error = 'A sessão expirou. Tente novamente.';
    } elseif (Auth::attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
        header('Location: /admin/');
        exit;
    } else {
        $error = 'Email ou palavra-passe inválidos.';
    }
    $user = Auth::user();
}

if (!$user):
?>
<!doctype html>
<html lang="pt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — QualPacote</title>
<link rel="stylesheet" href="/assets/app.css?v=2">
</head>
<body class="admin-body">
<main class="login-wrap">
<form class="login-card" method="post">
<a class="brand" href="/">Qual<span>Pacote</span>
</a>
<h1>Administração</h1>
<p>Actualize operadoras, pacotes, tarifas normais e fontes.</p>
<?php if ($error): ?>
<div class="notice error">
<?= e($error) ?>
</div>
<?php endif; ?>
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<input type="hidden" name="action" value="login">
<label>Email<input type="email" name="email" required autocomplete="username">
</label>
<label>Palavra-passe<input type="password" name="password" required autocomplete="current-password">
</label>
<button class="primary-btn" type="submit">Entrar</button>
</form>
</main>
</body>
</html>
<?php exit; endif;

$page = (string) ($_GET['page'] ?? 'dashboard');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['_csrf'] ?? null)) {
        $error = 'A sessão expirou. Recarregue a página e tente novamente.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'save_operator') {
                $repo->saveOperator($_POST);
                $message = 'Operadora guardada.';
                $page = 'operators';
            } elseif ($action === 'save_plan') {
                $types = (array) ($_POST['benefit_type'] ?? []);
                $quantities = (array) ($_POST['benefit_quantity'] ?? []);
                $units = (array) ($_POST['benefit_unit'] ?? []);
                $networks = (array) ($_POST['benefit_network_scope'] ?? []);
                $starts = (array) ($_POST['benefit_start_time'] ?? []);
                $ends = (array) ($_POST['benefit_end_time'] ?? []);
                $apps = (array) ($_POST['benefit_app_scope'] ?? []);
                $labels = (array) ($_POST['benefit_label'] ?? []);
                $benefits = [];
                foreach ($types as $i => $type) {
                    $benefits[] = ['type'=>$type,'quantity'=>$quantities[$i] ?? 0,'unit'=>$units[$i] ?? '', 'network_scope'=>$networks[$i] ?? 'ALL','start_time'=>$starts[$i] ?? '','end_time'=>$ends[$i] ?? '','app_scope'=>$apps[$i] ?? '','label'=>$labels[$i] ?? ''];
                }
                $repo->savePlan($_POST, $benefits, (int) $user['id']);
                $message = 'Plano guardado e nova versão registada.';
                $page = 'plans';
            } elseif ($action === 'set_plan_state') {
                $repo->setPlanState((int) ($_POST['id'] ?? 0), !empty($_POST['active']), !empty($_POST['published']), (int) $user['id']);
                $message = 'Estado do plano actualizado.';
                $page = 'plans';
            } elseif ($action === 'save_tariff') {
                $types = (array) ($_POST['rate_service_type'] ?? []);
                $prices = (array) ($_POST['rate_price'] ?? []);
                $units = (array) ($_POST['rate_unit'] ?? []);
                $networks = (array) ($_POST['rate_network_scope'] ?? []);
                $starts = (array) ($_POST['rate_start_time'] ?? []);
                $ends = (array) ($_POST['rate_end_time'] ?? []);
                $labels = (array) ($_POST['rate_label'] ?? []);
                $rates = [];
                foreach ($types as $i => $type) {
                    $rates[] = ['service_type'=>$type,'price_kz_per_unit'=>$prices[$i] ?? 0,'unit'=>$units[$i] ?? '', 'network_scope'=>$networks[$i] ?? 'ALL','start_time'=>$starts[$i] ?? '','end_time'=>$ends[$i] ?? '','label'=>$labels[$i] ?? ''];
                }
                $repo->saveTariff($_POST, $rates, (int) $user['id']);
                $message = 'Tarifa base guardada e nova versão registada.';
                $page = 'tariffs';
            } elseif ($action === 'set_tariff_state') {
                $repo->setTariffState((int) ($_POST['id'] ?? 0), !empty($_POST['active']), !empty($_POST['published']), (int) $user['id']);
                $message = 'Estado da tarifa actualizado.';
                $page = 'tariffs';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$stats = $repo->dashboardStats();
$operators = $repo->operators(false);
$plans = $repo->planList();
$tariffs = $repo->tariffList();

$editingPlan = null;
if ($page === 'plan-form' && isset($_GET['id'])) $editingPlan = $repo->findPlan((int) $_GET['id']);
$editingTariff = null;
if ($page === 'tariff-form' && isset($_GET['id'])) $editingTariff = $repo->findTariff((int) $_GET['id']);
$editingOperator = null;
if ($page === 'operators' && isset($_GET['operator_id'])) {
    foreach ($operators as $row) if ((int) $row['id'] === (int) $_GET['operator_id']) { $editingOperator = $row; break; }
}

$blankBenefit = ['type'=>'DATA','quantity'=>'','unit'=>'MB','network_scope'=>'ALL','start_time'=>'','end_time'=>'','app_scope'=>'','label'=>''];
$planBenefits = $editingPlan['benefits'] ?? [$blankBenefit];
if ($planBenefits === []) $planBenefits = [$blankBenefit];
$blankRate = ['service_type'=>'VOICE','price_kz_per_unit'=>'','unit'=>'MIN','network_scope'=>'ALL','start_time'=>'','end_time'=>'','label'=>''];
$tariffRates = $editingTariff['rates'] ?? [$blankRate];
if ($tariffRates === []) $tariffRates = [$blankRate];
?>
<!doctype html>
<html lang="pt">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin — QualPacote</title>
<link rel="stylesheet" href="/assets/app.css?v=2">
</head>
<body class="admin-body">
<div class="admin-shell">
<aside class="admin-sidebar">
<a class="brand inverse" href="/admin/">Qual<span>Pacote</span>
</a>
<nav>
<a href="/admin/" class="<?= $page === 'dashboard' ? 'active' : '' ?>">Visão geral</a>
<a href="/admin/?page=plans" class="<?= in_array($page,['plans','plan-form'],true) ? 'active' : '' ?>">Planos</a>
<a href="/admin/?page=tariffs" class="<?= in_array($page,['tariffs','tariff-form'],true) ? 'active' : '' ?>">Tarifas de saldo</a>
<a href="/admin/?page=operators" class="<?= $page === 'operators' ? 'active' : '' ?>">Operadoras</a>
<a href="/" target="_blank" rel="noopener">Abrir site</a>
</nav>
<div class="admin-user">
<span>
<?= e($user['email']) ?>
</span>
<a href="/admin/?logout=1">Sair</a>
</div>
</aside>
<main class="admin-content">
<?php if ($message): ?>
<div class="notice success">
<?= e($message) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="notice error">
<?= e($error) ?>
</div>
<?php endif; ?>

<?php if ($page === 'dashboard'): ?>
<div class="admin-heading">
<div>
<p class="eyebrow">Central de inteligência</p>
<h1>Visão geral</h1>
</div>
<div class="heading-actions">
<a class="secondary-btn small" href="/admin/?page=tariff-form">Nova tarifa</a>
<a class="primary-btn small" href="/admin/?page=plan-form">Novo plano</a>
</div>
</div>
<div class="stats-grid intelligence-stats">
<article>
<strong>
<?= (int) $stats['operators'] ?>
</strong>
<span>operadoras activas</span>
</article>
<article>
<strong>
<?= (int) $stats['plans'] ?>
</strong>
<span>planos publicados</span>
</article>
<article>
<strong>
<?= (int) $stats['tariffs'] ?>
</strong>
<span>tarifas normais publicadas</span>
</article>
<article class="<?= (int) $stats['missing_tariffs'] > 0 ? 'warn' : '' ?>">
<strong>
<?= (int) $stats['missing_tariffs'] ?>
</strong>
<span>operadoras sem tarifa padrão</span>
</article>
<article class="<?= ((int) $stats['stale'] + (int) $stats['stale_tariffs']) > 0 ? 'warn' : '' ?>">
<strong>
<?= (int) $stats['stale'] + (int) $stats['stale_tariffs'] ?>
</strong>
<span>itens sem revisão há +7 dias</span>
</article>
<article class="<?= (int) $stats['source_changes'] > 0 ? 'warn' : '' ?>">
<strong>
<?= (int) $stats['source_changes'] ?>
</strong>
<span>fontes com alteração detectada</span>
</article>
</div>
<section class="admin-panel">
<div class="panel-heading">
<div>
<h2>Cobertura mínima do catálogo</h2>
<p>A recomendação só é forte quando conhece pacotes e o custo real do saldo normal.</p>
</div>
</div>
<div class="checklist">
<label>
<input type="checkbox" disabled<?= (int) $stats['tariffs'] > 0 ? ' checked' : '' ?>> Tarifas base cadastradas e versionadas</label>
<label>
<input type="checkbox" disabled> Voz normal: mesma rede e outras redes</label>
<label>
<input type="checkbox" disabled> Internet avulsa por MB, quando existir</label>
<label>
<input type="checkbox" disabled> SMS avulso</label>
<label>
<input type="checkbox" disabled> Pacotes diários, semanais, mensais e combos</label>
</div>
</section>

<?php elseif ($page === 'operators'): ?>
<div class="admin-heading">
<div>
<p class="eyebrow">Catálogo</p>
<h1>Operadoras</h1>
</div>
</div>
<section class="admin-panel">
<div class="table-wrap">
<table class="admin-table">
<thead>
<tr>
<th>Operadora</th>
<th>Código</th>
<th>Site</th>
<th>Estado</th>
<th>
</th>
</tr>
</thead>
<tbody>
<?php foreach ($operators as $operator): ?>
<tr>
<td>
<?= e($operator['name']) ?>
</td>
<td>
<?= e($operator['slug']) ?>
</td>
<td>
<?= e($operator['website'] ?? '—') ?>
</td>
<td>
<?= (int) $operator['active'] === 1 ? 'Activa' : 'Inactiva' ?>
</td>
<td>
<a href="/admin/?page=operators&operator_id=<?= (int) $operator['id'] ?>">Editar</a>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
</section>
<section class="admin-panel">
<div class="panel-heading">
<h2>
<?= $editingOperator ? 'Editar operadora' : 'Adicionar operadora' ?>
</h2>
</div>
<form class="admin-form" method="post">
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<input type="hidden" name="action" value="save_operator">
<input type="hidden" name="id" value="<?= (int) ($editingOperator['id'] ?? 0) ?>">
<div class="form-grid">
<label>Nome<input name="name" required value="<?= e($editingOperator['name'] ?? '') ?>">
</label>
<label>Código<input name="slug" required value="<?= e($editingOperator['slug'] ?? '') ?>">
</label>
<label class="span-2">Site oficial<input name="website" type="url" value="<?= e($editingOperator['website'] ?? '') ?>">
</label>
<label>Ordem<input name="display_order" type="number" value="<?= e($editingOperator['display_order'] ?? '10') ?>">
</label>
<label class="inline-check">
<input name="active" type="checkbox" value="1"<?= checked(!isset($editingOperator['active']) || (int) $editingOperator['active'] === 1) ?>> Activa</label>
</div>
<button class="primary-btn small" type="submit">Guardar operadora</button>
</form>
</section>

<?php elseif ($page === 'plans'): ?>
<div class="admin-heading">
<div>
<p class="eyebrow">Catálogo</p>
<h1>Planos</h1>
</div>
<a class="primary-btn small" href="/admin/?page=plan-form">Novo plano</a>
</div>
<section class="admin-panel">
<div class="table-wrap">
<table class="admin-table">
<thead>
<tr>
<th>Operadora</th>
<th>Plano</th>
<th>Preço</th>
<th>Validade</th>
<th>Verificado</th>
<th>Estado</th>
<th>
</th>
</tr>
</thead>
<tbody>
<?php foreach ($plans as $plan): ?>
<tr>
<td>
<?= e($plan['operator_name']) ?>
</td>
<td>
<strong>
<?= e($plan['name']) ?>
</strong>
<small>v<?= (int) $plan['version_no'] ?>
</small>
</td>
<td>
<?= e(money($plan['price_kz'])) ?>
</td>
<td>
<?= (int) $plan['validity_days'] ?> dias</td>
<td>
<?= e(date_ao($plan['source_checked_at'])) ?>
</td>
<td>
<?php if ((int) $plan['active'] === 1 && (int) $plan['published'] === 1): ?>
<span class="status ok">Publicado</span>
<?php elseif ((int) $plan['active'] === 1): ?>
<span class="status">Rascunho</span>
<?php else: ?>
<span class="status muted">Inactivo</span>
<?php endif; ?>
</td>
<td>
<a href="/admin/?page=plan-form&id=<?= (int) $plan['id'] ?>">Editar</a>
</td>
</tr>
<?php endforeach; ?>
<?php if ($plans === []): ?>
<tr>
<td colspan="7">Ainda não existem planos.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>

<?php elseif ($page === 'tariffs'): ?>
<div class="admin-heading">
<div>
<p class="eyebrow">Saldo normal</p>
<h1>Tarifas base</h1>
<p>Estas tarifas permitem comparar um pacote com o que o mesmo dinheiro faria sem pacote.</p>
</div>
<a class="primary-btn small" href="/admin/?page=tariff-form">Nova tarifa</a>
</div>
<section class="admin-panel">
<div class="table-wrap">
<table class="admin-table">
<thead>
<tr>
<th>Operadora</th>
<th>Tarifa</th>
<th>Validade saldo</th>
<th>Verificado</th>
<th>Estado</th>
<th>
</th>
</tr>
</thead>
<tbody>
<?php foreach ($tariffs as $tariff): ?>
<tr>
<td>
<?= e($tariff['operator_name']) ?>
</td>
<td>
<strong>
<?= e($tariff['name']) ?>
</strong>
<?php if ((int) $tariff['is_default'] === 1): ?>
<small>Tarifa padrão · v<?= (int) $tariff['version_no'] ?>
</small>
<?php else: ?>
<small>v<?= (int) $tariff['version_no'] ?>
</small>
<?php endif; ?>
</td>
<td>
<?= $tariff['balance_validity_days'] === null ? 'Sem prazo registado' : (int) $tariff['balance_validity_days'] . ' dias' ?>
</td>
<td>
<?= e(date_ao($tariff['source_checked_at'])) ?>
</td>
<td>
<?php if ((int) $tariff['active'] === 1 && (int) $tariff['published'] === 1): ?>
<span class="status ok">Publicada</span>
<?php elseif ((int) $tariff['active'] === 1): ?>
<span class="status">Rascunho</span>
<?php else: ?>
<span class="status muted">Inactiva</span>
<?php endif; ?>
</td>
<td>
<a href="/admin/?page=tariff-form&id=<?= (int) $tariff['id'] ?>">Editar</a>
</td>
</tr>
<?php endforeach; ?>
<?php if ($tariffs === []): ?>
<tr>
<td colspan="6">Ainda não existem tarifas normais. Execute a migração ou cadastre a primeira tarifa.</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>
</section>

<?php elseif ($page === 'plan-form'): ?>
<div class="admin-heading">
<div>
<p class="eyebrow">Catálogo</p>
<h1>
<?= $editingPlan ? 'Actualizar plano' : 'Novo plano' ?>
</h1>
<?php if ($editingPlan): ?>
<p>Ao guardar, o sistema cria uma nova versão e preserva a anterior.</p>
<?php endif; ?>
</div>
<a class="secondary-btn small" href="/admin/?page=plans">Voltar</a>
</div>
<form class="admin-form admin-panel" method="post">
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<input type="hidden" name="action" value="save_plan">
<input type="hidden" name="id" value="<?= (int) ($editingPlan['id'] ?? 0) ?>">
<div class="panel-heading">
<h2>Identificação</h2>
</div>
<div class="form-grid">
<label>Operadora<select name="operator_id" required>
<option value="">Escolher</option>
<?php foreach ($operators as $operator): ?>
<option value="<?= (int) $operator['id'] ?>"<?= selected($editingPlan['operator_id'] ?? '', $operator['id']) ?>>
<?= e($operator['name']) ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>Nome do plano<input name="name" required value="<?= e($editingPlan['name'] ?? '') ?>">
</label>
<label>Preço (Kz)<input name="price_kz" type="number" min="1" step="1" required value="<?= e($editingPlan['price_kz'] ?? '') ?>">
</label>
<label>Validade (dias)<input name="validity_days" type="number" min="1" required value="<?= e($editingPlan['validity_days'] ?? '30') ?>">
</label>
<label class="span-2">Código de activação<input name="activation_code" value="<?= e($editingPlan['activation_code'] ?? '') ?>">
</label>
</div>
<div class="panel-heading subsection">
<h2>Benefícios</h2>
<button type="button" class="secondary-btn small" data-add-benefit>Adicionar benefício</button>
</div>
<div class="benefits-editor" data-benefits>
<?php foreach ($planBenefits as $benefit): ?>
<div class="benefit-row">
<label>Tipo<select name="benefit_type[]">
<?php foreach (['DATA'=>'Dados','VOICE'=>'Voz','SMS'=>'SMS','SOCIAL'=>'Dados sociais'] as $v=>$l): ?>
<option value="<?= e($v) ?>"<?= selected($benefit['type'] ?? '', $v) ?>>
<?= e($l) ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>Quantidade<input name="benefit_quantity[]" type="number" min="0" step="0.01" value="<?= e($benefit['quantity'] ?? '') ?>">
</label>
<label>Unidade<select name="benefit_unit[]">
<?php foreach (['MB','MIN','SMS'] as $unit): ?>
<option value="<?= e($unit) ?>"<?= selected($benefit['unit'] ?? '', $unit) ?>>
<?= e($unit) ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>Rede<select name="benefit_network_scope[]">
<option value="ALL"<?= selected($benefit['network_scope'] ?? 'ALL','ALL') ?>>Todas</option>
<option value="ONNET"<?= selected($benefit['network_scope'] ?? '','ONNET') ?>>Mesma rede</option>
<option value="OFFNET"<?= selected($benefit['network_scope'] ?? '','OFFNET') ?>>Outras redes</option>
</select>
</label>
<label>Início<input name="benefit_start_time[]" type="time" value="<?= e(isset($benefit['start_time']) ? substr((string) $benefit['start_time'],0,5) : '') ?>">
</label>
<label>Fim<input name="benefit_end_time[]" type="time" value="<?= e(isset($benefit['end_time']) ? substr((string) $benefit['end_time'],0,5) : '') ?>">
</label>
<label>Aplicações<input name="benefit_app_scope[]" value="<?= e($benefit['app_scope'] ?? '') ?>">
</label>
<label>Descrição<input name="benefit_label[]" value="<?= e($benefit['label'] ?? '') ?>">
</label>
<button type="button" class="remove-benefit" data-remove-benefit>×</button>
</div>
<?php endforeach; ?>
</div>
<div class="panel-heading subsection">
<h2>Fonte e publicação</h2>
</div>
<div class="form-grid">
<label class="span-2">Fonte oficial<input name="source_url" type="url" required value="<?= e($editingPlan['source_url'] ?? '') ?>">
</label>
<label>Verificado em<input name="source_checked_at" type="date" required value="<?= e($editingPlan['source_checked_at'] ?? date('Y-m-d')) ?>">
</label>
<label class="span-2">Notas<textarea name="notes" rows="3">
<?= e($editingPlan['notes'] ?? '') ?>
</textarea>
</label>
<label class="inline-check">
<input name="active" type="checkbox" value="1"<?= checked(!isset($editingPlan['active']) || (int) $editingPlan['active'] === 1) ?>> Activo</label>
<label class="inline-check">
<input name="published" type="checkbox" value="1"<?= checked(!isset($editingPlan['published']) || (int) $editingPlan['published'] === 1) ?>> Publicado</label>
</div>
<button class="primary-btn" type="submit">Guardar plano</button>
</form>
<?php if ($editingPlan): ?>
<form method="post" class="state-form admin-panel">
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<input type="hidden" name="action" value="set_plan_state">
<input type="hidden" name="id" value="<?= (int) $editingPlan['id'] ?>">
<div>
<strong>Estado sem criar nova versão</strong>
<p>Use apenas para publicar, retirar de publicação ou desactivar.</p>
</div>
<label class="inline-check">
<input name="active" type="checkbox" value="1"<?= checked((int) $editingPlan['active'] === 1) ?>> Activo</label>
<label class="inline-check">
<input name="published" type="checkbox" value="1"<?= checked((int) $editingPlan['published'] === 1) ?>> Publicado</label>
<button class="secondary-btn small" type="submit">Actualizar estado</button>
</form>
<?php endif; ?>

<?php elseif ($page === 'tariff-form'): ?>
<div class="admin-heading">
<div>
<p class="eyebrow">Saldo normal</p>
<h1>
<?= $editingTariff ? 'Actualizar tarifa base' : 'Nova tarifa base' ?>
</h1>
<p>Registe quanto custa cada minuto, MB ou SMS pago directamente pelo saldo.</p>
</div>
<a class="secondary-btn small" href="/admin/?page=tariffs">Voltar</a>
</div>
<form class="admin-form admin-panel" method="post">
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<input type="hidden" name="action" value="save_tariff">
<input type="hidden" name="id" value="<?= (int) ($editingTariff['id'] ?? 0) ?>">
<div class="panel-heading">
<h2>Identificação</h2>
</div>
<div class="form-grid">
<label>Operadora<select name="operator_id" required>
<option value="">Escolher</option>
<?php foreach ($operators as $operator): ?>
<option value="<?= (int) $operator['id'] ?>"<?= selected($editingTariff['operator_id'] ?? '', $operator['id']) ?>>
<?= e($operator['name']) ?>
</option>
<?php endforeach; ?>
</select>
</label>
<label>Nome da tarifa<input name="name" required value="<?= e($editingTariff['name'] ?? '') ?>" placeholder="Ex.: Pré-pago / Fala Mais">
</label>
<label>Validade do saldo (dias)<input name="balance_validity_days" type="number" min="1" value="<?= e($editingTariff['balance_validity_days'] ?? '') ?>" placeholder="Vazio se não houver prazo de pacote">
</label>
<label class="inline-check">
<input name="is_default" type="checkbox" value="1"<?= checked((int) ($editingTariff['is_default'] ?? 0) === 1) ?>> Tarifa padrão desta operadora</label>
</div>
<div class="panel-heading subsection">
<div>
<h2>Preços por consumo</h2>
<p>Converta Kz/segundo para Kz/minuto antes de registar voz. Ex.: 0,333 Kz/s = 19,98 Kz/min.</p>
</div>
<button type="button" class="secondary-btn small" data-add-rate>Adicionar preço</button>
</div>
<div class="rates-editor" data-rates>
<?php foreach ($tariffRates as $rate): ?>
<div class="rate-row">
<label>Serviço<select name="rate_service_type[]">
<option value="VOICE"<?= selected($rate['service_type'] ?? '', 'VOICE') ?>>Chamadas</option>
<option value="DATA"<?= selected($rate['service_type'] ?? '', 'DATA') ?>>Internet</option>
<option value="SMS"<?= selected($rate['service_type'] ?? '', 'SMS') ?>>SMS</option>
</select>
</label>
<label>Preço por unidade<input name="rate_price[]" type="number" min="0" step="0.001" value="<?= e($rate['price_kz_per_unit'] ?? '') ?>">
</label>
<label>Unidade<select name="rate_unit[]">
<option value="MIN"<?= selected($rate['unit'] ?? '', 'MIN') ?>>Kz/min</option>
<option value="MB"<?= selected($rate['unit'] ?? '', 'MB') ?>>Kz/MB</option>
<option value="SMS"<?= selected($rate['unit'] ?? '', 'SMS') ?>>Kz/SMS</option>
</select>
</label>
<label>Rede<select name="rate_network_scope[]">
<option value="ALL"<?= selected($rate['network_scope'] ?? 'ALL','ALL') ?>>Todas</option>
<option value="ONNET"<?= selected($rate['network_scope'] ?? '','ONNET') ?>>Mesma rede</option>
<option value="OFFNET"<?= selected($rate['network_scope'] ?? '','OFFNET') ?>>Outras redes</option>
</select>
</label>
<label>Início<input name="rate_start_time[]" type="time" value="<?= e(isset($rate['start_time']) ? substr((string) $rate['start_time'],0,5) : '') ?>">
</label>
<label>Fim<input name="rate_end_time[]" type="time" value="<?= e(isset($rate['end_time']) ? substr((string) $rate['end_time'],0,5) : '') ?>">
</label>
<label>Descrição<input name="rate_label[]" value="<?= e($rate['label'] ?? '') ?>" placeholder="Ex.: Horário económico">
</label>
<button type="button" class="remove-benefit" data-remove-rate>×</button>
</div>
<?php endforeach; ?>
</div>
<div class="panel-heading subsection">
<h2>Fonte e publicação</h2>
</div>
<div class="form-grid">
<label class="span-2">Fonte oficial<input name="source_url" type="url" required value="<?= e($editingTariff['source_url'] ?? '') ?>">
</label>
<label>Verificado em<input name="source_checked_at" type="date" required value="<?= e($editingTariff['source_checked_at'] ?? date('Y-m-d')) ?>">
</label>
<label class="span-2">Notas<textarea name="notes" rows="3">
<?= e($editingTariff['notes'] ?? '') ?>
</textarea>
</label>
<label class="inline-check">
<input name="active" type="checkbox" value="1"<?= checked(!isset($editingTariff['active']) || (int) $editingTariff['active'] === 1) ?>> Activa</label>
<label class="inline-check">
<input name="published" type="checkbox" value="1"<?= checked(!isset($editingTariff['published']) || (int) $editingTariff['published'] === 1) ?>> Publicada</label>
</div>
<button class="primary-btn" type="submit">Guardar tarifa</button>
</form>
<?php if ($editingTariff): ?>
<form method="post" class="state-form admin-panel">
<input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
<input type="hidden" name="action" value="set_tariff_state">
<input type="hidden" name="id" value="<?= (int) $editingTariff['id'] ?>">
<div>
<strong>Estado sem criar nova versão</strong>
<p>Use apenas para publicar, retirar de publicação ou desactivar.</p>
</div>
<label class="inline-check">
<input name="active" type="checkbox" value="1"<?= checked((int) $editingTariff['active'] === 1) ?>> Activa</label>
<label class="inline-check">
<input name="published" type="checkbox" value="1"<?= checked((int) $editingTariff['published'] === 1) ?>> Publicada</label>
<button class="secondary-btn small" type="submit">Actualizar estado</button>
</form>
<?php endif; ?>
<?php endif; ?>
</main>
</div>

<template id="benefit-template">
<div class="benefit-row">
<label>Tipo<select name="benefit_type[]">
<option value="DATA">Dados</option>
<option value="VOICE">Voz</option>
<option value="SMS">SMS</option>
<option value="SOCIAL">Dados sociais</option>
</select>
</label>
<label>Quantidade<input name="benefit_quantity[]" type="number" min="0" step="0.01">
</label>
<label>Unidade<select name="benefit_unit[]">
<option value="MB">MB</option>
<option value="MIN">MIN</option>
<option value="SMS">SMS</option>
</select>
</label>
<label>Rede<select name="benefit_network_scope[]">
<option value="ALL">Todas</option>
<option value="ONNET">Mesma rede</option>
<option value="OFFNET">Outras redes</option>
</select>
</label>
<label>Início<input name="benefit_start_time[]" type="time">
</label>
<label>Fim<input name="benefit_end_time[]" type="time">
</label>
<label>Aplicações<input name="benefit_app_scope[]">
</label>
<label>Descrição<input name="benefit_label[]">
</label>
<button type="button" class="remove-benefit" data-remove-benefit>×</button>
</div>
</template>
<template id="rate-template">
<div class="rate-row">
<label>Serviço<select name="rate_service_type[]">
<option value="VOICE">Chamadas</option>
<option value="DATA">Internet</option>
<option value="SMS">SMS</option>
</select>
</label>
<label>Preço por unidade<input name="rate_price[]" type="number" min="0" step="0.001">
</label>
<label>Unidade<select name="rate_unit[]">
<option value="MIN">Kz/min</option>
<option value="MB">Kz/MB</option>
<option value="SMS">Kz/SMS</option>
</select>
</label>
<label>Rede<select name="rate_network_scope[]">
<option value="ALL">Todas</option>
<option value="ONNET">Mesma rede</option>
<option value="OFFNET">Outras redes</option>
</select>
</label>
<label>Início<input name="rate_start_time[]" type="time">
</label>
<label>Fim<input name="rate_end_time[]" type="time">
</label>
<label>Descrição<input name="rate_label[]">
</label>
<button type="button" class="remove-benefit" data-remove-rate>×</button>
</div>
</template>
<script src="/assets/app.js?v=2">
</script>
</body>
</html>
