# CivicTelemetry & SCALES

**CivicTelemetry** is an independent, non-official observatory of U.S. federal spending and institutional signals.  
The guiding idea is simple:

> **Follow the public money and contracts, and treat them as telemetry for how institutions behave.**

The project has two main layers:

1. **CivicTelemetry core** – a lightweight PHP site that surfaces federal spend and contracts (currently seeded around **DHS / ICE**).
2. **SCALES** – the **Separation-of-powers Constitutional Alert & Early-warning System**, a transparent scoring model that turns evidence about laws, contracts, and institutional behavior into readable risk scores and bands.

This repository contains the public-facing site, configuration, and scoring pipeline used to generate the SCALES dashboards and modules.

---

## Live site

- **Production:** `https://civictelemetry.org/`

Key public pages:

- `index.php` – CivicTelemetry landing page and ICE-focused “Federal Spend Pulse (alpha)”.
- `agency.php` – DHS overview (currently built from ICE seed data).
- `subagency.php` – ICE drill-down (summary metrics, monthly outlays, contracts tables).
- `methods.php` – CivicTelemetry data and methods explainer.

SCALES pages:

- `scales.php` – **Constitutional Risk Dashboard** (global SCALES score, indices, pillars, ledger).
- `scales_ai_pei.php` – **AI Preference Engineering Index (AI-PEI)** module.
- `scales_caei.php` – **Coercive Apparatus Expansion Index (CAEI)** module.
- `scales_sdei.php` – **Surveillance & Data Exploitation Index (SDEI)** module.
- `scales_methods.php` – SCALES pillars, indicators, bands, and interpretation.

---

## Stack & hosting

- **Hosting:** HostGator Baby Plan (shared Linux)
- **Stack:** “fat-free” LAMP
  - Apache (via HostGator)
  - PHP 8.x
  - MySQL (`cultuncy_civictelemetry`) for ICE seed data and ETL pipeline
- **Domain:** `civictelemetry.org`
  - Served from: `/home2/cultuncy/civictelemetry.org/`

The SCALES model itself is deliberately file-based:

- Configuration in **YAML/Markdown** under `config/`.
- Current scores and bands in **JSON** under `data/` and `data/modules/`.
- Evidence as **CSV** under `ops/`.
- Small Python scripts recompute scores from evidence + config.

All intended production data for spend/contracts will come from **public federal APIs** (Treasury FiscalData, USAspending); the current ICE dataset is a small, hand-seeded mirror for early UI and pipeline work.

---

## Directory layout (simplified)

Inside `/home2/cultuncy/civictelemetry.org/` (and the git repo):

### Core CivicTelemetry UI

- `index.php`  
  Home dashboard – “Federal Spend Pulse (alpha)”. ICE summary, telemetry signal, latest contracts.

- `agency.php`  
  DHS overview page (e.g., `agency.php?code=070`).

- `subagency.php`  
  ICE drill-down page (e.g., `subagency.php?code=7012`):
  - Summary metrics (latest obligations, average pressure, contract count)
  - Telemetry chart using `/api/ice_monthly.php`
  - Monthly outlays & pressure table
  - Contracts & vendors table

- `methods.php`  
  CivicTelemetry methods & data sources.

### SCALES dashboards

- `scales.php`  
  Constitutional risk dashboard (global score, ILG/CCI/CSSI indices, pillars, evidence ledger, module links).

- `scales_ai_pei.php`  
  AI Preference Engineering Index (AI-enabled persuasion capacity).

- `scales_caei.php`  
  Coercive Apparatus Expansion Index (capacity, reach, targeting, control).

- `scales_sdei.php`  
  Surveillance & Data Exploitation Index (visibility, linkage, analytics, governance).

- `scales_methods.php`  
  SCALES methodology, pillar definitions, indicator structure, and banding rules.

### Configuration & state

- `config/SCALES_Config.yaml`  
  Machine-readable spec for:
  - Bands (Green/Amber/Red/Black core; Low/Moderate/High/Extreme modules)
  - Indices (ILG, CCI, CSSI)
  - Pillars (S, C, A, L, E, S2) and indicators
  - Modules (AI-PEI, CAEI, SDEI) and their components.

- `config/SCALES_Config.md`  
  Human-readable version for documentation/portfolio.

- `data/scales_state.json`  
  Current SCALES global snapshot: global score, indices, pillars, and an `as_of` timestamp.

- `data/scales_ledger_public.json`  
  Public evidence ledger entries (date, actor, pillar, indicator, score, short reason, link).

- `data/modules/AI_PEI_state.json`  
  Module state for AI-PEI.

- `data/modules/CAEI_state.json`  
  Module state for CAEI.

- `data/modules/SDEI_state.json`  
  Module state for SDEI.

### Evidence & scoring

- `ops/SCALES_Evidence.csv`  
  Scored evidence for the core SCALES model (indicator-level 0–4 scores tied to public sources).

- `ops/AI_PEI_Evidence.csv`  
  Evidence for AI-PEI components (persuasion infrastructure, control, deployment, counter-speech, governance).

- `ops/CAEI_Evidence.csv`  
  Evidence for coercive apparatus (enforcement capacity, operational reach, targeting, oversight, legal restraints).

- `ops/SDEI_Evidence.csv`  
  Evidence for surveillance & data exploitation (collection breadth, linkability, analytic capability, governance, rights & remedies).

- `scales_score_from_csv.py`  
  Recomputes `data/scales_state.json` from `SCALES_Evidence.csv` + `SCALES_Config.yaml`.

- `scales_ai_pei_score_from_csv.py`  
  Recomputes `AI_PEI_state.json`.

- `scales_caei_score_from_csv.py`  
  Recomputes `CAEI_state.json`.

- `scales_sdei_score_from_csv.py`  
  Recomputes `SDEI_state.json`.

### Validation & deployment helpers

- `scripts/check_scales_state.php`  
  Validates `scales_state.json` and the public ledger (structure, ranges, required fields).

- `scripts/check_scales_modules.php`  
  Validates module JSON files against config expectations.

- `scripts/prepublish_scales.sh`  
  Runs both validators; used on the HostGator side to gate new state before it is treated as “live”.

---

## ETL design (ICE – current administration)

For ICE, CivicTelemetry includes an ETL script (`data/ingest_ice_admin_mts.php`) scoped to the **current administration**:

- `ADMIN_START_DATE = 2025-01-20`  
- `ADMIN_START_MONTH = 2025-01-01`

Behavior (current alpha):

- Calls **Treasury FiscalData** (MTS Table 5) for DHS (`agency_identifier = 070`) starting at `ADMIN_START_MONTH`.
- For each month in that window:
  - Inserts a row into `monthly_outlays` for ICE if it does not exist.
  - Or updates `est_obligations_usd` for ICE using `ON DUPLICATE KEY UPDATE`.
- Does **not** modify `pressure_score` or `notes`, which remain interpretive metrics.

Each ETL run is logged into an `ingest_log` table:

- `ts_started`, `ts_finished`
- `script_name`
- `agency_code`, `subagency_code`
- `admin_start_date`
- `rows_affected`
- `status` (SUCCESS or ERROR)
- `message` (curl / API errors, success notes)

In the current alpha, the ETL is run manually for validation. A later phase will attach it to cron to refresh ICE obligations as Treasury updates its Monthly Treasury Statement data.

---

## SCALES scoring workflow

To update SCALES scores in a scoring environment (e.g., DataCamp DataLab or local dev):

1. **Edit evidence**

   - Add or revise rows in the appropriate CSV under `ops/`.
   - Each row includes:
     - `date`, `jurisdiction`, `actor`
     - `indicator_id` or `component_code`
     - `score_0_4` (0–4 rubric)
     - `short_reason`
     - `source_type`, `source_link`
     - `confidence`

2. **Recompute scores**

   From the repo root:

   ```bash
   python scales_score_from_csv.py
   python scales_ai_pei_score_from_csv.py
   python scales_caei_score_from_csv.py
   python scales_sdei_score_from_csv.py

<!-- cron deploy test 2 -->
