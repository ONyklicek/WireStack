<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Components;

/**
 * Hidden form field.
 */
class Hidden extends Field
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->hidden();
    }

    protected function viewName(): string
    {
        return 'wire-forms::components.hidden';
    }
}
