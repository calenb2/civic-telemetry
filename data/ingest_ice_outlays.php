<?php
// ingest_ice_outlays.php
//
// Simple demo: call a stable FiscalData endpoint and print a few lines.
// This script does NOT write anything to the database.
// It's just a "sanity check" that CLI + curl + JSON work.

echo "Calling Treasury FiscalData (debt_to_penny)...\n";

$endpoint = 'https://api.fiscaldata.treasury.gov/services/api/fiscal_service/v2/accounting/od/debt_to_penny';
$params   = '?sort=-record_date&page[size]=5';

$url = $endpoint . $params;

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
if ($response === false) {
    echo "Curl error: " . curl_error($ch) . "\n";
    exit(1);
}
curl_close($ch);

$data = json_decode($response, true);
if (!isset($data['data']) || !is_array($data['data'])) {
    echo "Unexpected response:\n";
    echo substr($response, 0, 500) . "\n";
    exit(1);
}

$rows = $data['data'];
echo "Received " . count($rows) . " rows from FiscalData (debt_to_penny).\n\n";

foreach ($rows as $r) {
    $date = $r['record_date'] ?? null;
    $debt = $r['tot_pub_debt_out_amt'] ?? null;
    if (!$date || $debt === null) {
        continue;
    }

    echo sprintf(
        "Record date: %s  |  Total public debt outstanding: %s\n",
        $date,
        $debt
    );
}

echo "\nETL demo complete. No database writes performed.\n";
