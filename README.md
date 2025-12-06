# CivicTelemetry.org

CivicTelemetry is an independent, non-official observatory of U.S. federal spending and institutional signals.

The guiding idea is simple:  
**follow the public money and contracts, and treat them as telemetry for how institutions behave.**

This early alpha focuses on **U.S. Immigration and Customs Enforcement (ICE)** within the **Department of Homeland Security (DHS)**, using a small, seeded dataset that mirrors how real Treasury and USAspending data will be ingested and visualized later.

---

## Stack & Hosting

- **Hosting:** HostGator Baby Plan (shared Linux)
- **Stack:** LAMP
  - Apache (via HostGator)
  - MySQL (`cultuncy_civictelemetry`)
  - PHP 8.x
- **Domain:** `CivicTelemetry.org`  
  - Served from: `/home2/cultuncy/civictelemetry.org/`

No external paid services are required. All intended production data will come from **public federal APIs** (Treasury/FiscalData, USAspending).

---

## Directory Layout (current)

Inside `/home2/cultuncy/civictelemetry.org/`:

- `index.php`  
  Home dashboard – "Federal Spend Pulse (alpha)".  
  Shows ICE summary, telemetry signal, latest contracts.

- `subagency.php`  
  ICE drill-down page (e.g., `subagency.php?code=7012`).  
  - Summary metrics (latest obligations, average pressure, contract count)  
  - Telemetry chart (Chart.js) using `/api/ice_monthly.php`  
  - Monthly outlays & pressure table  
  - Contracts & vendors table

- `agency.php`  
  DHS overview page (e.g., `agency.php?code=070`).  
  - DHS header  
  - Summary cards driven by ICE for now  
  - Component tiles (ICE + placeholders for future components)

- `methods.php`  
  Methods & Data explainer: purpose, data sources, metrics, limitations.

- `db.php`  
  Central PHP DB connection helper:
  ```php
  $DB_HOST = 'localhost';
  $DB_NAME = 'cultuncy_civictelemetry';
  $DB_USER = 'cultuncy_ctuser';
  $DB_PASS = '********';

ETL DESIGN (ICE – current administration)
-----------------------------------------

For ICE, CivicTelemetry includes an ETL script (`data/ingest_ice_admin_mts.php`) that is scoped to the current
administration:

- ADMIN_START_DATE = 2025-01-20
- ADMIN_START_MONTH = 2025-01-01

Behavior:

- Calls Treasury FiscalData (MTS Table 5) for DHS (agency_identifier 070) starting at ADMIN_START_MONTH.
- For each month in that window:
  - Inserts a row into `monthly_outlays` for ICE if it does not exist.
  - Or updates `est_obligations_usd` using `ON DUPLICATE KEY UPDATE` if the row already exists.
- Does not modify `pressure_score` or `notes` fields, which remain interpretive metrics.

The script logs each run into an `ingest_log` table with:

- `ts_started`, `ts_finished`
- `script_name`
- `agency_code`, `subagency_code`
- `admin_start_date`
- `rows_affected`
- `status` (SUCCESS or ERROR)
- `message` (e.g., curl errors or success notes)

In the current alpha, this ETL is run manually for validation. A future phase will attach it to cron to refresh
ICE obligations automatically as the Treasury updates its Monthly Treasury Statement data.
