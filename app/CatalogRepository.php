<?php
declare(strict_types=1);
namespace QualPacote;

use PDO;
use RuntimeException;
use Throwable;

final class CatalogRepository
{
    public function __construct(private PDO $db) {}

    public function available(): bool
    {
        return (bool) $this->db->query("SHOW TABLES LIKE 'catalog_sources'")->fetchColumn();
    }

    public function register(int $operator, string $url, string $title, string $kind = 'offer', string $adapter = 'manual', string $status = 'discovered'): int
    {
        if (!SourceFetcher::allowed($url)) throw new RuntimeException('Use uma fonte num domínio oficial permitido.');
        $operatorQuery = $this->db->prepare('SELECT slug FROM operators WHERE id=?');
        $operatorQuery->execute([$operator]);
        $operatorHost = ['unitel'=>'unitel.ao','africell'=>'africell.ao','movicel'=>'movicel.co.ao'][$operatorQuery->fetchColumn() ?: ''] ?? null;
        $urlHost = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (!$operatorHost || !in_array($urlHost, [$operatorHost,'www.' . $operatorHost, ...($operatorHost === 'movicel.co.ao' ? ['www2.movicel.co.ao'] : [])], true)) throw new RuntimeException('A fonte não corresponde à operadora escolhida.');
        if (!in_array($kind, ['offer','discovery','document'], true) || !in_array($adapter, ['manual','afrimix','movinet','konekta4g'], true) || !in_array($status, ['discovered','verified','ignored'], true)) throw new RuntimeException('Tipo de fonte inválido.');
        $expectedHost = match ($adapter) { 'afrimix','konekta4g'=>'africell.ao', 'movinet'=>'movicel.co.ao', default=>null };
        if ($expectedHost && !in_array(strtolower(parse_url($url,PHP_URL_HOST)), [$expectedHost,'www.' . $expectedHost],true)) throw new RuntimeException('O leitor não corresponde à operadora da fonte.');
        $stmt = $this->db->prepare('INSERT INTO catalog_sources (operator_id,url,url_hash,title,source_kind,adapter,status,enabled,created_at) VALUES (?,?,?,?,?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $stmt->execute([$operator,$url,hash('sha256',$url),substr($title,0,180),$kind,$adapter,$status]);
        return (int) $this->db->lastInsertId();
    }

    public function seedSources(): void
    {
        $seeds = [
            'unitel'=>[['https://www.unitel.ao/','Ofertas UNITEL','discovery','manual']],
            'africell'=>[['https://www.africell.ao/','Ofertas Africell','discovery','manual'],['https://www.africell.ao/afrimix/','Afrimix','offer','afrimix'],['https://www.africell.ao/konekta/plans/Konekta-4G/','Konekta 4G','offer','konekta4g']],
            'movicel'=>[
                ['https://www.movicel.co.ao/','Ofertas Movicel','discovery','manual'],
                ['https://www.movicel.co.ao/plano/movinet.html','Movinet','offer','movinet'],
                ['https://www.movicel.co.ao/plano/FAQ/Movinet.html','Condições Movinet','document','manual'],
                ['https://www.movicel.co.ao/plano/FAQ/PlanoMix.html','Condições Kuia/Takuiá','document','manual'],
                ['https://www.movicel.co.ao/img/NewPlanImg/faq/PlanoMixPB4.png','Benefícios Kuia/Takuiá','document','manual'],
                ['https://www.movicel.co.ao/plano/FAQ/NovoFlex.html','Condições FLEX','document','manual'],
                ['https://www.movicel.co.ao/img/NewPlanImg/faq/NovaPlanoFlexP3.png','Benefícios FLEX por confirmar','document','manual'],
                ['https://www.movicel.co.ao/plano/karga.html','Karga — activação actual','offer','manual'],
                ['https://www.movicel.co.ao/plano/saiba-mais/karga.html','Karga — benefícios por confirmar','offer','manual'],
            ],
        ];
        foreach ($this->db->query('SELECT id,slug FROM operators')->fetchAll() as $operator) {
            foreach ($seeds[$operator['slug']] ?? [] as $source) $this->register((int) $operator['id'], ...array_merge($source, ['verified']));
        }
        // Also watch existing plan/tariff sources, including families not yet parsed.
        $sql = 'SELECT p.operator_id,p.name,pv.source_url FROM plans p JOIN plan_versions pv ON pv.plan_id=p.id WHERE p.active=1 AND p.published=1 AND pv.id=(SELECT id FROM plan_versions WHERE plan_id=p.id ORDER BY version_no DESC,id DESC LIMIT 1)';
        $rows = $this->db->query($sql)->fetchAll();
        if ($this->db->query("SHOW TABLES LIKE 'tariffs'")->fetchColumn()) $rows = array_merge($rows, $this->db->query('SELECT t.operator_id,t.name,tv.source_url FROM tariffs t JOIN tariff_versions tv ON tv.tariff_id=t.id WHERE t.active=1 AND t.published=1 AND tv.id=(SELECT id FROM tariff_versions WHERE tariff_id=t.id ORDER BY version_no DESC,id DESC LIMIT 1)')->fetchAll());
        foreach ($rows as $row) if (SourceFetcher::allowed($row['source_url'])) $this->register((int) $row['operator_id'], $row['source_url'], $row['name'], str_contains(strtolower($row['source_url']), '.pdf') ? 'document' : 'offer', 'manual', 'verified');
    }

    public function sources(): array
    {
        return $this->db->query('SELECT s.*,o.name AS operator_name,(SELECT id FROM catalog_snapshots WHERE source_id=s.id ORDER BY id DESC LIMIT 1) AS snapshot_id FROM catalog_sources s JOIN operators o ON o.id=s.operator_id ORDER BY o.display_order,s.source_kind,s.title')->fetchAll();
    }

    public function source(int $id): array
    {
        $stmt = $this->db->prepare('SELECT * FROM catalog_sources WHERE id=?'); $stmt->execute([$id]);
        return $stmt->fetch() ?: throw new RuntimeException('Fonte inexistente.');
    }

    public function configure(int $id, string $adapter, string $status, bool $enabled): void
    {
        $source = $this->source($id);
        $this->register((int) $source['operator_id'], $source['url'], $source['title'], $source['source_kind'], $adapter, $status);
        $this->db->prepare('UPDATE catalog_sources SET adapter=?,status=?,enabled=? WHERE id=?')->execute([$adapter,$status,$enabled ? 1 : 0,$id]);
    }

    /** Testable transport boundary. Retrieval errors never resolve an open proposal. */
    public function recordDocument(array $source, string $body, int $status = 200): array
    {
        $ownTransaction = !$this->db->inTransaction();
        if ($ownTransaction) $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM catalog_sources WHERE id=? FOR UPDATE');
            $lock->execute([$source['id']]);
            if (!$lock->fetchColumn()) throw new RuntimeException('Fonte inexistente.');
            $result = $this->persistDocument($source, $body, $status);
            if ($ownTransaction) $this->db->commit();
            return $result;
        } catch (Throwable $e) {
            if ($ownTransaction && $this->db->inTransaction()) $this->db->rollBack();
            if ($ownTransaction) $this->fail((int) $source['id'], $e->getMessage());
            throw $e;
        }
    }

    private function persistDocument(array $source, string $body, int $status): array
    {
        $type = str_starts_with($body, '%PDF') ? 'application/pdf' : (str_starts_with($body, "\x89PNG") ? 'image/png' : (str_starts_with($body, "\xff\xd8\xff") ? 'image/jpeg' : null));
        $text = $type ? '[Documento ' . $type . ': revisão manual necessária. SHA256 ' . hash('sha256',$body) . ']' : SourceDocument::text($body);
        if (strlen($text) < 60 || preg_match('/^(?:site unavailable|access denied|bad gateway|service unavailable)/i', $text)) throw new RuntimeException('Página sem informação suficiente ou página de erro.');
        $stmt = $this->db->prepare('SELECT * FROM catalog_snapshots WHERE source_id=? ORDER BY id DESC LIMIT 1'); $stmt->execute([$source['id']]); $previous = $stmt->fetch();
        $textHash = hash('sha256',$text);
        $changed = !$previous || !hash_equals($previous['text_hash'], $textHash);
        $snapshot = $previous ? (int) $previous['id'] : 0;
        if ($changed) {
            $stmt = $this->db->prepare('INSERT INTO catalog_snapshots (source_id,content_hash,text_hash,evidence_text,evidence_blob,evidence_type,fetched_at,http_status) VALUES (?,?,?,?,?,?,NOW(),?)');
            $stmt->execute([$source['id'],hash('sha256',$body),$textHash,$text,$type ? $body : null,$type,$status]); $snapshot = (int) $this->db->lastInsertId();
            $this->db->prepare("UPDATE catalog_proposals SET state='blocked' WHERE source_id=? AND state='pending' AND snapshot_id<>?")->execute([$source['id'],$snapshot]);
        }
        $this->db->prepare('UPDATE catalog_sources SET last_attempt_at=NOW(),last_success_at=NOW(),last_error=NULL WHERE id=?')->execute([$source['id']]);
        $discovered = 0;
        if ($source['source_kind'] === 'discovery') {
            foreach (SourceDocument::links($body, $source['url']) as $link) {
                $stmt = $this->db->prepare('SELECT id FROM catalog_sources WHERE url_hash=?'); $stmt->execute([hash('sha256',$link['url'])]);
                if (!$stmt->fetchColumn()) { $this->register((int) $source['operator_id'], $link['url'], $link['title']); $discovered++; }
                if ($discovered >= 50) break;
            }
        }
        $offers = SourceDocument::extract($source['adapter'], $text);
        if ($source['adapter'] !== 'manual' && $offers === []) {
            $this->fail((int) $source['id'], 'A extracção não encontrou ofertas. A página pode ter mudado; revisão manual necessária.');
            throw new RuntimeException('A extracção não encontrou ofertas.');
        }
        if ($changed) {
            foreach ($offers as $offer) $this->propose($source, $snapshot, $offer, ['Extracção automática: confirmar condições, códigos e compatibilidade na evidência antes de publicar.']);
            if ($offers === [] && $source['source_kind'] !== 'discovery') $this->propose($source, $snapshot, ['name'=>$source['title'],'benefits'=>[]], ['Esta fonte requer transcrição manual; ainda não existe uma proposta publicável.']);
        }
        return ['changed'=>$changed,'snapshot_id'=>$snapshot,'offers'=>count($offers),'discovered'=>$discovered];
    }

    public function fail(int $sourceId, string $error): void
    {
        $this->db->prepare('UPDATE catalog_sources SET last_attempt_at=NOW(),last_error=? WHERE id=?')->execute([substr($error,0,2000),$sourceId]);
    }

    public function propose(array $source, int $snapshot, array $payload, array $warnings = []): int
    {
        $payload = array_intersect_key($payload, array_flip(['id','name','price_kz','validity_days','activation_code','benefits','rules','notes','withdrawn']));
        $name = trim((string) ($payload['name'] ?? $source['title']));
        if ($name === '' || strlen($name) > 180) throw new RuntimeException('Nome inválido.');
        $plan = null;
        if (!empty($payload['id'])) $plan = (new PlanRepository($this->db))->findPlan((int) $payload['id']);
        if (!$plan && !empty($payload['rules']['source_offer_key'])) {
            $stmt = $this->db->prepare("SELECT p.id FROM plans p JOIN plan_versions pv ON pv.plan_id=p.id JOIN plan_version_rules r ON r.plan_version_id=pv.id WHERE p.operator_id=? AND pv.source_url=? AND JSON_UNQUOTE(JSON_EXTRACT(r.rules_json,'$.source_offer_key'))=? AND pv.id=(SELECT id FROM plan_versions WHERE plan_id=p.id ORDER BY version_no DESC,id DESC LIMIT 1) LIMIT 1");
            $stmt->execute([$source['operator_id'],$source['url'],$payload['rules']['source_offer_key']]);
            if ($id = $stmt->fetchColumn()) $plan = (new PlanRepository($this->db))->findPlan((int) $id);
        }
        if (!$plan) {
            $stmt = $this->db->prepare('SELECT id FROM plans WHERE operator_id=? AND name=? LIMIT 1'); $stmt->execute([$source['operator_id'],$name]);
            if ($id = $stmt->fetchColumn()) $plan = (new PlanRepository($this->db))->findPlan((int) $id);
        }
        if ($plan && (int) $plan['operator_id'] !== (int) $source['operator_id']) throw new RuntimeException('O plano pertence a outra operadora.');
        $payload['name'] = $name; $payload['source_url'] = $source['url']; $payload['operator_id'] = (int) $source['operator_id'];
        // A proposal is attached to a specific retrieved revision, not today's date.
        $stmt = $this->db->prepare('SELECT fetched_at FROM catalog_snapshots WHERE id=? AND source_id=?'); $stmt->execute([$snapshot,$source['id']]);
        $date = $stmt->fetchColumn(); if (!$date) throw new RuntimeException('Evidência inválida.');
        $payload['source_checked_at'] = substr($date,0,10); $payload['rules'] = PlanRules::normalize((array) ($payload['rules'] ?? []));
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $signature = hash('sha256', $source['id'] . ':' . $snapshot . ':' . ($plan['version_id'] ?? 0) . ':' . $json);
        $stmt = $this->db->prepare('INSERT INTO catalog_proposals (source_id,snapshot_id,plan_id,expected_version_id,name,payload_json,warnings_json,signature,created_at) VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)');
        $stmt->execute([$source['id'],$snapshot,$plan['id'] ?? null,$plan['version_id'] ?? null,$name,$json,json_encode($warnings, JSON_UNESCAPED_UNICODE),$signature]);
        return (int) $this->db->lastInsertId();
    }

    public function proposals(): array
    {
        return $this->db->query("SELECT p.*,s.url,o.name AS operator_name FROM catalog_proposals p JOIN catalog_sources s ON s.id=p.source_id JOIN operators o ON o.id=s.operator_id WHERE p.state='pending' OR (p.state='blocked' AND p.snapshot_id=(SELECT MAX(id) FROM catalog_snapshots WHERE source_id=p.source_id)) ORDER BY p.created_at DESC,p.id DESC LIMIT 200")->fetchAll();
    }

    public function proposal(int $id): array
    {
        $stmt = $this->db->prepare('SELECT p.*,s.url,s.title AS source_title,x.evidence_text,x.evidence_type,x.fetched_at FROM catalog_proposals p JOIN catalog_sources s ON s.id=p.source_id JOIN catalog_snapshots x ON x.id=p.snapshot_id WHERE p.id=?'); $stmt->execute([$id]);
        $row = $stmt->fetch() ?: throw new RuntimeException('Proposta inexistente.');
        $row['payload'] = json_decode($row['payload_json'], true, 512, JSON_THROW_ON_ERROR);
        return $row;
    }

    public function approve(int $id, int $admin): int
    {
        // Serialize all reviewers of a source, including creation of a new named plan.
        $proposal = $this->proposal($id);
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT id FROM catalog_sources WHERE id=? FOR UPDATE'); $stmt->execute([$proposal['source_id']]);
            $proposal = $this->proposal($id);
            if ($proposal['state'] !== 'pending') throw new RuntimeException('Esta proposta já foi tratada ou está bloqueada.');
            $stmt = $this->db->prepare('SELECT MAX(id) FROM catalog_snapshots WHERE source_id=?'); $stmt->execute([$proposal['source_id']]);
            if ((int) $stmt->fetchColumn() !== (int) $proposal['snapshot_id']) throw new RuntimeException('A fonte já tem uma revisão mais recente. Prepare outra proposta com a evidência actual.');
            $repo = new PlanRepository($this->db);
            if ($proposal['plan_id']) { $lock = $this->db->prepare('SELECT id FROM plans WHERE id=? FOR UPDATE'); $lock->execute([$proposal['plan_id']]); }
            $current = $proposal['plan_id'] ? $repo->findPlan((int) $proposal['plan_id']) : null;
            if ($current && (int) $current['version_id'] !== (int) $proposal['expected_version_id']) throw new RuntimeException('O plano foi alterado depois da proposta. Compare novamente antes de publicar.');
            if (!$current) {
                $stmt = $this->db->prepare('SELECT id FROM plans WHERE operator_id=? AND name=? LIMIT 1'); $stmt->execute([$proposal['payload']['operator_id'],$proposal['name']]);
                if ($stmt->fetchColumn()) throw new RuntimeException('Já existe um plano com este nome. Prepare uma proposta para a versão actual.');
            }
            // savePlan participates in this transaction; the proposal and its new version commit together.
            $data = $proposal['payload']; $data['id'] = $proposal['plan_id'] ?? 0; $data['active'] = 1; $data['published'] = 1;
            $plan = $repo->savePlan($data, (array) ($data['benefits'] ?? []), $admin);
            $version = $repo->findPlan($plan)['version_id'];
            $this->db->prepare("UPDATE catalog_sources SET status='verified' WHERE id=?")->execute([$proposal['source_id']]);
            $this->db->prepare("UPDATE catalog_proposals SET state='approved',reviewed_by=?,reviewed_at=NOW() WHERE id=?")->execute([$admin,$id]);
            $this->db->prepare('INSERT INTO catalog_reviews (snapshot_id,plan_id,version_id,admin_id,reviewed_at) VALUES (?,?,?,?,NOW())')->execute([$proposal['snapshot_id'],$plan,$version,$admin]);
            $this->db->prepare("UPDATE catalog_proposals SET state='blocked' WHERE state='pending' AND id<>? AND (plan_id=? OR (source_id=? AND name=?))")->execute([$id,$plan,$proposal['source_id'],$proposal['name']]);
            $this->db->prepare('INSERT INTO audit_logs (admin_id,action,entity_type,entity_id,payload_json,created_at) VALUES (?,\'approve_catalog\',\'plan\',?,?,NOW())')->execute([$admin,$plan,json_encode(['proposal'=>$id,'snapshot'=>$proposal['snapshot_id'],'version'=>$version])]);
            $this->db->commit(); return $plan;
        } catch (Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack(); throw $e; }
    }

    public function dismiss(int $id, int $admin): void
    {
        $this->db->prepare("UPDATE catalog_proposals SET state='dismissed',reviewed_by=?,reviewed_at=NOW() WHERE id=? AND state IN ('pending','blocked')")->execute([$admin,$id]);
    }

    public function health(): array
    {
        return ['pending'=>(int) $this->db->query("SELECT COUNT(*) FROM catalog_proposals WHERE state='pending'")->fetchColumn(),
            'errors'=>(int) $this->db->query("SELECT COUNT(*) FROM catalog_sources WHERE enabled=1 AND last_error IS NOT NULL")->fetchColumn(),
            'discovered'=>(int) $this->db->query("SELECT COUNT(*) FROM catalog_sources WHERE status='discovered'")->fetchColumn(),
            'run'=>$this->db->query('SELECT * FROM catalog_runs ORDER BY id DESC LIMIT 1')->fetch() ?: null];
    }
}
