<?php
/**
 * SCALES modules sanity checker (AI-PEI, CAEI, SDEI).
 *
 * Run from CLI:
 *   php scripts/check_scales_modules.php
 */

$baseDir    = dirname(__DIR__);
$configPath = $baseDir . '/config/SCALES_Config.yaml';
$modulesDir = $baseDir . '/data/modules';

$errors   = [];
$warnings = [];

function merr($msg) {
    global $errors;
    $errors[] = $msg;
    echo "[ERROR] $msg\n";
}

function mwarn($msg) {
    global $warnings;
    $warnings[] = $msg;
    echo "[WARN]  $msg\n";
}

echo "=== SCALES Modules Check ===\n";
echo "Base directory: {$baseDir}\n\n";

/**
 * 1. Load YAML config if possible to get expected modules/components.
 */
$config = null;
if (file_exists($configPath) && function_exists('yaml_parse_file')) {
    echo "Loading config from SCALES_Config.yaml...\n";
    $cfg = @yaml_parse_file($configPath);
    if (is_array($cfg)) {
        $config = $cfg;
        echo "Config loaded.\n\n";
    } else {
        mwarn("Could not parse SCALES_Config.yaml; falling back to built-in module expectations.");
    }
} else {
    if (!file_exists($configPath)) {
        mwarn("Config file SCALES_Config.yaml not found; falling back to built-in module expectations.");
    } else {
        mwarn("PHP yaml extension not available; cannot parse YAML. Falling back to built-in module expectations.");
    }
}

// Default expectations if YAML not available
$expectedModules = [
    'AI_PEI' => ['PIC','CC','DP','CT_raw','GS_raw'],
    'CAEI'   => ['EC','OR','TI','OA_raw','LR_raw'],
    'SDEI'   => ['CB','IL','AC','AG_raw','RR_raw'],
];

if ($config && isset($config['modules']) && is_array($config['modules'])) {
    $expectedModules = [];
    foreach ($config['modules'] as $modKey => $mod) {
        $codes = [];
        if (!empty($mod['components']) && is_array($mod['components'])) {
            foreach ($mod['components'] as $c) {
                if (isset($c['code'])) {
                    $codes[] = $c['code'];
                }
            }
        }
        $expectedModules[$modKey] = $codes;
    }
}

echo "Expected modules and components:\n";
foreach ($expectedModules as $mKey => $comps) {
    echo "  - {$mKey}: " . implode(', ', $comps) . "\n";
}
echo "\n";

/**
 * 2. Check each module JSON state file.
 */
foreach ($expectedModules as $mKey => $expectedComponents) {
    echo "--- Checking module: {$mKey} ---\n";

    $filePath = $modulesDir . '/' . $mKey . '_state.json';
    if (!file_exists($filePath)) {
        mwarn("Missing module state file: {$filePath}");
        continue;
    }

    $raw = file_get_contents($filePath);
    $state = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        merr("Module {$mKey} state JSON invalid: " . json_last_error_msg());
        continue;
    }
    if (!is_array($state)) {
        merr("Module {$mKey} state JSON did not decode to an object/array.");
        continue;
    }

    // Basic top-level checks
    if (!isset($state['key']) || $state['key'] !== $mKey) {
        mwarn("Module {$mKey} top-level 'key' is missing or does not match '{$mKey}'.");
    }
    if (empty($state['name'])) {
        mwarn("Module {$mKey} 'name' is missing or empty.");
    }
    if (!isset($state['score'])) {
        mwarn("Module {$mKey} missing 'score'.");
    } else {
        $s = $state['score'];
        if (!is_numeric($s) || $s < 0 || $s > 100) {
            merr("Module {$mKey} score out of range (0–100): " . print_r($s, true));
        }
    }
    if (!isset($state['band'])) {
        mwarn("Module {$mKey} missing 'band'.");
    }

    // Component checks
    if (empty($state['components']) || !is_array($state['components'])) {
        mwarn("Module {$mKey} has no 'components' array.");
        continue;
    }

    // Index components by code for easier lookup
    $componentsByCode = [];
    foreach ($state['components'] as $c) {
        if (isset($c['code'])) {
            $componentsByCode[$c['code']] = $c;
        }
    }

    // Ensure each expected component exists and has a 0–4 score
    foreach ($expectedComponents as $cCode) {
        if (!isset($componentsByCode[$cCode])) {
            mwarn("Module {$mKey} missing expected component '{$cCode}'.");
            continue;
        }
        $comp = $componentsByCode[$cCode];
        if (!isset($comp['score_0_4'])) {
            mwarn("Module {$mKey} component '{$cCode}' missing 'score_0_4'.");
        } else {
            $cs = $comp['score_0_4'];
            if (!is_numeric($cs) || $cs < 0 || $cs > 4) {
                merr("Module {$mKey} component '{$cCode}' score_0_4 out of range (0–4): " . print_r($cs, true));
            }
        }
    }

    // Warn on unexpected/extra components
    foreach ($componentsByCode as $cCode => $_) {
        if (!in_array($cCode, $expectedComponents, true)) {
            mwarn("Module {$mKey} has unexpected component code '{$cCode}' not in config.");
        }
    }

    echo "\n";
}

//
