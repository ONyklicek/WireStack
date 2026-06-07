{{-- Base text column cell. Column owns state/config; this partial owns markup. --}}
@php
    /** @var string $content raw formatted value (escaped here unless $isHtml) */
    /** @var string $textClasses */
    /** @var bool $isHtml */
    /** @var string $iconHtml resolved icon svg (may be empty) */
    /** @var string $iconPosition before|after */
    /** @var string|null $url */
    /** @var bool $openInNewTab */
    /** @var bool $copyable */
    /** @var mixed $copyValue */
    /** @var string $copyMessage */
    /** @var string|null $tooltip */
    /** @var string|null $description */
    /** @var string $descriptionPosition above|below */

    // 1. Text styling / escaping
    if ($textClasses !== '' && ! $isHtml) {
        $out = '<span class="'.e($textClasses).'">'.e($content).'</span>';
    } elseif ($textClasses !== '') {
        $out = '<span class="'.e($textClasses).'">'.$content.'</span>';
    } elseif (! $isHtml) {
        $out = e($content);
    } else {
        $out = $content;
    }

    // 2. Icon
    if ($iconHtml !== '') {
        $out = $iconPosition === 'after' ? $out.' '.$iconHtml : $iconHtml.' '.$out;
    }

    // 3. URL link
    if ($url) {
        $target = $openInNewTab ? ' target="_blank"' : '';
        $out = '<a href="'.e($url).'"'.$target.' class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 hover:underline">'.$out.'</a>';
    }

    // 4. Copyable (shared partial)
    if ($copyable) {
        $out = view('wire-table::tables.columns.partials.copyable', [
            'content' => $out,
            'copyValue' => $copyValue,
            'copyMessage' => $copyMessage,
        ])->render();
    }

    // 5. Tooltip
    if ($tooltip) {
        $out = '<span title="'.e($tooltip).'" class="cursor-help">'.$out.'</span>';
    }

    // 6. Description
    if ($description !== null && $description !== '') {
        $descriptionHtml = '<p class="text-sm text-gray-500 dark:text-gray-400">'.e($description).'</p>';
        $out = $descriptionPosition === 'above' ? $descriptionHtml.$out : $out.$descriptionHtml;
        $out = '<div>'.$out.'</div>';
    }
@endphp
{!! $out !!}
