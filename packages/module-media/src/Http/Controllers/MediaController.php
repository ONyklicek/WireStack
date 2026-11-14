<?php

declare(strict_types=1);

namespace NyonCode\WireModuleMedia\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NyonCode\WireModuleMedia\Models\Media;
use NyonCode\WireModuleMedia\Support\MediaAccess;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serving a file the disk will not hand out itself.
 *
 * A `public` disk answers a URL and the browser fetches the file directly, which
 * is the fastest thing that can happen and is left exactly as it was. A private
 * disk — `local`, an S3 bucket with no public access — has no such URL, and the
 * library's answer used to be `null`: the grid showed blank tiles and the
 * download link was missing, with nothing saying why.
 *
 * So this exists for the other case, and only for it. It is the difference
 * between a media module that can hold a contract and one that can only hold a
 * logo.
 *
 * **Every request is authorised.** The route is behind whatever middleware the
 * application configured (`web` and `auth` by default), and the file itself is
 * checked against the policy through {@see MediaAccess} — because middleware
 * answers "is anybody signed in" and a policy answers "may *this* person see
 * *this* file", and only the second one is the question.
 */
class MediaController
{
    /**
     * Stream the file for display — what an `<img src>` and a preview ask for.
     *
     * `?variant=tile` asks for a named scaled copy instead of the original. That
     * is the only way a private disk gets thumbnails at all: it has no address
     * of its own, so without a name here the route could only stream the full
     * file and a grid of a hundred private photographs stayed a hundred
     * full-size photographs.
     */
    public function show(Request $request, Media $media): Response
    {
        return $this->stream($media, 'inline', $request->query('variant'));
    }

    /** The same bytes, as a download. */
    public function download(Request $request, Media $media): Response
    {
        return $this->stream($media, 'attachment');
    }

    private function stream(Media $media, string $disposition, mixed $variant = null): Response
    {
        abort_if(MediaAccess::denies('view', $media), 403);

        $disk = Storage::disk((string) $media->disk);

        // A named size, and the original whenever there is no such copy: a
        // library that has not remade its thumbnails must show the picture it
        // has rather than a 404.
        $path = is_string($variant) && $variant !== ''
            ? ($media->thumbPathFor($variant) ?? (string) $media->path)
            : (string) $media->path;

        // The row can outlive the file — a disk emptied by hand, a restore from
        // a backup that skipped storage. A 404 says which of the two is missing
        // far better than a stream of nothing.
        abort_unless($disk->exists($path), 404);

        $type = $path === (string) $media->path
            ? (string) ($media->mime_type ?: 'application/octet-stream')
            : ((string) $disk->mimeType($path) ?: 'application/octet-stream');

        // A file a browser would run rather than show — an SVG, an HTML page —
        // opened from this route runs on the application's own origin, with the
        // signed-in user's session: stored XSS by upload. It is never drawn
        // inline, and the sandbox keeps it inert even where something does.
        $scriptable = self::scriptable($type);

        if ($scriptable) {
            $disposition = 'attachment';
        }

        return new StreamedResponse(
            static function () use ($disk, $path): void {
                $stream = $disk->readStream($path);

                if (is_resource($stream)) {
                    fpassthru($stream);
                    fclose($stream);
                }
            },
            200,
            [
                // The thumbnail's own type and length, not the original's: a
                // WebP served as `image/png` with the original's byte count is
                // a picture some browsers refuse and every proxy mistrusts.
                'Content-Type' => $type,
                'Content-Length' => (string) ($path === (string) $media->path ? $media->size : $disk->size($path)),
                // The stored name, not the path: a file downloaded as
                // `9f2a1c8e.pdf` is a file nobody can find again. Encoded the
                // way RFC 6266 says, with an ASCII fallback — a quote or a line
                // break in an uploaded name must not end the header.
                'Content-Disposition' => self::disposition($disposition, (string) $media->name),
                // The type above is the one stored for the file, and the
                // browser is to believe it rather than guess from the bytes.
                'X-Content-Type-Options' => 'nosniff',
                // Private, because the whole reason this route exists is that the
                // file is not public — a shared cache holding it would undo the
                // policy check above.
                'Cache-Control' => 'private, max-age=3600',
            ] + ($scriptable ? ['Content-Security-Policy' => "default-src 'none'; sandbox"] : []),
        );
    }

    /** Whether a browser runs this type rather than displaying it. */
    private static function scriptable(string $type): bool
    {
        $type = strtolower(trim(explode(';', $type)[0]));

        return in_array($type, [
            'text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml', 'application/xml',
            'text/javascript', 'application/javascript', 'application/ecmascript', 'text/ecmascript',
        ], true) || str_ends_with($type, '+xml');
    }

    private static function disposition(string $disposition, string $name): string
    {
        $name = str_replace(["\r", "\n", "\0", '/', '\\'], ' ', $name);
        $name = trim($name) === '' ? 'file' : $name;
        $fallback = trim((string) preg_replace('/[^\x20-\x7e]|["%]/', '_', Str::ascii($name))) ?: 'file';

        return HeaderUtils::makeDisposition($disposition, $name, $fallback);
    }
}
