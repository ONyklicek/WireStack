#!/usr/bin/env bash

# Install the whole stack into a clean Laravel application, the way a new user
# does, and check that the admin is ready to use afterwards.
#
# Every other gate runs against the Testbench skeleton or the workbench, and
# both are set up by hand: routes, Fortify's actions, a home to land on. None of
# that is what `wire:install` leaves behind, so a gap in the installer is
# invisible to them. This is the one check that starts from `laravel new`:
#
#   1. a fresh laravel/laravel, with this monorepo as path repositories;
#   2. every package and module required, and `wire:install --all` run;
#   3. a super-admin and a plain user created with `wire:user`;
#   4. the application served, and walked over HTTP: a guest is sent to sign
#      in, signing in lands in the admin (not on Fortify's `/home`), every
#      entry in the sidebar answers, sign-out works, and a user with no
#      permissions is refused rather than shown an error;
#   5. the same sign-in done in a real browser (scripts/clean-install/
#      verify-browser.mjs): the form works, the admin renders styled, and the
#      console stays clean;
#   6. with --tenancy, a tenant zone added the way docs/panels/tenancy.md adds
#      one — WIRE_TENANCY, HasTenants on the user model, a zone in
#      routes/web.php — and walked: somebody in no company, registering one,
#      its pages, a stranger's 404, and an invitation accepted (ADR 0040).
#
# Found on its first run: signing in went to Fortify's `/home`, which nothing
# routes, and the panel's own prefix was a 404 as well.
#
# Usage:
#   bash scripts/verify-clean-install.sh              # build, check, remove the app
#   bash scripts/verify-clean-install.sh --keep       # leave the app behind to poke at
#   bash scripts/verify-clean-install.sh --tenancy    # and then a tenant zone over it
#   APP_DIR=/tmp/wire-clean bash scripts/verify-clean-install.sh --keep
#   PORT=8096 bash scripts/verify-clean-install.sh
#
# Needs network access (composer create-project, npm install) and Chrome for
# step 5; set SKIP_BROWSER=1 to stop after the HTTP checks.
#
# Exit 0 = every check passed; 1 = at least one failed; 2 = setup itself failed.

set -uo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
KEEP=0
TENANCY=0
for arg in "$@"; do
    case "$arg" in
        --keep) KEEP=1 ;;
        --tenancy) TENANCY=1 ;;
        *) printf 'unknown option: %s\n' "$arg" >&2; exit 2 ;;
    esac
done

APP_DIR="${APP_DIR:-$(mktemp -d "${TMPDIR:-/tmp}/wire-clean-install.XXXXXX")/app}"
PORT="${PORT:-8096}"
ORIGIN="http://127.0.0.1:${PORT}"
LOG="${APP_DIR%/app}/verify.log"

ADMIN_EMAIL='admin@example.test'
PLAIN_EMAIL='plain@example.test'
PASSWORD='Correct-Horse-Battery-9'

pass=0
failed=()
SERVER_PID=""

check() { # name, condition-exit-code, detail
    if [[ "$2" -eq 0 ]]; then
        printf '  PASS  %s\n' "$1"
        pass=$((pass + 1))
    else
        printf '  FAIL  %s%s\n' "$1" "${3:+ — $3}"
        failed+=("$1")
    fi
}

step() { printf '\n== %s\n' "$1"; }

die() {
    printf '\nSETUP FAILED: %s\n' "$1" >&2
    [[ -f "$LOG" ]] && tail -30 "$LOG" >&2
    exit 2
}

cleanup() {
    if [[ -n "$SERVER_PID" ]] && kill -0 "$SERVER_PID" 2>/dev/null; then
        kill "$SERVER_PID" 2>/dev/null
        wait "$SERVER_PID" 2>/dev/null
    fi
    # `artisan serve` forks the PHP built-in server; take that down too.
    lsof -ti "tcp:${PORT}" -sTCP:LISTEN 2>/dev/null | xargs kill 2>/dev/null

    if [[ $KEEP -eq 0 ]]; then
        rm -rf "${APP_DIR%/app}"
    else
        printf '\nThe application is left at %s\n' "$APP_DIR"
    fi
}
trap cleanup EXIT

mkdir -p "$(dirname "$APP_DIR")"
: > "$LOG"

# ── 1. A clean application ──────────────────────────────────────────────────
step "A fresh Laravel application in $APP_DIR"

composer create-project laravel/laravel "$APP_DIR" --no-interaction --prefer-dist >>"$LOG" 2>&1 \
    || die 'composer create-project failed'

cd "$APP_DIR" || die "no $APP_DIR"

php -r '
    $c = json_decode(file_get_contents("composer.json"), true);
    $c["repositories"] = [["type" => "path", "url" => $argv[1]."/packages/*", "options" => ["symlink" => true]]];
    $c["minimum-stability"] = "dev";
    $c["prefer-stable"] = true;
    file_put_contents("composer.json", json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
' "$ROOT_DIR" || die 'could not add the path repositories'

# ── 2. Everything a new user would install ──────────────────────────────────
step 'Requiring the stack and every module'

COMPOSER_ROOT_VERSION=dev-main composer require --no-interaction -W \
    'nyoncode/wire-suite:*@dev' \
    'nyoncode/wire-module-auth:*@dev' \
    'nyoncode/wire-module-users:*@dev' \
    'nyoncode/wire-module-settings:*@dev' \
    'nyoncode/wire-module-tenants:*@dev' \
    'nyoncode/wire-module-audit:*@dev' \
    'nyoncode/wire-module-notifications:*@dev' \
    'nyoncode/wire-module-media:*@dev' \
    'nyoncode/laravel-permission-extended' >>"$LOG" 2>&1 \
    || die 'composer require failed'

step 'php artisan wire:install --all'

php artisan wire:install --all --no-interaction >>"$LOG" 2>&1
install_code=$?
check 'wire:install --all finishes cleanly' "$install_code" "exit $install_code, see $LOG"

php artisan wire:user --name='Admin' --email="$ADMIN_EMAIL" --password="$PASSWORD" --super-admin --no-interaction >>"$LOG" 2>&1 \
    || die 'wire:user could not create the super-admin'
php artisan wire:user --name='Plain' --email="$PLAIN_EMAIL" --password="$PASSWORD" --no-interaction >>"$LOG" 2>&1 \
    || die 'wire:user could not create the plain user'

# ── 3. What the installer left in the application ───────────────────────────
step 'What the installer wrote'

home="$(php artisan tinker --execute='echo config("fortify.home");' 2>/dev/null | tail -1)"
[[ "$home" == '/admin' ]]
check "fortify.home points at the admin (/admin)" $? "it is '$home'"

# The prefix itself answers — named `wire.home` when nothing claims it, and the
# landing page's own name when something does. Since the installer writes a
# dashboard at the root, that is `wire.overview.index` here; asserting the name
# would be asserting which of the two is in play rather than that the address
# works at all.
php artisan route:list --path=admin 2>/dev/null | grep -qE 'GET\|HEAD[[:space:]]+admin[[:space:]]'
check 'the admin has an address of its own' $?

ls public/build/manifest.json >/dev/null 2>&1
check 'the frontend was built' $?

# No provider routes anything (ADR 0041): the screens exist only because the
# installer placed each group in the application's own route file.
for group in panel auth-codes; do
    grep -q "Route::wire('${group}')" routes/web.php
    check "routes/web.php places Route::wire('${group}')" $?
done
# Company screens belong to an application that uses tenancy, and this one
# does not (yet) — the installer must not have routed them.
! grep -q "Route::wire('tenants')" routes/web.php
check 'and no company routes in an application without tenancy' $?

# ── 4. Over HTTP ────────────────────────────────────────────────────────────
step "Serving on $ORIGIN"

php artisan serve --host=127.0.0.1 --port="$PORT" >>"$LOG" 2>&1 &
SERVER_PID=$!
for _ in $(seq 1 40); do
    curl -fsS -o /dev/null "$ORIGIN/login" 2>/dev/null && break
    sleep 0.5
done
curl -fsS -o /dev/null "$ORIGIN/login" || die 'the application never answered'

JAR="$(mktemp)"

# The CSRF token from a page, for the form posts below.
token_from() { curl -s -c "$JAR" -b "$JAR" "$1" | grep -o 'name="_token" value="[^"]*"' | head -1 | sed 's/.*value="//;s/"$//'; }
status_of() { curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$1"; }
location_of() { curl -s -o /dev/null -w '%{redirect_url}' -b "$JAR" "$1"; }

sign_in() { # email
    rm -f "$JAR"
    local token
    token="$(token_from "$ORIGIN/login")"
    curl -s -o /dev/null -w '%{redirect_url}' -c "$JAR" -b "$JAR" -X POST \
        --data-urlencode "_token=$token" --data-urlencode "email=$1" --data-urlencode "password=$PASSWORD" \
        "$ORIGIN/login"
}

step 'A guest'

rm -f "$JAR"
[[ "$(location_of "$ORIGIN/admin")" == "$ORIGIN/login" ]]
check 'the admin sends a guest to sign in' $?
[[ "$(status_of "$ORIGIN/login")" == 200 ]]
check 'the sign-in screen answers' $?

step 'The super-admin'

landed="$(sign_in "$ADMIN_EMAIL")"
[[ "$landed" == "$ORIGIN/admin" ]]
check 'signing in lands on the admin, not on /home' $? "went to $landed"

# The installer writes a dashboard and points it at the admin's own address, so
# `/admin` is a page rather than a redirect. It used to forward to whichever
# screen sorted first in the sidebar — on a `--all` install that was the media
# library, which is not what signing in should open.
[[ "$(status_of "$ORIGIN/admin")" == 200 ]]
check 'the admin s address is a page of its own, not a forward' $? "HTTP $(status_of "$ORIGIN/admin")"

page="$(curl -s -b "$JAR" "$ORIGIN/admin")"
grep -q 'wire-widget-grid' <<<"$page"
check 'and that page is the dashboard the installer wrote' $?
grep -q 'data-wire="admin-sidebar"' <<<"$page"
check 'inside the admin shell, with its sidebar' $?
grep -q 'Overview' <<<"$page"
check 'which lists it first, above every module s group' $?
grep -Eq 'href="[^"]*/build/assets/[^"]*\.css"' <<<"$page"
check 'styled by the built stylesheet' $?

# Every link the sidebar offers, followed. A module that registered an entry
# it cannot serve is the kind of thing only a clean install shows. The menu's
# links are the `wire:navigate` ones; the tag spans lines, hence perl.
links="$(perl -0ne 'print "$1\n" while /href="([^"]+)"\s+wire:navigate/g' <<<"$page" \
    | grep "^$ORIGIN/admin/\|^/admin/" | sort -u)"
# (`/admin` itself is the brand link: a redirect by design, checked above.)
[[ -n "$links" ]]
check 'the sidebar offers pages' $?
for link in $links; do
    [[ "$link" == /* ]] && link="$ORIGIN$link"
    code="$(status_of "$link")"
    [[ "$code" == 200 ]]
    check "sidebar entry answers: ${link#"$ORIGIN"}" $? "HTTP $code"
done

token="$(grep -o 'name="_token" value="[^"]*"' <<<"$page" | head -1 | sed 's/.*value="//;s/"$//')"
out="$(curl -s -o /dev/null -w '%{redirect_url}' -c "$JAR" -b "$JAR" -X POST --data-urlencode "_token=$token" "$ORIGIN/logout")"
[[ "$(location_of "$ORIGIN/admin")" == "$ORIGIN/login" ]]
check 'signing out ends the session' $? "logout went to $out"

step 'A user with no permissions'

landed="$(sign_in "$PLAIN_EMAIL")"
[[ "$landed" == "$ORIGIN/admin" ]]
check 'lands on the admin as well' $? "went to $landed"
code="$(status_of "$ORIGIN/admin")"
target="$(location_of "$ORIGIN/admin")"
if [[ "$code" == 200 ]]; then
    # The dashboard the installer wrote guards nothing, so anybody signed in
    # opens it — which is a better landing than the 403 this used to accept.
    check 'and opens the dashboard, which asks for no permission' 0
elif [[ "$code" == 302 ]]; then
    [[ "$(status_of "$target")" == 200 ]]
    check 'and is taken to a page they may open' $? "$target answered $(status_of "$target")"
else
    [[ "$code" == 403 ]]
    check 'and is refused, not shown an error' $? "HTTP $code"
fi

rm -f "$JAR"

# ── 5. In a browser ─────────────────────────────────────────────────────────
if [[ "${SKIP_BROWSER:-0}" != 1 ]]; then
    step 'In a browser'

    CLEAN_ORIGIN="$ORIGIN" CLEAN_EMAIL="$ADMIN_EMAIL" CLEAN_PASSWORD="$PASSWORD" \
        node "$ROOT_DIR/scripts/clean-install/verify-browser.mjs"
    browser_code=$?
    check 'the browser pass' "$browser_code"
fi

# ── 6. A tenant zone ────────────────────────────────────────────────────────
if [[ $TENANCY -eq 1 ]]; then
    step 'A tenant zone, set up as docs/panels/tenancy.md sets one up'

    # What the docs ask of an application, and nothing the workbench does for
    # it: tenancy on, the user model answering HasTenants, a zone in the routes.
    echo 'WIRE_TENANCY=true' >> .env
    php -r '
        $file = "app/Models/User.php";
        $source = file_get_contents($file);
        $contract = "\\NyonCode\\WirePanels\\Contracts\\HasTenants";
        $source = preg_match("/class User extends \\S+\\s+implements\\s/", $source)
            ? preg_replace("/(class User extends \\S+\\s+implements\\s+)/", "\\1".$contract.", ", $source, 1)
            : preg_replace("/(class User extends \\S+)/", "\\1 implements ".$contract, $source, 1);
        $source = preg_replace("/(class User[^{]*\\{)/", "\\1\n    use \\NyonCode\\WirePanels\\Concerns\\InteractsWithTenants;\n", $source, 1);
        file_put_contents($file, $source);
    ' || die 'could not give the user model HasTenants'
    cat >> routes/web.php <<'PHP'

// A tenant zone (docs/panels/tenancy.md): the company in the path, and the
// company screens outside it.
Route::middleware(['web', 'auth'])->prefix('app')
    ->group(fn () => Route::wire('panel', zone: 'app', tenant: 'path', only: ['company', 'members']));
Route::middleware(['web', 'auth'])->prefix('tenants')->group(fn () => Route::wire('tenants'));
PHP

    php artisan route:list --path=app 2>/dev/null | grep -q 'app/{tenant}/members'
    check 'the zone routes the companies module s pages' $?

    # The server read .env when it started.
    kill "$SERVER_PID" 2>/dev/null; wait "$SERVER_PID" 2>/dev/null
    lsof -ti "tcp:${PORT}" -sTCP:LISTEN 2>/dev/null | xargs kill 2>/dev/null
    php artisan serve --host=127.0.0.1 --port="$PORT" >>"$LOG" 2>&1 &
    SERVER_PID=$!
    for _ in $(seq 1 40); do
        curl -fsS -o /dev/null "$ORIGIN/login" 2>/dev/null && break
        sleep 0.5
    done

    tinker() { php artisan tinker --execute="$1" 2>>"$LOG" | tail -1; }

    sign_in "$ADMIN_EMAIL" >/dev/null
    code="$(status_of "$ORIGIN/app")"
    page="$(curl -s -b "$JAR" "$ORIGIN/app")"
    [[ "$code" == 200 ]] && grep -q 'data-wire="tenants-none"' <<<"$page"
    check 'somebody in no company is shown where to start, not an error' $? "HTTP $code"
    code="$(status_of "$ORIGIN/tenants/register")"
    [[ "$code" == 200 ]]
    check 'the registration screen answers' $? "HTTP $code"

    # Registering itself is a Livewire form, which curl cannot submit; the
    # browser drivers cover the form, this covers the application under it.
    tinker '(new NyonCode\WireModuleTenants\Actions\RegisterTenant)("Acme", "acme", App\Models\User::where("email", "'"$ADMIN_EMAIL"'")->first()); echo "ok";' | grep -q ok
    check 'a company registers' $?

    target="$(location_of "$ORIGIN/app")"
    [[ "$target" == "$ORIGIN/app/acme" ]]
    check 'the zone s own address sends its owner into it' $? "went to $target"
    target="$(location_of "$ORIGIN/app/acme")"
    [[ "$target" == "$ORIGIN/app/acme/"* && "$(status_of "$target")" == 200 ]]
    check 'and on to a page of the company that answers' $? "$target answered $(status_of "$target")"
    page="$(curl -s -b "$JAR" "$ORIGIN/app/acme/members")"
    grep -q "$ADMIN_EMAIL" <<<"$page" && ! grep -q "$PLAIN_EMAIL" <<<"$page"
    check 'the members screen lists the company s members, and only those' $?
    grep -Eq 'href="[^"]*/app/acme/company"' <<<"$page"
    check 'and the menu links into the same company' $?

    sign_in "$PLAIN_EMAIL" >/dev/null
    code="$(status_of "$ORIGIN/app/acme/company")"
    [[ "$code" == 404 ]]
    check 'a company that is not yours is a 404' $? "HTTP $code"

    invite="$(tinker 'URL::forceRootUrl("'"$ORIGIN"'"); $acme = NyonCode\WireModuleTenants\Models\Tenant::where("slug", "acme")->first(); $owner = App\Models\User::where("email", "'"$ADMIN_EMAIL"'")->first(); $invitation = (new NyonCode\WireModuleTenants\Actions\InviteMember)($acme, "'"$PLAIN_EMAIL"'", NyonCode\WireModuleTenants\Enums\MemberRole::Member, $owner); echo URL::temporarySignedRoute("wire-module-tenants.invitations.accept", $invitation->expires_at, ["invitation" => $invitation->getKey()]);')"
    [[ "$invite" == "$ORIGIN/tenants/invitations/"* ]]
    check 'an owner invites by address' $? "$invite"
    target="$(location_of "$invite")"
    [[ "$target" == "$ORIGIN/app/acme" ]]
    check 'accepting the invitation lands in the company' $? "went to $target"
    code="$(status_of "$ORIGIN/app/acme/company")"
    [[ "$code" == 200 ]]
    check 'and its pages open for the new member' $? "HTTP $code"
fi

echo
if [[ ${#failed[@]} -eq 0 ]]; then
    echo "All ${pass} checks passed."
    exit 0
fi

echo "${pass} passed, ${#failed[@]} failed. Install log: $LOG"
[[ $KEEP -eq 0 ]] && echo '(run again with --keep to leave the application behind)'
exit 1
