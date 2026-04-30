# ADR 0005: Tailwind 4 Support

## Status
Accepted

## Context
Tailwind CSS 4 was released with a new configuration format (CSS-based instead of JS-based) and new features. Our Blade components use Tailwind utility classes extensively. Should we support Tailwind 3, 4, or both?

## Decision
**Support Tailwind 3 only in v0.1.0. Tailwind 4 support deferred to v0.2.0.**

Reasons:
1. Tailwind 4 is still early in adoption. Most Laravel projects use Tailwind 3.
2. Our utility classes (e.g. `bg-blue-500`, `text-sm`, `rounded-lg`) are compatible with both versions. The breaking changes in Tailwind 4 affect configuration, not utility classes.
3. Testing with Tailwind 4 requires a separate CI matrix dimension, adding complexity.

## Consequences
- **Good:** Simpler CI, fewer unknowns for v0.1.0.
- **Good:** Utility classes we use are forward-compatible with Tailwind 4.
- **Action:** Add Tailwind 4 testing in v0.2.0. Document any class name changes needed.
