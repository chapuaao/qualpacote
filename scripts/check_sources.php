<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/app/bootstrap.php';
use QualPacote\CatalogRepository;
use QualPacote\Database;
use QualPacote\SourceFetcher;

$db = Database::connection(); $repo = new CatalogRepository($db);
if (!$repo->available()) { fwrite(STDERR, "Importe database/migrations/20261011_catalog_intelligence.sql.\n"); exit(1); }
$locked = (int) $db->query("SELECT GET_LOCK('qualpacote_source_check',0)")->fetchColumn();
if (!$locked) { fwrite(STDERR, "Já existe uma verificação em curso.\n"); exit(1); }
$run = 0; $checked = 0; $errors = 0; $discovered = 0;
try {
    $repo->seedSources();
    $db->exec('INSERT INTO catalog_runs (started_at) VALUES (NOW())'); $run = (int) $db->lastInsertId();
    $sources = $repo->sources();
    usort($sources, static fn ($a,$b) => strcmp($a['last_attempt_at'] ?? '', $b['last_attempt_at'] ?? ''));
    $attempts = 0;
    foreach ($sources as $source) {
        if (!$source['enabled'] || $source['status'] === 'ignored') continue;
        if (++$attempts > 120) break;
        try {
            $document = (new SourceFetcher())->fetch($source['url']);
            $result = $repo->recordDocument($source, $document['body'], $document['status']);
            $checked++; $discovered += $result['discovered'];
            fwrite(STDOUT, ($result['changed'] ? '[REVER] ' : '[SEM ALTERAÇÃO] ') . $source['url'] . "\n");
        } catch (Throwable $e) {
            $errors++; $repo->fail((int) $source['id'], $e->getMessage());
            fwrite(STDERR, '[ERRO] ' . $source['url'] . ': ' . $e->getMessage() . "\n");
        }
    }
    $db->prepare('UPDATE catalog_runs SET finished_at=NOW(),checked_count=?,error_count=?,discovered_count=?,state=? WHERE id=?')->execute([$checked,$errors,$discovered,$errors ? 'error' : 'ok',$run]);
} catch (Throwable $e) {
    $errors++;
    if ($run) $db->prepare("UPDATE catalog_runs SET finished_at=NOW(),state='error',error_message=? WHERE id=?")->execute([$e->getMessage(),$run]);
    fwrite(STDERR, $e->getMessage() . "\n");
} finally { $db->query("SELECT RELEASE_LOCK('qualpacote_source_check')"); }
exit($errors ? 1 : 0);
