<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

use Closure;
use NyonCode\WireCore\Actions\Action;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Icons\Icon;
use NyonCode\WireForms\Concerns\InteractsWithFieldActions;

/**
 * Interactive button field.
 *
 * A first-class, design-system-styled button bound to a closure that runs on
 * the server with the form's reactive `$get` / `$set` context — the supported
 * alternative to hand-rolling a `<button>` inside an {@see Html} field.
 *
 * Presentation (label, icon, color, size, outlined) is delegated to an internal
 * {@see Action}, so buttons share the exact styling and color palette as table
 * and modal actions. The host Livewire component triggers the closure through
 * {@see InteractsWithFieldActions::callFieldAction()}.
 *
 *     Button::make('generate_slug')
 *         ->label('Generate')
 *         ->icon('heroicon-o-sparkles')
 *         ->action(fn ($get, $set) => $set('slug', Str::slug($get('title') ?? '')));
 */
class Button extends Field
{
    protected Action $buttonAction;

    public function __construct(string $name)
    {
        parent::__construct($name);

        $this->buttonAction = Action::make($name);
    }

    /**
     * Closure invoked when the button is pressed. Receives the field's reactive
     * accessors: `$get`, `$set`, `$state`, plus `$component` / `$livewire`.
     */
    public function action(Closure $callback): static
    {
        $this->buttonAction->action($callback);

        return $this;
    }

    public function label(string|Closure|null $label): static
    {
        $this->buttonAction->label($label);

        return parent::label($label);
    }

    public function icon(string|Icon|Closure|null $icon, ?string $position = 'before'): static
    {
        $this->buttonAction->icon($icon, $position);

        return $this;
    }

    public function color(string|Color|Closure|null $color): static
    {
        $this->buttonAction->color($color);

        return $this;
    }

    public function size(string|Closure $size): static
    {
        $this->buttonAction->size($size);

        return parent::size($size);
    }

    public function outlined(bool $outlined = true): static
    {
        $this->buttonAction->outlined($outlined);

        return $this;
    }

    public function getButtonAction(): Action
    {
        return $this->buttonAction;
    }

    /**
     * The button itself is the field's only action; fall back to any affix
     * actions for completeness.
     */
    public function getFieldAction(string $name): ?Action
    {
        if ($name === $this->buttonAction->getName()) {
            return $this->buttonAction;
        }

        return parent::getFieldAction($name);
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.button';
    }
}
