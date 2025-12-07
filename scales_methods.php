<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SCALES – Methods & Scoring</title>
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
    ul { padding-left:20px; }
    code { background:#eceff1; padding:1px 3px; border-radius:3px; font-size:0.9em; }
    .note { font-size:0.85rem; color:#777; margin-top:12px; }
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
      <a href="methods.php">Data Methods</a>
    </div>
  </div>

  <h1>SCALES – Methods &amp; Scoring</h1>
  <h3>How the constitutional risk dashboard is constructed (alpha)</h3>

  <div class="section">
    <h2>Purpose</h2>
    <p>
      SCALES (Separation-of-powers Composite Assessment &amp; Legal Early-warning System) is designed to turn a messy stream
      of political, legal, and financial events into structured, repeatable risk scores about democratic stress and
      separation-of-powers health.
    </p>
    <p>
      The goals are:
    </p>
    <ul>
      <li>Make constitutional risk visible and explainable, not mystical.</li>
      <li>Keep a clear line between <strong>raw facts</strong> and <strong>interpretive analysis</strong>.</li>
      <li>Document assumptions so scores can be defended or revised on the merits.</li>
    </ul>
  </div>

  <div class="section">
    <h2>Structure: Indicators → Pillars → Indices</h2>
    <p>SCALES is built in layers:</p>
    <ul>
      <li><strong>Indicators (0–4):</strong> specific, observable conditions (e.g., election certification integrity, emergency powers).</li>
      <li><strong>Pillars:</strong> groups of indicators across six domains:
        Structural safeguards, Coercive apparatus, Administrative state, Legislative/electoral integrity,
        Economic power &amp; patronage, and Speech/media/information space.
      </li>
      <li><strong>Composite indices (0–100):</strong> higher-level views such as guardrails, coercion/capture, and civic space/signal.</li>
      <li><strong>Global SCALES score (0–100):</strong> a composite of the three indices, banded into risk levels.</li>
    </ul>
  </div>

  <div class="section">
    <h2>Evidence & Ledger</h2>
    <p>Each indicator score is supported by an evidence entry containing:</p>
    <ul>
      <li>A short neutral description of the event.</li>
      <li>The actor (court, legislature, executive, agency).</li>
      <li>Time window and jurisdiction.</li>
      <li>Source citation (law, opinion, dataset, or credible reporting).</li>
      <li>Pillar and indicator ID.</li>
      <li>Score (0–4) and confidence.</li>
    </ul>
    <p class="note">
      The public file <code>data/scales_ledger_public.json</code> is a subset of the full internal ledger, filtered
      to entries that can safely be shared and clearly cited.
    </p>
  </div>

  <div class="section">
    <h2>Scoring & Bands</h2>
    <p>Indicator scoring follows this general rubric:</p>
    <ul>
      <li><strong>0</strong> – Normal / baseline conditions.</li>
      <li><strong>1</strong> – Mild concern or isolated incidents.</li>
      <li><strong>2</strong> – Significant concern with repeated or serious issues.</li>
      <li><strong>3</strong> – Severe erosion or active abuse.</li>
      <li><strong>4</strong> – Acute crisis or breakdown.</li>
    </ul>
    <p>Pillars and indices are normalized to 0–100 and assigned bands:</p>
    <ul>
      <li><strong>0–20</strong> – Green (stable)</li>
      <li><strong>20–40</strong> – Yellow (watch)</li>
      <li><strong>40–60</strong> – Amber (elevated)</li>
      <li><strong>60–80</strong> – Red (severe)</li>
      <li><strong>80–100</strong> – Black (acute/crisis)</li>
    </ul>
  </div>

  <div class="section">
    <h2>Tripwires</h2>
    <p>
      Tripwires are predefined conditions that immediately raise concern regardless of averages, such as:
    </p>
    <ul>
      <li>Use of military or federalized forces against peaceful domestic political activity.</li>
      <li>Refusal to comply with final constitutional court rulings.</li>
      <li>Overturning or non-recognition of clearly certified election results.</li>
    </ul>
    <p class="note">
      Tripwires are flagged in the SCALES state JSON so users can see both gradual trends and discrete shocks.
    </p>
  </div>

  <div class="section">
    <h2>Relationship to Civic Telemetry Data</h2>
    <p>
      CivicTelemetry’s core outlay and contract data come directly from public sources (e.g., Treasury FiscalData,
      USAspending.gov). SCALES does not alter those records.
    </p>
    <p>
      Instead, SCALES reads those facts and overlays its own interpretive scores. The site keeps that distinction
      visible:
    </p>
    <ul>
      <li><strong>“Data” views</strong> show raw spending and awards.</li>
      <li><strong>“SCALES” views</strong> show risk bands, scores, and narratives about institutional health.</li>
    </ul>
  </div>

  <div class="section">
    <h2>Evidence sources and automation</h2>
    <p style="font-size:0.9rem;">
      Each SCALES evidence row is tied to at least one primary or near-primary document. The model does not rely on rumors
      or single anonymous sources. Instead, it watches a small set of public feeds and repositories that can be harvested
      in a repeatable way:
    </p>
    <ul style="font-size:0.9rem;">
      <li><strong>Supreme Court opinions</strong> (<code>scotus_opinion_pdf</code>) – official slip opinions from <code>supremecourt.gov</code> that change presidential accountability, separation of powers, or agency authority.</li>
      <li><strong>Federal statutes and CRS reports</strong> (<code>congress_public_law</code>, <code>congress_crs_report</code>) – reauthorizations and reforms such as FISA Section 702 under the Reforming Intelligence and Securing America Act (RISAA).</li>
      <li><strong>Declassified oversight material</strong> (<code>odni_fisc_opinion</code>, <code>ig_report</code>) – Foreign Intelligence Surveillance Court opinions and inspector-general reports that describe how legal powers are being used in practice.</li>
      <li><strong>Regulatory enforcement actions</strong> (<code>ftc_press_release</code>, <code>agency_enforcement</code>) – enforcement by agencies like the FTC against data brokers, platforms, or vendors that governs how surveillance data can be collected and sold.</li>
      <li><strong>Court decisions on rights and remedies</strong> (<code>federal_court_decision</code>, <code>civil_liberties_org_summary</code>) – rulings that expand or constrain remedies against unconstitutional surveillance or abuse.</li>
    </ul>
    <p style="font-size:0.9rem;">
      In this alpha, evidence rows are curated by hand from these sources. The <code>source_type</code> field in each CSV
      is deliberately constrained so that a later automation step can poll the same feeds, propose candidate evidence rows,
      and let a human reviewer decide which ones become part of the public ledger.
    </p>
  </div>

  <div class="footer">
    CivicTelemetry.org · Independent civic observatory · Not affiliated with DHS, ICE, or the U.S. Government.
  </div>
</div>
</body>
</html>
