#!/usr/bin/env bash
# BioLinks ships zero external HTTP calls. That is a product promise, not a
# preference: nothing else in the codebase enforces it.
set -uo pipefail

paths=(biolinks.php uninstall.php includes templates assets)
prune=(-path './assets/vendor' -prune -o)

php_hits=$(grep -rnE "wp_remote_(get|post|head|request)|curl_init|curl_exec|fsockopen|file_get_contents\([[:space:]]*['\"]https?://" \
  --include='*.php' "${paths[@]}" 2>/dev/null | grep -v 'assets/vendor' || true)

asset_hits=$(grep -rnE "src=['\"]https?://|@import[[:space:]]+url\(['\"]?https?://|fetch\(['\"]https?://|XMLHttpRequest" \
  --include='*.php' --include='*.js' --include='*.css' "${paths[@]}" 2>/dev/null | grep -v 'assets/vendor' || true)

if [ -n "$php_hits$asset_hits" ]; then
  printf 'FAIL  external network access reintroduced:\n%s\n%s\n' "$php_hits" "$asset_hits" >&2
  exit 1
fi

printf 'ok    no external HTTP call outside assets/vendor\n'
