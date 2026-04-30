<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Rendering;

use Illuminate\Contracts\View\View;
use NyonCode\WireForms\Forms\Config\FormConfig;
use NyonCode\WireForms\Forms\Runtime\FormRuntime;

/**
 * Renders a Form into a Blade view.
 *
 * @internal This class is not part of the public API.
 */
final class FormRenderer
{
    public function __construct(
        private readonly FormConfig $config,
        private readonly FormRuntime $runtime,
    ) {}

    public function render(): View
    {
        $this->runtime->prepare();

        return view('wire-forms::form', [
            'components' => $this->config->schema,
            'statePath' => $this->config->statePath,
        ]);
    }

    public function toHtml(): string
    {
        return $this->render()->render();
    }
}
