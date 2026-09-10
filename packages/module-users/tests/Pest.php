<?php

declare(strict_types=1);

// TestCase binding is configured in tests/Pest.php at the monorepo root.
//
// Helpers do not go here: Pest loads the root `Pest.php` and not this one, so a
// function declared here is never defined. Shared test setup for this package
// lives in `Tests\Support\Access`, which autoloads like any other class.
