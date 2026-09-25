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
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — QualPacote</title>
    <link rel="stylesheet" href="/assets/app.css?v=1">
</head>
<body class="admin-body">
    <main class="login-wrap">
        <form class="login-card" method="post">
            <a class="brand" href="/">Qual<span>Pacote</span></a>
            <h1>Administração</h1>
            <p>Actualize operadoras, planos, benefícios e fontes.</p>
            <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
            <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
            <input type="hidden" name="action" value="login">
            <label>Email
                <input type="email" name="email" required autocomplete="username">
            </label>
            <label>Palavra-passe
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="primary-btn" type="submit">Entrar</button>
        </form>
    </main>
</body>
</html>
<?php
exit;
endif;

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
                    $benefits[] = [
                        'type' => $type,
                        'quantity' => $quantities[$i] ?? 0,
                        'unit' => $units[$i] ?? '',
                        'network_scope' => $networks[$i] ?? 'ALL',
                        'start_time' => $starts[$i] ?? '',
                        'end_time' => $ends[$i] ?? '',
                        'app_scope' => $apps[$i] ?? '',
                        'label' => $labels[$i] ?? '',
                    ];
                }

                $repo->savePlan($_POST, $benefits, (int) $user['id']);
                $message = 'Plano guardado e nova versão registada.';
                $page = 'plans';
            } elseif ($action === 'set_plan_state') {
                $repo->setPlanState(
                    (int) ($_POST['id'] ?? 0),
                    !empty($_POST['active']),
                    !empty($_POST['published']),
                    (int) $user['id']
                );
                $message = 'Estado do plano actualizado.';
                $page = 'plans';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$stats = $repo->dashboardStats();
$operators = $repo->operators(false);
$plans = $repo->planList();

$editingPlan = null;
if ($page === 'plan-form' && isset($_GET['id'])) {
    $editingPlan = $repo->findPlan((int) $_GET['id']);
}

$editingOperator = null;
if ($page === 'operators' && isset($_GET['operator_id'])) {
    $operatorId = (int) $_GET['operator_id'];
    foreach ($operators as $operatorRow) {
        if ((int) $operatorRow['id'] === $operatorId) {
            $editingOperator = $operatorRow;
            break;
        }
    }
}

$blankBenefit = [
    'type' => 'DATA',
    'quantity' => '',
    'unit' => 'MB',
    'network_scope' => 'ALL',
    'start_time' => '',
    'end_time' => '',
    'app_scope' => '',
    'label' => '',
];

$planBenefits = $editingPlan['benefits'] ?? [$blankBenefit];
if ($planBenefits === []) {
    $planBenefits = [$blankBenefit];
}
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin — QualPacote</title>
    <link rel="stylesheet" href="/assets/app.css?v=1">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="brand inverse" href="/admin/">Qual<span>Pacote</span></a>
        <nav>
            <a href="/admin/" class="<?= $page === 'dashboard' ? 'active' : '' ?>">Visão geral</a>
            <a href="/admin/?page=plans" class="<?= in_array($page, ['plans', 'plan-form'], true) ? 'active' : '' ?>">Planos</a>
            <a href="/admin/?page=operators" class="<?= $page === 'operators' ? 'active' : '' ?>">Operadoras</a>
            <a href="/" target="_blank" rel="noopener">Abrir site</a>
        </nav>
        <div class="admin-user">
            <span><?= e($user['email']) ?></span>
            <a href="/admin/?logout=1">Sair</a>
        </div>
    </aside>

    <main class="admin-content">
        <?php if ($message): ?><div class="notice success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>

        <?php if ($page === 'dashboard'): ?>
            <div class="admin-heading">
                <div><p class="eyebrow">Catálogo</p><h1>Visão geral</h1></div>
                <a class="primary-btn small" href="/admin/?page=plan-form">Novo plano</a>
            </div>

            <div class="stats-grid">
                <article><strong><?= (int) $stats['operators'] ?></strong><span>operadoras activas</span></article>
                <article><strong><?= (int) $stats['plans'] ?></strong><span>planos publicados</span></article>
                <article class="<?= (int) $stats['stale'] > 0 ? 'warn' : '' ?>"><strong><?= (int) $stats['stale'] ?></strong><span>planos sem revisão há mais de 7 dias</span></article>
                <article class="<?= (int) $stats['source_changes'] > 0 ? 'warn' : '' ?>"><strong><?= (int) $stats['source_changes'] ?></strong><span>fontes com alteração detectada</span></article>
                <article><strong><?= e(date_ao($stats['last_checked'])) ?></strong><span>última verificação</span></article>
            </div>

            <section class="admin-panel">
                <div class="panel-heading"><div><h2>Rotina diária</h2><p>Mantenha as recomendações confiáveis.</p></div></div>
                <div class="checklist">
                    <label><input type="checkbox"> Verificar páginas oficiais das operadoras</label>
                    <label><input type="checkbox"> Confirmar alterações de preço, validade e benefícios</label>
                    <label><input type="checkbox"> Registar nova versão dos planos alterados</label>
                    <label><input type="checkbox"> Rever planos com fonte há mais de 7 dias</label>
                </div>
            </section>

        <?php elseif ($page === 'operators'): ?>
            <div class="admin-heading"><div><p class="eyebrow">Catálogo</p><h1>Operadoras</h1></div></div>
            <section class="admin-panel">
                <div class="table-wrap"><table class="admin-table">
                    <thead><tr><th>Operadora</th><th>Código</th><th>Site</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($operators as $operator): ?>
                        <tr><td><?= e($operator['name']) ?></td><td><?= e($operator['slug']) ?></td><td><?= e($operator['website'] ?? '—') ?></td><td><?= (int) $operator['active'] === 1 ? 'Activa' : 'Inactiva' ?></td><td><a href="/admin/?page=operators&operator_id=<?= (int) $operator['id'] ?>">Editar</a></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            </section>
            <section class="admin-panel">
                <div class="panel-heading"><h2><?= $editingOperator ? 'Editar operadora' : 'Adicionar operadora' ?></h2></div>
                <form class="admin-form" method="post">
                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                    <input type="hidden" name="action" value="save_operator">
                    <input type="hidden" name="id" value="<?= (int) ($editingOperator['id'] ?? 0) ?>">
                    <div class="form-grid">
                        <label>Nome<input name="name" required value="<?= e($editingOperator['name'] ?? '') ?>"></label>
                        <label>Código<input name="slug" required placeholder="ex.: operadora" value="<?= e($editingOperator['slug'] ?? '') ?>"></label>
                        <label class="span-2">Site oficial<input name="website" type="url" value="<?= e($editingOperator['website'] ?? '') ?>"></label>
                        <label>Ordem<input name="display_order" type="number" value="<?= e($editingOperator['display_order'] ?? '10') ?>"></label>
                        <label class="inline-check"><input name="active" type="checkbox" value="1"<?= checked(!isset($editingOperator['active']) || (int) $editingOperator['active'] === 1) ?>> Activa</label>
                    </div>
                    <button class="primary-btn small" type="submit">Guardar operadora</button>
                </form>
            </section>

        <?php elseif ($page === 'plans'): ?>
            <div class="admin-heading"><div><p class="eyebrow">Catálogo</p><h1>Planos</h1></div><a class="primary-btn small" href="/admin/?page=plan-form">Novo plano</a></div>
            <section class="admin-panel">
                <div class="table-wrap"><table class="admin-table">
                    <thead><tr><th>Operadora</th><th>Plano</th><th>Preço</th><th>Validade</th><th>Verificado</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <tr>
                            <td><?= e($plan['operator_name']) ?></td>
                            <td><strong><?= e($plan['name']) ?></strong><small>v<?= (int) $plan['version_no'] ?></small></td>
                            <td><?= e(money($plan['price_kz'])) ?></td>
                            <td><?= (int) $plan['validity_days'] ?> dias</td>
                            <td><?= e(date_ao($plan['source_checked_at'])) ?></td>
                            <td><?php if ((int) $plan['active'] === 1 && (int) $plan['published'] === 1): ?><span class="status ok">Publicado</span><?php elseif ((int) $plan['active'] === 1): ?><span class="status">Rascunho</span><?php else: ?><span class="status muted">Inactivo</span><?php endif; ?></td>
                            <td><a href="/admin/?page=plan-form&id=<?= (int) $plan['id'] ?>">Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($plans === []): ?><tr><td colspan="7">Ainda não existem planos.</td></tr><?php endif; ?>
                    </tbody>
                </table></div>
            </section>

        <?php elseif ($page === 'plan-form'): ?>
            <div class="admin-heading">
                <div><p class="eyebrow">Catálogo</p><h1><?= $editingPlan ? 'Actualizar plano' : 'Novo plano' ?></h1><?php if ($editingPlan): ?><p>Ao guardar, o sistema cria uma nova versão e preserva a anterior.</p><?php endif; ?></div>
                <a class="secondary-btn small" href="/admin/?page=plans">Voltar</a>
            </div>

            <form class="admin-form admin-panel" method="post">
                <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>">
                <input type="hidden" name="action" value="save_plan">
                <input type="hidden" name="id" value="<?= (int) ($editingPlan['id'] ?? 0) ?>">
                <div class="panel-heading"><h2>Identificação</h2></div>
                <div class="form-grid">
                    <label>Operadora<select name="operator_id" required><option value="">Escolher</option><?php foreach ($operators as $operator): ?><option value="<?= (int) $operator['id'] ?>"<?= selected($editingPlan['operator_id'] ?? '', $operator['id']) ?>><?= e($operator['name']) ?></option><?php endforeach; ?></select></label>
                    <label>Nome do plano<input name="name" required value="<?= e($editingPlan['name'] ?? '') ?>"></label>
                    <label>Preço (Kz)<input name="price_kz" type="number" min="1" step="1" required value="<?= e($editingPlan['price_kz'] ?? '') ?>"></label>
                    <label>Validade (dias)<input name="validity_days" type="number" min="1" required value="<?= e($editingPlan['validity_days'] ?? '30') ?>"></label>
                    <label class="span-2">Código de activação<input name="activation_code" value="<?= e($editingPlan['activation_code'] ?? '') ?>" placeholder="Ex.: *123*...#"></label>
                </div>

                <div class="panel-heading subsection"><h2>Benefícios</h2><button type="button" class="secondary-btn small" data-add-benefit>Adicionar benefício</button></div>
                <div class="benefits-editor" data-benefits>
                    <?php foreach ($planBenefits as $benefit): ?>
                        <div class="benefit-row">
                            <label>Tipo<select name="benefit_type[]"><?php foreach (['DATA' => 'Dados', 'VOICE' => 'Voz', 'SMS' => 'SMS', 'SOCIAL' => 'Dados sociais'] as $value => $label): ?><option value="<?= e($value) ?>"<?= selected($benefit['type'] ?? '', $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                            <label>Quantidade<input name="benefit_quantity[]" type="number" min="0" step="0.01" value="<?= e($benefit['quantity'] ?? '') ?>"></label>
                            <label>Unidade<select name="benefit_unit[]"><?php foreach (['MB', 'MIN', 'SMS'] as $unit): ?><option value="<?= e($unit) ?>"<?= selected($benefit['unit'] ?? '', $unit) ?>><?= e($unit) ?></option><?php endforeach; ?></select></label>
                            <label>Rede<select name="benefit_network_scope[]"><option value="ALL"<?= selected($benefit['network_scope'] ?? 'ALL', 'ALL') ?>>Todas</option><option value="ONNET"<?= selected($benefit['network_scope'] ?? '', 'ONNET') ?>>Mesma rede</option><option value="OFFNET"<?= selected($benefit['network_scope'] ?? '', 'OFFNET') ?>>Outras redes</option></select></label>
                            <label>Início<input name="benefit_start_time[]" type="time" value="<?= e(isset($benefit['start_time']) ? substr((string) $benefit['start_time'], 0, 5) : '') ?>"></label>
                            <label>Fim<input name="benefit_end_time[]" type="time" value="<?= e(isset($benefit['end_time']) ? substr((string) $benefit['end_time'], 0, 5) : '') ?>"></label>
                            <label>Aplicações<input name="benefit_app_scope[]" value="<?= e($benefit['app_scope'] ?? '') ?>" placeholder="Ex.: WhatsApp, Facebook"></label>
                            <label>Descrição<input name="benefit_label[]" value="<?= e($benefit['label'] ?? '') ?>" placeholder="Opcional"></label>
                            <button type="button" class="remove-benefit" data-remove-benefit aria-label="Remover">×</button>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="panel-heading subsection"><h2>Fonte e publicação</h2></div>
                <div class="form-grid">
                    <label class="span-2">Fonte oficial<input name="source_url" type="url" required value="<?= e($editingPlan['source_url'] ?? '') ?>"></label>
                    <label>Verificado em<input name="source_checked_at" type="date" required value="<?= e($editingPlan['source_checked_at'] ?? date('Y-m-d')) ?>"></label>
                    <label class="span-2">Notas<textarea name="notes" rows="3"><?= e($editingPlan['notes'] ?? '') ?></textarea></label>
                    <label class="inline-check"><input name="active" type="checkbox" value="1"<?= checked(!isset($editingPlan['active']) || (int) $editingPlan['active'] === 1) ?>> Activo</label>
                    <label class="inline-check"><input name="published" type="checkbox" value="1"<?= checked(!isset($editingPlan['published']) || (int) $editingPlan['published'] === 1) ?>> Publicado</label>
                </div>
                <button class="primary-btn" type="submit">Guardar plano</button>
            </form>

            <?php if ($editingPlan): ?>
                <form method="post" class="state-form admin-panel">
                    <input type="hidden" name="_csrf" value="<?= e(Csrf::token()) ?>"><input type="hidden" name="action" value="set_plan_state"><input type="hidden" name="id" value="<?= (int) $editingPlan['id'] ?>">
                    <div><strong>Estado sem criar nova versão</strong><p>Use apenas para publicar, retirar de publicação ou desactivar um plano.</p></div>
                    <label class="inline-check"><input name="active" type="checkbox" value="1"<?= checked((int) $editingPlan['active'] === 1) ?>> Activo</label>
                    <label class="inline-check"><input name="published" type="checkbox" value="1"<?= checked((int) $editingPlan['published'] === 1) ?>> Publicado</label>
                    <button class="secondary-btn small" type="submit">Actualizar estado</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<template id="benefit-template">
    <div class="benefit-row">
        <label>Tipo<select name="benefit_type[]"><option value="DATA">Dados</option><option value="VOICE">Voz</option><option value="SMS">SMS</option><option value="SOCIAL">Dados sociais</option></select></label>
        <label>Quantidade<input name="benefit_quantity[]" type="number" min="0" step="0.01"></label>
        <label>Unidade<select name="benefit_unit[]"><option value="MB">MB</option><option value="MIN">MIN</option><option value="SMS">SMS</option></select></label>
        <label>Rede<select name="benefit_network_scope[]"><option value="ALL">Todas</option><option value="ONNET">Mesma rede</option><option value="OFFNET">Outras redes</option></select></label>
        <label>Início<input name="benefit_start_time[]" type="time"></label><label>Fim<input name="benefit_end_time[]" type="time"></label>
        <label>Aplicações<input name="benefit_app_scope[]" placeholder="Ex.: WhatsApp, Facebook"></label><label>Descrição<input name="benefit_label[]" placeholder="Opcional"></label>
        <button type="button" class="remove-benefit" data-remove-benefit aria-label="Remover">×</button>
    </div>
</template>
<script src="/assets/app.js?v=1"></script>
</body>
</html>
