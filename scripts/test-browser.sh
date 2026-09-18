#!/usr/bin/env bash

# Run the Pest browser tests (the pilot in packages/module-auth/tests/Browser).
#
# pestphp/pest-plugin-browser is not in composer.json on purpose: it needs PHP
# 8.4 and Symfony 8, and this stack supports PHP 8.2 and Laravel 12, so as a
# require-dev it made `composer install` unresolvable on half the CI matrix.
# It is installed by hand where it is wanted, and composer.json put back after.

set -euo pipefail
cd "$(dirname "$0")/.."

if [ ! -d vendor/pestphp/pest-plugin-browser ]; then
    echo "pestphp/pest-plugin-browser is not installed. It needs PHP 8.4+; install it with:"
    echo
    echo "  composer require --dev pestphp/pest-plugin-browser:^5.0 && git checkout composer.json"
    echo "  npx playwright install chromium"
    exit 1
fi

exec vendor/bin/pest --configuration packages/module-auth/phpunit.xml packages/module-auth/tests/Browser "$@"
