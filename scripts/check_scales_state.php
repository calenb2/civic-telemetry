<?php
/**
 * SCALES state / ledger sanity checker
 *
 * Run from CLI:
 *   php scripts/check_scales_state.php
 *
 * It will:
 *  - Optionally read config/SCALES_Config.yaml (if yaml extension is available)
 *  - Validate data/scales_state.json and data/scales_ledger_public.json
 *  - Emit errors/warnings and exit non-zero if errors are found
 */

$baseDir    = dirname(__DIR__);
$configPath = $baseDir . '/config/SCALES_Config.yaml';
$statePath  = $baseDir . '/data/scales_state.json';
$ledgerPath = $baseDir . '/data/scales_ledger_public.json';

$errors   = [];
$warnings = [];

function err($msg) {
    global $errors;
    $errors[] = $msg;
    echo "[ERROR] $msg\n";
}

function warn($msg) {
    global $warnings;
    $warnings[] = $msg;
    echo "[WARN]  $msg\n";
}

echo "=== SCALES State Check ===\n";
echo "Base directory: {$baseDir}\n\n";

/**
 * 1. Load YAML config if possible
 */
$config = null;
if (file_exists($configPath) && function_exists('yaml_parse_file')) {
    echo "Loading config from SCALES_Config.yaml...\n";
    $cfg = @yaml_parse_file($configPath);
    if (is_array($cfg)) {
        $config = $cfg;
        echo "Config loaded.\n\n";
    } else {
        warn("Could not parse SCALES_Config.yaml; falling back to built-in expectations.");
    }
} else {
    if (!file_exists($configPath)) {
        warn("Config file SCALES_Config.yaml not found; falling back to built-in expectations.");
    } else {
        warn("PHP yaml extension not available; cannot parse YAML. Falling back to built-in expectations.");
    }
}

// Default expectations if YAML isn't available or parse fails
$expectedIndices = ['ILG', 'CCI', 'CSSI'];
$expectedPillars = ['S', 'C', 'A', 'L', 'E', 'S2'];

if ($config) {
    // Derive expected indices from config
    if (!empty($config['indices']) && is_array($config['indices'])) {
        $expectedIndices = array_keys($config['indices']);
    }
    // Derive expected pillars from config
    if (!empty($config['pillars']) && is_array($config['pillars'])) {
        $tmp = [];
        foreach ($config['pillars'] as $p) {
            if (isset($p['code'])) {
                $tmp[] = $p['code'];
            }
        }
        if (!empty($tmp)) {
            $expectedPillars = $tmp;
        }
    }
}

echo "Expected indices: " . implode(', ', $expectedIndices) . "\n";
echo "Expected pillars: " . implode(', ', $expectedPillars) . "\n\n";

/**
 * 2. Validate scales_state.json
 */
echo "--- Checking scales_state.json ---\n";

if (!file_exists($statePath)) {
    err("Missing file: data/scales_state.json");
} else {
    $raw = file_get_contents($statePath);
    $state = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        err("scales_state.json is not valid JSON: " . json_last_error_msg());
    } elseif (!is_array($state)) {
        err("scales_state.json did not decode to an object/array.");
    } else {
        // Global
        if (!isset($state['global'])) {
            warn("scales_state.json has no 'global' section.");
        } else {
            $g = $state['global'];
            if (isset($g['score'])) {
                $score = $g['score'];
                if (!is_numeric($score) || $score < 0 || $score > 100) {
                    err("Global score out of range (0–100): " . print_r($score, true));
                }
            } else {
                warn("Global section missing 'score'.");
            }
        }

        // Indices
        if (empty($state['indices']) || !is_array($state['indices'])) {
            warn("No 'indices' section found in scales_state.json.");
        } else {
            foreach ($expectedIndices as $idxKey) {
                if (!isset($state['indices'][$idxKey])) {
                    warn("Expected index '$idxKey' is missing from state.indices.");
                    continue;
                }
                $idx = $state['indices'][$idxKey];
                if (!isset($idx['score'])) {
                    warn("Index '$idxKey' missing score.");
                } else {
                    $s = $idx['score'];
                    if (!is_numeric($s) || $s < 0 || $s > 100) {
                        err("Index '$idxKey' score out of range (0–100): " . print_r($s, true));
                    }
                }
            }
        }

        // Pillars
        if (empty($state['pillars']) || !is_array($state['pillars'])) {
            warn("No 'pillars' section found in scales_state.json.");
        } else {
            foreach ($expectedPillars as $pCode) {
                $found = null;
                foreach ($state['pillars'] as $p) {
                    if (isset($p['code']) && $p['code'] === $pCode) {
                        $found = $p;
                        break;
                    }
                }
                if (!$found) {
                    warn("Expected pillar '$pCode' not found in state.pillars.");
                    continue;
                }
                if (isset($found['score'])) {
                    $ps = $found['score'];
                    if (!is_numeric($ps) || $ps < 0 || $ps > 4) {
                        err("Pillar '$pCode' score out of range (0–4): " . print_r($ps, true));
                    }
                } else {
                    warn("Pillar '$pCode' missing 'score'.");
                }
            }
        }
    }
}

echo "\n";

/**
 * 3. Validate scales_ledger_public.json
 */
echo "--- Checking scales_ledger_public.json ---\n";

if (!file_exists($ledgerPath)) {
    warn("Missing file: data/scales_ledger_public.json (not fatal, but ledger will appear empty).");
} else {
    $rawL = file_get_contents($ledgerPath);
    $ledger = json_decode($rawL, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        err("scales_ledger_public.json is not valid JSON: " . json_last_error_msg());
    } elseif (!is_array($ledger)) {
        err("scales_ledger_public.json did not decode to an array.");
    } else {
        $requiredFields = ['id', 'date', 'pillar', 'indicator_id', 'score_0_4', 'short_reason', 'source_type'];
        $maxCheck = 100; // don't spam if ledger is huge
        $count = 0;
        foreach ($ledger as $row) {
            $count++;
            if ($count > $maxCheck) {
                warn("More than {$maxCheck} ledger entries; only first {$maxCheck} checked.");
                break;
            }
            // Required fields present?
            foreach ($requiredFields as $f) {
                if (!array_key_exists($f, $row) || $row[$f] === '' || $row[$f] === null) {
                    err("Ledger entry missing required field '$f' (id=" . (isset($row['id']) ? $row['id'] : 'UNKNOWN') . ").");
                }
            }
            // Pillar valid?
            if (isset($row['pillar']) && !in_array($row['pillar'], $expectedPillars, true)) {
                warn("Ledger entry has pillar '" . $row['pillar'] . "' which is not in expected set (" . implode(', ', $expectedPillars) . "). id=" . (isset($row['id']) ? $row['id'] : 'UNKNOWN'));
            }
            // Score range check
            if (isset($row['score_0_4'])) {
                $ls = $row['score_0_4'];
                if (!is_numeric($ls) || $ls < 0 || $ls > 4) {
                    err("Ledger entry score_0_4 out of range (0–4): " . print_r($ls, true) . " (id=" . (isset($row['id']) ? $row['id'] : 'UNKNOWN') . ")");
                }
            }
        }
        echo "Checked " . min($count, $maxCheck) . " ledger entries.\n";
    }
}

echo "\n=== Summary ===\n";
echo "Errors:   " . count($errors) . "\n";
echo "Warnings: " . count($warnings) . "\n";

if (!empty($errors)) {
    echo "Result: FAILED (fix errors above before publishing).\n";
    exit(1);
} else {
    echo "Result: OK (no hard errors detected).\n";
    exit(0);
}
