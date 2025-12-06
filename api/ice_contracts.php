<?php
header('Content-Type: application/json');

require __DIR__ . '/../db.php';

$subCode = '7012';

$sql = "
    SELECT 
        c.id,
        v.name AS vendor_name,
        c.award_title,
        c.category,
        c.action_date,
        c.amount_obligated_usd,
        c.competition_type,
        c.recorded_bidders
    FROM contracts c
    JOIN vendors v ON c.vendor_id = v.id
    WHERE c.subagency_id = (SELECT id FROM subagencies WHERE code = ?)
    ORDER BY c.action_date DESC
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
