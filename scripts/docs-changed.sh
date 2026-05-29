#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SINCE_REF=""
DRY_RUN=0
FORCE_FULL=0

usage() {
    cat <<'EOF'
Usage: bash scripts/docs-changed.sh [options]

Options:
  --since REF   Compare against a git ref instead of the current working tree.
  --dry-run     Show detected mode and files without running anything.
  --full        Force a full docs refresh with preview recapture.
  --help        Show this help.
EOF
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --since)
            SINCE_REF="$2"
            shift 2
            ;;
        --dry-run)
            DRY_RUN=1
            shift
            ;;
        --full)
            FORCE_FULL=1
            shift
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

if ! git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "Not inside a git worktree. Falling back to full refresh."
    exec bash scripts/refresh-docs-site.sh
fi

collect_changed_files() {
    if [[ -n "$SINCE_REF" ]]; then
        git diff --name-only --relative "${SINCE_REF}...HEAD" --
    else
        git diff --name-only --relative HEAD --
    fi

    git ls-files --others --exclude-standard
}

CHANGED_FILES=()
while IFS= read -r file; do
    CHANGED_FILES+=("$file")
done < <(collect_changed_files | sed '/^$/d' | sort -u)

if [[ ${#CHANGED_FILES[@]} -eq 0 ]]; then
    echo "No changes detected."
    exit 0
fi

DOCS_FILES=()
SITE_REBUILD=0
FULL_REFRESH=0

for file in "${CHANGED_FILES[@]}"; do
    case "$file" in
        docs/*.md|docs/*/*.md|docs/*/*/*.md)
            DOCS_FILES+=("$file")
            SITE_REBUILD=1
            ;;
        docs-site/build.php|docs-site/README.md|docs-site/templates/*|docs-site/assets/*|docs-site/assets/*/*)
            DOCS_FILES+=("$file")
            SITE_REBUILD=1
            ;;
        docs-site/scripts/capture-previews.mjs|package.json|package-lock.json|vite.config.js|resources/*|resources/*/*|scripts/refresh-docs-site.sh|workbench/routes/web.php|workbench/app/Livewire/Previews/*|workbench/resources/views/*|workbench/resources/views/*/*|workbench/resources/views/*/*/*)
            DOCS_FILES+=("$file")
            FULL_REFRESH=1
            ;;
    esac
done

if [[ "$FORCE_FULL" -eq 1 ]]; then
    FULL_REFRESH=1
fi

MODE="none"
COMMAND=()

if [[ "$FULL_REFRESH" -eq 1 ]]; then
    MODE="full-refresh"
    COMMAND=(bash scripts/refresh-docs-site.sh)
elif [[ "$SITE_REBUILD" -eq 1 ]]; then
    MODE="site-build"
    COMMAND=(php docs-site/build.php)
fi

echo "Detected ${#CHANGED_FILES[@]} total changed file(s); ${#DOCS_FILES[@]} affect the docs workflow."

if [[ ${#DOCS_FILES[@]} -gt 0 ]]; then
    printf 'Docs-related changes:\n'
    printf '  - %s\n' "${DOCS_FILES[@]}"
fi

echo "Selected mode: ${MODE}"

if [[ "$MODE" == "none" ]]; then
    echo "No docs refresh needed."
    exit 0
fi

if [[ "$DRY_RUN" -eq 1 ]]; then
    printf 'Command:'
    printf ' %q' "${COMMAND[@]}"
    printf '\n'
    exit 0
fi

exec "${COMMAND[@]}"
