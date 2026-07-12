<?php

declare(strict_types=1);

/*
 * Docs-site build gate, as a normal test. Deterministic and offline — it does
 * NOT hit the Torchlight API (which needs a token). It catches every docs-build
 * failure class we have actually hit: leaked `[tl!]` markers in prose, invalid
 * closing fences, unbalanced fences, broken relative links, a missing build
 * asset (the strip script — the MODULE_NOT_FOUND that broke the deploy), a
 * build.php that errors, and a page whose structure leaked (content after
 * </html> — the `->prefix('$')` copy-source-leak signature).
 */

$root = dirname(__DIR__, 2);

/** @return list<string> every docs markdown file (EN + cs mirror) */
function docsMarkdownFiles(string $root): array
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/docs', FilesystemIterator::SKIP_DOTS));
    $files = [];
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'md') {
            $files[] = $f->getPathname();
        }
    }
    sort($files);

    return $files;
}

test('docs markdown has balanced, well-formed code fences and no leaked Torchlight markers', function () use ($root) {
    $issues = [];

    foreach (docsMarkdownFiles($root) as $file) {
        $rel = substr($file, strlen($root) + 1);
        $lines = explode("\n", (string) file_get_contents($file));
        $inCode = false;
        $fences = 0;

        foreach ($lines as $i => $line) {
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '```')) {
                $fences++;
                // A closing fence may only be `` ``` `` (+ trailing whitespace).
                if ($inCode && rtrim($trimmed) !== '```') {
                    $issues[] = "{$rel}:".($i + 1).' — invalid closing fence (trailing text after ```)';
                }
                $inCode = ! $inCode;
            } elseif (! $inCode && str_contains($line, '[tl!')) {
                $issues[] = "{$rel}:".($i + 1).' — leaked Torchlight marker in prose';
            }
        }

        if ($fences % 2 !== 0) {
            $issues[] = "{$rel} — unbalanced code fences ({$fences})";
        }
    }

    expect($issues)->toBe([]);
});

test('docs relative markdown links all resolve', function () use ($root) {
    $issues = [];

    foreach (docsMarkdownFiles($root) as $file) {
        $rel = substr($file, strlen($root) + 1);
        $text = (string) file_get_contents($file);

        if (preg_match_all('/\]\((?!https?:|#|mailto:)([^)#]+\.md)(#[^)]*)?\)/', $text, $matches)) {
            foreach ($matches[1] as $link) {
                $target = realpath(dirname($file).'/'.$link);
                if ($target === false || ! file_exists($target)) {
                    $issues[] = "{$rel} — broken relative link: {$link}";
                }
            }
        }
    }

    expect($issues)->toBe([]);
});

test('docs build assets referenced by the pipeline exist', function () use ($root) {
    // Referenced by package.json (docs:highlight) and .github/workflows/deploy-docs.yml.
    expect(file_exists($root.'/docs-site/scripts/strip-copy-source.mjs'))->toBeTrue();
    expect(file_exists($root.'/docs-site/scripts/verify-docs.mjs'))->toBeTrue();
    expect(file_exists($root.'/docs-site/build.php'))->toBeTrue();
});

test('docs-site build.php runs clean for every locale and produces structurally sound HTML', function () use ($root) {
    $locales = ['en'];
    $config = json_decode((string) file_get_contents($root.'/docs-site/config.json'), true);
    if (is_array($config['locales'] ?? null)) {
        $codes = array_values(array_filter(array_map(fn ($l) => $l['code'] ?? null, $config['locales'])));
        if ($codes !== []) {
            $locales = $codes;
        }
    }

    $tmp = sys_get_temp_dir().'/docs-build-test-'.bin2hex(random_bytes(4));

    foreach ($locales as $code) {
        $cmd = sprintf(
            'DOCS_BUILD_LOCALE=%s DOCS_DIST_DIR=%s %s %s 2>&1',
            escapeshellarg($code),
            escapeshellarg($tmp),
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root.'/docs-site/build.php'),
        );
        exec($cmd, $out, $exit);
        expect($exit)->toBe(0, "build.php failed for locale {$code}: ".implode("\n", $out));
    }

    // Structural soundness: no page may have content after </html> (the copy-source
    // leak signature), and every page must be non-trivial HTML.
    $htmls = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getExtension() === 'html') {
            $htmls[] = $f->getPathname();
        }
    }

    expect($htmls)->not->toBeEmpty();

    $leaks = [];
    foreach ($htmls as $html) {
        $content = (string) file_get_contents($html);
        $parts = explode('</html>', $content);
        if (count($parts) > 1 && trim(implode('</html>', array_slice($parts, 1))) !== '') {
            $leaks[] = substr($html, strlen($tmp) + 1).' — content after </html>';
        }
    }

    // Clean up before asserting so a failure does not leave the temp dir behind.
    exec('rm -rf '.escapeshellarg($tmp));

    expect($leaks)->toBe([]);
});
