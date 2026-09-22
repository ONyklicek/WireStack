<?php

declare(strict_types=1);

namespace NyonCode\WireModuleUsers\ValueObjects;

/**
 * What a `User-Agent` header says, to the depth a list of sessions needs it.
 *
 * Deliberately shallow. The card has to tell "Chrome on Windows" from "Safari
 * on an iPhone" so a person recognises their own devices; it does not need
 * versions, engines or bot detection, and a full parser would be a dependency
 * this module carries for one line of text. A header nothing here recognises
 * reads as unknown rather than as a guess.
 */
final readonly class UserAgent
{
    public function __construct(
        public ?string $platform,
        public ?string $browser,
        public bool $desktop,
    ) {}

    /**
     * Read one header.
     *
     * The order of both lists is the point: every Chromium browser also says
     * "Chrome" and "Safari", and every iPhone also says "Mac OS X", so the more
     * specific token has to be asked first.
     */
    public static function parse(?string $header): self
    {
        $header = (string) $header;

        return new self(
            platform: self::first($header, [
                'iOS' => '/iPhone|iPad|iPod/',
                'Android' => '/Android/',
                'Windows' => '/Windows/',
                'ChromeOS' => '/CrOS/',
                'macOS' => '/Macintosh|Mac OS X/',
                'Linux' => '/Linux/',
            ]),
            browser: self::first($header, [
                'Edge' => '/Edg(e|A|iOS)?\//',
                'Opera' => '/OPR\/|Opera/',
                'Firefox' => '/Firefox\/|FxiOS\//',
                'Chrome' => '/Chrome\/|CriOS\//',
                'Safari' => '/Safari\//',
            ]),
            // An empty header is not a phone: the card falls back to the
            // desktop icon, which is what most unknown clients are.
            desktop: preg_match('/Mobile|Android|iPhone|iPad|iPod/', $header) !== 1,
        );
    }

    /**
     * @param  array<string, string>  $patterns  name => pattern, most specific first
     */
    private static function first(string $header, array $patterns): ?string
    {
        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $header) === 1) {
                return $name;
            }
        }

        return null;
    }
}
