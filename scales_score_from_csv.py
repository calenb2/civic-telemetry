import yaml
import pandas as pd
import json
from pathlib import Path

CONFIG_PATH = Path("config/SCALES_Config.yaml")
EVIDENCE_CSV = Path("ops/SCALES_Evidence.csv")
OUTPUT_STATE = Path("data/scales_state.json")


def load_config():
    with open(CONFIG_PATH, "r", encoding="utf-8") as f:
        return yaml.safe_load(f)


def load_evidence():
    df = pd.read_csv(EVIDENCE_CSV)
    required = ["indicator_id", "pillar", "score_0_4"]
    missing = [c for c in required if c not in df.columns]
    if missing:
        raise ValueError(f"Evidence CSV missing columns: {missing}")
    return df


def compute_indicator_scores(df):
    grouped = df.groupby("indicator_id")["score_0_4"].mean()
    return grouped.to_dict()


def map_indicator_to_pillar(config):
    mapping = {}
    for ind in config.get("indicators", []):
        iid = ind.get("id")
        pillar = ind.get("pillar")
        if iid and pillar:
            mapping[iid] = pillar
    return mapping


def compute_pillar_scores(ind_scores, ind_to_pillar, default_weight=1.0):
    sums = {}
    weights = {}
    for iid, score in ind_scores.items():
        pillar = ind_to_pillar.get(iid)
        if not pillar:
            continue
        sums[pillar] = sums.get(pillar, 0.0) + score * default_weight
        weights[pillar] = weights.get(pillar, 0.0) + default_weight

    pillar_scores = {}
    for pillar, total in sums.items():
        w = weights[pillar]
        pillar_scores[pillar] = total / w if w > 0 else None
    return pillar_scores


def compute_index_scores(config, pillar_scores):
    index_scores = {}
    for idx_key, idx_conf in config.get("indices", {}).items():
        weights = idx_conf.get("pillar_weights", {})
        if not weights:
            continue

        total_w = sum(weights.values())
        if total_w <= 0:
            continue
        norm_weights = {p: w / total_w for p, w in weights.items()}

        raw = 0.0
        for p_code, w in norm_weights.items():
            ps = pillar_scores.get(p_code)
            if ps is None:
                continue
            raw += ps * w  # 0–4 scale

        score_0_100 = (raw / 4.0) * 100.0
        index_scores[idx_key] = score_0_100
    return index_scores


def compute_global_score(config, index_scores):
    gw = config.get("global_score", {}).get("index_weights", {})
    if not gw:
        return None
    total_w = sum(gw.values())
    if total_w <= 0:
        return None
    norm_weights = {k: w / total_w for k, w in gw.items()}
    raw = 0.0
    for idx_key, w in norm_weights.items():
        val = index_scores.get(idx_key)
        if val is None:
            continue
        raw += val * w
    return raw  # already 0–100


def band_constitutional(score):
    if score is None:
        return None
    if score < 20:
        return "Green"
    if score < 40:
        return "Yellow"
    if score < 60:
        return "Amber"
    if score < 80:
        return "Red"
    return "Black"


def build_state_json(config, pillar_scores, index_scores, global_score):
    pillars_out = []
    for p in config.get("pillars", []):
        code = p.get("code")
        name = p.get("name")
        ps = pillar_scores.get(code)
        band = None  # pillar banding optional
        pillars_out.append({
            "code": code,
            "name": name,
            "score": ps,
            "band": band,
        })

    indices_out = {}
    for key, val in index_scores.items():
        band = band_constitutional(val)
        indices_out[key] = {
            "name": config["indices"][key]["name"],
            "score": round(val, 1),
            "band": band,
            "summary": ""  # narrative can be filled separately
        }

    global_band = band_constitutional(global_score)

    state = {
        "version": config.get("version", "2.0.0"),
        "as_of": config.get("as_of", ""),
        "global": {
            "score": round(global_score, 1) if global_score is not None else None,
            "band": global_band,
            "summary": ""
        },
        "indices": indices_out,
        "pillars": pillars_out
    }
    return state


def main():
    config = load_config()
    df = load_evidence()
    ind_scores = compute_indicator_scores(df)
    ind_to_pillar = map_indicator_to_pillar(config)
    pillar_scores = compute_pillar_scores(ind_scores, ind_to_pillar)
    index_scores = compute_index_scores(config, pillar_scores)
    global_score = compute_global_score(config, index_scores)

    state = build_state_json(config, pillar_scores, index_scores, global_score)

    OUTPUT_STATE.parent.mkdir(parents=True, exist_ok=True)
    with open(OUTPUT_STATE, "w", encoding="utf-8") as f:
        json.dump(state, f, indent=2, ensure_ascii=False)

    print(f"Wrote SCALES state to {OUTPUT_STATE}")


if __name__ == "__main__":
    main()
