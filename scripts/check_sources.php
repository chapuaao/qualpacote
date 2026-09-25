<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

use QualPacote\Database;

function console_write(string $message, bool $error = false): void
{
    $stream = fopen($error ? 'php://stderr' : 'php://stdout', 'wb');
    if ($stream) {
        fwrite($stream, $message);
        fclose($stream);
    }
}

if (!function_exists('curl_init')) {
    console_write("A extensão cURL do PHP é necessária para verificar fontes.\n", true);
    exit(1);
}

$db = Database::connection();
$urls = $db->query("
    SELECT DISTINCT pv.source_url
    FROM plans p
    INNER JOIN plan_versions pv ON pv.id = (
        SELECT pv2.id FROM plan_versions pv2
        WHERE pv2.plan_id = p.id
        ORDER BY pv2.version_no DESC, pv2.id DESC LIMIT 1
    )
    WHERE p.active = 1 AND p.published = 1 AND pv.source_url IS NOT NULL AND pv.source_url <> ''
")->fetchAll(PDO::FETCH_COLUMN);

try {
    $hasTariffs = (bool) $db->query("SHOW TABLES LIKE 'tariffs'")->fetchColumn();
    if ($hasTariffs) {
        $tariffUrls = $db->query("
            SELECT DISTINCT tv.source_url
            FROM tariffs t
            INNER JOIN tariff_versions tv ON tv.id = (
                SELECT tv2.id FROM tariff_versions tv2
                WHERE tv2.tariff_id = t.id
                ORDER BY tv2.version_no DESC, tv2.id DESC LIMIT 1
            )
            WHERE t.active = 1 AND t.published = 1 AND tv.source_url IS NOT NULL AND tv.source_url <> ''
        ")->fetchAll(PDO::FETCH_COLUMN);
        $urls = array_values(array_unique(array_merge($urls, $tariffUrls)));
    }
} catch (Throwable) {
    $hasTariffs = false;
}

$insert = $db->prepare('INSERT INTO source_checks (source_url,http_status,content_hash,changed,error_message,checked_at) VALUES (?,?,?,?,?,NOW())');

foreach ($urls as $url) {
    $url = (string) $url;
    if (!preg_match('#^https?://#i', $url)) continue;

    $previous = $db->prepare('SELECT content_hash,changed,checked_at FROM source_checks WHERE source_url=? AND content_hash IS NOT NULL ORDER BY id DESC LIMIT 1');
    $previous->execute([$url]);
    $previousRow = $previous->fetch(PDO::FETCH_ASSOC) ?: null;
    $previousHash = $previousRow['content_hash'] ?? null;

    $verifiedDates = [];
    $verifiedPlan = $db->prepare('SELECT MAX(source_checked_at) FROM plan_versions WHERE source_url=?');
    $verifiedPlan->execute([$url]);
    if ($date = $verifiedPlan->fetchColumn()) $verifiedDates[] = (string) $date;
    if ($hasTariffs) {
        $verifiedTariff = $db->prepare('SELECT MAX(source_checked_at) FROM tariff_versions WHERE source_url=?');
        $verifiedTariff->execute([$url]);
        if ($date = $verifiedTariff->fetchColumn()) $verifiedDates[] = (string) $date;
    }
    rsort($verifiedDates);
    $catalogCheckedAt = $verifiedDates[0] ?? null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'QualPacote/1.1 source-check (+qualpacote.poligest.ao)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 400) {
        $insert->execute([$url,$status ?: null,null,0,$error ?: 'Resposta HTTP inválida']);
        console_write("[ERRO] {$status} {$url}\n", true);
        continue;
    }

    $hash = hash('sha256', (string) $body);
    $contentChanged = $previousHash !== null && !hash_equals((string) $previousHash, $hash);
    $unreviewedChange = false;
    if (!$contentChanged && $previousRow && (int) $previousRow['changed'] === 1) {
        $changeDate = substr((string) $previousRow['checked_at'], 0, 10);
        $catalogDate = $catalogCheckedAt ? substr($catalogCheckedAt, 0, 10) : null;
        $unreviewedChange = $catalogDate === null || $catalogDate < $changeDate;
    }

    $changed = $contentChanged || $unreviewedChange;
    $insert->execute([$url,$status,$hash,$changed ? 1 : 0,null]);
    console_write(sprintf("[%s] %s\n", $changed ? 'ALTEROU' : 'OK', $url));
}
