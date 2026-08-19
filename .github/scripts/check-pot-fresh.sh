#!/usr/bin/env bash
# A string added without regenerating the POT never reaches translate.wordpress.org.
set -uo pipefail

command -v wp >/dev/null || { printf 'FAIL  wp-cli is required\n' >&2; exit 1; }

tmp=$(mktemp -d)
trap 'rm -rf "$tmp"' EXIT

root_flag=()
[ "$(id -u)" -eq 0 ] && root_flag=(--allow-root)

if ! wp i18n make-pot . "$tmp/fresh.pot" \
      --slug=biolinks --domain=biolinks --exclude=assets/vendor --package-name="BioLinks" \
      "${root_flag[@]}" >"$tmp/log" 2>&1; then
  printf 'FAIL  wp i18n make-pot did not run:\n' >&2
  cat "$tmp/log" >&2
  exit 1
fi

msgids() { grep -E '^msgid ' "$1" | sort -u; }

if diff <(msgids languages/biolinks.pot) <(msgids "$tmp/fresh.pot") > "$tmp/diff"; then
  printf 'ok    languages/biolinks.pot covers every source string\n'
else
  printf 'FAIL  languages/biolinks.pot is stale, regenerate with wp i18n make-pot\n' >&2
  printf '      < committed POT, > freshly generated\n' >&2
  cat "$tmp/diff" >&2
  exit 1
fi
