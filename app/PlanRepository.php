<?php
declare(strict_types=1);

namespace QualPacote;

use PDO;
use RuntimeException;
use Throwable;

final class PlanRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function operators(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM operators';
        if ($activeOnly) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY display_order, name';

        return $this->db->query($sql)->fetchAll();
    }

    public function activePlans(array $operatorIds = []): array
    {
        $params = [];
        $where = ['p.active = 1', 'p.published = 1', 'o.active = 1'];

        if ($operatorIds !== []) {
            $operatorIds = array_values(array_filter(array_map('intval', $operatorIds), static fn ($id) => $id > 0));
            if ($operatorIds !== []) {
                $placeholders = implode(',', array_fill(0, count($operatorIds), '?'));
                $where[] = "p.operator_id IN ($placeholders)";
                $params = $operatorIds;
            }
        }

        $sql = "
            SELECT
                p.id,
                p.name,
                p.operator_id,
                o.name AS operator_name,
                o.slug AS operator_slug,
                pv.id AS version_id,
                pv.version_no,
                pv.price_kz,
                pv.validity_days,
                pv.activation_code,
                pv.source_url,
                pv.source_checked_at,
                pv.notes
            FROM plans p
            INNER JOIN operators o ON o.id = p.operator_id
            INNER JOIN plan_versions pv ON pv.id = (
                SELECT pv2.id
                FROM plan_versions pv2
                WHERE pv2.plan_id = p.id
                ORDER BY pv2.version_no DESC, pv2.id DESC
                LIMIT 1
            )
            WHERE " . implode(' AND ', $where) . "
            ORDER BY pv.price_kz ASC, o.name ASC, p.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $plans = $stmt->fetchAll();

        if ($plans === []) {
            return [];
        }

        $versionIds = array_map(static fn ($plan) => (int) $plan['version_id'], $plans);
        $placeholders = implode(',', array_fill(0, count($versionIds), '?'));
        $benefitStmt = $this->db->prepare(
            "SELECT * FROM benefits WHERE plan_version_id IN ($placeholders) ORDER BY id"
        );
        $benefitStmt->execute($versionIds);
        $benefits = $benefitStmt->fetchAll();

        $benefitsByVersion = [];
        foreach ($benefits as $benefit) {
            $benefitsByVersion[(int) $benefit['plan_version_id']][] = $benefit;
        }

        foreach ($plans as &$plan) {
            $plan['benefits'] = $benefitsByVersion[(int) $plan['version_id']] ?? [];
        }

        return $plans;
    }

    public function planList(): array
    {
        $sql = "
            SELECT
                p.id,
                p.name,
                p.active,
                p.published,
                o.name AS operator_name,
                pv.version_no,
                pv.price_kz,
                pv.validity_days,
                pv.source_checked_at
            FROM plans p
            INNER JOIN operators o ON o.id = p.operator_id
            INNER JOIN plan_versions pv ON pv.id = (
                SELECT pv2.id
                FROM plan_versions pv2
                WHERE pv2.plan_id = p.id
                ORDER BY pv2.version_no DESC, pv2.id DESC
                LIMIT 1
            )
            ORDER BY o.name, p.name
        ";

        return $this->db->query($sql)->fetchAll();
    }

    public function findPlan(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                pv.id AS version_id,
                pv.version_no,
                pv.price_kz,
                pv.validity_days,
                pv.activation_code,
                pv.source_url,
                pv.source_checked_at,
                pv.notes
            FROM plans p
            INNER JOIN plan_versions pv ON pv.id = (
                SELECT pv2.id
                FROM plan_versions pv2
                WHERE pv2.plan_id = p.id
                ORDER BY pv2.version_no DESC, pv2.id DESC
                LIMIT 1
            )
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        $plan = $stmt->fetch();

        if (!$plan) {
            return null;
        }

        $benefitStmt = $this->db->prepare('SELECT * FROM benefits WHERE plan_version_id = ? ORDER BY id');
        $benefitStmt->execute([(int) $plan['version_id']]);
        $plan['benefits'] = $benefitStmt->fetchAll();

        return $plan;
    }

    public function saveOperator(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);
        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $website = trim((string) ($data['website'] ?? ''));
        $displayOrder = (int) ($data['display_order'] ?? 0);
        $active = !empty($data['active']) ? 1 : 0;

        if ($name === '' || $slug === '') {
            throw new RuntimeException('Nome e código da operadora são obrigatórios.');
        }

        if ($id > 0) {
            $stmt = $this->db->prepare(
                'UPDATE operators SET name = ?, slug = ?, website = ?, display_order = ?, active = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$name, $slug, $website ?: null, $displayOrder, $active, $id]);
            return $id;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO operators (name, slug, website, display_order, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        );
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

        if ($operatorId < 1 || $name === '' || $price <= 0 || $validity < 1) {
            throw new RuntimeException('Operadora, nome, preço e validade são obrigatórios.');
        }

        if ($sourceUrl === '' || $sourceCheckedAt === '') {
            throw new RuntimeException('Fonte e data de verificação são obrigatórias.');
        }

        $cleanBenefits = [];
        foreach ($benefits as $benefit) {
            $type = strtoupper(trim((string) ($benefit['type'] ?? '')));
            $quantity = (float) ($benefit['quantity'] ?? 0);
            $unit = strtoupper(trim((string) ($benefit['unit'] ?? '')));
            $networkScope = strtoupper(trim((string) ($benefit['network_scope'] ?? 'ALL')));
            $startTime = trim((string) ($benefit['start_time'] ?? ''));
            $endTime = trim((string) ($benefit['end_time'] ?? ''));
            $appScope = trim((string) ($benefit['app_scope'] ?? ''));
            $label = trim((string) ($benefit['label'] ?? ''));

            if (!in_array($type, ['DATA', 'VOICE', 'SMS', 'SOCIAL'], true) || $quantity <= 0) {
                continue;
            }

            if (!in_array($unit, ['MB', 'MIN', 'SMS'], true)) {
                continue;
            }

            if (!in_array($networkScope, ['ALL', 'ONNET', 'OFFNET'], true)) {
                $networkScope = 'ALL';
            }

            $cleanBenefits[] = [
                'type' => $type,
                'quantity' => $quantity,
                'unit' => $unit,
                'network_scope' => $networkScope,
                'start_time' => $startTime ?: null,
                'end_time' => $endTime ?: null,
                'app_scope' => $appScope ?: null,
                'label' => $label ?: null,
            ];
        }

        if ($cleanBenefits === []) {
            throw new RuntimeException('Adicione pelo menos um benefício válido ao plano.');
        }

        $isNew = $planId === 0;
        $this->db->beginTransaction();

        try {
            if ($planId > 0) {
                $stmt = $this->db->prepare(
                    'UPDATE plans SET operator_id = ?, name = ?, active = ?, published = ?, updated_at = NOW() WHERE id = ?'
                );
                $stmt->execute([$operatorId, $name, $active, $published, $planId]);

                $versionStmt = $this->db->prepare(
                    'SELECT COALESCE(MAX(version_no), 0) AS max_version FROM plan_versions WHERE plan_id = ?'
                );
                $versionStmt->execute([$planId]);
                $versionNo = ((int) $versionStmt->fetchColumn()) + 1;

                $closeStmt = $this->db->prepare(
                    'UPDATE plan_versions SET active_to = NOW() WHERE plan_id = ? AND active_to IS NULL'
                );
                $closeStmt->execute([$planId]);
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO plans (operator_id, name, active, published, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())'
                );
                $stmt->execute([$operatorId, $name, $active, $published]);
                $planId = (int) $this->db->lastInsertId();
                $versionNo = 1;
            }

            $versionStmt = $this->db->prepare(
                'INSERT INTO plan_versions
                    (plan_id, version_no, price_kz, validity_days, activation_code, source_url, source_checked_at, notes, active_from, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())'
            );
            $versionStmt->execute([
                $planId,
                $versionNo,
                $price,
                $validity,
                $activationCode ?: null,
                $sourceUrl,
                $sourceCheckedAt,
                $notes ?: null,
                $adminId,
            ]);

            $versionId = (int) $this->db->lastInsertId();
            $benefitStmt = $this->db->prepare(
                'INSERT INTO benefits
                    (plan_version_id, type, quantity, unit, network_scope, start_time, end_time, app_scope, label, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
            );

            foreach ($cleanBenefits as $benefit) {
                $benefitStmt->execute([
                    $versionId,
                    $benefit['type'],
                    $benefit['quantity'],
                    $benefit['unit'],
                    $benefit['network_scope'],
                    $benefit['start_time'],
                    $benefit['end_time'],
                    $benefit['app_scope'],
                    $benefit['label'],
                ]);
            }

            $this->audit($adminId, $planId, $isNew ? 'create_plan' : 'save_plan', [
                'version' => $versionNo,
                'name' => $name,
            ]);

            $this->db->commit();
            return $planId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function setPlanState(int $planId, bool $active, bool $published, int $adminId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE plans SET active = ?, published = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$active ? 1 : 0, $published ? 1 : 0, $planId]);

        $this->audit($adminId, $planId, 'set_plan_state', [
            'active' => $active,
            'published' => $published,
        ]);
    }

    public function dashboardStats(): array
    {
        $stats = [];
        $stats['operators'] = (int) $this->db->query('SELECT COUNT(*) FROM operators WHERE active = 1')->fetchColumn();
        $stats['plans'] = (int) $this->db->query('SELECT COUNT(*) FROM plans WHERE active = 1 AND published = 1')->fetchColumn();
        $stats['stale'] = (int) $this->db->query("
            SELECT COUNT(*)
            FROM plans p
            INNER JOIN plan_versions pv ON pv.id = (
                SELECT pv2.id FROM plan_versions pv2 WHERE pv2.plan_id = p.id ORDER BY pv2.version_no DESC LIMIT 1
            )
            WHERE p.active = 1
              AND p.published = 1
              AND pv.source_checked_at < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ")->fetchColumn();

        $stats['last_checked'] = $this->db->query("
            SELECT MAX(pv.source_checked_at)
            FROM plans p
            INNER JOIN plan_versions pv ON pv.id = (
                SELECT pv2.id FROM plan_versions pv2 WHERE pv2.plan_id = p.id ORDER BY pv2.version_no DESC LIMIT 1
            )
            WHERE p.active = 1 AND p.published = 1
        ")->fetchColumn() ?: null;

        $stats['source_changes'] = (int) $this->db->query("
            SELECT COUNT(*)
            FROM source_checks sc
            INNER JOIN (
                SELECT source_url, MAX(id) AS max_id
                FROM source_checks
                GROUP BY source_url
            ) latest ON latest.max_id = sc.id
            WHERE sc.changed = 1
        ")->fetchColumn();

        return $stats;
    }

    public function logSearch(array $profile, int $resultCount): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO search_events
                    (budget_kz, goal, usage_level, duration_days, schedule_profile, call_scope, result_count, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                (float) ($profile['budget_kz'] ?? 0),
                (string) ($profile['goal'] ?? ''),
                (string) ($profile['usage_level'] ?? ''),
                (int) ($profile['duration_days'] ?? 0),
                (string) ($profile['schedule'] ?? ''),
                (string) ($profile['call_scope'] ?? ''),
                $resultCount,
            ]);
        } catch (Throwable) {
        }
    }

    private function audit(int $adminId, int $entityId, string $action, array $payload): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, payload_json, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $adminId,
            $action,
            'plan',
            $entityId,
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}
