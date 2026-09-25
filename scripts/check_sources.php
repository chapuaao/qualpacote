<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute este script pela linha de comandos.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/app/bootstrap.php';

use QualPacote\Database;

if (!function_exists('curl_init')) {
    fwrite(STDERR, "A extensão cURL do PHP é necessária para verificar fontes.\n");
    exit(1);
}

$db = Database::connection();
$urls = $db->query("
    SELECT DISTINCT pv.source_url
    FROM plans p
    INNER JOIN plan_versions pv ON pv.id = (
        SELECT pv2.id
        FROM plan_versions pv2
        WHERE pv2.plan_id = p.id
        ORDER BY pv2.version_no DESC, pv2.id DESC
        LIMIT 1
    )
    WHERE p.active = 1
      AND p.published = 1
      AND pv.source_url IS NOT NULL
      AND pv.source_url <> ''
")->fetchAll(PDO::FETCH_COLUMN);

$insert = $db->prepare(
    'INSERT INTO source_checks
        (source_url, http_status, content_hash, changed, error_message, checked_at)
     VALUES (?, ?, ?, ?, ?, NOW())'
);

foreach ($urls as $url) {
    $url = (string) $url;
    if (!preg_match('#^https?://#i', $url)) {
        continue;
    }

    $previous = $db->prepare(
        'SELECT content_hash, changed, checked_at FROM source_checks WHERE source_url = ? AND content_hash IS NOT NULL ORDER BY id DESC LIMIT 1'
    );
    $previous->execute([$url]);
    $previousRow = $previous->fetch(PDO::FETCH_ASSOC) ?: null;
    $previousHash = $previousRow['content_hash'] ?? null;

    $verifiedStmt = $db->prepare(
        'SELECT MAX(source_checked_at) FROM plan_versions WHERE source_url = ?'
    );
    $verifiedStmt->execute([$url]);
    $catalogCheckedAt = $verifiedStmt->fetchColumn() ?: null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 4,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_USERAGENT => 'QualPacote/1.0 source-check (+qualpacote.poligest.ao)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($body === false || $status < 200 || $status >= 400) {
        $insert->execute([$url, $status ?: null, null, 0, $error ?: 'Resposta HTTP inválida']);
        fwrite(STDOUT, "[ERRO] {$status} {$url}\n");
        continue;
    }

    $hash = hash('sha256', (string) $body);
    $contentChanged = $previousHash !== null && !hash_equals((string) $previousHash, $hash);
    $unreviewedChange = false;

    if (!$contentChanged && $previousRow && (int) $previousRow['changed'] === 1) {
        $changeDate = substr((string) $previousRow['checked_at'], 0, 10);
        $catalogDate = $catalogCheckedAt ? substr((string) $catalogCheckedAt, 0, 10) : null;
        $unreviewedChange = $catalogDate === null || $catalogDate < $changeDate;
    }

    $changed = $contentChanged || $unreviewedChange;
    $insert->execute([$url, $status, $hash, $changed ? 1 : 0, null]);

    fwrite(
        STDOUT,
        sprintf("[%s] %s\n", $changed ? 'ALTEROU' : 'OK', $url)
    );
}
