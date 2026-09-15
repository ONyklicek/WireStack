<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Setup\Contracts\SetupConsole;
use NyonCode\WireCore\Foundation\Setup\SetupOutcome;
use NyonCode\WireCore\Foundation\Setup\SetupRegistry;
use NyonCode\WireCore\Foundation\Setup\SetupState;
use NyonCode\WirePanels\Install\RegisterResourceRoutes;

/*
 * The line that turns registered resources into pages you can open.
 *
 * It is the last thing standing between a complete installation and a 404 on
 * every screen, and it was left as a sentence in three different installers
 * because the prefix and the middleware are genuinely the application's to
 * choose. So the step asks for them.
 */

/**
 * A console that answers from a script and records what it was told.
 *
 * @param  array<int, string>  $answers
 * @param  array<int, string>  $said
 */
function rrrConsole(array $answers = [], array &$said = [], bool $interactive = true): SetupConsole
{
    return new class($answers, $said, $interactive) implements SetupConsole
    {
        /**
         * @param  array<int, string>  $answers
         * @param  array<int, string>  $said
         */
        public function __construct(private array $answers, private array &$said, private bool $interactive) {}

        public function ask(string $question, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        public function secret(string $question): string
        {
            return array_shift($this->answers) ?? '';
        }

        public function confirm(string $question, bool $default = true): bool
        {
            return $default;
        }

        public function choose(string $question, array $options, ?string $default = null): string
        {
            return array_shift($this->answers) ?? (string) $default;
        }

        /**
         * @param  array<int|string, string>  $options
         * @param  array<int, int|string>  $default
         * @return array<int, int|string>
         */
        public function select(string $question, array $options, array $default = []): array
        {
            return $default;
        }

        public function note(string $message): void
        {
            $this->said[] = $message;
        }

        public function warn(string $message): void
        {
            $this->said[] = $message;
        }

        public function isInteractive(): bool
        {
            return $this->interactive;
        }
    };
}

/**
 * Run something with routes/web.php put back exactly as it was.
 *
 * The file is the Testbench skeleton's, shared by every suite in the monorepo —
 * a test that appends to it and walks away routes this framework into every
 * later run.
 */
function rrrRestoringRoutes(Closure $body): void
{
    $path = base_path('routes/web.php');
    $before = is_file($path) ? (string) file_get_contents($path) : null;

    try {
        $body();
    } finally {
        $before === null ? @unlink($path) : file_put_contents($path, $before);
    }
}

beforeEach(function () {
    config()->set('wire-panels.routes.enabled', false);
});

it('is contributed by this package', function () {
    expect(SetupRegistry::instance()->all())->toContain(RegisterResourceRoutes::class);
});

it('is pending while nothing routes the resources', function () {
    rrrRestoringRoutes(function () {
        file_put_contents(base_path('routes/web.php'), "<?php\n");

        $step = new RegisterResourceRoutes;

        expect($step->state())->toBe(SetupState::Pending)
            ->and($step->summary())->toContain('Route::wireResources()')
            ->and($step->label())->toBe('Routes')
            ->and($step->sort())->toBe(200);
    });
});

it('is done when the application already calls the macro', function () {
    rrrRestoringRoutes(function () {
        file_put_contents(base_path('routes/web.php'), "<?php\nRoute::wireResources();\n");

        $step = new RegisterResourceRoutes;

        expect($step->state())->toBe(SetupState::Done)
            ->and($step->summary())->toContain('already calls');
    });
});

it('is done when the config registers them instead', function () {
    config()->set('wire-panels.routes.enabled', true);

    $step = new RegisterResourceRoutes;

    expect($step->state())->toBe(SetupState::Done)
        ->and($step->summary())->toBe('registered from wire-panels.routes');
});

it('writes a group carrying the prefix and middleware that were chosen', function () {
    rrrRestoringRoutes(function () {
        file_put_contents(base_path('routes/web.php'), "<?php\n");
        $said = [];

        expect((new RegisterResourceRoutes)->apply(rrrConsole(['panel', 'web,auth,verified'], $said)))
            ->toBe(SetupOutcome::Applied);

        $written = (string) file_get_contents(base_path('routes/web.php'));

        expect($written)->toContain("Route::middleware(['web', 'auth', 'verified'])")
            ->and($written)->toContain("->prefix('panel')")
            ->and($written)->toContain('Route::wireResources();')
            ->and(implode("\n", $said))->toContain('/panel');

        // And now it knows it has been done.
        expect((new RegisterResourceRoutes)->state())->toBe(SetupState::Done);
    });
});

it('leaves the prefix off when the admin is the application root', function () {
    rrrRestoringRoutes(function () {
        file_put_contents(base_path('routes/web.php'), "<?php\n");
        $said = [];

        expect((new RegisterResourceRoutes)->apply(rrrConsole(['/', 'web'], $said)))->toBe(SetupOutcome::Applied);

        expect((string) file_get_contents(base_path('routes/web.php')))->not->toContain('->prefix(')
            ->and(implode("\n", $said))->toContain('application root');
    });
});

it('is blocked when there is no routes/web.php to add them to', function () {
    rrrRestoringRoutes(function () {
        @unlink(base_path('routes/web.php'));

        $step = new RegisterResourceRoutes;

        expect($step->state())->toBe(SetupState::Blocked)
            ->and($step->summary())->toContain('no routes/web.php');
    });
});

it('fails rather than pretending, and hands over the group it could not write', function () {
    rrrRestoringRoutes(function () {
        $path = base_path('routes/web.php');
        file_put_contents($path, "<?php\n");
        chmod($path, 0444);
        $said = [];

        try {
            expect((new RegisterResourceRoutes)->apply(rrrConsole(['admin', 'web'], $said)))
                ->toBe(SetupOutcome::Failed)
                ->and(implode("\n", $said))->toContain('Route::wireResources();');
        } finally {
            chmod($path, 0644);
        }
    });
});

it('belongs to its own package, so unticking that package skips it', function () {
    // What the first half of the installer was told, the second half obeys.
    expect((new RegisterResourceRoutes)->package())->toBe('nyoncode/wire-panels');
});

it('asks again for a prefix that would break the file it is written into', function () {
    // Both answers are PHP source in `routes/web.php`: a quote in either was a
    // parse error, and every request to the application with it.
    rrrRestoringRoutes(function () {
        file_put_contents(base_path('routes/web.php'), "<?php\n");
        $said = [];

        expect((new RegisterResourceRoutes)->apply(rrrConsole(["o'neil", 'team/{tenant}', "web,we'b", 'web,App\\Http\\Middleware\\Admin,can:admin'], $said)))
            ->toBe(SetupOutcome::Applied);

        $written = (string) file_get_contents(base_path('routes/web.php'));

        expect($written)->toContain("->prefix('team/{tenant}')")
            ->and($written)->toContain("Route::middleware(['web', 'App\\\\Http\\\\Middleware\\\\Admin', 'can:admin'])")
            ->and(implode("\n", $said))->toContain("Not a URL prefix: o'neil")
            ->and(implode("\n", $said))->toContain("Not a middleware name: we'b");

        exec(PHP_BINARY.' -l '.escapeshellarg(base_path('routes/web.php')), $output, $code);
        expect($code)->toBe(0);
    });
});

it('writes nothing after three answers it cannot use', function () {
    rrrRestoringRoutes(function () {
        file_put_contents(base_path('routes/web.php'), "<?php\n");
        $said = [];

        expect((new RegisterResourceRoutes)->apply(rrrConsole(['a b', "c'", 'd"'], $said)))->toBe(SetupOutcome::Skipped)
            ->and((string) file_get_contents(base_path('routes/web.php')))->toBe("<?php\n")
            ->and(implode("\n", $said))->toContain('Nothing was written');
    });
});
