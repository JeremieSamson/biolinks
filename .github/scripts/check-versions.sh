#!/usr/bin/env bash
# The release version lives in four places and a mismatch on "Tested up to"
# makes the plugin drop out of the WordPress.org directory search.
set -uo pipefail

fail=0
err() { printf 'FAIL  %s\n' "$*" >&2; fail=1; }
ok()  { printf 'ok    %s\n' "$*"; }

php_version=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' biolinks.php | head -1)
php_const=$(sed -n "s/.*BIOLINKS_VERSION'[^']*'\([0-9][0-9.]*\)'.*/\1/p" biolinks.php | head -1)
readme_tag=$(sed -n 's/^Stable tag:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' readme.txt | head -1)
php_tested=$(sed -n 's/^[[:space:]]*\*[[:space:]]*Tested up to:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' biolinks.php | head -1)
readme_tested=$(sed -n 's/^Tested up to:[[:space:]]*\([0-9][0-9.]*\).*/\1/p' readme.txt | head -1)

for pair in "biolinks.php Version:$php_version" \
            "BIOLINKS_VERSION:$php_const" \
            "readme.txt Stable tag:$readme_tag" \
            "biolinks.php Tested up to:$php_tested" \
            "readme.txt Tested up to:$readme_tested"; do
  [ -n "${pair#*:}" ] || err "could not read ${pair%%:*}"
done
[ "$fail" -eq 0 ] || exit 1

if [ "$php_version" = "$php_const" ] && [ "$php_version" = "$readme_tag" ]; then
  ok "version $php_version consistent across biolinks.php and readme.txt"
else
  err "version mismatch: header=$php_version constant=$php_const stable tag=$readme_tag"
fi

if [ "$php_tested" = "$readme_tested" ]; then
  ok "tested up to $php_tested consistent"
else
  err "tested up to mismatch: biolinks.php=$php_tested readme.txt=$readme_tested"
fi

# readme.txt needs the version under both == Changelog == and == Upgrade Notice ==.
entries=$(grep -c "^= ${php_version} =$" readme.txt || true)
if [ "$entries" -ge 2 ]; then
  ok "readme.txt documents $php_version in changelog and upgrade notice"
else
  err "readme.txt has $entries entries for '= $php_version =', expected 2"
fi

exit "$fail"
