// Torchlight syntax highlighting for the generated docs site.
// Runs after `php docs-site/build.php` against the static HTML in dist/.
// https://torchlight.dev/docs
module.exports = {
    // Read token. Override with the TORCHLIGHT_TOKEN env var in CI if you
    // prefer to keep it out of version control. Use `||` (not `??`) so an
    // unset-but-present CI secret (empty string, which is NOT nullish) still
    // falls back to this token instead of authenticating with an empty string —
    // an empty token makes every block hit the un-highlighted fallback path.
    token: process.env.TORCHLIGHT_TOKEN || 'torch_WPj4fApEpg8CwpSjLA40P6fjWy3HOcfHkppqfFz0',

    // Dark theme that matches the docs site code surface.
    theme: 'github-dark',

    // Cache highlighted blocks so re-runs only hit the API for changed code.
    cache: 'docs-site/.torchlight-cache',

    options: {
        // The docs site renders its own copy button + chrome, keep it lean.
        lineNumbers: false,
        // Torchlight sets the theme background on the <pre> via this style.
        diffIndicators: true,
    },
};
