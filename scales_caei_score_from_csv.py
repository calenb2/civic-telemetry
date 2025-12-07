import yaml
import pandas as pd
import json
from pathlib import Path

CONFIG_PATH = Path("config/SCALES_Config.yaml")
EVIDENCE_CSV = Path("ops/CAEI_Evidence.csv")
OUTPUT_STATE = Path("data/modules/CAEI_state.json")


def load_config():
    with open(CONFIG_PATH, "r", encoding="utf-8") as f:
        return yaml.safe_load(f)


def load_evidence():
    df = pd.read_csv(EVIDENCE_CSV)
    required = ["component_code", "score_0_4"]
    missing = [c for c in required if c not in df.columns]
    if missing:
        raise ValueError(f"CAEI evidence CSV missing columns: {missing}")
    return df


def band_module(score):
    # 0–25 Low, 25–50 Moderate, 50–75 High, 75–100 Extreme
    if score is None:
        return None
    if score < 25:
        return "Low"
    if score < 50:
        return "Moderate"
    if score < 75:
        return "High"
    return "Extreme"


def band_component(score_0_4):
    if score_0_4 is None:
        return None
    if score_0_4 < 1:
        return "Low"
    if score_0_4 < 2:
        return "Moderate"
    if score_0_4 < 3:
        return "High"
    return "Extreme"


def main():
    config = load_config()
    modules_conf = config.get("modules", {})
    cae_conf = modules_conf.get("CAEI", {})
    comps_conf = cae_conf.get("components", [])
    scoring_conf = cae_conf.get("scoring", {})

    max_raw = scoring_conf.get("max_raw", 20)
    components = [c for c in comps_conf if "code" in c]

    df = load_evidence()

    # Average score per component_code
    comp_scores_series = df.groupby("component_code")["score_0_4"].mean()
    comp_scores = comp_scores_series.to_dict()

    components_out = []
    raw_total = 0.0

    for c in components:
        code = c["code"]
        name = c.get("name", code)
        weight = float(c.get("weight", 1.0))
        raw_score = comp_scores.get(code)

        if raw_score is not None:
            raw_total += raw_score * weight

        band = band_component(raw_score) if raw_score is not None else None

        components_out.append({
            "code": code,
            "name": name,
            "score_0_4": float(raw_score) if raw_score is not None else None,
            "band": band,
            "summary": ""  # fill later
        })

    # Normalize to 0–100
    if max_raw > 0:
        score_norm = (raw_total / max_raw) * 100.0
    else:
        score_norm = None

    module_band = band_module(score_norm)

    state = {
        "version": "1.0.0",
        "as_of": "2025-12-06",
        "key": "CAEI",
        "name": "Coercive Apparatus Expansion Index",
        "score": round(score_norm, 1) if score_norm is not None else None,
        "band": module_band,
        "summary": "Illustrative CAEI score computed from component-level evidence.",
        "components": components_out,
        "notes": [
            "Scores in this alpha run are illustrative and based on a small evidence set.",
            "Production runs should use a richer, regularly updated CAEI_Evidence.csv."
        ]
    }

    OUTPUT_STATE.parent.mkdir(parents=True, exist_ok=True)
    with open(OUTPUT_STATE, "w", encoding="utf-8") as f:
        json.dump(state, f, indent=2, ensure_ascii=False)

    print(f"Wrote CAEI state to {OUTPUT_STATE}")


if __name__ == "__main__":
    main()
