<?php
declare(strict_types=1);

namespace QualPacote;

use PDO;
use RuntimeException;
use Throwable;

require_once __DIR__ . '/PlanRules.php';
require_once __DIR__ . '/SourceFetcher.php';

final class PlanRepository
{
    private ?bool $tariffTablesAvailable = null;
    private ?bool $rulesAvailable = null;

    public function __construct(private PDO $db)
    {
    }

    public function operators(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM operators';
        if ($activeOnly) $sql .= ' WHERE active = 1';
        $sql .= ' ORDER BY display_order, name';
        return $this->db->query($sql)->fetchAll();
    }

    public function activePlans(array $operatorIds = []): array
    {
        $params = [];
        $where = ['p.active = 1', 'p.published = 1', 'o.active = 1', 'pv.active_from <= NOW()', '(pv.active_to IS NULL OR pv.active_to > NOW())'];
        $this->appendOperatorFilter($where, $params, $operatorIds, 'p.operator_id');

        $sql = "
            SELECT p.id, p.name, p.operator_id, o.name AS operator_name, o.slug AS operator_slug,
                   pv.id AS version_id, pv.version_no, pv.price_kz, pv.validity_days,
                   pv.activation_code, pv.source_url, pv.source_checked_at, pv.notes
            FROM plans p
            INNER JOIN operators o ON o.id = p.operator_id
            INNER JOIN plan_versions pv ON pv.id = (
                SELECT pv2.id FROM plan_versions pv2
                WHERE pv2.plan_id = p.id
                ORDER BY pv2.version_no DESC, pv2.id DESC LIMIT 1
            )
            WHERE " . implode(' AND ', $where) . "
            ORDER BY pv.price_kz ASC, o.name ASC, p.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $plans = $stmt->fetchAll();
        if ($plans === []) return [];

        $versionIds = array_map(static fn ($plan) => (int) $plan['version_id'], $plans);
        $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
        $benefitStmt = $this->db->prepare("SELECT * FROM benefits WHERE plan_version_id IN ($placeholders) ORDER BY id");
        $benefitStmt->execute($versionIds);
        $benefitsByVersion = [];
        foreach ($benefitStmt->fetchAll() as $benefit) {
            $benefitsByVersion[(int) $benefit['plan_version_id']][] = $benefit;
        }
        foreach ($plans as &$plan) $plan['benefits'] = $benefitsByVersion[(int) $plan['version_id']] ?? [];
        unset($plan);
        return $this->attachRules($plans);
    }

    public function activeTariffs(array $operatorIds = []): array
    {
        if (!$this->hasTariffTables()) return [];

        $params = [];
        $where = ['t.active = 1', 't.published = 1', 'o.active = 1', 'tv.active_from <= NOW()', '(tv.active_to IS NULL OR tv.active_to > NOW())'];
        $this->appendOperatorFilter($where, $params, $operatorIds, 't.operator_id');

        $sql = "
            SELECT t.id, t.name, t.operator_id, t.is_default, o.name AS operator_name, o.slug AS operator_slug,
                   tv.id AS version_id, tv.version_no, tv.balance_validity_days,
                   tv.source_url, tv.source_checked_at, tv.notes
            FROM tariffs t
            INNER JOIN operators o ON o.id = t.operator_id
            INNER JOIN tariff_versions tv ON tv.id = (
                SELECT tv2.id FROM tariff_versions tv2
                WHERE tv2.tariff_id = t.id
                ORDER BY tv2.version_no DESC, tv2.id DESC LIMIT 1
            )
            WHERE " . implode(' AND ', $where) . "
            ORDER BY t.is_default DESC, o.display_order, t.name
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $tariffs = $stmt->fetchAll();
        if ($tariffs === []) return [];

        $versionIds = array_map(static fn ($tariff) => (int) $tariff['version_id'], $tariffs);
        $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
        $rateStmt = $this->db->prepare("SELECT * FROM tariff_rates WHERE tariff_version_id IN ($placeholders) ORDER BY id");
        $rateStmt->execute($versionIds);
        $ratesByVersion = [];
        foreach ($rateStmt->fetchAll() as $rate) {
            $ratesByVersion[(int) $rate['tariff_version_id']][] = $rate;
        }
        foreach ($tariffs as &$tariff) $tariff['rates'] = $ratesByVersion[(int) $tariff['version_id']] ?? [];
        unset($tariff);
        return $tariffs;
    }

    public function planList(): array
    {
        $sql = "
            SELECT p.id, p.name, p.active, p.published, o.name AS operator_name,
                   pv.version_no, pv.price_kz, pv.validity_days, pv.source_checked_at
            FROM plans p
            INNER JOIN operators o ON o.id = p.operator_id
            INNER JOIN plan_versions pv ON pv.id = (
                SELECT pv2.id FROM plan_versions pv2 WHERE pv2.plan_id = p.id
                ORDER BY pv2.version_no DESC, pv2.id DESC LIMIT 1
            )
            ORDER BY o.name, p.name
        ";
        return $this->db->query($sql)->fetchAll();
    }

    public function tariffList(): array
    {
        if (!$this->hasTariffTables()) return [];
        $sql = "
            SELECT t.id, t.name, t.active, t.published, t.is_default, o.name AS operator_name,
                   tv.version_no, tv.balance_validity_days, tv.source_checked_at
            FROM tariffs t
            INNER JOIN operators o ON o.id = t.operator_id
            INNER JOIN tariff_versions tv ON tv.id = (
                SELECT tv2.id FROM tariff_versions tv2 WHERE tv2.tariff_id = t.id
                ORDER BY tv2.version_no DESC, tv2.id DESC LIMIT 1
            )
            ORDER BY o.name, t.is_default DESC, t.name
        ";
        return $this->db->query($sql)->fetchAll();
    }

    public function findPlan(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT p.*, pv.id AS version_id, pv.version_no, pv.price_kz, pv.validity_days,
                   pv.activation_code, pv.source_url, pv.source_checked_at, pv.notes
            FROM plans p
            INNER JOIN plan_versions pv ON pv.id = (
                SELECT pv2.id FROM plan_versions pv2 WHERE pv2.plan_id = p.id
                ORDER BY pv2.version_no DESC, pv2.id DESC LIMIT 1
            )
            WHERE p.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        $plan = $stmt->fetch();
        if (!$plan) return null;
        $benefitStmt = $this->db->prepare('SELECT * FROM benefits WHERE plan_version_id = ? ORDER BY id');
        $benefitStmt->execute([(int) $plan['version_id']]);
        $plan['benefits'] = $benefitStmt->fetchAll();
        return $this->attachRules([$plan])[0];
    }

    public function findTariff(int $id): ?array
    {
        if (!$this->hasTariffTables()) return null;
        $stmt = $this->db->prepare("
            SELECT t.*, tv.id AS version_id, tv.version_no, tv.balance_validity_days,
                   tv.source_url, tv.source_checked_at, tv.notes
            FROM tariffs t
            INNER JOIN tariff_versions tv ON tv.id = (
                SELECT tv2.id FROM tariff_versions tv2 WHERE tv2.tariff_id = t.id
                ORDER BY tv2.version_no DESC, tv2.id DESC LIMIT 1
            )
            WHERE t.id = ? LIMIT 1
        ");
        $stmt->execute([$id]);
        $tariff = $stmt->fetch();
        if (!$tariff) return null;
        $rateStmt = $this->db->prepare('SELECT * FROM tariff_rates WHERE tariff_version_id = ? ORDER BY id');
        $rateStmt->execute([(int) $tariff['version_id']]);
        $tariff['rates'] = $rateStmt->fetchAll();
        return $tariff;
    }

    public function saveOperator(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $website = trim((string) ($data['website'] ?? ''));
        $displayOrder = (int) ($data['display_order'] ?? 0);
        $active = !empty($data['active']) ? 1 : 0;
        if ($name === '' || $slug === '') throw new RuntimeException('Nome e código da operadora são obrigatórios.');

        if ($id > 0) {
            $stmt = $this->db->prepare('UPDATE operators SET name=?, slug=?, website=?, display_order=?, active=?, updated_at=NOW() WHERE id=?');
            $stmt->execute([$name, $slug, $website ?: null, $displayOrder, $active, $id]);
            return $id;
        }
        $stmt = $this->db->prepare('INSERT INTO operators (name,slug,website,display_order,active,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())');
        $stmt->execute([$name, $slug, $website ?: null, $displayOrder, $active]);
        return (int) $this->db->lastInsertId();
    }

    public function savePlan(array $data, array $benefits, int $adminId): int
    {
        $planId = (int) ($data['id'] ?? 0);
        $operatorId = (int) ($data['operator_id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $price = (float) ($data['price_kz'] ?? 0);
        $validity = (int) ($data['validity_days'] ?? 0);
        $activationCode = trim((string) ($data['activation_code'] ?? ''));
        $sourceUrl = trim((string) ($data['source_url'] ?? ''));
        $sourceCheckedAt = trim((string) ($data['source_checked_at'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $active = !empty($data['active']) ? 1 : 0;
        $published = !empty($data['published']) ? 1 : 0;

        if ($operatorId < 1 || $name === '' || !is_finite($price) || $price <= 0 || $validity < 1 || $validity > 365) throw new RuntimeException('Operadora, nome, preço e validade são obrigatórios.');
        if (!SourceFetcher::allowed($sourceUrl) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D',$sourceCheckedAt) || date('Y-m-d', strtotime($sourceCheckedAt) ?: 0) !== $sourceCheckedAt || $sourceCheckedAt > date('Y-m-d')) throw new RuntimeException('Use uma fonte oficial permitida e uma data de confirmação válida, sem datas futuras.');
        $this->assertSourceOperator($operatorId, $sourceUrl);
        $cleanBenefits = $this->cleanBenefits($benefits);
        if ($cleanBenefits === []) throw new RuntimeException('Adicione pelo menos um benefício válido ao plano.');

        $rules = PlanRules::normalize((array) ($data['rules'] ?? []));
        if (!$this->hasRules() && !empty($data['rules'])) throw new RuntimeException('Importe a migração de inteligência do catálogo antes de guardar regras.');
        $isNew = $planId === 0;
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            if ($planId > 0) {
                $lock = $this->db->prepare('SELECT id FROM plans WHERE id=? FOR UPDATE');
                $lock->execute([$planId]);
                if (!$lock->fetchColumn()) throw new RuntimeException('Plano inexistente.');
                $stmt = $this->db->prepare('UPDATE plans SET operator_id=?, name=?, active=?, published=?, updated_at=NOW() WHERE id=?');
                $stmt->execute([$operatorId, $name, $active, $published, $planId]);
                $versionStmt = $this->db->prepare('SELECT COALESCE(MAX(version_no),0) FROM plan_versions WHERE plan_id=?');
                $versionStmt->execute([$planId]);
                $versionNo = ((int) $versionStmt->fetchColumn()) + 1;
                $this->db->prepare('UPDATE plan_versions SET active_to=NOW() WHERE plan_id=? AND active_to IS NULL')->execute([$planId]);
            } else {
                $stmt = $this->db->prepare('INSERT INTO plans (operator_id,name,active,published,created_at,updated_at) VALUES (?,?,?,?,NOW(),NOW())');
                $stmt->execute([$operatorId, $name, $active, $published]);
                $planId = (int) $this->db->lastInsertId();
                $versionNo = 1;
            }

            $versionStmt = $this->db->prepare('INSERT INTO plan_versions (plan_id,version_no,price_kz,validity_days,activation_code,source_url,source_checked_at,notes,active_from,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,NOW(),?,NOW())');
            $versionStmt->execute([$planId,$versionNo,$price,$validity,$activationCode ?: null,$sourceUrl,$sourceCheckedAt,$notes ?: null,$adminId]);
            $versionId = (int) $this->db->lastInsertId();
            $benefitStmt = $this->db->prepare('INSERT INTO benefits (plan_version_id,type,quantity,unit,network_scope,start_time,end_time,app_scope,label,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
            foreach ($cleanBenefits as $benefit) {
                $benefitStmt->execute([$versionId,$benefit['type'],$benefit['quantity'],$benefit['unit'],$benefit['network_scope'],$benefit['start_time'],$benefit['end_time'],$benefit['app_scope'],$benefit['label']]);
            }
            if ($this->hasRules()) {
                $this->db->prepare('INSERT INTO plan_version_rules (plan_version_id,rules_json) VALUES (?,?)')->execute([$versionId, json_encode($rules, JSON_UNESCAPED_UNICODE)]);
            }
            $this->audit($adminId, 'plan', $planId, $isNew ? 'create_plan' : 'save_plan', ['version'=>$versionNo,'name'=>$name]);
            if ($ownsTransaction) $this->db->commit();
            return $planId;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function saveTariff(array $data, array $rates, int $adminId): int
    {
        if (!$this->hasTariffTables()) throw new RuntimeException('Execute primeiro a migração de tarifas base.');
        $tariffId = (int) ($data['id'] ?? 0);
        $operatorId = (int) ($data['operator_id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $validityRaw = trim((string) ($data['balance_validity_days'] ?? ''));
        $validity = $validityRaw === '' ? null : max(1, (int) $validityRaw);
        $sourceUrl = trim((string) ($data['source_url'] ?? ''));
        $sourceCheckedAt = trim((string) ($data['source_checked_at'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $active = !empty($data['active']) ? 1 : 0;
        $published = !empty($data['published']) ? 1 : 0;
        $isDefault = !empty($data['is_default']) ? 1 : 0;

        if ($operatorId < 1 || $name === '') throw new RuntimeException('Operadora e nome da tarifa são obrigatórios.');
        if (!SourceFetcher::allowed($sourceUrl) || !preg_match('/\A\d{4}-\d{2}-\d{2}\z/D',$sourceCheckedAt) || date('Y-m-d', strtotime($sourceCheckedAt) ?: 0) !== $sourceCheckedAt || $sourceCheckedAt > date('Y-m-d')) throw new RuntimeException('Use uma fonte oficial permitida e uma data de confirmação válida, sem datas futuras.');
        $this->assertSourceOperator($operatorId, $sourceUrl);
        $cleanRates = $this->cleanRates($rates);
        if ($cleanRates === []) throw new RuntimeException('Adicione pelo menos uma tarifa válida.');

        $isNew = $tariffId === 0;
        $ownsTransaction = !$this->db->inTransaction();
        if ($ownsTransaction) $this->db->beginTransaction();
        try {
            if ($isDefault) {
                $clear = $this->db->prepare('UPDATE tariffs SET is_default=0, updated_at=NOW() WHERE operator_id=? AND id<>?');
                $clear->execute([$operatorId, $tariffId]);
            }

            if ($tariffId > 0) {
                $stmt = $this->db->prepare('UPDATE tariffs SET operator_id=?, name=?, is_default=?, active=?, published=?, updated_at=NOW() WHERE id=?');
                $stmt->execute([$operatorId,$name,$isDefault,$active,$published,$tariffId]);
                $versionStmt = $this->db->prepare('SELECT COALESCE(MAX(version_no),0) FROM tariff_versions WHERE tariff_id=?');
                $versionStmt->execute([$tariffId]);
                $versionNo = ((int) $versionStmt->fetchColumn()) + 1;
                $this->db->prepare('UPDATE tariff_versions SET active_to=NOW() WHERE tariff_id=? AND active_to IS NULL')->execute([$tariffId]);
            } else {
                $stmt = $this->db->prepare('INSERT INTO tariffs (operator_id,name,is_default,active,published,created_at,updated_at) VALUES (?,?,?,?,?,NOW(),NOW())');
                $stmt->execute([$operatorId,$name,$isDefault,$active,$published]);
                $tariffId = (int) $this->db->lastInsertId();
                $versionNo = 1;
            }

            $versionStmt = $this->db->prepare('INSERT INTO tariff_versions (tariff_id,version_no,balance_validity_days,source_url,source_checked_at,notes,active_from,created_by,created_at) VALUES (?,?,?,?,?,?,NOW(),?,NOW())');
            $versionStmt->execute([$tariffId,$versionNo,$validity,$sourceUrl,$sourceCheckedAt,$notes ?: null,$adminId]);
            $versionId = (int) $this->db->lastInsertId();
            $rateStmt = $this->db->prepare('INSERT INTO tariff_rates (tariff_version_id,service_type,unit,price_kz_per_unit,network_scope,start_time,end_time,label,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
            foreach ($cleanRates as $rate) {
                $rateStmt->execute([$versionId,$rate['service_type'],$rate['unit'],$rate['price_kz_per_unit'],$rate['network_scope'],$rate['start_time'],$rate['end_time'],$rate['label']]);
            }
            $this->audit($adminId, 'tariff', $tariffId, $isNew ? 'create_tariff' : 'save_tariff', ['version'=>$versionNo,'name'=>$name]);
            if ($ownsTransaction) $this->db->commit();
            return $tariffId;
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function setPlanState(int $planId, bool $active, bool $published, int $adminId): void
    {
        $this->db->prepare('UPDATE plans SET active=?, published=?, updated_at=NOW() WHERE id=?')->execute([$active ? 1 : 0,$published ? 1 : 0,$planId]);
        $this->audit($adminId, 'plan', $planId, 'set_plan_state', ['active'=>$active,'published'=>$published]);
    }

    public function setTariffState(int $tariffId, bool $active, bool $published, int $adminId): void
    {
        if (!$this->hasTariffTables()) return;
        $this->db->prepare('UPDATE tariffs SET active=?, published=?, updated_at=NOW() WHERE id=?')->execute([$active ? 1 : 0,$published ? 1 : 0,$tariffId]);
        $this->audit($adminId, 'tariff', $tariffId, 'set_tariff_state', ['active'=>$active,'published'=>$published]);
    }

    public function dashboardStats(): array
    {
        $stats = [];
        $stats['operators'] = (int) $this->db->query('SELECT COUNT(*) FROM operators WHERE active=1')->fetchColumn();
        $stats['plans'] = (int) $this->db->query('SELECT COUNT(*) FROM plans WHERE active=1 AND published=1')->fetchColumn();
        $stats['stale'] = (int) $this->db->query("SELECT COUNT(*) FROM plans p INNER JOIN plan_versions pv ON pv.id=(SELECT pv2.id FROM plan_versions pv2 WHERE pv2.plan_id=p.id ORDER BY pv2.version_no DESC LIMIT 1) WHERE p.active=1 AND p.published=1 AND pv.source_checked_at<DATE_SUB(CURDATE(),INTERVAL 7 DAY)")->fetchColumn();
        $planLastChecked = $this->db->query("SELECT MAX(pv.source_checked_at) FROM plans p INNER JOIN plan_versions pv ON pv.id=(SELECT pv2.id FROM plan_versions pv2 WHERE pv2.plan_id=p.id ORDER BY pv2.version_no DESC LIMIT 1) WHERE p.active=1 AND p.published=1")->fetchColumn() ?: null;

        $stats['tariffs'] = 0;
        $stats['missing_tariffs'] = 0;
        $stats['stale_tariffs'] = 0;
        $tariffLastChecked = null;
        if ($this->hasTariffTables()) {
            $stats['tariffs'] = (int) $this->db->query('SELECT COUNT(*) FROM tariffs WHERE active=1 AND published=1')->fetchColumn();
            $stats['missing_tariffs'] = (int) $this->db->query("SELECT COUNT(*) FROM operators o WHERE o.active=1 AND NOT EXISTS (SELECT 1 FROM tariffs t WHERE t.operator_id=o.id AND t.active=1 AND t.published=1 AND t.is_default=1)")->fetchColumn();
            $stats['stale_tariffs'] = (int) $this->db->query("SELECT COUNT(*) FROM tariffs t INNER JOIN tariff_versions tv ON tv.id=(SELECT tv2.id FROM tariff_versions tv2 WHERE tv2.tariff_id=t.id ORDER BY tv2.version_no DESC LIMIT 1) WHERE t.active=1 AND t.published=1 AND tv.source_checked_at<DATE_SUB(CURDATE(),INTERVAL 7 DAY)")->fetchColumn();
            $tariffLastChecked = $this->db->query("SELECT MAX(tv.source_checked_at) FROM tariffs t INNER JOIN tariff_versions tv ON tv.id=(SELECT tv2.id FROM tariff_versions tv2 WHERE tv2.tariff_id=t.id ORDER BY tv2.version_no DESC LIMIT 1) WHERE t.active=1 AND t.published=1")->fetchColumn() ?: null;
        }

        $stats['last_checked'] = $this->maxDate($planLastChecked, $tariffLastChecked);
        if ($this->db->query("SHOW TABLES LIKE 'catalog_proposals'")->fetchColumn()) {
            $stats['source_changes'] = (int) $this->db->query("SELECT COUNT(DISTINCT source_id) FROM catalog_proposals WHERE state='pending'")->fetchColumn();
            return $stats;
        }
        $stats['source_changes'] = (int) $this->db->query("SELECT COUNT(*) FROM source_checks sc INNER JOIN (SELECT source_url,MAX(id) max_id FROM source_checks GROUP BY source_url) latest ON latest.max_id=sc.id WHERE sc.changed=1")->fetchColumn();
        return $stats;
    }

    public function logSearch(array $profile, int $resultCount): ?int
    {
        try {
            $services = implode('+', (array) ($profile['services'] ?? []));
            $stmt = $this->db->prepare('INSERT INTO search_events (budget_kz,goal,usage_level,duration_days,schedule_profile,call_scope,result_count,created_at) VALUES (?,?,?,?,?,?,?,?)');
            $stmt->execute([
                (float) ($profile['budget_kz'] ?? 0),
                $services !== '' ? $services : (string) ($profile['goal'] ?? ''),
                (string) ($profile['priority'] ?? $profile['usage_level'] ?? ''),
                (int) ($profile['duration_days'] ?? 0),
                (string) ($profile['schedule'] ?? ''),
                (string) ($profile['call_scope'] ?? ''),
                $resultCount,
                date('Y-m-d H:i:s'),
            ]);
            return (int) $this->db->lastInsertId();
        } catch (Throwable) {
            return null;
        }
    }

    private function cleanBenefits(array $benefits): array
    {
        $clean = [];
        foreach ($benefits as $benefit) {
            $type = strtoupper(trim((string) ($benefit['type'] ?? '')));
            $quantity = (float) ($benefit['quantity'] ?? 0);
            $unit = strtoupper(trim((string) ($benefit['unit'] ?? '')));
            $networkScope = strtoupper(trim((string) ($benefit['network_scope'] ?? 'ALL')));
            if ($quantity === 0.0 && trim((string) ($benefit['quantity'] ?? '')) === '') continue;
            if (!in_array($type, ['DATA','VOICE','SMS','SOCIAL'], true) || !is_finite($quantity) || $quantity <= 0) throw new RuntimeException('Benefício com tipo ou quantidade inválidos.');
            $expected = ['DATA'=>'MB','SOCIAL'=>'MB','VOICE'=>'MIN','SMS'=>'SMS'][$type];
            if ($unit !== $expected || !in_array($networkScope, ['ALL','ONNET','OFFNET'], true)) throw new RuntimeException('Unidade ou rede incompatível com o benefício.');
            $start = trim((string) ($benefit['start_time'] ?? ''));
            $end = trim((string) ($benefit['end_time'] ?? ''));
            if (($start === '') !== ($end === '') || ($start !== '' && (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/', $start) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/', $end)))) throw new RuntimeException('Indique início e fim válidos para a restrição de horário.');
            $clean[] = [
                'type'=>$type,'quantity'=>$quantity,'unit'=>$unit,'network_scope'=>$networkScope,
                'start_time'=>trim((string) ($benefit['start_time'] ?? '')) ?: null,
                'end_time'=>trim((string) ($benefit['end_time'] ?? '')) ?: null,
                'app_scope'=>trim((string) ($benefit['app_scope'] ?? '')) ?: null,
                'label'=>trim((string) ($benefit['label'] ?? '')) ?: null,
            ];
        }
        return $clean;
    }

    private function cleanRates(array $rates): array
    {
        $clean = [];
        foreach ($rates as $rate) {
            $type = strtoupper(trim((string) ($rate['service_type'] ?? '')));
            $unit = strtoupper(trim((string) ($rate['unit'] ?? '')));
            $price = (float) ($rate['price_kz_per_unit'] ?? 0);
            $networkScope = strtoupper(trim((string) ($rate['network_scope'] ?? 'ALL')));
            if ($price === 0.0 && trim((string) ($rate['price_kz_per_unit'] ?? '')) === '') continue;
            if (!in_array($type, ['DATA','VOICE','SMS'], true) || !is_finite($price) || $price <= 0) throw new RuntimeException('Serviço ou preço inválido na tarifa.');
            $expectedUnit = ['DATA'=>'MB','VOICE'=>'MIN','SMS'=>'SMS'][$type];
            if ($unit !== $expectedUnit || !in_array($networkScope, ['ALL','ONNET','OFFNET'], true)) throw new RuntimeException('Unidade ou rede incompatível com a tarifa.');
            $start = trim((string) ($rate['start_time'] ?? ''));
            $end = trim((string) ($rate['end_time'] ?? ''));
            if (($start === '') !== ($end === '') || ($start !== '' && (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/', $start) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?$/', $end)))) throw new RuntimeException('Indique início e fim válidos para o horário da tarifa.');
            $clean[] = [
                'service_type'=>$type,'unit'=>$unit,'price_kz_per_unit'=>$price,'network_scope'=>$networkScope,
                'start_time'=>trim((string) ($rate['start_time'] ?? '')) ?: null,
                'end_time'=>trim((string) ($rate['end_time'] ?? '')) ?: null,
                'label'=>trim((string) ($rate['label'] ?? '')) ?: null,
            ];
        }
        return $clean;
    }

    private function assertSourceOperator(int $operatorId, string $url): void
    {
        $stmt = $this->db->prepare('SELECT slug FROM operators WHERE id=?');
        $stmt->execute([$operatorId]);
        $host = ['unitel'=>'unitel.ao','africell'=>'africell.ao','movicel'=>'movicel.co.ao'][$stmt->fetchColumn() ?: ''] ?? null;
        if (!$host || !in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), [$host,'www.' . $host, ...($host === 'movicel.co.ao' ? ['www2.movicel.co.ao'] : [])], true)) throw new RuntimeException('A fonte não corresponde à operadora escolhida.');
    }

    private function appendOperatorFilter(array &$where, array &$params, array $operatorIds, string $column): void
    {
        $operatorIds = array_values(array_filter(array_map('intval', $operatorIds), static fn ($id) => $id > 0));
        if ($operatorIds === []) return;
        $where[] = $column . ' IN (' . implode(',', array_fill(0, count($operatorIds), '?')) . ')';
        array_push($params, ...$operatorIds);
    }

    public function hasRules(): bool
    {
        if ($this->rulesAvailable === null) $this->rulesAvailable = (bool) $this->db->query("SHOW TABLES LIKE 'plan_version_rules'")->fetchColumn();
        return $this->rulesAvailable;
    }

    private function attachRules(array $plans): array
    {
        $rules = [];
        if ($this->hasRules() && $plans !== []) {
            $ids = array_column($plans, 'version_id');
            $stmt = $this->db->prepare('SELECT * FROM plan_version_rules WHERE plan_version_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) $rules[$row['plan_version_id']] = json_decode($row['rules_json'], true) ?: [];
        }
        $observed = [];
        if ($plans !== [] && $this->db->query("SHOW TABLES LIKE 'catalog_reviews'")->fetchColumn()) {
            $ids = array_column($plans,'version_id');
            $stmt = $this->db->prepare("SELECT r.version_id,MAX(s.last_success_at) AS observed_at FROM catalog_reviews r JOIN catalog_snapshots x ON x.id=r.snapshot_id JOIN catalog_sources s ON s.id=x.source_id WHERE r.version_id IN (" . implode(',',array_fill(0,count($ids),'?')) . ") AND s.enabled=1 AND s.status='verified' AND s.last_error IS NULL AND r.snapshot_id=(SELECT MAX(id) FROM catalog_snapshots WHERE source_id=s.id) GROUP BY r.version_id");
            $stmt->execute($ids);
            foreach ($stmt->fetchAll() as $row) $observed[$row['version_id']] = $row['observed_at'];
        }
        foreach ($plans as &$plan) {
            $plan['rules'] = $rules[$plan['version_id']] ?? ['category'=>'mobile','repeat_policy'=>'unknown'];
            $plan['source_observed_at'] = $observed[$plan['version_id']] ?? null;
        }
        return $plans;
    }

    private function hasTariffTables(): bool
    {
        if ($this->tariffTablesAvailable !== null) return $this->tariffTablesAvailable;
        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'tariffs'");
            $this->tariffTablesAvailable = (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            $this->tariffTablesAvailable = false;
        }
        return $this->tariffTablesAvailable;
    }

    private function maxDate(mixed $a, mixed $b): ?string
    {
        $a = $a ? (string) $a : null;
        $b = $b ? (string) $b : null;
        if ($a === null) return $b;
        if ($b === null) return $a;
        return strcmp($a, $b) >= 0 ? $a : $b;
    }

    private function audit(int $adminId, string $entityType, int $entityId, string $action, array $payload): void
    {
        $stmt = $this->db->prepare('INSERT INTO audit_logs (admin_id,action,entity_type,entity_id,payload_json,created_at) VALUES (?,?,?,?,?,NOW())');
        $stmt->execute([$adminId,$action,$entityType,$entityId,json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    }
}
