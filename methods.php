<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Methods & Data – CivicTelemetry</title>
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
        .layout { max-width: 900px; margin: 0 auto; }
        h1 { margin-bottom:0; }
        h3 { margin-top:5px; color:#666; }
        a { color:#1565c0; text-decoration:none; }
        .section {
            background:#fff;
            padding:18px 22px;
            border-radius:10px;
            box-shadow:0 3px 10px rgba(0,0,0,0.06);
            margin-top:24px;
        }
        h2 { margin-top:0; }
        ul { padding-left:20px; }
        code { background:#eceff1; padding:1px 3px; border-radius:3px; font-size:0.9em; }
        .note { font-size:0.85rem; color:#777; margin-top:12px; }
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
            <a href="scales.php">SCALES</a>
            <a href="methods.php"><strong>Methods</strong></a>
        </div>

    </div>

    <h1>Methods &amp; Data</h1>


    <h3>How CivicTelemetry thinks about public spending & institutional signals</h3>

    <div class="section">
        <h2>Purpose</h2>
        <p>
            <strong>CivicTelemetry.org</strong> is an independent, non-official project that treats
            public spending as a form of telemetry for institutions. The goal is simple:
            make it easier for citizens, researchers, and journalists to see how money,
            vendors, and enforcement priorities move over time.
        </p>
        <p>
            The current alpha focuses on the <strong>U.S. Immigration and Customs Enforcement (ICE)</strong>
            component of the <strong>Department of Homeland Security (DHS)</strong>. Over time, the
            scope will expand to additional DHS components and other federal agencies.
        </p>
    </div>

    <div class="section">
        <h2>Data Sources (intended)</h2>
        <p>
            CivicTelemetry is designed to rely only on <strong>publicly available government data</strong>.
            The primary sources for production data will be:
        </p>
        <ul>
            <li>
                <strong>U.S. Department of the Treasury</strong> – Monthly Treasury Statement (MTS) and related datasets,
                accessed via the <em>FiscalData</em> APIs (<code>fiscaldata.treasury.gov</code>).
            </li>
            <li>
                <strong>USAspending.gov</strong> – Federal spending, contract, and grant award data,
                including sub-agency identifiers, vendor names, award amounts, and competition details.
            </li>
        </ul>
        <p class="note">
            The current alpha uses <em>seeded but structurally realistic data</em> based on public reporting,
            to develop the schema and visualization patterns. When full API integration is enabled, this
            page will be updated with the exact queries and versioning approach.
        </p>
    </div>
<div class="section">
    <h2>Ingestion & Automation (ICE, current administration)</h2>
    <p>
        CivicTelemetry includes a scoped ingest pipeline for <strong>ICE monthly telemetry</strong> under the
        current presidential administration. Rather than simply showing static seeded values, the platform is
        designed so that obligations figures can be updated from public APIs as the Treasury publishes new data.
    </p>
    <p>
        The current ingest script, <code>ingest_ice_admin_mts.php</code>, is configured to:
    </p>
    <ul>
        <li>
            Treat <strong>2025-01-20</strong> as the start of the current administration, with a managed window
            beginning at <strong>2025-01-01</strong> for monthly data.
        </li>
        <li>
            Call the Treasury <strong>FiscalData</strong> API (Monthly Treasury Statement, DHS as agency 070) to
            retrieve spending data in that window.
        </li>
        <li>
            For each month in that window, <strong>INSERT or UPDATE</strong> the estimated obligations for ICE in
            the <code>monthly_outlays</code> table.
            Obligations use an upsert pattern:
            <code>ON DUPLICATE KEY UPDATE est_obligations_usd = VALUES(est_obligations_usd)</code>.
        </li>
        <li>
            <strong>Preserve interpretive metrics</strong> such as the Pressure Score (1–5) and narrative notes,
            even when obligations are updated.
        </li>
    </ul>
    <p>
        Each run of the script is recorded in an <code>ingest_log</code> table with the start/finish timestamps,
        script name, targeted agency/subagency, administration start date, rows affected, and status
        (<code>SUCCESS</code> or <code>ERROR</code>). This creates a simple audit trail so that anybody reviewing
        the project can see when data was ingested and whether any errors occurred.
    </p>
    <p class="note">
        In this alpha stage, the script is run manually and used to validate the pipeline. A future phase will
        attach it to a modest cron schedule so that ICE telemetry is automatically refreshed as Treasury updates
        the underlying data.
    </p>
</div>

    <div class="section">
        <h2>Key Concepts & Metrics</h2>
        <h3>Estimated Obligations</h3>
        <p>
            For each month and sub-agency, <strong>Estimated Obligations</strong> represent an approximation
            of how much funding is being obligated to that entity, based on Treasury outlay series and/or
            the aggregation of federal contract obligations from USAspending.gov.
        </p>

        <h3>Pressure Score (1–5)</h3>
        <p>
            The <strong>Pressure Score</strong> is a simple 1–5 ordinal indicator intended to capture the
            intensity of enforcement and related spending over a given period. It is a synthetic, interpretive
            metric, not a government-supplied number.
        <
<p class="note">
    For convenience, CivicTelemetry also exposes CSV downloads for the current ICE dataset
    (monthly outlays and contracts). These are accessible via links on the ICE drill-down page.
</p>

    <hr style="margin-top:32px;border:none;border-top:1px solid #e0e0e0;">
    <p style="margin-top:12px;font-size:0.8rem;color:#777;text-align:center;">
        CivicTelemetry.org · Independent civic observatory · Not affiliated with DHS, ICE, or the U.S. Government.
    </p>
