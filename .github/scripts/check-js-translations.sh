#!/usr/bin/env bash
# wp-cli 2.12 names make-json output after a truncated handle (assets/a.js),
# so WordPress never finds the file and JS strings silently stay in English.
set -uo pipefail

fail=0
expected_hash=$(printf '%s' 'assets/admin.js' | md5sum | cut -d' ' -f1)

for po in languages/biolinks-*.po; do
  [ -e "$po" ] || continue
  locale=$(basename "$po" .po); locale=${locale#biolinks-}
  json="languages/biolinks-${locale}-${expected_hash}.json"
  if [ -f "$json" ]; then
    printf 'ok    %s\n' "$json"
  else
    printf 'FAIL  missing %s (wrong make-json hash?)\n' "$json" >&2
    ls languages/biolinks-"${locale}"-*.json 2>/dev/null >&2 || true
    fail=1
  fi
done

exit "$fail"
