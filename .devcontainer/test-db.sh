#!/usr/bin/env bash

# Run CI's database leg (.github/workflows/ci.yml, job "databases") against one
# of the devcontainer's servers.
#
# Usage:
#   bash .devcontainer/test-db.sh mysql
#   bash .devcontainer/test-db.sh mariadb
#   bash .devcontainer/test-db.sh pgsql
#   bash .devcontainer/test-db.sh pgsql --filter=Search   # extra args go to Pest
#   bash .devcontainer/test-db.sh mysql packages/table/tests/Feature   # paths replace the default set

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

case "${1:-}" in
    mysql)   export DB_CONNECTION=mysql DB_HOST=mysql    DB_PORT=3306 DB_USERNAME=root ;;
    mariadb) export DB_CONNECTION=mariadb DB_HOST=mariadb DB_PORT=3306 DB_USERNAME=root ;;
    pgsql)   export DB_CONNECTION=pgsql DB_HOST=postgres DB_PORT=5432 DB_USERNAME=postgres ;;
    *)
        echo "Usage: $0 mysql|mariadb|pgsql [pest args...]" >&2
        exit 1
        ;;
esac
shift

export DB_DATABASE=wire_test DB_PASSWORD=password

paths=()
for arg in "$@"; do
    [[ "$arg" != -* && -e "$arg" ]] && paths+=("$arg")
done

if [[ ${#paths[@]} -eq 0 ]]; then
    set -- \
        packages/core/tests/Feature \
        packages/forms/tests/Feature \
        packages/forms/tests/Standalone \
        packages/table/tests/Feature \
        packages/sortable/tests/Feature \
        tests/Integration \
        packages/table/tests/Unit/Services/TableQueryServiceTest.php \
        "$@"
fi

exec vendor/bin/pest "$@"
