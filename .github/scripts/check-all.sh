#!/usr/bin/env bash
# Every guard the CI runs, in one local command. The i18n checks need wp-cli and gettext.
set -uo pipefail
cd "$(dirname "$0")/../.."

fail=0
for check in check-versions check-no-network check-no-emdash check-js-translations check-pot-fresh check-mo-fresh; do
  printf '\n== %s\n' "$check"
  .github/scripts/"$check".sh || fail=1
done

exit "$fail"
