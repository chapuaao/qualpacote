<?php
declare(strict_types=1);

namespace QualPacote;

use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

final class UsageRepository
{
    private ?bool $detailsAvailable = null;
    public function __construct(private PDO $db) {}

    private function hasDetails(): bool
    {
        if ($this->detailsAvailable === null) $this->detailsAvailable = (bool) $this->db->query("SHOW TABLES LIKE 'usage_result_details'")->fetchColumn();
        return $this->detailsAvailable;
    }

    public function recordVisit(string $visitor): void
    {
        $stmt = $this->db->prepare('INSERT INTO usage_visitors (visitor_key,first_seen_at) VALUES (?,?) ON DUPLICATE KEY UPDATE visitor_key=VALUES(visitor_key)');
        $stmt->execute([$visitor, date('Y-m-d H:i:s')]);
        $stmt = $this->db->prepare('INSERT INTO usage_visit_days (visitor_key,visit_date) VALUES (?,?) ON DUPLICATE KEY UPDATE visitor_key=VALUES(visitor_key)');
        $stmt->execute([$visitor, date('Y-m-d')]);
    }

    public static function resultKey(array $result): string
    {
        if ((!empty($result['is_combination']) || ($result['cycles'] ?? 1) > 1) && !empty($result['result_key'])) return $result['result_key'];
        $item = $result['kind'] === 'plan' ? $result['plan'] : $result['tariff'];
        return $result['kind'] . ':' . (int) $item['id'] . ':' . (int) $item['version_id'];
    }

    public function recordConsultation(string $visitor, string $requestToken, array $profile, array $results, string $returnQuery): array
    {
        $existing = $this->consultationResults($visitor, $requestToken);
        if ($existing !== null) return $existing;

        $this->db->beginTransaction();
        try {
            $searchId = (new PlanRepository($this->db))->logSearch($profile, count($results));
            if ($searchId === null) throw new RuntimeException('Falha ao registar consulta.');
            $stmt = $this->db->prepare('INSERT INTO usage_consultations (search_event_id,visitor_key,request_token,return_query) VALUES (?,?,?,?)');
            $stmt->execute([$searchId, $visitor, $requestToken, substr($returnQuery, 0, 8192)]);
            $insert = $this->db->prepare('INSERT INTO usage_results (search_event_id,vote_token,kind,item_id,version_id,operator_name,title,result_position) VALUES (?,?,?,?,?,?,?,?)');
            foreach ($results as $i => $result) {
                $item = $result['kind'] === 'plan' ? $result['plan'] : $result['tariff'];
                $insert->execute([$searchId, bin2hex(random_bytes(32)), $result['kind'], (int) $item['id'], (int) $item['version_id'], $result['operator_name'], substr($result['title'],0,255), $i + 1]);
                $resultId = (int) $this->db->lastInsertId();
                if ($this->hasDetails()) $this->db->prepare('INSERT INTO usage_result_details (result_id,result_key,components_json) VALUES (?,?,?)')->execute([$resultId,self::resultKey($result),json_encode($result['components'] ?? [], JSON_UNESCAPED_UNICODE)]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            // The UNIQUE request token also protects simultaneous retries.
            $existing = $this->consultationResults($visitor, $requestToken);
            if ($existing !== null) return $existing;
            throw $e;
        }
        return $this->consultationResults($visitor, $requestToken) ?? [];
    }

    private function consultationResults(string $visitor, string $token): ?array
    {
        $stmt = $this->db->prepare('SELECT search_event_id FROM usage_consultations WHERE visitor_key=? AND request_token=?');
        $stmt->execute([$visitor, $token]);
        $id = $stmt->fetchColumn();
        if ($id === false) return null;
        $select = $this->hasDetails() ? 'SELECT r.kind,r.item_id,r.version_id,r.vote_token,r.vote,d.result_key FROM usage_results r LEFT JOIN usage_result_details d ON d.result_id=r.id WHERE r.search_event_id=? ORDER BY r.result_position' : 'SELECT kind,item_id,version_id,vote_token,vote FROM usage_results WHERE search_event_id=? ORDER BY result_position';
        $stmt = $this->db->prepare($select);
        $stmt->execute([$id]);
        $results = [];
        foreach ($stmt->fetchAll() as $row) {
            $results[$row['result_key'] ?? ($row['kind'] . ':' . $row['item_id'] . ':' . $row['version_id'])] = $row;
        }
        return $results;
    }

    public function vote(string $visitor, string $token, int $vote): string
    {
        if (!in_array($vote, [-1, 1], true) || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            throw new InvalidArgumentException('Voto inválido.');
        }
        $stmt = $this->db->prepare('SELECT r.id,c.return_query FROM usage_results r INNER JOIN usage_consultations c ON c.search_event_id=r.search_event_id WHERE r.vote_token=? AND c.visitor_key=?');
        $stmt->execute([$token, $visitor]);
        $result = $stmt->fetch();
        if (!$result) throw new InvalidArgumentException('Esta recomendação não pertence à sua sessão. Volte a consultar os pacotes.');
        // An existing vote can change; clicking it again never adds another vote.
        $stmt = $this->db->prepare('UPDATE usage_results SET vote=?,voted_at=? WHERE id=? AND (vote IS NULL OR vote<>?)');
        $stmt->execute([$vote, date('Y-m-d H:i:s'), $result['id'], $vote]);
        return '/?' . $result['return_query'] . '#resultados';
    }

    public static function period(string $from, string $to): array
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if (!$start || !$end || $start->format('Y-m-d') !== $from || $end->format('Y-m-d') !== $to
            || $end < $start || (int) $start->diff($end)->days > 365) {
            throw new InvalidArgumentException('Escolha datas válidas, com início anterior ao fim e um intervalo máximo de 366 dias.');
        }
        return [$start->format('Y-m-d H:i:s'), $end->modify('+1 day')->format('Y-m-d H:i:s')];
    }

    private function rows(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function statistics(string $from, string $to): array
    {
        $period = self::period($from, $to);
        $summary = $this->rows('SELECT COUNT(*) consultations FROM search_events WHERE created_at>=? AND created_at<?', $period)[0];
        $summary['visitors'] = $this->rows('SELECT COUNT(DISTINCT visitor_key) visitors FROM usage_visit_days WHERE visit_date>=? AND visit_date<?', [$from, substr($period[1], 0, 10)])[0]['visitors'];
        $summary['users'] = $this->rows('SELECT COUNT(DISTINCT c.visitor_key) users FROM usage_consultations c INNER JOIN search_events s ON s.id=c.search_event_id WHERE s.created_at>=? AND s.created_at<?', $period)[0]['users'];
        $votes = $this->rows('SELECT COALESCE(SUM(vote=1),0) positive,COALESCE(SUM(vote=-1),0) negative FROM usage_results WHERE voted_at>=? AND voted_at<?', $period)[0];
        $summary = array_merge($summary, $votes);
        $totals = $this->rows('SELECT (SELECT COUNT(*) FROM search_events) consultations,(SELECT COUNT(*) FROM usage_visitors) visitors,(SELECT COUNT(DISTINCT visitor_key) FROM usage_consultations) users,COALESCE(SUM(vote=1),0) positive,COALESCE(SUM(vote=-1),0) negative FROM usage_results')[0];

        $daily = [];
        $end = new DateTimeImmutable($to);
        for ($day = new DateTimeImmutable($from); $day <= $end; $day = $day->modify('+1 day')) {
            $daily[$day->format('Y-m-d')] = ['visitors'=>0,'consultations'=>0,'positive'=>0,'negative'=>0];
        }
        foreach ($this->rows('SELECT visit_date day,COUNT(*) visitors FROM usage_visit_days WHERE visit_date>=? AND visit_date<? GROUP BY visit_date', [$from, substr($period[1], 0, 10)]) as $row) $daily[$row['day']]['visitors'] = (int) $row['visitors'];
        foreach ($this->rows('SELECT DATE(created_at) day,COUNT(*) consultations FROM search_events WHERE created_at>=? AND created_at<? GROUP BY DATE(created_at)', $period) as $row) $daily[$row['day']]['consultations'] = (int) $row['consultations'];
        foreach ($this->rows('SELECT DATE(voted_at) day,SUM(vote=1) positive,SUM(vote=-1) negative FROM usage_results WHERE voted_at>=? AND voted_at<? GROUP BY DATE(voted_at)', $period) as $row) {
            $daily[$row['day']]['positive'] = (int) $row['positive'];
            $daily[$row['day']]['negative'] = (int) $row['negative'];
        }

        // Preserve the exact version that was displayed, even if renamed later.
        $items = [];
        foreach ($this->rows('SELECT r.kind,r.item_id,r.version_id,MAX(r.title) title,MAX(r.operator_name) operator_name,COUNT(*) impressions FROM usage_results r INNER JOIN search_events s ON s.id=r.search_event_id WHERE s.created_at>=? AND s.created_at<? GROUP BY r.kind,r.item_id,r.version_id', $period) as $row) {
            $key = $row['kind'] . ':' . $row['item_id'] . ':' . $row['version_id'];
            $items[$key] = array_merge($row, ['positive'=>0,'negative'=>0]);
        }
        foreach ($this->rows('SELECT kind,item_id,version_id,MAX(title) title,MAX(operator_name) operator_name,SUM(vote=1) positive,SUM(vote=-1) negative FROM usage_results WHERE voted_at>=? AND voted_at<? GROUP BY kind,item_id,version_id', $period) as $row) {
            $key = $row['kind'] . ':' . $row['item_id'] . ':' . $row['version_id'];
            $items[$key] = array_merge($items[$key] ?? ['impressions'=>0], $row);
        }
        usort($items, static fn ($a, $b) => ((int) $b['positive'] + (int) $b['negative']) <=> ((int) $a['positive'] + (int) $a['negative']) ?: (int) $b['impressions'] <=> (int) $a['impressions']);
        $searches = $this->rows('SELECT s.id,s.created_at,s.budget_kz,s.goal,s.result_count,COALESCE(SUM(r.vote=1),0) positive,COALESCE(SUM(r.vote=-1),0) negative FROM search_events s LEFT JOIN usage_results r ON r.search_event_id=s.id WHERE s.created_at>=? AND s.created_at<? GROUP BY s.id,s.created_at,s.budget_kz,s.goal,s.result_count ORDER BY s.created_at DESC,s.id DESC LIMIT 100', $period);
        $history = $this->rows('SELECT r.title,r.operator_name,r.kind,r.vote,r.voted_at,r.search_event_id FROM usage_results r WHERE r.voted_at>=? AND r.voted_at<? ORDER BY r.voted_at DESC,r.id DESC LIMIT 100', $period);
        $legacy = $this->rows('SELECT COUNT(*) total FROM search_events s LEFT JOIN usage_consultations c ON c.search_event_id=s.id WHERE c.search_event_id IS NULL')[0]['total'];
        return compact('summary','totals','daily','items','searches','history','legacy');
    }
}
