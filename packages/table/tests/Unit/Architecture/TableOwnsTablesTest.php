<?php

declare(strict_types=1);

/*
 * wire-table holds table concerns. The application owner layer does not live
 * here, and neither will the ERP axes that come after it.
 *
 * This exists because the boundary was crossed once already: the resource
 * contracts, the registry and ListPage all shipped inside this package before
 * the cost was noticed — an application whose resource has a form and no list
 * had to install a table package, with its assets, migrations, config and
 * Livewire synthesizer, to declare that resource at all.
 *
 * The rule is not "no file may say Resource". A table may perfectly well be
 * *given* to a resource; what it must not do is own the concept. So this asserts
 * on the two things that mark ownership — the namespace a file declares, and the
 * types it imports.
 *
 * Where the owner layer actually lives:
 *   identity + registry   wire-core   Core\Resources
 *   ProvidesResourceForm  wire-forms  (names Form)
 *   …Infolist             wire-core   Infolists\Contracts (names Infolist)
 *   …Table + every Page   wire-panels (names Table, sits above every component)
 */
$ownerConcepts = [
    'Resource' => 'the resource layer (wire-core for identity, wire-panels for surfaces and pages)',
    'ResourceRegistry' => 'wire-core Core\Resources',
    'Workspace' => 'the owner layer',
    'NavigationItem' => 'the owner layer',
    'Workflow' => 'the workflow axis (V2.4), not this package',
    'StateMachine' => 'the workflow axis (V2.4), not this package',
];

/**
 * @return array<int, string>
 */
function ttSourceFiles(): array
{
    $root = dirname(__DIR__, 3).'/src';
    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

it('declares no namespace belonging to the owner layer', function () use ($ownerConcepts) {
    $offenders = [];

    foreach (ttSourceFiles() as $file) {
        $source = (string) file_get_contents($file);

        if (! preg_match('/^namespace\s+([^;]+);/m', $source, $matches)) {
            continue;
        }

        foreach (array_keys($ownerConcepts) as $concept) {
            // Namespace segment, not substring: a hypothetical `Resourcing`
            // should not trip, and neither should a class *name* — only the
            // folder a file lives in, which is what declares ownership.
            if (preg_match('/\\\\'.$concept.'s?(\\\\|$)/', $matches[1]) === 1) {
                $offenders[] = basename($file).' → '.$matches[1];
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('imports nothing from the owner layer', function () use ($ownerConcepts) {
    $offenders = [];

    foreach (ttSourceFiles() as $file) {
        $source = (string) file_get_contents($file);

        preg_match_all('/^use\s+([^;]+);/m', $source, $matches);

        foreach ($matches[1] as $import) {
            foreach ($ownerConcepts as $concept => $home) {
                if (str_contains($import, 'Resources\\') || preg_match('/\b'.$concept.'\b/', $import) === 1) {
                    $offenders[] = basename($file).' imports '.$import.' — belongs to '.$home;
                    break;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('ships no resource page view', function () {
    // A Livewire page for the owner layer is wire-panels' to render, and a view
    // is how one would sneak back in without any PHP naming it.
    $views = dirname(__DIR__, 3).'/resources/views';

    expect(glob($views.'/resources'))->toBe([])
        ->and(glob($views.'/**/*page*.blade.php'))->toBe([]);
});
