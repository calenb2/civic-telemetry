#!/bin/bash

BASE_DIR="/home2/cultuncy/civictelemetry.org"

cd "$BASE_DIR" || exit 1

echo "=== Running SCALES pre-publish checks ==="

php scripts/check_scales_state.php
STATE_RC=$?

php scripts/check_scales_modules.php
MOD_RC=$?

echo
if [ $STATE_RC -eq 0 ] && [ $MOD_RC -eq 0 ]; then
  echo "All SCALES checks passed. OK to publish."
  exit 0
else
  echo "One or more SCALES checks FAILED."
  echo "check_scales_state.php exit code: $STATE_RC"
  echo "check_scales_modules.php exit code: $MOD_RC"
  exit 1
fi
