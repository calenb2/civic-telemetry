<?php
header('Content-Type: application/json');

require __DIR__ . '/../db.php';

// ICE code
$subCode = '7012';

$sql = "
    SELECT 
        DATE_FORMAT(month, '%Y-%m') AS month,
        est_obligations_usd,
        pressure_score,
        notes
    FROM monthly_outlays
    WHERE subagency_id = (SELECT id FROM subagencies WHERE code = ?)
    ORDER BY month
";

$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $subCode);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
