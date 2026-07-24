#!/usr/bin/env bash
# Fetch frontend vendor assets for offline/LAN deployment.
# Run once on a machine with internet access, then deploy the repo.
# The UI works without these (app.css + built-in chart fallback), but
# Bootstrap 5 and Chart.js enhance it.
set -euo pipefail

DIR="$(cd "$(dirname "$0")/.." && pwd)/public/assets/vendor"
mkdir -p "$DIR"

echo "Downloading Bootstrap 5.3 CSS..."
curl -fsSL -o "$DIR/bootstrap.min.css" \
  https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css

echo "Downloading Chart.js 4 (UMD)..."
curl -fsSL -o "$DIR/chart.umd.js" \
  https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.js

echo "Done. Assets saved to $DIR"
