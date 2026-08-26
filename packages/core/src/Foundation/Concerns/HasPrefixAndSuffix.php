<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Foundation\Concerns;

use Closure;
use NyonCode\WireCore\Foundation\Contracts\ActionContract;
use NyonCode\WireCore\Foundation\Icons\Icon;

/**
 * Prefix/suffix text and icons for input fields, plus optional interactive
 * affix actions (Filament-style `suffixAction()` / `prefixAction()` /
 * `hintAction()`).
 */
trait HasPrefixAndSuffix
{
    protected string|Closure|null $prefix = null;

    protected string|Closure|null $suffix = null;

    protected string|Closure|null $prefixIcon = null;

    protected string|Closure|null $suffixIcon = null;

    protected ?ActionContract $prefixAction = null;

    protected ?ActionContract $suffixAction = null;

    protected ?ActionContract $hintAction = null;

    /** Show static text inside the field, before the input. */
    public function prefix(string|Closure|null $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    /** Show static text inside the field, after the input. */
    public function suffix(string|Closure|null $suffix): static
    {
        $this->suffix = $suffix;

        return $this;
    }

    /** Show an icon inside the field, before the input. */
    public function prefixIcon(string|Icon|Closure|null $icon): static
    {
        $this->prefixIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    /** Show an icon inside the field, after the input. */
    public function suffixIcon(string|Icon|Closure|null $icon): static
    {
        $this->suffixIcon = $icon instanceof Icon ? $icon->value() : $icon;

        return $this;
    }

    /**
     * Interactive action rendered before the input (inside the affix wrapper).
     */
    public function prefixAction(ActionContract $action): static
    {
        $this->prefixAction = $action;

        return $this;
    }

    /**
     * Interactive action rendered after the input (inside the affix wrapper).
     */
    public function suffixAction(ActionContract $action): static
    {
        $this->suffixAction = $action;

        return $this;
    }

    /**
     * Interactive action rendered alongside the field hint.
     */
    public function hintAction(ActionContract $action): static
    {
        $this->hintAction = $action;

        return $this;
    }

    public function getPrefix(): ?string
    {
        return $this->evaluate($this->prefix);
    }

    public function getSuffix(): ?string
    {
        return $this->evaluate($this->suffix);
    }

    public function getPrefixIcon(): ?string
    {
        $value = $this->evaluate($this->prefixIcon);

        return $value instanceof Icon ? $value->value() : $value;
    }

    public function getSuffixIcon(): ?string
    {
        $value = $this->evaluate($this->suffixIcon);

        return $value instanceof Icon ? $value->value() : $value;
    }

    public function getPrefixAction(): ?ActionContract
    {
        return $this->prefixAction;
    }

    public function getSuffixAction(): ?ActionContract
    {
        return $this->suffixAction;
    }

    public function getHintAction(): ?ActionContract
    {
        return $this->hintAction;
    }

    /**
     * Resolve an affix action by name (prefix, suffix, or hint slot).
     */
    public function getFieldAction(string $name): ?ActionContract
    {
        foreach ([$this->prefixAction, $this->suffixAction, $this->hintAction] as $action) {
            if ($action !== null && $action->getName() === $name) {
                return $action;
            }
        }

        return null;
    }

    /**
     * Whether anything renders in the leading affix slot.
     */
    public function hasPrefixContent(): bool
    {
        return $this->getPrefix() !== null
            || $this->getPrefixIcon() !== null
            || $this->prefixAction !== null;
    }

    /**
     * Whether anything renders in the trailing affix slot.
     */
    public function hasSuffixContent(): bool
    {
        return $this->getSuffix() !== null
            || $this->getSuffixIcon() !== null
            || $this->suffixAction !== null;
    }

    public function hasAffix(): bool
    {
        return $this->hasPrefixContent() || $this->hasSuffixContent();
    }
}
