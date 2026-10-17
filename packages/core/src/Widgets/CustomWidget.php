<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

class CustomWidget extends Widget
{
    /** @var view-string|null */
    protected ?string $customView = null;

    /** @var array<string, mixed> */
    protected array $customViewData = [];

    /**
     * Set the Blade view that renders the widget body.
     *
     * @param  view-string  $view
     */
    public function view(string $view): static
    {
        $this->customView = $view;

        return $this;
    }

    /**
     * Set the data passed to the custom view.
     *
     * @param  array<string, mixed>  $data
     */
    public function viewData(array $data): static
    {
        $this->customViewData = $data;

        return $this;
    }

    /** @return view-string|null */
    public function getCustomView(): ?string
    {
        return $this->customView;
    }

    protected function viewName(): string
    {
        return $this->customView ?? 'wire-core::widgets.custom';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return $this->customViewData;
    }
}
