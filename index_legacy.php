<?php
require __DIR__ . '/db.php';

$iceCode = '7012';

/* Fetch ICE info */
$sqlSub = "
    SELECT s.id AS sub_id, s.name AS sub_name, a.name AS agency_name
    FROM subagencies s
    JOIN agencies a ON s.agency_id = a.id
    WHERE s.code = ?
    LIMIT 1
";
$stmtSub = $mysqli->prepare($sqlSub);
$stmtSub->bind_param('s', $iceCode);
$stmtSub->execute();
$subRes = $stmtSub->get_result();
$iceInfo = $subRes->fetch_assoc();
$iceId = $iceInfo ? (int)$iceInfo['sub_id'] : null;

$latest = $avg = $cnt = null;
$latestPressure = null;

if ($iceId) {
    $sqlLatest = "
        SELECT month, est_obligations_usd, pressure_score
        FROM monthly_outlays
        WHERE subagency_id = ?
        ORDER BY month DESC
        LIMIT 1
    ";
    $stmtLatest = $mysqli->prepare($sqlLatest);
    $stmtLatest->bind_param('i', $iceId);
    $stmtLatest->execute();
    $latestRes = $stmtLatest->get_result();
    $latest = $latestRes->fetch_assoc();
    $latestPressure = $latest ? (int)$latest['pressure_score'] : null;

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
    $avg = $avgRow ? (float)$avgRow['avg_pressure'] : null;

    $sqlCnt = "
        SELECT COUNT(*) AS cnt
        FROM contracts
        WHERE subagency_id = ?
    ";
    $stmtCnt = $mysqli->prepare($sqlCnt);
    $stmtCnt->bind_param('i', $iceId);
    $stmtCnt->execute();
    $cntRes = $stmtCnt->get_result();
    $cntRow = $cntRes->fetch_assoc();
    $cnt = $cntRow ? (int)$cntRow['cnt'] : 0;

    // Latest 3 contracts
    $sqlLatestContracts = "
        SELECT c.action_date,
               v.name AS vendor_name,
               c.award_title,
               c.category,
               c.amount_obligated_usd
        FROM contracts c
        JOIN vendors v ON c.vendor_id = v.id
        WHERE c.subagency_id = ?
        ORDER BY c.action_date DESC
        LIMIT 3
    ";
    $stmtLC = $mysqli->prepare($sqlLatestContracts);
    $stmtLC->bind_param('i', $iceId);
    $stmtLC->execute();
    $lcRes = $stmtLC->get_result();
    $latestContracts = $lcRes->fetch_all(MYSQLI_ASSOC);
} else {
    $latestContracts = [];
}

/* Derive a simple qualitative signal from latestPressure */
$signalLabel = 'Unknown';
$signalColor = '#9e9e9e';
if ($latestPressure !== null) {
    if ($latestPressure <= 2) {
        $signalLabel = 'Low';
        $signalColor = '#43a047';
    } elseif ($latestPressure <= 3) {
        $signalLabel = 'Elevated';
        $signalColor = '#f9a825';
    } elseif ($latestPressure <= 4) {
        $signalLabel = 'High';
        $signalColor = '#fb8c00';
    } else {
        $signalLabel = 'Very High';
        $signalColor = '#e53935';
    }
}

// ------------------------------------------
// Federal Signals: aggregate snapshot for ICE + DOJ-ENF
// ------------------------------------------

$signals = [];
$signalCodes = ['7012', 'DOJ-ENF'];

foreach ($signalCodes as $code) {
    // Basic info: subagency + agency
    $sqlInfo = "
        SELECT s.id AS sub_id, s.name AS sub_name, s.code AS sub_code,
               a.name AS agency_name, a.code AS agency_code
        FROM subagencies s
        JOIN agencies a ON s.agency_id = a.id
        WHERE s.code = ?
        LIMIT 1
    ";
    $stmtInfo = $mysqli->prepare($sqlInfo);
    $stmtInfo->bind_param('s', $code);
    $stmtInfo->execute();
    $infoRes = $stmtInfo->get_result();
    $infoRow = $infoRes->fetch_assoc();

    if (!$infoRow) {
        continue;
    }

    $subId    = (int)$infoRow['sub_id'];
    $subName  = $infoRow['sub_name'];
    $subCode  = $infoRow['sub_code'];
    $agency   = $infoRow['agency_name'];
    $agencyCode = $infoRow['agency_code'];

    // Latest obligations
    $sqlLatest = "
        SELECT month, est_obligations_usd
        FROM monthly_outlays
        WHERE subagency_id = ?
        ORDER BY month DESC
        LIMIT 1
    ";
    $stmtLatest = $mysqli->prepare($sqlLatest);
    $stmtLatest->bind_param('i', $subId);
    $stmtLatest->execute();
    $lRes = $stmtLatest->get_result();
    $latestRow = $lRes->fetch_assoc();

    $latestMonth = $latestRow ? $latestRow['month'] : null;
    $latestOblig = $latestRow ? (float)$latestRow['est_obligations_usd'] : null;

    // Average pressure
    $sqlAvg = "
        SELECT AVG(pressure_score) AS avg_pressure
        FROM monthly_outlays
        WHERE subagency_id = ?
    ";
    $stmtAvg = $mysqli->prepare($sqlAvg);
    $stmtAvg->bind_param('i', $subId);
    $stmtAvg->execute();
    $avgRes = $stmtAvg->get_result();
    $avgRow = $avgRes->fetch_assoc();
    $avgPressure = $avgRow ? (float)$avgRow['avg_pressure'] : null;

    // Contract count
    $sqlCnt = "
        SELECT COUNT(*) AS cnt
        FROM contracts
        WHERE subagency_id = ?
    ";
    $stmtCnt = $mysqli->prepare($sqlCnt);
    $stmtCnt->bind_param('i', $subId);
    $stmtCnt->execute();
    $cntRes = $stmtCnt->get_result();
    $cntRow = $cntRes->fetch_assoc();
    $contractCount = $cntRow ? (int)$cntRow['cnt'] : 0;

    $signals[] = [
        'agency_name'    => $agency,
        'agency_code'    => $agencyCode,
        'sub_name'       => $subName,
        'sub_code'       => $subCode,
        'latest_month'   => $latestMonth,
        'latest_oblig'   => $latestOblig,
        'avg_pressure'   => $avgPressure,
        'contract_count' => $contractCount
    ];
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CivicTelemetry – Federal Spend Pulse (alpha)</title>
    <style>
    
            .section {
            background:#fff;
            padding:18px 22px;
            border-radius:10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.06);
            margin-top:24px;
        }
        table.federal-signals {
            width:100%;
            border-collapse:collapse;
            margin-top:10px;
            font-size:0.9rem;
        }
        table.federal-signals th,
        table.federal-signals td {
            padding:8px 6px;
            border-bottom:1px solid #e2e4ea;
            text-align:left;
            vertical-align:top;
        }
        table.federal-signals th {
            background:#f0f2f7;
        }
        .signals-num {
            text-align:right;
            white-space:nowrap;
        }

    
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
        .grid { display:flex; flex-wrap:wrap; gap:18px; margin-top:24px; }
        .card {
            flex:1 1 260px;
            background:#fff;
            padding:18px;
            border-radius:10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.06);
        }
        .card h4 { margin:0 0 4px 0; font-size:0.9rem; text-transform:uppercase; color:#777; }
        .card-title { font-size:1.1rem; font-weight:600; margin-bottom:8px; }
        .value { font-size:1.4rem; font-weight:600; }
        .pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:0.75rem; background:#e3f2fd; color:#1565c0; }
        .section {
            background:#fff;
            padding:18px;
            border-radius:10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.06);
            margin-top:24px;
        }
        table { width:100%; border-collapse: collapse; margin-top:10px; font-size:0.9rem; }
        th, td { padding:8px 6px; border-bottom:1px solid #e2e4ea; text-align:left; }
        th { background:#f0f2f7; }
        .tag { display:inline-block; padding:2px 6px; border-radius:4px; font-size:0.75rem; background:#e3f2fd; color:#1565c0; }
    </style>
</head>
<body>
<div class="layout">
        <div class="top-nav">
        <div class="brand">
            <a href="index.php">CivicTelemetry</a>
        </div>
        <div class="links">
            <a href="index.php">Dashboard</a>
            <a href="agency.php?code=070">DHS</a>
            <a href="methods.php">Methods</a>
        </div>
    </div>

    <h1>CivicTelemetry</h1>
    <h3>Federal Spend Pulse (alpha)</h3>


    <p style="font-size:0.85rem;">
        <a href="methods.php">Methods &amp; Data</a>
    </p>

        <p style="max-width:800px;">
        CivicTelemetry is an independent observatory of U.S. federal spending patterns and institutional signals.
        This early alpha focuses on <strong>U.S. Immigration and Customs Enforcement (ICE)</strong> within the Department of Homeland Security.
        You can also view the parent <a href="agency.php?code=070">DHS overview</a>.
    </p>


    <div class="grid">
        <div class="card">
            <div class="card-title">
                ICE – Enforcement & Tech Spend
            </div>
            <?php if ($latest && $iceInfo): ?>
                <p>
                    <strong>Latest month obligations:</strong><br>
                    <span class="value">
                        $<?php echo number_format((float)$latest['est_obligations_usd'], 0); ?>
                    </span>
                    <br>
                    <span class="pill">
                        <?php echo htmlspecialchars(date('Y-m', strtotime($latest['month']))); ?>
                    </span>
                </p>
                <p>
                    <strong>Average pressure (12 mo):</strong>
                    <?php echo $avg !== null ? number_format($avg, 1) . ' / 5' : 'N/A'; ?><br>
                    <strong>Tracked contracts:</strong> <?php echo (int)$cnt; ?>
                </p>
                <p>
                    <a href="subagency.php?code=7012">View ICE drill-down →</a>
                </p>
            <?php else: ?>
                <p>No ICE data available.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-title">ICE Telemetry Signal</div>
            <?php if ($latestPressure !== null): ?>
                <p>
                    <strong>Latest pressure score:</strong><br>
                    <span class="value" style="color:<?php echo $signalColor; ?>">
                        <?php echo $latestPressure; ?> / 5 (<?php echo $signalLabel; ?>)
                    </span>
                </p>
                <p style="font-size:0.85rem;">
                    Higher scores indicate more intense enforcement and spending activity over recent months.
                </p>
            <?php else: ?>
                <p>Signal unavailable.</p>
            <?php endif; ?>
        </div>
<div class="card">
    <div class="card-title">Justice (DOJ)</div>
    <p style="font-size:0.85rem;">
        DOJ Enforcement & Investigations (DOJ-ENF) now includes seeded telemetry, contracts, risk lenses, and timeline events.
        This provides a comparison point to ICE’s enforcement and technology footprint.
    </p>
    <p style="font-size:0.85rem;">
        <a href="subagency.php?code=DOJ-ENF">View DOJ-ENF telemetry →</a>
    </p>
</div>

        <div class="card">
            <div class="card-title">Roadmap</div>
            <ul style="margin-top:4px; padding-left:18px; font-size:0.85rem;">
                <li>Add CBP, USCIS, and FEMA under DHS</li>
                <li>Introduce DoD and DOJ components</li>
                <li>Visualize vendor concentration and competition</li>
                <li>Publish methods & commentary for public review</li>
            </ul>
        </div>
    </div>
<div class="card">
    <div class="card-title">Enforcement Observatory</div>
    <p style="font-size:0.85rem;">
        Compare ICE and DOJ Enforcement &amp; Investigations side-by-side, including obligations, pressure, contracts, and risk lenses.
    </p>
    <p style="font-size:0.85rem;">
        <a href="observatory.php">Open Observatory →</a>
    </p>
</div>

    <div class="section">
        <h2>Latest ICE Contracts in CivicTelemetry</h2>
        <?php if (!empty($latestContracts)): ?>
            <table>
                <tr>
                    <th>Action Date</th>
                    <th>Vendor</th>
                    <th>Award Title</th>
                    <th>Category</th>
                    <th>Amount (USD)</th>
                </tr>
                <?php foreach ($latestContracts as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['action_date']); ?></td>
                        <td><?php echo htmlspecialchars($c['vendor_name']); ?></td>
                        <td><?php echo htmlspecialchars($c['award_title']); ?></td>
                        <td><span class="tag"><?php echo htmlspecialchars($c['category']); ?></span></td>
                        <td>$<?php echo number_format((float)$c['amount_obligated_usd'], 0); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
            <p style="font-size:0.85rem;color:#666;margin-top:8px;">
                This table shows the most recent awards in the CivicTelemetry dataset. See the ICE drill-down page for full context.
            </p>
        <?php else: ?>
            <p>No contracts available yet.</p>
        <?php endif; ?>
    </div>

    <p style="margin-top:28px;font-size:0.8rem;color:#777;">
        This early alpha uses seeded but structurally realistic data. Future iterations will connect directly to Treasury and USAspending.gov
        with transparent methods and versioned datasets.
    </p>
    
        <?php if (!empty($signals)): ?>
    <div class="section">
        <h2>Federal Signals (alpha)</h2>
        <p style="font-size:0.9rem;color:#555;">
            A quick snapshot of enforcement-focused components across agencies. Values are seeded for DOJ-ENF;
            ICE includes a richer set of events and contracts. Pressure scores are interpretive metrics on a 1–5 scale.
        </p>

        <table class="federal-signals">
            <tr>
                <th>Agency</th>
                <th>Subagency</th>
                <th>Latest Month</th>
                <th class="signals-num">Latest Obligations (USD)</th>
                <th class="signals-num">Average Pressure</th>
                <th class="signals-num">Tracked Contracts</th>
            </tr>
            <?php foreach ($signals as $s): ?>
                <?php
                    $fmtMonth = $s['latest_month']
                        ? htmlspecialchars(date('Y-m', strtotime($s['latest_month'])))
                        : 'N/A';
                    $fmtOblig = $s['latest_oblig'] !== null
                        ? '$' . number_format($s['latest_oblig'], 0)
                        : 'N/A';
                    $fmtPressure = $s['avg_pressure'] !== null
                        ? number_format($s['avg_pressure'], 1) . ' / 5'
                        : 'N/A';
                ?>
                <tr>
                    <td>
                        <?php echo htmlspecialchars($s['agency_name']); ?><br>
                        <span style="font-size:0.8rem;color:#777;">
                            Code <?php echo htmlspecialchars($s['agency_code']); ?>
                        </span>
                    </td>
                    <td>
                        <a href="subagency.php?code=<?php echo urlencode($s['sub_code']); ?>">
                            <?php echo htmlspecialchars($s['sub_name']); ?>
                        </a><br>
                        <span style="font-size:0.8rem;color:#777;">
                            Code <?php echo htmlspecialchars($s['sub_code']); ?>
                        </span>
                    </td>
                    <td><?php echo $fmtMonth; ?></td>
                    <td class="signals-num"><?php echo $fmtOblig; ?></td>
                    <td class="signals-num"><?php echo $fmtPressure; ?></td>
                    <td class="signals-num"><?php echo (int)$s['contract_count']; ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>

    
    <hr style="margin-top:32px;border:none;border-top:1px solid #e0e0e0;">
    <p style="margin-top:12px;font-size:0.8rem;color:#777;text-align:center;">
        CivicTelemetry.org · Independent civic observatory · Not affiliated with DHS, ICE, or the U.S. Government.
    </p>
    
</div>
</body>
</html>
