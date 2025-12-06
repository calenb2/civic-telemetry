<?php
// scales.php – SCALES Constitutional Risk Dashboard

$statePath = __DIR__ . '/data/scales_state.json';
$ledgerPath = __DIR__ . '/data/scales_ledger_public.json';

$state = null;
$ledger = [];

if (file_exists($statePath)) {
    $raw = file_get_contents($statePath);
    $state = json_decode($raw, true);
}

if (file_exists($ledgerPath)) {
    $rawL = file_get_contents($ledgerPath);
    $ledger = json_decode($rawL, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SCALES – Constitutional Risk Dashboard</title>
  <style>
    body { font-family: Arial, sans-serif; margin:40px; background:#f8f9fb; color:#222; }
    .layout { max-width: 1100px; margin:0 auto; }
    .top-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
    .top-nav .brand a { font-weight:700; font-size:1.2rem; color:#222; text-decoration:none; }
    .top-nav .links a { margin-left:14px; font-size:0.9rem; color:#1565c0; text-decoration:none; }
    h1 { margin-bottom:0; }
    h3 { margin-top:5px; color:#666; }
    .grid { display:flex; flex-wrap:wrap; gap:18px; margin-top:22px; }
    .card { flex:1 1 260px; background:#fff; padding:18px; border-radius:10px; box-shadow:0 3px 10px rgba(0,0,0,0.06); }
    .card h4 { margin:0 0 4px 0; font-size:0.9rem; text-transform:uppercase; color:#777; }
    .card .value { font-size:1.4rem; font-weight:600; }
    .band-pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:0.75rem; background:#e3f2fd; color:#1565c0; }
    .section { background:#fff; padding:18px; border-radius:10px; box-shadow:0 3px 10px rgba(0,0,0,0.06); margin-top:24px; }
    table { width:100%; border-collapse:collapse; margin-top:10px; font-size:0.9rem; }
    th, td { padding:8px 6px; border-bottom:1px solid #e2e4ea; text-align:left; vertical-align:top; }
    th { background:#f0f2f7; }
    .footer { margin-top:32px; font-size:0.8rem; color:#777; text-align:center; }
  </style>
</head>
<body>
<div class="layout">
  <div class="top-nav">
    <div class="brand"><a href="index.php">CivicTelemetry</a></div>
    <div class="links">
      <a href="index.php">Dashboard</a>
      <a href="agency.php?code=070">DHS</a>
      <a href="scales.php"><strong>SCALES</strong></a>
      <a href="methods.php">Methods</a>
    </div>
  </div>

  <h1>SCALES – Constitutional Risk Dashboard</h1>
  <h3>Separation-of-powers early-warning model (alpha)</h3>

  <?php if (!$state): ?>
    <p>No SCALES state data is available yet. Once <code>data/scales_state.json</code> is populated, this page will update automatically.</p>
  <?php else: ?>
    <?php
      $global = $state['global'] ?? null;
      $indices = $state['indices'] ?? [];
      $pillars = $state['pillars'] ?? [];
    ?>

    <div class="grid">
      <div class="card">
        <h4>Global SCALES Score</h4>
        <?php if ($global): ?>
          <div class="value"><?php echo (int)$global['score']; ?> / 100</div>
          <div>
            <span class="band-pill"><?php echo htmlspecialchars($global['band'] ?? ''); ?></span>
          </div>
          <p style="margin-top:8px;font-size:0.9rem;">
            <?php echo htmlspecialchars($global['summary'] ?? ''); ?>
          </p>
        <?php else: ?>
          <p>No global score present.</p>
        <?php endif; ?>
      </div>

      <?php foreach ($indices as $key => $idx): ?>
        <div class="card">
          <h4><?php echo htmlspecialchars($idx['name'] ?? $key); ?></h4>
          <div class="value"><?php echo isset($idx['score']) ? (int)$idx['score'] : 'N/A'; ?> / 100</div>
          <div>
            <span class="band-pill"><?php echo htmlspecialchars($idx['band'] ?? ''); ?></span>
          </div>
          <p style="margin-top:8px;font-size:0.9rem;">
            <?php echo htmlspecialchars($idx['summary'] ?? ''); ?>
          </p>
        </div>
      <?php endforeach; ?>
    </div>

    <div class="section">
      <h2>Pillars at a Glance</h2>
      <?php if (empty($pillars)): ?>
        <p>No pillar data available.</p>
      <?php else: ?>
        <table>
          <tr>
            <th>Code</th>
            <th>Pillar</th>
            <th>Score (0–4)</th>
            <th>Band</th>
          </tr>
          <?php foreach ($pillars as $p): ?>
            <tr>
              <td><?php echo htmlspecialchars($p['code'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($p['name'] ?? ''); ?></td>
              <td><?php echo isset($p['score']) ? number_format((float)$p['score'], 2) : 'N/A'; ?></td>
              <td><?php echo htmlspecialchars($p['band'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <div class="section">
      <h2>Recent Events in the Evidence Ledger</h2>
      <?php if (empty($ledger)): ?>
        <p>No public ledger entries yet.</p>
      <?php else: ?>
        <table>
          <tr>
            <th>Date</th>
            <th>Actor</th>
            <th>Pillar</th>
            <th>Indicator</th>
            <th>Score</th>
            <th>Summary</th>
          </tr>
          <?php foreach ($ledger as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['date'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['actor'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($row['pillar'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars(($row['indicator_id'] ?? '') . ' ' . ($row['indicator_label'] ?? '')); ?></td>
              <td><?php echo isset($row['score_0_4']) ? (int)$row['score_0_4'] : 'N/A'; ?></td>
              <td><?php echo htmlspecialchars($row['short_reason'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="section" style="margin-top:24px;">
    <h2>About SCALES</h2>
    <p style="font-size:0.9rem;">
      SCALES is an independent research model. Scores are interpretive, not official. All inputs are tied to public
      documents (laws, rulings, datasets, credible reporting), and the scoring rules are documented on the SCALES
      methods page.
    </p>
    <p style="font-size:0.9rem;">
      See <a href="scales_methods.php">SCALES Methods</a> for pillar definitions, indicator structure, and banding rules.
    </p>
  </div>
      <div class="section">
    <h2>SCALES Modules</h2>
    <p style="font-size:0.9rem;">
      SCALES can host multiple sector-specific indices under its constitutional core. In this alpha, three modules are scaffolded:
      the <strong>AI Preference Engineering Index (AI-PEI)</strong>, the <strong>Coercive Apparatus Expansion Index (CAEI)</strong>,
      and the <strong>Surveillance &amp; Data Exploitation Index (SDEI)</strong>.
    </p>
    <ul style="font-size:0.9rem;">
      <li>
        <a href="scales_ai_pei.php">AI Preference Engineering Index (AI-PEI) &rarr;</a>
      </li>
      <li>
        <a href="scales_caei.php">Coercive Apparatus Expansion Index (CAEI) &rarr;</a>
      </li>
      <li>
        <a href="scales_sdei.php">Surveillance &amp; Data Exploitation Index (SDEI) &rarr;</a>
      </li>
    </ul>
  </div>



  <div class="footer">
    CivicTelemetry.org · Independent civic observatory · Not affiliated with DHS, ICE, or the U.S. Government.
  </div>
</div>
</body>
</html>
