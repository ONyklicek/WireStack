#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PREVIEW_PORT="${PREVIEW_PORT:-8085}"
DRIVER_PORT="${DRIVER_PORT:-4444}"
CAPTURE_ENABLED=1
SITE_BUILD_ENABLED=1

usage() {
    cat <<'EOF'
Usage: bash scripts/refresh-docs-site.sh [options]

Options:
  --skip-capture      Rebuild Vite assets and docs site without refreshing preview PNGs.
  --skip-site-build   Refresh preview PNGs only.
  --preview-port N    Override the local Testbench preview port. Default: 8085
  --driver-port N     Override the Safari WebDriver port. Default: 4444
  --help              Show this help.
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-capture)
            CAPTURE_ENABLED=0
            shift
            ;;
        --skip-site-build)
            SITE_BUILD_ENABLED=0
            shift
            ;;
        --preview-port)
            PREVIEW_PORT="$2"
            shift 2
            ;;
        --driver-port)
            DRIVER_PORT="$2"
            shift 2
            ;;
        --help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown option: $1" >&2
            usage >&2
            exit 1
            ;;
    esac
done

cd "$ROOT_DIR"

npm run build

SERVER_PID=""
DRIVER_PID=""

cleanup() {
    if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null || true
        wait "$SERVER_PID" 2>/dev/null || true
    fi

    if [[ -n "$DRIVER_PID" ]] && kill -0 "$DRIVER_PID" 2>/dev/null; then
        kill "$DRIVER_PID" 2>/dev/null || true
        wait "$DRIVER_PID" 2>/dev/null || true
    fi
}

trap cleanup EXIT

wait_for_url() {
    local url="$1"
    local label="$2"
    local attempt

    for attempt in {1..30}; do
        if curl -fsS "$url" >/dev/null 2>&1; then
            return 0
        fi

        sleep 1
    done

    echo "Timed out waiting for ${label}: ${url}" >&2
    return 1
}

if [[ "$CAPTURE_ENABLED" -eq 1 ]]; then
    # Build the workbench (create SQLite DB, migrate-fresh, seed) so table,
    # widget, and infolist previews have data. Without this the preview server
    # serves 500s ("no such table") for every DB-backed preview.
    vendor/bin/testbench workbench:build

    vendor/bin/testbench serve --host=127.0.0.1 --port="$PREVIEW_PORT" >/tmp/wire-preview-server.log 2>&1 &
    SERVER_PID="$!"

    wait_for_url "http://127.0.0.1:${PREVIEW_PORT}/previews/forms-overview" "preview server"

    # Screenshots are captured with headless Chrome over the DevTools Protocol
    # (no Safari WebDriver required).
    PREVIEW_BASE_URL="http://127.0.0.1:${PREVIEW_PORT}/previews" \
        node docs-site/scripts/capture-previews.mjs
fi

if [[ "$SITE_BUILD_ENABLED" -eq 1 ]]; then
    # Build every configured locale of the default version so the local preview
    # has a working language switcher (CI assembles the full version matrix).
    locales=$(php -r '$c=json_decode(file_get_contents("docs-site/config.json"),true);foreach($c["locales"] as $l){echo ($l["code"]??"")."\n";}')
    for code in $locales; do
        DOCS_BUILD_LOCALE="$code" php docs-site/build.php
    done

    # Syntax-highlight code blocks in the generated HTML with Torchlight.
    # Requires a token in torchlight.config.cjs or the TORCHLIGHT_TOKEN env var.
    if [[ -f torchlight.config.cjs ]]; then
        node node_modules/@torchlight-api/torchlight-cli/dist/bin/torchlight.cjs.js \
            -c torchlight.config.cjs -i docs-site/dist || \
            echo "Torchlight highlighting skipped (CLI unavailable or no token)." >&2
    fi
fi
