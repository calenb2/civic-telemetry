<?php
require __DIR__ . '/db.php';

/**
 * Route B: Cross-Agency Enforcement Observatory
 * 
 * We will compare a small set of enforcement-focused subagencies:
 * - ICE (7012)
 * - DOJ-ENF (DOJ – Enforcement & Investigations)
 * 
 * Each row will show:
 * - Parent agency
 * - Subagency name + code
 * - Latest month obligations
 * - Average pressure
 * - Tracked contracts
 * - Selected risk dimensions (latest month)
 */

$focusCodes = ['7012', 'DOJ-ENF'];  // You can add more later (CBP, USCIS, etc.)

$subagencies = [];

// Dimensions we care about in the cross-agency view
$riskDimensionsOfInterest = [
    'SurveillanceExpansion',
    'PrivateCarceralDependence',
    'BudgetOverrunReliance',
    'CivilLibertiesStress'
];

foreach ($focusCodes as $code) {
    // 1) Basic info: subagency + agency
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
    $info = $infoRes->fetch_assoc();

    if (!$info) {
        continue; // skip if somehow not found
    }

    $subId = (int)$info['sub_id'];

    // 2) Latest obligations & month
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
    $latest = $lRes->fetch_assoc();

    $latestMonth = $latest ? $latest['month'] : null;
    $latestObligations = $latest ? (float)$latest['est_obligations_usd'] : null;

    // 3) Average pressure
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

    // 4) Contract count
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

    // 5) Risk scores: latest month and specific dimensions
    $sqlRiskMonth = "
        SELECT MAX(month) AS latest_month
        FROM risk_scores
        WHERE subagency_id = ?
    ";
    $stmtRiskMonth = $mysqli->prepare($sqlRiskMonth);
    $stmtRiskMonth->bind_param('i', $subId);
    $stmtRiskMonth->execute();
    $rmRes = $stmtRiskMonth->get_result();
    $rmRow = $rmRes->fetch_assoc();
    $latestRiskMonth = $rmRow ? $rmRow['latest_month'] : null;

    $riskMap = [];
    if ($latestRiskMonth) {
        $sqlRisk = "
            SELECT dimension, score, rationale
            FROM risk_scores
            WHERE subagency_id = ?
              AND month = ?
        ";
        $stmtRisk = $mysqli->prepare($sqlRisk);
        $stmtRisk->bind_param('is', $subId, $latestRiskMonth);
        $stmtRisk->execute();
        $rRes = $stmtRisk->get_result();
        while ($r = $rRes->fetch_assoc()) {
            $riskMap[$r['dimension']] = [
                'score' => (int)$r['score'],
                'rationale' => $r['rationale']
            ];
        }
    }

    $subagencies[] = [
        'agency_name'      => $info['agency_name'],
        'agency_code'      => $info['agency_code'],
        'sub_name'         => $info['sub_name'],
        'sub_code'         => $info['sub_code'],
        'latest_month'     => $latestMonth,
        'latest_oblig'     => $latestObligations,
        'avg_pressure'     => $avgPressure,
        'contract_count'   => $contractCount,
        'risk_month'       => $latestRiskMonth,
        'risk_scores'      => $riskMap
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>CivicTelemetry – Enforcement Observatory (alpha)</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background:#f8f9fb; color:#222; }
        .layout { max-width: 1100px; margin: 0 auto; }
        h1 { margin-bottom:0; }
        h3 { margin-top:5px; color:#666; }
        a { color:#1565c0; text-decoration:none; }

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

        .section {
            background:#fff;
            padding:18px 22px;
            border-radius:10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.06);
            margin-top:24px;
        }

        table { width:100%; border-collapse:collapse; margin-top:10px; font-size:0.9rem; }
        th, td { padding:8px 6px; border-bottom:1px solid #e2e4ea; text-align:left; vertical-align:top; }
        th { background:#f0f2f7; }
        .num { text-align:right; }
        .pill {
            display:inline-block;
            padding:2px 6px;
            border-radius:999px;
            font-size:0.75rem;
            background:#e3f2fd;
            color:#1565c0;
        }
        .risk-dim {
            font-weight:600;
        }
        .risk-score {
            font-size:0.85rem;
            color:#444;
        }
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

    <h1>Enforcement Observatory (alpha)</h1>
    <h3>Cross-agency telemetry and risk snapshot</h3>

    <div class="section">
        <p style="font-size:0.95rem; color:#555;">
            This view compares selected enforcement-focused components across agencies, using
            monthly obligations, pressure scores, contract counts, and a small set of institutional
            risk lenses. Seeded values are used for DOJ-ENF; ICE values include richer events and contracts.
        </p>

        <table>
            <tr>
                <th>Agency</th>
                <th>Subagency</th>
                <th>Latest Month</th>
                <th class="num">Latest Obligations (USD)</th>
                <th class="num">Average Pressure</th>
                <th class="num">Tracked Contracts</th>
                <th>Surveillance Expansion</th>
                <th>Private Carceral Dependence</th>
                <th>Budget Overrun Reliance</th>
                <th>Civil Liberties Stress</th>
            </tr>
            <?php foreach ($subagencies as $s): ?>
                <?php
                    $risk = $s['risk_scores'];
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
                        <span style="font-size:0.8rem;color:#777;">Code <?php echo htmlspecialchars($s['agency_code']); ?></span>
                    </td>
                    <td>
                        <a href="subagency.php?code=<?php echo urlencode($s['sub_code']); ?>">
                            <?php echo htmlspecialchars($s['sub_name']); ?>
                        </a><br>
                        <span style="font-size:0.8rem;color:#777;">Code <?php echo htmlspecialchars($s['sub_code']); ?></span>
                    </td>
                    <td><?php echo $fmtMonth; ?></td>
                    <td class="num"><?php echo $fmtOblig; ?></td>
                    <td class="num"><?php echo $fmtPressure; ?></td>
                    <td class="num"><?php echo (int)$s['contract_count']; ?></td>

                    <?php foreach ($riskDimensionsOfInterest as $dim): ?>
    <?php if (isset($risk[$dim])): ?>
        <td class="num">
            <span class="risk-score">
                <?php echo (int)$risk[$dim]['score']; ?> / 5
            </span>
        </td>
    <?php else: ?>
        <td class="num" style="font-size:0.8rem;color:#aaa;">–</td>
    <?php endif; ?>
<?php endforeach; ?>

                </tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top:8px;font-size:0.8rem;color:#777;">
    Risk scores are on a 1–5 scale (1 = low, 5 = very high) and are interpretive metrics created by CivicTelemetry.
</p>

    </div>

    <hr style="margin-top:32px;border:none;border-top:1px solid #e0e0e0;">
    <p style="margin-top:12px;font-size:0.8rem;color:#777;text-align:center;">
        CivicTelemetry.org · Independent civic observatory · Not affiliated with DHS, DOJ, ICE, or the U.S. Government.
    </p>
</div>
</body>
</html>
