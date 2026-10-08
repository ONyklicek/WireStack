#!/usr/bin/env bash

# Runs once, when the container is created.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

git config --global --add safe.directory "$PWD"

composer install --no-interaction --no-progress
npm ci --no-audit --no-fund
npm run build

# The preview workbench (vendor/bin/testbench serve --port=8085): publishes
# assets, creates the SQLite database, migrates and seeds it.
vendor/bin/testbench workbench:build --ansi
