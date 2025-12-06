<?php
// ingest_ice_admin_mts.php
//
// Goal: Maintain ICE monthly_outlays from the start of the current administration onward.
// - ADMIN_START_DATE defines the earliest date we ingest.
// - For each month in that window, we INSERT or UPDATE est_obligations_usd.
// - We do NOT touch pressure_score or notes.
//
// NOTE: For now, this script uses MTS Table 5 rows as a federal outlay example.
//       DHS-specific filtering will be refined once the endpoint documentation is integrated.
//
// Run manually from CLI:
//   php ingest_ice_admin_mts.php

require __DIR__ . '/../db.php';

// 1) Configuration
$ADMIN_START_DATE  = '2025-01-20';  // first day of the administration
$ADMIN_START_MONTH = '2025-01-01';  // first month to manage

$SCRIPT_NAME    = 'ingest_ice_admin_mts.php';
$AGENCY_CODE    = null;     // unknown at this level for now
$SUBAGENCY_CODE = '7012';   // ICE

// 2) Start log entry
$tsStarted = date('Y-m-d H:i:s');

$logStmt = $mysqli->prepare("
    INSERT INTO ingest_log (ts_started, script_name, agency_code, subagency_code, admin_start_date, status)
    VALUES (?, ?, ?, ?, ?, 'SUCCESS')
");
$logStmt->bind_param('sssss', $tsStarted, $SCRIPT_NAME, $AGENCY_CODE, $SUBAGENCY_CODE, $ADMIN_START_DATE);
$logStmt->execute();
$logId = $logStmt->insert_id;

function updateLog($mysqli, $logId, $status, $rowsAffected, $message = null) {
    $tsFinished = date('Y-m-d H:i:s');
    $stmt = $mysqli->prepare("
        UPDATE ingest_log
        SET ts_finished = ?, status = ?, rows_affected = ?, message = ?
        WHERE id = ?
    ");
    $stmt->bind_param('ssisi', $tsFinished, $status, $rowsAffected, $message, $logId);
    $stmt->execute();
}

// 3) Look up ICE subagency_id
$subStmt = $mysqli->prepare("SELECT id FROM subagencies WHERE code = ? LIMIT 1");
$subStmt->bind_param('s', $SUBAGENCY_CODE);
$subStmt->execute();
$subRes = $subStmt->get_result();
$subRow = $subRes->fetch_assoc();

if (!$subRow) {
    $msg = "Could not find ICE subagency (code {$SUBAGENCY_CODE}).";
    echo "ERROR: {$msg}\n";
    updateLog($mysqli, $logId, 'ERROR', 0, $msg);
    exit(1);
}
$iceId = (int)$subRow['id'];

// 4) Call FiscalData MTS endpoint
$endpoint = 'https://api.fiscaldata.treasury.gov/services/api/fiscal_service/v1/accounting/mts/mts_table_5';
$params   = sprintf(
    '?filter=record_date:gte:%s&sort=record_date&page[size]=100',
    $ADMIN_START_MONTH
);

$url = $endpoint . $params;

echo "Calling Treasury FiscalData MTS (Table 5) from {$ADMIN_START_MONTH}...\n";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
if ($response === false) {
    $msg = "Treasury curl error: " . curl_error($ch);
    echo $msg . "\n";
    updateLog($mysqli, $logId, 'ERROR', 0, $msg);
    exit(1);
}
curl_close($ch);

// 5) Decode JSON
$data = json_decode($response, true);
if (!isset($data['data']) || !is_array($data['data'])) {
    $snippet = substr($response, 0, 500);
    $msg = "Unexpected Treasury response: " . $snippet;
    echo $msg . "\n";
    updateLog($mysqli, $logId, 'ERROR', 0, $msg);
    exit(1);
}

$rows = $data['data'];
echo "Received " . count($rows) . " MTS rows (admin window).\n";

// Optional debug: comment out once comfortable
if (!empty($rows)) {
    echo "First row keys:\n";
    print_r(array_keys($rows[0]));
    echo "\nFirst row sample:\n";
    print_r($rows[0]);
    echo "\n";
}

// 6) Prepare UPSERT-style query for monthly_outlays
// We update only est_obligations_usd; pressure_score and notes remain intact.
$upsertSql = "
    INSERT INTO monthly_outlays (subagency_id, month, est_obligations_usd)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE est_obligations_usd = VALUES(est_obligations_usd)
";
$upsertStmt = $mysqli->prepare($upsertSql);

$affected = 0;

foreach ($rows as $r) {
    // Derive month from record_date (YYYY-MM-DD -> YYYY-MM-01)
    $recordDate = $r['record_date'] ?? null;
    if (!$recordDate) {
        continue;
    }

    $monthPart = substr($recordDate, 0, 7); // "YYYY-MM"
    $monthDate = $monthPart . '-01';

    // Only process months in or after ADMIN_START_MONTH
    if ($monthDate < $ADMIN_START_MONTH) {
        continue;
    }

    // Use current fiscal year-to-date net outlays as our proxy
    $ytdOutlays = $r['current_fytd_net_outly_amt'] ?? null;
    if ($ytdOutlays === null) {
        continue;
    }

    $amount = (float)$ytdOutlays;

    // DRY RUN: show what would be upserted, but do not write to DB
    echo "[DRY RUN] Would upsert ICE month {$monthDate} with FYTD net outlays {$amount}...\n";

    // Commented out to prevent writes in this alpha phase:
    // $upsertStmt->bind_param('isd', $iceId, $monthDate, $amount);
    // $upsertStmt->execute();

    // if ($upsertStmt->affected_rows > 0) {
    //     $affected += $upsertStmt->affected_rows;
    //     echo "  -> Row inserted or obligations updated.\n";
    // } else {
    //     echo "  -> No change (same value already stored).\n";
    // }
}


echo "\nAdmin-window ingest DRY RUN complete. No changes were written to monthly_outlays.\n";
echo "Pressure scores and notes remain unchanged.\n";

updateLog($mysqli, $logId, 'SUCCESS', 0, 'Admin-window ICE ingest DRY RUN completed (no DB writes).');
