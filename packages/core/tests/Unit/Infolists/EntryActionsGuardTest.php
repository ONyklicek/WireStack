<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Infolists\Components\TextEntry;
use NyonCode\WireCore\Infolists\Infolist;

/**
 * Infolist C2 (render-optimization-audit-2026-07-17.md): the `entry-actions` partial
 * is now guarded at the call site (`@if($field->hasActions())`) instead of being
 * `@include`d for every entry and rendering empty. An action-less entry — the common
 * case — pays zero `entry-actions` view renders; the byte output is unchanged because
 * the partial rendered nothing anyway. The core suite covers byte-identity; this is
 * the render-count guard mandated by AI_CODING_STANDARD "Rendering".
 */
function entryActionsRenders(array $schema): int
{
    $count = 0;
    View::composer('wire-core::infolists.entry-actions', function () use (&$count): void {
        $count++;
    });

    Infolist::make()->record(['name' => 'Ada', 'email' => 'ada@example.test'])
        ->schema($schema)->toHtml();

    return $count;
}

it('action-less entries render zero entry-actions views', function () {
    $renders = entryActionsRenders([
        TextEntry::make('name'),
        TextEntry::make('email'),
    ]);

    expect($renders)->toBe(0);
});

it('an entry with actions still renders its entry-actions', function () {
    $renders = entryActionsRenders([
        TextEntry::make('name'),
        TextEntry::make('email')->actions([Action::make('copy')->label('Copy')]),
    ]);

    // Only the one entry that has actions renders the partial.
    expect($renders)->toBe(1);
});
