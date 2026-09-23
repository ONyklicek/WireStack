#!/usr/bin/env node
/*
 * The guard every `build:*-assets` script opens with.
 *
 * It exists because of the order the forms build runs in:
 *
 *   rm -rf packages/forms/dist/tiptap && esbuild … && esbuild … && esbuild …
 *
 * The `rm` is not optional — `--splitting` writes `chunk-[hash].js`, and a
 * stale chunk from an earlier build would otherwise be left beside the new one
 * for ever. But it runs *first*, so anything that stops esbuild from starting
 * deletes four tracked artifacts and then reports a failure that says nothing
 * about them. That happened: `node_modules/esbuild/bin/esbuild` was the
 * Apple-Silicon binary on an Intel machine, every build died with `Bad CPU type
 * in executable`, and the visible damage was three missing files in `git
 * status` rather than the one line that explains it.
 *
 * So: ask esbuild whether it can run at all, before the first destructive step.
 * A failure here costs nothing and names its own fix; a failure a line later
 * costs the artifacts.
 *
 * This is deliberately *not* a version check. Which esbuild builds the bundles
 * is `package-lock.json`'s business, and a second opinion here would be one
 * more thing to keep in step. The only question asked is "does it start".
 */

import { spawnSync } from 'node:child_process'
import { existsSync, readFileSync } from 'node:fs'

const binary = 'node_modules/esbuild/bin/esbuild'

const fail = (...lines) => {
    console.error(`\nesbuild cannot run, so no bundle was built — and nothing was deleted.\n`)
    lines.forEach((line) => console.error(`  ${line}`))
    console.error('')
    process.exit(1)
}

if (! existsSync(binary)) {
    fail(
        `${binary} is not there.`,
        'Run `npm install`.',
    )
}

const probe = spawnSync(binary, ['--version'], { encoding: 'utf8' })

if (probe.status === 0) {
    process.exit(0)
}

/*
 * Mach-O and ELF both name their architecture in the first bytes of the file,
 * and reading it is what turns "Bad CPU type in executable" into a sentence
 * somebody can act on. The magic numbers are the two 64-bit little-endian
 * forms this repository is ever built on; anything else falls through to the
 * generic message, which is still correct.
 */
const architecture = () => {
    try {
        const head = readFileSync(binary).subarray(0, 8)

        if (head[0] === 0xcf && head[1] === 0xfa && head[2] === 0xed && head[3] === 0xfe) {
            return { 0x07: 'x86_64', 0x0c: 'arm64' }[head[4]] ?? null   // Mach-O 64-bit
        }

        if (head[0] === 0x7f && head.subarray(1, 4).toString() === 'ELF') {
            return { 0x3e: 'x86_64', 0xb7: 'arm64' }[head[18]] ?? null  // ELF 64-bit
        }
    } catch {
        // Unreadable is not worth a second failure; the generic message stands.
    }

    return null
}

const built = architecture()
const running = process.arch === 'x64' ? 'x86_64' : process.arch

if (built !== null && built !== running) {
    fail(
        `${binary} is built for ${built}, but node is running as ${running}.`,
        'The installed binary is for the wrong platform — the optional dependency',
        'that matched the machine the install ran on, not this one.',
        '',
        'Fix it with:  npm rebuild esbuild',
        '',
        'If that fails, the platform package it copies from is broken too —',
        '`bin/esbuild` is a hard link into `@esbuild/<platform>`, so anything that',
        'overwrote it in place overwrote both. Then:',
        '',
        '  rm -rf node_modules/esbuild node_modules/@esbuild && npm install',
        '',
        '(Neither touches package.json or package-lock.json.)',
    )
}

fail(
    (probe.stderr || probe.error?.message || `exit code ${probe.status}`).trim(),
    '',
    'Try `npm rebuild esbuild`, then `npm install` if that does not help.',
)
