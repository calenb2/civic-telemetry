<?php
require __DIR__ . '/db.php';

// Get subagency code from query, default to ICE (7012)
$code = isset($_GET['code']) ? $_GET['code'] : '7012';

/* 1) Fetch subagency + parent agency info */
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
    http_response_code(404);
    echo "Subagency not found.";
    exit;
}
$subId = (int)$info['sub_id'];

/* 2) Summary metrics: latest month, avg pressure, contract count */
$sqlLatest = "
    SELECT month, est_obligations_usd, pressure_score
    FROM monthly_outlays
    WHERE subagency_id = ?
    ORDER BY month DESC
    LIMIT 1
";
$stmtLatest = $mysqli->prepare($sqlLatest);
$stmtLatest->bind_param('i', $subId);
$stmtLatest->execute();
$latestRes = $stmtLatest->get_result();
$latest = $latestRes->fetch_assoc();

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

$sqlCountContracts = "
    SELECT COUNT(*) AS cnt
    FROM contracts
    WHERE subagency_id = ?
";
$stmtCnt = $mysqli->prepare($sqlCountContracts);
$stmtCnt->bind_param('i', $subId);
$stmtCnt->execute();
$cntRes = $stmtCnt->get_result();
$cntRow = $cntRes->fetch_assoc();
$contractCount = $cntRow ? (int)$cntRow['cnt'] : 0;

/* 3) Full monthly outlay series */
$sqlOutlays = "
    SELECT month, est_obligations_usd, pressure_score, notes
    FROM monthly_outlays
    WHERE subagency_id = ?
    ORDER BY month
";
$stmtOut = $mysqli->prepare($sqlOutlays);
$stmtOut->bind_param('i', $subId);
$stmtOut->execute();
$outRes = $stmtOut->get_result();
$outlays = $outRes->fetch_all(MYSQLI_ASSOC);

/* 4) Contracts list */
$sqlContracts = "
    SELECT c.action_date,
           v.name AS vendor_name,
           c.award_title,
           c.category,
           c.amount_obligated_usd,
           c.competition_type,
           c.recorded_bidders
    FROM contracts c
    JOIN vendors v ON c.vendor_id = v.id
    WHERE c.subagency_id = ?
    ORDER BY c.action_date DESC
";
$stmtCon = $mysqli->prepare($sqlContracts);
$stmtCon->bind_param('i', $subId);
$stmtCon->execute();
$conRes = $stmtCon->get_result();
$contracts = $conRes->fetch_all(MYSQLI_ASSOC);

/* 5) Event timeline for this subagency */
$sqlEvents = "
    SELECT event_date, title, description, source_url
    FROM events
    WHERE subagency_id = ?
    ORDER BY event_date
";
$stmtEvt = $mysqli->prepare($sqlEvents);
$stmtEvt->bind_param('i', $subId);
$stmtEvt->execute();
$evtRes = $stmtEvt->get_result();
$events = $evtRes->fetch_all(MYSQLI_ASSOC);

/* 6) Risk scores (latest month for this subagency) */
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

$riskScores = [];
if ($latestRiskMonth) {
    $sqlRisk = "
        SELECT dimension, score, rationale
        FROM risk_scores
        WHERE subagency_id = ?
          AND month = ?
        ORDER BY dimension
    ";
    $stmtRisk = $mysqli->prepare($sqlRisk);
    $stmtRisk->bind_param('is', $subId, $latestRiskMonth);
    $stmtRisk->execute();
    $rRes = $stmtRisk->get_result();
    $riskScores = $rRes->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($info['sub_name']); ?> – CivicTelemetry</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f8f9fb; color: #222; }
        .layout { max-width: 1100px; margin: 0 auto; }
        h1 { margin-bottom: 0; }
        h3 { margin-top: 4px; color: #666; }
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

        .summary {
            display:flex;
            gap:16px;
            margin-top:20px;
            flex-wrap:wrap;
        }
        .card {
            flex:1 1 260px;
            background:#fff;
            padding:16px 18px;
            border-radius:10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.07);
        }
        .card h4 { margin:0 0 4px 0; font-size:0.9rem; text-transform:uppercase; color:#777; }
        .value { font-size:1.4rem; font-weight:600; }
        .pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:0.75rem; background:#e3f2fd; color:#1565c0; }

        .section {
            background:#fff;
            padding:18px;
            margin-top:24px;
            border-radius:10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.06);
        }
        table { width:100%; border-collapse:collapse; margin-top:10px; font-size:0.9rem; }
        th, td { padding:8px 6px; border-bottom:1px solid #e2e4ea; text-align:left; vertical-align:top; }
        th { background:#f0f2f7; }
        .tag { display:inline-block; padding:2px 6px; border-radius:4px; font-size:0.75rem; background:#e3f2fd; color:#1565c0; }

        .risk-list {
            list-style:none;
            padding-left:0;
            margin:0;
        }
        .risk-list li {
            margin-bottom:8px;
        }
        .risk-dimension {
            font-weight:600;
        }
        .risk-score {
            font-size:0.9rem;
            color:#444;
        }
        .risk-rationale {
            font-size:0.85rem;
            color:#666;
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    <h1><?php echo htmlspecialchars($info['sub_name']); ?></h1>
    <h3><?php echo htmlspecialchars($info['agency_name']); ?> · Code <?php echo htmlspecialchars($info['sub_code']); ?></h3>

    <div class="summary">
        <div class="card">
            <h4>Latest Month Obligations</h4>
            <?php if ($latest): ?>
                <div class="value">
                    $<?php echo number_format((float)$latest['est_obligations_usd'], 0); ?>
                </div>
                <div>
                    Month: <?php echo htmlspecialchars(date('Y-m', strtotime($latest['month']))); ?>
                </div>
            <?php else: ?>
                <div>No telemetry available yet.</div>
            <?php endif; ?>
        </div>
        <div class="card">
            <h4>Average Pressure (12 mo)</h4>
            <?php if ($avgPressure !== null): ?>
                <div class="value">
                    <?php echo number_format($avgPressure, 1); ?> / 5
                </div>
            <?php else: ?>
                <div class="value">N/A</div>
            <?php endif; ?>
            <div>Higher values indicate more intense enforcement/spend.</div>
        </div>
        <div class="card">
            <h4>Tracked Contracts</h4>
            <div class="value"><?php echo (int)$contractCount; ?></div>
            <?php if ($contractCount == 0): ?>
                <div>No contracts in current dataset.</div>
            <?php else: ?>
                <div>Contracts in current CivicTelemetry dataset.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="section">
        <h2>Telemetry Trend</h2>
        <p style="font-size:0.85rem;color:#666;margin-top:0;">
            This chart shows estimated obligations (bars) and enforcement pressure (line) over time.
        </p>
        <canvas id="iceTrendChart" height="110"></canvas>
    </div>

    <div class="card" style="margin-top:18px;">
        <h4>Risk Lenses (latest month)</h4>
        <?php if (empty($riskScores)): ?>
            <p style="font-size:0.85rem;color:#666;">
                No risk lenses have been recorded for this subagency yet.
            </p>
        <?php else: ?>
            <p style="font-size:0.85rem;color:#666;margin-top:4px;">
                These scores summarize institutional risk dimensions for
                <strong><?php echo htmlspecialchars(date('Y-m', strtotime($latestRiskMonth))); ?></strong>.
            </p>
            <ul class="risk-list">
                <?php foreach ($riskScores as $rs): ?>
                    <li>
                        <div class="risk-dimension">
                            <?php echo htmlspecialchars($rs['dimension']); ?>
                            <span class="risk-score">
                                – <?php echo (int)$rs['score']; ?> / 5
                            </span>
                        </div>
                        <div class="risk-rationale">
                            <?php echo htmlspecialchars($rs['rationale']); ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Monthly Outlays &amp; Pressure</h2>
        <?php if (empty($outlays)): ?>
            <p>No monthly telemetry data is available for this subagency yet.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Month</th>
                    <th>Estimated Obligations (USD)</th>
                    <th>Pressure Score</th>
                    <th>Notes</th>
                </tr>
                <?php foreach ($outlays as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('Y-m', strtotime($row['month']))); ?></td>
                        <td>$<?php echo number_format((float)$row['est_obligations_usd'], 0); ?></td>
                        <td><?php echo (int)$row['pressure_score']; ?></td>
                        <td><?php echo htmlspecialchars($row['notes']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Key Contracts &amp; Vendors</h2>
        <?php if (empty($contracts)): ?>
            <p>No contract records are available for this subagency yet.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Action Date</th>
                    <th>Vendor</th>
                    <th>Award Title</th>
                    <th>Category</th>
                    <th>Amount (USD)</th>
                    <th>Competition</th>
                    <th>Recorded Bidders</th>
                </tr>
                <?php foreach ($contracts as $c): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($c['action_date']); ?></td>
                        <td><?php echo htmlspecialchars($c['vendor_name']); ?></td>
                        <td><?php echo htmlspecialchars($c['award_title']); ?></td>
                        <td><span class="tag"><?php echo htmlspecialchars($c['category']); ?></span></td>
                        <td>$<?php echo number_format((float)$c['amount_obligated_usd'], 0); ?></td>
                        <td><?php echo htmlspecialchars($c['competition_type']); ?></td>
                        <td><?php echo (int)$c['recorded_bidders']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="section">
        <h2>Data Downloads</h2>
        <p style="font-size:0.9rem;color:#555;">
            These downloads reflect the current CivicTelemetry dataset for this subagency. In this alpha, the values
            are seeded but structurally realistic for ICE. Future versions will be driven directly from Treasury and
            USAspending.gov APIs with clear refresh notes.
        </p>
        <ul style="font-size:0.9rem;">
            <li>
                <a href="api/ice_monthly_csv.php">Download ICE monthly outlays &amp; pressure (CSV)</a>
            </li>
            <li>
                <a href="api/ice_contracts_csv.php">Download ICE contracts &amp; vendors (CSV)</a>
            </li>
        </ul>
    </div>

    <div class="section">
        <h2>Event Timeline</h2>
        <?php if (empty($events)): ?>
            <p>No timeline events have been recorded for this subagency yet.</p>
        <?php else: ?>
            <ul style="list-style:none; padding-left:0; font-size:0.9rem;">
                <?php foreach ($events as $e): ?>
                    <li style="margin-bottom:12px;">
                        <div style="font-weight:600;">
                            <?php echo htmlspecialchars(date('Y-m-d', strtotime($e['event_date']))); ?>
                            &nbsp;&middot;&nbsp;
                            <?php echo htmlspecialchars($e['title']); ?>
                        </div>
                        <div style="color:#555; margin-top:2px;">
                            <?php echo nl2br(htmlspecialchars($e['description'])); ?>
                        </div>
                        <?php if (!empty($e['source_url'])): ?>
                            <div style="margin-top:2px;">
                                <a href="<?php echo htmlspecialchars($e['source_url']); ?>" target="_blank" style="font-size:0.85rem;">
                                    Source
                                </a>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>

    <hr style="margin-top:32px;border:none;border-top:1px solid #e0e0e0;">
    <p style="margin-top:12px;font-size:0.8rem;color:#777;text-align:center;">
        CivicTelemetry.org · Independent civic observatory · Not affiliated with DHS, ICE, or the U.S. Government.
    </p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Fetch ICE monthly telemetry from the API
    fetch('api/ice_monthly.php')
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (!data || !data.length) {
                return;
            }

            const labels = data.map(function (row) {
                return row.month; // 'YYYY-MM'
            });

            const obligations = data.map(function (row) {
                return Number(row.est_obligations_usd) / 1e9; // billions
            });

            const pressure = data.map(function (row) {
                return Number(row.pressure_score);
            });

            const ctx = document.getElementById('iceTrendChart').getContext('2d');

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Estimated Obligations (billions USD)',
                            data: obligations,
                            yAxisID: 'y1'
                        },
                        {
                            type: 'line',
                            label: 'Pressure Score (1–5)',
                            data: pressure,
                            yAxisID: 'y2',
                            tension: 0.25
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    scales: {
                        y1: {
                            type: 'linear',
                            position: 'left',
                            title: {
                                display: true,
                                text: 'Obligations (billions USD)'
                            }
                        },
                        y2: {
                            type: 'linear',
                            position: 'right',
                            min: 0,
                            max: 5,
                            grid: {
                                drawOnChartArea: false
                            },
                            title: {
                                display: true,
                                text: 'Pressure Score'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: true
                        }
                    }
                }
            });
        })
        .catch(function (err) {
            console.error('Error loading ICE telemetry chart:', err);
        });
});
</script>

</body>
</html>
