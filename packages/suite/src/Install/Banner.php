<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install;

/**
 * The WireStack mark, as a terminal can draw it.
 *
 * The brand mark (`docs-site/assets/brand/wirestack-mark.svg`) is a wire routed
 * like an S — right along the top, back on itself, right again — with a terminal
 * at each open end. Three rows and two turns is the smallest drawing that still
 * reads as that rather than as a bracket, and the ends keep the dots because
 * they are what say "wire" instead of "arrow".
 *
 * Its own class rather than a heredoc in the command: the command is about
 * orchestrating installers, the mark is a picture, and the picture is the one
 * part of the output worth a test of its own.
 */
final readonly class Banner
{
    /**
     * The brand amber. Symfony degrades a hex colour to the nearest the
     * terminal has, so this costs nothing where it cannot be drawn.
     */
    private const WIRE = '#f59e0b';

    /**
     * @return array<int, string>
     */
    public function lines(): array
    {
        $wire = static fn (string $art): string => '<fg='.self::WIRE.'>'.$art.'</>';

        return [
            '',
            $wire('   ╭───────●'),
            $wire('   ╰─────╮').'      <options=bold>WireStack</>',
            $wire(' ●───────╯').'      <fg=gray>the whole stack, set up in one pass</>',
            '',
        ];
    }
}
