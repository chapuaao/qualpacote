<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
use QualPacote\Auth;
use QualPacote\Database;
if (!Auth::user()) { http_response_code(403); exit; }
$stmt = Database::connection()->prepare('SELECT evidence_blob,evidence_type FROM catalog_snapshots WHERE id=?');
$stmt->execute([(int) ($_GET['id'] ?? 0)]); $snapshot = $stmt->fetch();
if (!$snapshot || !$snapshot['evidence_blob'] || !in_array($snapshot['evidence_type'], ['image/png','image/jpeg','application/pdf'],true)) { http_response_code(404); exit; }
header('Cache-Control: private, no-store');
header('Content-Type: ' . $snapshot['evidence_type']);
header('Content-Disposition: inline');
header('Content-Length: ' . strlen($snapshot['evidence_blob']));
echo $snapshot['evidence_blob'];
