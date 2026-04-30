# Installation

## Requirements

- PHP 8.2+
- Laravel 10.x, 11.x, or 12.x
- Livewire 3.x
- Tailwind CSS 3.x
- Node.js & npm (for Vite asset compilation)

## 1. Install Livewire

If you don't have Livewire installed yet:

```bash
composer require livewire/livewire:^3.0
```

Livewire 3 automatically includes **Alpine.js** — no separate installation needed.

## 2. Install Wire Table

```bash
composer require nyoncode/wire-table
```

This automatically installs `wire-core` and `wire-forms` as dependencies. The service providers are auto-discovered.

## 3. Frontend Setup (Tailwind CSS + Vite)

Wire packages use **Tailwind CSS utility classes** and **inline Alpine.js** in their Blade templates. Your Laravel app must have Tailwind configured so these classes are included in your CSS build.

### New Project

If you're starting fresh, install Tailwind with Vite:

```bash
npm install -D tailwindcss @tailwindcss/forms postcss autoprefixer
npx tailwindcss init -p
```

### Tailwind Content Configuration

Add the Wire packages' Blade views to your Tailwind content paths so their classes are included in the CSS build.

**Tailwind 3** (`tailwind.config.js`):

```js
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './app/**/*.php',
        // Wire ecosystem packages
        './vendor/nyoncode/wire-core/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-forms/resources/views/**/*.blade.php',
        './vendor/nyoncode/wire-table/resources/views/**/*.blade.php',
    ],
    darkMode: 'class',
    theme: {
        extend: {},
    },
    plugins: [
        require('@tailwindcss/forms'),
    ],
}
```

**Tailwind 4** (`resources/css/app.css`):

```css
@import "tailwindcss";
@plugin "@tailwindcss/forms";
@source "../views";
@source "../../vendor/nyoncode/wire-core/resources/views";
@source "../../vendor/nyoncode/wire-forms/resources/views";
@source "../../vendor/nyoncode/wire-table/resources/views";
```

### Vite Configuration

Standard Laravel Vite setup (`vite.config.js`):

```js
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
```

### CSS Entry Point

Your `resources/css/app.css`:

```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

### JS Entry Point

Your `resources/js/app.js` — no Wire-specific JS needed:

```js
import './bootstrap';
```

### Build

```bash
npm run dev    # development with hot reload
npm run build  # production build
```

## 4. Layout Template

Your main layout must include Vite assets and Livewire:

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
    {{ $slot }}

    {{-- Toast notifications --}}
    <x-wire-notifications::toast-container />

    @livewireScripts
</body>
</html>
```

> **Note:** `@livewireStyles` and `@livewireScripts` automatically include Alpine.js. Do not add Alpine.js separately.

## 5. Publish Configuration (optional)

```bash
# Wire Table config
php artisan vendor:publish --tag=wire-table-config

# Wire Core config (notifications, icons)
php artisan vendor:publish --tag=wire-core-config

# Wire Forms config (date formats, file upload defaults)
php artisan vendor:publish --tag=wire-forms-config
```

### Wire Table Config (`config/wire-table.php`)

```php
return [
    'defaults' => [
        'per_page' => 10,
        'per_page_options' => [10, 25, 50, 100],
        'searchable' => true,
        'sortable' => true,
        'hoverable' => true,
        'striped' => false,
    ],

    'text_input' => [
        'save_on_blur' => true,
        'save_on_enter' => true,
        'live_validation' => false,
        'live_debounce' => 500,
    ],

    'notification_driver' => null, // null = SessionDriver
];
```

## 6. Publish Views (optional)

To customize Blade templates:

```bash
php artisan vendor:publish --tag=wire-table-views
php artisan vendor:publish --tag=wire-forms-views
php artisan vendor:publish --tag=wire-core-views
```

Views are published to `resources/views/vendor/wire-{table,forms,core}/`.

## 7. Verify Installation

```bash
php artisan about
```

Displays WireTable, WireForms, and WireCore in the Laravel about output.

## Dark Mode

Wire components support dark mode via Tailwind's `dark:` classes. To enable:

1. Set `darkMode: 'class'` in `tailwind.config.js` (Tailwind 3)
2. Add `class="dark"` to your `<html>` element when dark mode is active

No additional configuration needed in Wire packages.

## Troubleshooting

### Styles not appearing

If Wire component styles are missing, Tailwind isn't scanning the package Blade views:

1. Verify the `content` paths in `tailwind.config.js` include the vendor paths
2. Run `npm run build` to rebuild
3. Clear Laravel view cache: `php artisan view:clear`

### Alpine.js conflicts

If you have a separate Alpine.js installation, it will conflict with Livewire's bundled Alpine. Remove your standalone Alpine and use Livewire's:

```diff
// resources/js/app.js
- import Alpine from 'alpinejs';
- window.Alpine = Alpine;
- Alpine.start();
```
