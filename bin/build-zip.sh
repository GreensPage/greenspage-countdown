#!/usr/bin/env bash
# Build the distributable greenspage-countdown.zip with a clean top-level folder,
# excluding development tooling listed in .distignore. Portable: needs only
# coreutils + zip (no rsync).
set -euo pipefail

SLUG="greenspage-countdown"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/build"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

# Verify plugin header version matches the git tag when building in CI.
if [ "${GITHUB_REF_TYPE:-}" = "tag" ]; then
  TAG="${GITHUB_REF_NAME#v}"
  HDR="$(grep -m1 -oE 'Version:[[:space:]]*[0-9.]+' "$ROOT/greenspage-countdown.php" | grep -oE '[0-9.]+')"
  if [ "$TAG" != "$HDR" ]; then
    echo "ERROR: tag v$TAG does not match plugin header version $HDR" >&2
    exit 1
  fi
fi

# Stage a full copy, then strip everything listed in .distignore.
mkdir -p "$STAGE/$SLUG"
cp -R "$ROOT/." "$STAGE/$SLUG/"
while IFS= read -r pattern; do
  [ -z "$pattern" ] && continue
  rm -rf "$STAGE/$SLUG/${pattern#/}"
done < "$ROOT/.distignore"

mkdir -p "$OUT"
rm -f "$OUT/$SLUG.zip"
( cd "$STAGE" && zip -rq "$SLUG.zip" "$SLUG" -x '*/.DS_Store' )
mv "$STAGE/$SLUG.zip" "$OUT/$SLUG.zip"
echo "Built $OUT/$SLUG.zip"
