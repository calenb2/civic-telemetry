<?php
// scales_caei.php – Coercive Apparatus Expansion Index module

$statePath = __DIR__ . '/data/modules/CAEI_state.json';
$state = null;

if (file_exists($statePath)) {
    $raw = file_get_contents($statePath);
    $tmp = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        $state = $tmp;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Coercive Apparatus Expansion Index – SCALES Module</title>
  <style>
    body { font-family: Arial, sans-serif; margin:40px; background:#f8f9fb; color:#222; }
    .layout { max-width: 900px; margin:0 auto; }
    .top-nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
    .top-nav .brand a { font-weight:700; font-size:1.2rem; color:#222; text-decoration:none; }
    .top-nav .links a { margin-left:14px; font-size:0.9rem; color:#1565c0; text-decoration:none; }
    h1 { margin-bottom:0; }
    h3 { margin-top:5px; color:#666; }
    .section { background:#fff; padding:18px 22px; border-radius:10px; box-shadow:0 3px 10px rgba(0,0,0,0.06); margin-top:24px; }
    h2 { margin-top:0; }
    table { width:100%; border-collapse:collapse; margin-top:10px; font-size:0.9rem; }
    th, td { padding:8px 6px; border-bottom:1px solid #e2e4ea; text-align:left; vertical-align:top; }
    th { background:#f0f2f7; }
    .band-pill { display:inline-block; padding:2px 8px; border-radius:999px; font-size:0.75rem; background:#e3f2fd; color:#1565c0; }
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
      <a href="scales.php">SCALES</a>
      <a href="methods.php">Methods</a>
    </div>
  </div>

  <h1>Coercive Apparatus Expansion Index (CAEI)</h1>
  <h3>SCALES module – capacity, reach, targeting, and control</h3>

<?php if ($module && !empty($module['as_of'])): ?>
  <p style="margin-top:4px; margin-bottom:16px; font-size:0.8rem; color:#666;">
    Last updated: <?php echo htmlspecialchars($module['as_of']); ?>
  </p>
<?php endif; ?>

  <?php if (!$state): ?>
    <div class="section">
      <p>No CAEI module data is available yet. Once <code>data/modules/CAEI_state.json</code> is populated, this page will update automatically.</p>
    </div>
  <?php else: ?>
    <?php
      $score    = $state['score'] ?? null;
      $band     = $state['band'] ?? null;
      $summary  = $state['summary'] ?? '';
      $components = $state['components'] ?? [];
    ?>

    <div class="section">
      <h2>Module Summary</h2>
      <p>
        <strong>Overall CAEI:</strong>
        <?php if ($score !== null): ?>
          <span style="font-size:1.4rem;font-weight:600;"><?php echo (int)$score; ?> / 100</span>
        <?php else: ?>
          <span>N/A</span>
        <?php endif; ?>
        <?php if ($band): ?>
          &nbsp;<span class="band-pill"><?php echo htmlspecialchars($band); ?></span>
        <?php endif; ?>
      </p>
      <p style="font-size:0.9rem;"><?php echo htmlspecialchars($summary); ?></p>
    </div>

    <div class="section">
      <h2>Component Scores (0–4)</h2>
      <?php if (empty($components)): ?>
        <p>No component scores provided.</p>
      <?php else: ?>
        <table>
          <tr>
            <th>Code</th>
            <th>Component</th>
            <th>Score (0–4)</th>
            <th>Band</th>
            <th>Summary</th>
          </tr>
          <?php foreach ($components as $c): ?>
            <tr>
              <td><?php echo htmlspecialchars($c['code'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($c['name'] ?? ''); ?></td>
              <td><?php echo isset($c['score_0_4']) ? (int)$c['score_0_4'] : 'N/A'; ?></td>
              <td><?php echo htmlspecialchars($c['band'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($c['summary'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      <?php endif; ?>
    </div>

    <?php if (!empty($state['notes'])): ?>
      <div class="section">
        <h2>Notes</h2>
        <ul style="font-size:0.9rem;">
          <?php foreach ($state['notes'] as $n): ?>
            <li><?php echo htmlspecialchars($n); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <div class="section">
      <h2>How CAEI fits into SCALES</h2>
      <p style="font-size:0.9rem;">
        CAEI is a sector-specific index under the SCALES umbrella. It tracks how much physical and legal coercive capacity
        the state has built, where it can operate, who it is used against, and how strong the brakes are.
      </p>
      <ul style="font-size:0.9rem;">
        <li>Links to the <strong>Coercive &amp; Security Apparatus</strong> pillar by summarizing muscle and reach.</li>
        <li>Interacts with <strong>Administrative State &amp; Enforcement</strong> when selective enforcement or targeting patterns emerge.</li>
        <li>Touches <strong>Structural &amp; Legal Guardrails</strong> when legal restraints are weakened or bypassed.</li>
      </ul>
      <p style="font-size:0.9rem;">
        In production, CAEI would be derived from detention capacity, task-force authorities, cross-agency enforcement agreements,
        litigation patterns, and statutory changes affecting the use of force and custody.
      </p>
    </div>
  <?php endif; ?>

  <div class="footer">
    CivicTelemetry.org · Independent civic observatory · Not affiliated with DHS, ICE, or the U.S. Government.
  </div>
</div>
</body>
</html>
