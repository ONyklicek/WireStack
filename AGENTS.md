# AGENTS.md

<!-- laravel-package-toolkit:start -->

## Building this package with laravel-package-toolkit

This package extends `PackageServiceProvider` and describes itself in one method,
`configure(Packager $packager)`. Before writing or changing that method, read the complete
API reference that ships with the installed release:

[vendor/nyoncode/laravel-package-toolkit/ai/AGENTS.md](vendor/nyoncode/laravel-package-toolkit/ai/AGENTS.md)

It covers every `hasX()` builder, which resources load vs. publish vs. both, how paths
resolve relative to the provider file, the publish-tag format, and the mistakes that fail
silently. Prefer it over recalling the API — it matches the version in `composer.lock`.

<!-- laravel-package-toolkit:end -->
