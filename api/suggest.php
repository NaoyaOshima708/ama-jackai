<?php
require_once dirname(__DIR__) . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$pdo  = getPDO();
$like = '%' . $q . '%';
$stmt = $pdo->prepare("
    SELECT ticker, company_name, sector
    FROM stock_master
    WHERE is_active = 1
      AND (ticker LIKE :q1 OR company_name LIKE :q2)
    ORDER BY ticker ASC
    LIMIT 10
");
$stmt->execute([':q1' => $like, ':q2' => $like]);
echo json_encode($stmt->fetchAll(), JSON_UNESCAPED_UNICODE);
