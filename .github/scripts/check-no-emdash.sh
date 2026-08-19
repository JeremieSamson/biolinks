#!/usr/bin/env bash
# Em dashes are banned from user facing copy.
set -uo pipefail

hits=$(grep -rn $'—' readme.txt README.md languages includes templates assets/admin.js assets/front.js 2>/dev/null \
  | grep -v 'assets/vendor' || true)

if [ -n "$hits" ]; then
  printf 'FAIL  em dash found in user facing copy:\n%s\n' "$hits" >&2
  exit 1
fi

printf 'ok    no em dash in user facing copy\n'
