<!doctype html>
<html lang="en" class="antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Color palette</title>
    @vite(['resources/css/app.css'])
</head>
@php
    use NyonCode\WireCore\Actions\Action;

    // Every distinct hue the canonical HasColor resolvers now render across all
    // owner-facing surfaces. Semantic aliases (blue → primary, green → emerald,
    // yellow → amber, info → cyan) collapse onto their brand hue on purpose, so
    // this row list uses the concrete keys that produce a distinct swatch.
    $hues = [
        'primary', 'slate', 'gray', 'zinc', 'neutral', 'stone', 'red', 'orange',
        'amber', 'lime', 'emerald', 'teal', 'cyan', 'sky', 'indigo', 'violet',
        'purple', 'fuchsia', 'pink', 'rose',
    ];
@endphp
<body class="bg-gray-100 p-8 dark:bg-gray-900">
<div data-preview-root class="mx-auto max-w-4xl rounded-2xl bg-white p-8 shadow-sm dark:bg-gray-800">
    <header class="mb-6">
        <h1 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Full Tailwind palette</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Every hue rendered through the canonical <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">HasColor</code>
            resolvers — the same <code class="rounded bg-gray-100 px-1 dark:bg-gray-700">-&gt;color('…')</code> value now
            resolves on every surface, not just badges.
        </p>
    </header>

    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="text-left text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    <th class="py-2 pr-4 font-medium">color</th>
                    <th class="px-3 py-2 font-medium">solid</th>
                    <th class="px-3 py-2 font-medium">soft</th>
                    <th class="px-3 py-2 font-medium">badge</th>
                    <th class="px-3 py-2 font-medium">button</th>
                    <th class="px-3 py-2 font-medium">text</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @foreach ($hues as $hue)
                    <tr>
                        <td class="py-2 pr-4 font-mono text-xs text-gray-600 dark:text-gray-300">{{ $hue }}</td>
                        <td class="px-3 py-2">
                            <span class="inline-block h-6 w-10 rounded {{ Action::getSolidBgClass($hue) }}"></span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-block h-6 w-10 rounded {{ Action::getSoftBgClass($hue) }}"></span>
                        </td>
                        <td class="px-3 py-2">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ Action::getBadgeColorClasses($hue) }}">{{ $hue }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <button type="button" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-white transition {{ Action::getModalSubmitButtonClasses($hue) }}">Action</button>
                        </td>
                        <td class="px-3 py-2">
                            <span class="font-medium {{ Action::getTextColorClasses($hue) }}">Sample</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
