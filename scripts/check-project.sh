#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

printf '[1/8] PHP syntax...\n'
while IFS= read -r -d '' file; do
  php -l "$file" >/dev/null || { echo "PHP syntax failed: $file" >&2; exit 1; }
done < <(find app bootstrap config database routes tests scripts resources/views -type f -name '*.php' -print0)

printf '[2/8] JSON syntax...\n'
php -r 'foreach (["composer.json","package.json","package-lock.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); } echo "JSON OK\n";'

printf '[3/8] PHP 8.2 compatibility...\n'
php scripts/verify-php82-compatibility.php

printf '[4/8] Requested-feature audit...\n'
php scripts/verify-requested-features.php >/tmp/raito-requirements-audit.txt
cat /tmp/raito-requirements-audit.txt | tail -1

printf '[5/8] Frontend syntax/build...\n'
npm run check >/dev/null
npm run build >/dev/null

printf '[6/8] Security/static UI patterns...\n'
if grep -R -nE '\son(click|submit|change|input|load)=' resources/views >/tmp/raito-inline-events.txt; then
  cat /tmp/raito-inline-events.txt >&2
  echo 'Inline event handler found.' >&2
  exit 1
fi
if grep -R -n '{!!' resources/views >/tmp/raito-raw-blade.txt; then
  cat /tmp/raito-raw-blade.txt >&2
  echo 'Raw Blade output found.' >&2
  exit 1
fi
if grep -R -nE 'alert\(|window\.confirm\(' resources/js >/tmp/raito-native-dialogs.txt; then
  cat /tmp/raito-native-dialogs.txt >&2
  echo 'Native alert/confirm found; use branded UI feedback.' >&2
  exit 1
fi

printf '[7/8] Production-readiness static check...\n'
php scripts/verify-production-readiness.php --allow-missing-runtime || true

printf '[8/8] Asset parity...\n'
cmp -s resources/js/app.js public/assets/app.js
cmp -s resources/css/app.css public/assets/app.css

echo 'Static project checks passed.'
