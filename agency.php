<?php
require __DIR__ . '/db.php';

// Agency code from query, default to DHS (070)
$code = isset($_GET['code']) ? $_GET['code'] : '070';

/* 1) Fetch agency info */
$sqlAgency = "
    SELECT id, code, name
    FROM agencies
    WHERE code = ?
    LIMIT 1
";
$stmtA = $mysqli->prepare($sqlAgency);
$stmtA->bind_param('s', $code);
$stmtA->execute();
$aRes = $stmtA->get_result();
$agency = $aRes->fetch_assoc();

if (!$agency) {
    http_response_code(404);
    echo 'Agency not found.';
    exit;
}
$agencyId = (int)$agency['id'];

/* 2) Get subagencies under this agency */
$sqlSubs = "
    SELECT id, code, name
    FROM subagencies
    WHERE agency_id = ?
";
$stmtSubs = $mysqli->prepare($sqlSubs);
$stmtSubs->bind_param('i', $agencyId);
$stmtSubs->execute();
$subsRes = $stmtSubs->get_result();
$subagencies = $subsRes->fetch_all(MYSQLI_ASSOC);

/* 3) For now, compute DHS summary by using ICE as the only component */
$iceSub = null;
foreach ($subagencies as $s) {
    if ($s['code'] === '7012') {
        $iceSub = $s;
        break;
    }
}

$latestOutlays = null;
$totalContracts = 0;
$avgPressureDHS = null;

if ($iceSub) {
    $iceId = (int)$iceSub['id'];

    $sqlLatest = "
        SELECT month, est_obligations_usd, pressure_score
        FROM monthly_outlays
        WHERE subagency_id = ?
        ORDER BY month DESC
        LIMIT 1
    ";
    $stmtL = $mysqli->prepare($sqlLatest);
    $stmtL->bind_param('i', $iceId);
    $stmtL->execute();
    $lRes = $stmtL->get_result();
    $latestOutlays = $lRes->fetch_assoc();

    $sqlCnt = "
        SELECT COUNT(*) AS cnt
        FROM contracts
        WHERE subagency_id = ?
    ";
    $stmtC = $mysqli->prepare($sqlCnt);
    $stmtC->bind_param('i', $iceId);
    $stmtC->execute();
    $cRes = $stmtC->get_result();
    $cRow = $cRes->fetch_assoc();
    $totalContracts = $cRow ? (int)$cRow['cnt'] : 0;

    $sqlAvg = "
        SELECT AVG(pressure_score) AS avg_pressure
        FROM monthly_outlays
        WHERE subagency_id = ?
    ";
    $stmtAvg = $mysqli->prepare($sqlAvg);
    $stmtAvg->bind_param('i', $iceId);
    $stmtAvg->execute();
    $avgRes = $stmtAvg->get_result();
    $avgRow = $avgRes->fetch_assoc();
    $avgPressureDHS = $avgRow ? (float)$avgRow['avg_pressure'] : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($agency['name']); ?> – CivicTelemetry</title>
    <style>
            .top-nav {
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:18px;
        }
        .top-nav .brand a {
            font-weight:700;
            font-size:1.2rem;
            color:#222;
            text-decoration:none;
        }
        .top-nav .links a {
            margin-left:14px;
            font-size:0.9rem;
        }

        body { font-family: Arial, sans-serif; margin:40px; background:#f8f9fb; color:#222; }
        .layout { max-width: 1100px; margin: 0 auto; }
        h1 { margin-bottom:0; }
        h3 { margin-top:5px; color:#666; }
        a { color:#1565c0; text-decoration:none; }
        .summary { display:flex; gap:18px; margin-top:22px; flex-wrap:wrap; }
        .card {
            flex:1 1 260px;
            background:#fff;
            padding:18px;
            border-radius:10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.06);
        }
        .card h4 { margin:0 0 4px 0; font-size:0.9rem; text-transform:uppercase; color:#777; }
        .value { font-size:1.3rem; font-weight:600; }
        .pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:0.75rem; background:#e3f2fd; color:#1565c0; }
        .section {
            background:#fff;
            padding:18px;
            border-radius:10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.06);
            margin-top:24px;
        }
        .sub-grid { display:flex; flex-wrap:wrap; gap:16px; margin-top:10px; }
        .sub-card {
            flex:1 1 260px;
            background:#fafbff;
            border-radius:8px;
            padding:14px;
            border:1px solid #dde2f2;
        }
    </style>
</head>
<body>
<div class="layout">
    <div class="layout">
    <div class="top-nav">
        <div class="brand">
            <a href="index.php">CivicTelemetry</a>
        </div>
        <div class="links">
            <a href="index.php">Dashboard</a>
            <a href="agency.php?code=070"><strong>DHS</strong></a>
            <a href="scales.php">SCALES</a>
            <a href="methods.php">Methods</a>
        </div>

    </div>

    <h1><?php echo htmlspecialchars($agency['name']); ?></h1>

    <h3>Agency Code <?php echo htmlspecialchars($agency['code']); ?></h3>

    <div class="summary">
        <div class="card">
            <h4>Latest ICE Obligations (proxy for DHS)</h4>
            <?php if ($latestOutlays): ?>
                <div class="value">
                    $<?php echo number_format((float)$latestOutlays['est_obligations_usd'], 0); ?>
                </div>
                <div>Month: <?php echo htmlspecialchars(date('Y-m', strtotime($latestOutlays['month']))); ?></div>
            <?php else: ?>
                <div>No data yet.</div>
            <?php endif; ?>
        </div>
        <div class="card">
            <h4>Average ICE Pressure (12 mo)</h4>
            <div class="value">
                <?php echo $avgPressureDHS !== null ? number_format($avgPressureDHS, 1) . ' / 5' : 'N/A'; ?>
            </div>
            <div>For now, DHS pressure is approximated from ICE telemetry only.</div>
        </div>
        <div class="card">
            <h4>Tracked Contracts (ICE)</h4>
            <div class="value"><?php echo (int)$totalContracts; ?></div>
            <div>Contracts currently in the CivicTelemetry view for this agency.</div>
        </div>
    </div>

    <div class="section">
        <h2>Components under <?php echo htmlspecialchars($agency['name']); ?></h2>
        <?php if (!empty($subagencies)): ?>
            <div class="sub-grid">
                <?php foreach ($subagencies as $s): ?>
                    <div class="sub-card">
                        <div style="font-weight:600;">
                            <?php echo htmlspecialchars($s['name']); ?>
                        </div>
                        <div style="font-size:0.85rem;color:#666;">
                            Code <?php echo htmlspecialchars($s['code']); ?>
                        </div>
                        <div style="margin-top:8px;">
                            <?php if ($s['code'] === '7012'): ?>
                                <a href="subagency.php?code=7012">View ICE telemetry →</a>
                            <?php else: ?>
                                <span style="font-size:0.85rem;color:#999;">Placeholder – telemetry coming soon.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No components registered for this agency yet.</p>
        <?php endif; ?>
    </div>
    <div class="section">
        <h2>Data Downloads (DHS – current CivicTelemetry view)</h2>
        <p style="font-size:0.9rem;color:#555;">
            These downloads reflect the current CivicTelemetry dataset for DHS, which in this alpha is focused
            on U.S. Immigration and Customs Enforcement (ICE). As additional components are added, this section
            will expand to include cross-component files.
        </p>
        <ul style="font-size:0.9rem;">
            <li>
                <a href="api/ice_monthly_csv.php">
                    ICE monthly outlays &amp; pressure (CSV)
                </a>
            </li>
            <li>
                <a href="api/ice_contracts_csv.php">
                    ICE contracts &amp; vendors (CSV)
                </a>
            </li>
        </ul>
    </div>

    <p style="margin-top:28px;font-size:0.8rem;color:#777;">
        In this alpha, DHS analytics are driven primarily by ICE data. As CivicTelemetry adds CBP, USCIS,
        FEMA, and other components, the DHS view will evolve into a true cross-component observatory.
    </p>
    <hr style="margin-top:32px;border:none;border-top:1px solid #e0e0e0;">
    <p style="margin-top:12px;font-size:0.8rem;color:#777;text-align:center;">
        CivicTelemetry.org · Independent civic observatory · Not affiliated with DHS, ICE, or the U.S. Government.
    </p>

</div>
</body>
</html>
