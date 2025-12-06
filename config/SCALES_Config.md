# SCALES v2 Configuration – Methods & Data Contract

**Name:** SCALES  
**Long name:** Separation-of-powers Composite Assessment & Legal Early-warning System  
**Version:** 2.0.0  
**As of:** 2025-12-06

This document is the human-facing version of `SCALES_Config.yaml`. It defines the structure of SCALES scores, pillars, indicators, tripwires, and modules, so that any scoring engine or researcher can reproduce and critique the model.

The YAML file is the machine-readable source of truth. This markdown is the explanatory counterpart.


---

## 1. Bands

SCALES uses two band systems:

### 1.1 Constitutional bands (core SCALES + ILG/CCI/CSSI)

Used for:

- Global SCALES score  
- Institutional & Legal Guardrails (ILG)  
- Coercion & Capture (CCI)  
- Civic Space & Signal (CSSI)

Ranges:

- **0–20 – Green (Stable)**
- **20–40 – Yellow (Watch)**
- **40–60 – Amber (Elevated)**
- **60–80 – Red (Severe)**
- **80–100 – Black (Acute / Crisis)**


### 1.2 Module bands (AI-PEI, CAEI, SDEI)

Used for sector-specific indices (Civic Moody style):

- **0–25 – Low**
- **25–50 – Moderate**
- **50–75 – High**
- **75–100 – Extreme**


---

## 2. Top-level Indices

SCALES presents three dashboard indices:

### 2.1 ILG – Institutional & Legal Guardrails

**Key:** `ILG`  
**Description:** Integrity of separation of powers, rule of law, and formal constraints.

Weights over pillars (conceptual):

- `S` – Structural & Constitutional Safeguards: 0.4  
- `L` – Legislative & Electoral Integrity: 0.4  
- `A` – Administrative State & Enforcement: 0.2


### 2.2 CCI – Coercion & Capture

**Key:** `CCI`  
**Description:** Use of security, administrative, and economic power for partisan or personal ends.

Pillar weights:

- `C` – Coercive & Security Apparatus: 0.4  
- `A` – Administrative State & Enforcement: 0.3  
- `E` – Economic Power & Patronage: 0.3


### 2.3 CSSI – Civic Space & Signal Index

**Key:** `CSSI`  
**Description:** Freedom and integrity of civic space and the information environment.

Pillar weights:

- `S2` – Speech, Media & Information Space: 0.5  
- `C` – Coercive apparatus effects on civil liberties: 0.25  
- `L` – Legal protections for civil / electoral rights: 0.25


---

## 3. Global SCALES Score

The **Global SCALES score** is defined as a weighted combination of ILG, CCI, and CSSI:

- ILG: 0.33  
- CCI: 0.33  
- CSSI: 0.34  

The scoring engine:

1. Computes each index on a 0–100 scale.  
2. Applies the above weights.  
3. Produces a global score on 0–100.  
4. Assigns a constitutional band (Green → Black).


---

## 4. Pillars

SCALES uses six pillars (S-C-A-L-E-S), where the second S is renamed `S2` in config:

- **S – Structural & Constitutional Safeguards**  
  Separation of powers, emergency powers, federalism, succession rules.

- **C – Coercive & Security Apparatus**  
  Military, police, intelligence services and how they are used.

- **A – Administrative State & Enforcement**  
  Neutrality of civil service, regulators, prosecutors; selective enforcement.

- **L – Legislative & Electoral Integrity**  
  Election rules, districting, certification, legislative process.

- **E – Economic Power & Patronage**  
  Use of state resources and regulation for patronage, capture, or punishment.

- **S2 – Speech, Media & Social Information Space**  
  Press freedom, platforms, disinfo, intimidation, propaganda.


---

## 5. Indicators

Indicators are the “rows” in the evidence ledger. Each one:

- Belongs to a pillar.
- Has an `indicator_id` (e.g., `L.2`).
- Is scored on a **0–4** scale with narrative anchors.

Example indicator in config:

- **ID:** `L.2`  
  **Pillar:** `L`  
  **Name:** Election certification integrity  
  **Description:** Behavior of key actors during certification of election results.  
  **Scale anchors (0–4):**
  - 0 – Routine, uncontested certification.
  - 1 – Rhetorical pressure but no meaningful disruption.
  - 2 – Serious pressure, certification ultimately holds.
  - 3 – Open attempts to overturn or refuse certification.
  - 4 – Successful overturning/refusal of clearly certified results.

Other indicators (e.g., `S.2` for emergency powers, `C.3` for use of security forces in domestic politics) follow the same pattern and will be elaborated in future revisions.


---

## 6. Scoring Rules

### 6.1 Indicator scale

- **Min:** 0  
- **Max:** 4  

Each evidence entry sets a 0–4 value for the indicator in a given time window, with a confidence rating.

### 6.2 Pillar aggregation

- Method: **weighted average** of indicator scores for that pillar.
- Default indicator weight: **1.0** (some can be upweighted later).
- Optional recency weighting can be added (not implemented yet).

Result is normalized to a 0–100 scale for display and banding.

### 6.3 Index aggregation

- Method: **weighted average** of relevant pillar scores using the weights in section 2.
- Result normalized to 0–100.

### 6.4 Global aggregation

- Method: **weighted average** of ILG, CCI, CSSI using the weights in section 3.
- Result normalized to 0–100 and banded using constitutional bands.


---

## 7. Tripwires

Tripwires are discrete, high-severity events that light up SCALES even if averages lag.

Example tripwires defined in config:

- **T1 – Armed forces vs peaceful domestic activity**
  - Pillar: C
  - Indicator: `C.3`
  - Severity: High
  - Cooldown: 365 days

- **T2 – Refusal to comply with final constitutional court orders**
  - Pillar: S
  - Indicator: `S.2`
  - Severity: High
  - Cooldown: 365 days

- **T3 – Overturning / non-recognition of clearly certified election results**
  - Pillar: L
  - Indicator: `L.2`
  - Severity: Critical
  - Cooldown: 730 days

Tripwires are recorded in the evidence ledger and flagged in the `scales_state.json` so dashboards can display them explicitly.


---

## 8. Modules (Civic Moody-style Indices)

SCALES hosts sector-specific indices that sit alongside the constitutional core.

Each module:

- Has a `key`, `name`, and description.
- Has a list of components (0–4 scores).
- Defines a simple combination rule (sum → normalize).

### 8.1 AI-PEI – AI Preference Engineering Index

**Key:** `AI_PEI`  
**Description:** Capacity to use AI and data-driven tools to sculpt public preferences at scale.

Components (0–4):

- `PIC` – Persuasion Infrastructure Capacity  
- `CC` – Centralization of Control  
- `DP` – Deployment Pattern  
- `CT_raw` – Contestability & Counter-speech (raw)  
- `GS_raw` – Governance & Safeguards (raw)

Scoring:

- `AI_PEI_raw = PIC + CC + DP + CT_term + GS_
