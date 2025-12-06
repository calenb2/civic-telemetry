<?php
header('Content-Type: application/json');

require __DIR__ . '/../db.php';

// Get subagency code from query; default to ICE (7012) for safety
$code = isset($_GET['code']) ? $_GET['code'] : '7012';

// Look up the subagency_id
$sqlSub = "
    SELECT id
    FROM subagencies
    WHERE code = ?
    LIMIT 1
";
$stmtSub = $mysqli->prepare($sqlSub);
$stmtSub->bind_param('s', $code);
$stmtSub->execute();
$resSub = $stmtSub->get_result();
$rowSub = $resSub->fetch_assoc();

if (!$rowSub) {
    echo json_encode([]);
    exit;
}

$subId = (int)$rowSub['id'];

// Fetch monthly outlays for this subagency
$sql = "
    SELECT 
        DATE_FORMAT(month, '%Y-%m') AS month,
        est_obligations_usd,
        pressure_score,
        notes
    FROM monthly_outlays
    WHERE subagency_id = ?
    ORDER BY month
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $subId);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
