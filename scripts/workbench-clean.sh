#!/usr/bin/env bash

# Reset the testbench skeleton the workbench runs on, then build it again.
#
# `vendor/orchestra/testbench-core/laravel/` is a real Laravel application that
# every package install writes into, and nothing ever cleans up after it. Three
# kinds of leftover, and none of them announces itself:
#
#   - Published views. Laravel resolves `resources/views/vendor/wire-*` before
#     the package's own views, so every Pest run and the preview server render a
#     frozen snapshot and a Blade edit appears to do nothing.
#   - Published migrations. Each install drops another timestamped copy into
#     `database/migrations/`, which then collides with `workbench/database/
#     migrations`; from the second run on, `migrate-fresh` dies on "table already
#     exists" and leaves the preview database half-migrated and unseeded — so the
#     CDP drivers fail wholesale for reasons that look like code bugs.
#   - The layout scaffolds. `wire-admin:install` and `wire-module-auth:install`
#     each write a layout view that belongs to the *application* rather than to a
#     package — `components/layouts/admin.blade.php` and `auth.blade.php` — plus,
#     for the admin, a published provider and the line naming it in
#     `bootstrap/providers.php`. None of the rules above catch them, and a
#     left-behind `auth.blade.php` fails `wire-module-auth`'s own suite on the
#     assertion that the installer says "Wrote" rather than "already exists" —
#     on the first run after somebody used the real installer, and only then. Left behind they are worse than silent: the
#     installer reports "already exists" where it should say "wrote", and
#     `wire-admin`'s own suite then fails on a tree nobody has touched.
#   - Third-party config the setup steps publish. `wire:install` now runs
#     `fortify:install` and `permission-extended:install` on the way through, and
#     those leave `config/fortify.php`, `config/permission.php` and an
#     `App\Providers\FortifyServiceProvider` behind — none of them named `wire-*`.
#     A left-behind `config/fortify.php` reads `config('app.url')` at boot and
#     deprecation-warns every test in every suite, which is a long way from where
#     it came from.
#
# Published config, the compiled views and the package/service caches go too:
# they are all regenerable, and a stale one outlives the change that invalidated
# it. The skeleton's own migrations (`0001_01_01_*`) are left alone.
#
# Usage:
#   bash scripts/workbench-clean.sh             # clean, drop the database, rebuild
#   bash scripts/workbench-clean.sh --keep-db   # leave database.sqlite in place
#   bash scripts/workbench-clean.sh --no-build  # clean only, skip workbench:build
#
# Exit 0 = the skeleton is clean (and rebuilt, unless --no-build).

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

SKELETON="vendor/orchestra/testbench-core/laravel"

KEEP_DB=0
BUILD=1

for arg in "$@"; do
    case "$arg" in
        --keep-db) KEEP_DB=1 ;;
        --no-build) BUILD=0 ;;
        -h|--help) sed -n '3,44p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $arg (try --help)" >&2; exit 1 ;;
    esac
done

if [[ ! -d "$SKELETON" ]]; then
    echo "No testbench skeleton at $SKELETON — run composer install first." >&2
    exit 1
fi

# A preview server holds the application this is about to delete underneath it,
# and testbench swaps `bootstrap/cache/testbench.yaml` while it serves. Cleaning
# around a live one leaves both halves wrong, so say so rather than race it.
if pgrep -f "testbench serve" >/dev/null 2>&1; then
    echo "A 'testbench serve' is running. Stop it first — cleaning under a live" >&2
    echo "preview server leaves the skeleton and the running app disagreeing." >&2
    exit 1
fi

removed() {
    local label="$1" count="$2"
    if [[ "$count" -gt 0 ]]; then
        printf '  %-22s %s\n' "$label" "$count"
    else
        printf '  %-22s —\n' "$label"
    fi
}

echo "Cleaning $SKELETON"

views=$(find "$SKELETON/resources/views/vendor" -maxdepth 1 -name 'wire-*' 2>/dev/null | wc -l | tr -d ' ')
rm -rf "$SKELETON"/resources/views/vendor/wire-*
removed "published views" "$views"

# `wire-*` plus the third-party config the setup steps publish. Named one by one
# rather than by a wider glob: the rest of `config/` is the skeleton's own.
configs=$(find "$SKELETON/config" -maxdepth 1 \( -name 'wire-*.php' -o -name 'fortify.php' -o -name 'permission.php' -o -name 'permission-extended.php' \) 2>/dev/null | wc -l | tr -d ' ')
find "$SKELETON/config" -maxdepth 1 \( -name 'wire-*.php' -o -name 'fortify.php' -o -name 'permission.php' -o -name 'permission-extended.php' \) -delete 2>/dev/null
removed "published config" "$configs"

migrations=$(find "$SKELETON/database/migrations" -maxdepth 1 -name '*.php' 2>/dev/null | wc -l | tr -d ' ')
find "$SKELETON/database/migrations" -maxdepth 1 -name '*.php' -delete 2>/dev/null
removed "published migrations" "$migrations"

scaffold=0

for file in \
    "$SKELETON/resources/views/components/layouts/admin.blade.php" \
    "$SKELETON/resources/views/components/layouts/auth.blade.php" \
    "$SKELETON/app/Providers/WireAdminServiceProvider.php" \
    "$SKELETON/app/Providers/FortifyServiceProvider.php"
do
    if [[ -f "$file" ]]; then
        rm -f "$file"
        scaffold=$((scaffold + 1))
    fi
done

# The line, not the file: `bootstrap/providers.php` is the application's own and
# may name providers that have nothing to do with this.
for provider in WireAdminServiceProvider FortifyServiceProvider; do
    if grep -q "$provider" "$SKELETON/bootstrap/providers.php" 2>/dev/null; then
        # `-i.bak` rather than `-i ''`: GNU and BSD sed disagree about the bare
        # form and this script runs on both.
        sed -i.bak "/$provider/d" "$SKELETON/bootstrap/providers.php"
        rm -f "$SKELETON/bootstrap/providers.php.bak"
        scaffold=$((scaffold + 1))
    fi
done

removed "app scaffolds" "$scaffold"

compiled=$(find "$SKELETON/storage/framework/views" -maxdepth 1 -name '*.php' 2>/dev/null | wc -l | tr -d ' ')
find "$SKELETON/storage/framework/views" -maxdepth 1 -name '*.php' -delete 2>/dev/null
removed "compiled views" "$compiled"

caches=$(find "$SKELETON/bootstrap/cache" -maxdepth 1 \( -name 'packages.php' -o -name 'services.php' \) 2>/dev/null | wc -l | tr -d ' ')
rm -f "$SKELETON/bootstrap/cache/packages.php" "$SKELETON/bootstrap/cache/services.php"
removed "package/service cache" "$caches"

rm -f "$SKELETON"/storage/logs/*.log

if [[ "$KEEP_DB" -eq 0 ]]; then
    rm -f "$SKELETON/database/database.sqlite"
    printf '  %-22s dropped\n' "database.sqlite"
else
    printf '  %-22s kept (--keep-db)\n' "database.sqlite"
fi

if [[ "$BUILD" -eq 0 ]]; then
    echo
    echo "Skipped the rebuild (--no-build). Run 'vendor/bin/testbench workbench:build'"
    echo "before the drivers or the preview server: without it there is no database."
    exit 0
fi

echo
echo "Rebuilding the workbench"
vendor/bin/testbench workbench:build || exit 1
