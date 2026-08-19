#!/usr/bin/env bash
# A stale MO silently serves the previous translations: the PO is never read at runtime.
set -uo pipefail

fail=0
translated() { grep -oE '[0-9]+ translated' | head -1 | grep -oE '[0-9]+'; }

for po in languages/biolinks-*.po; do
  [ -e "$po" ] || continue
  mo="${po%.po}.mo"
  if [ ! -f "$mo" ]; then
    printf 'FAIL  %s has no compiled %s\n' "$po" "$mo" >&2
    fail=1
    continue
  fi
  po_count=$(msgfmt --statistics -o /dev/null "$po" 2>&1 | translated)
  mo_count=$(msgunfmt "$mo" 2>/dev/null | msgfmt --statistics -o /dev/null - 2>&1 | translated)
  if [ "${po_count:-x}" = "${mo_count:-y}" ]; then
    printf 'ok    %s and %s both carry %s translations\n' "$po" "$mo" "$po_count"
  else
    printf 'FAIL  %s carries %s translations but %s carries %s, run wp i18n make-mo\n' \
      "$po" "${po_count:-?}" "$mo" "${mo_count:-?}" >&2
    fail=1
  fi
done

exit "$fail"
