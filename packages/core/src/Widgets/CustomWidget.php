<?php

declare(strict_types=1);

namespace NyonCode\WireCore\Widgets;

class CustomWidget extends Widget
{
    protected ?string $customView = null;

    /** @var array<string, mixed> */
    protected array $customViewData = [];

    public function view(string $view): static
    {
        $this->customView = $view;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function viewData(array $data): static
    {
        $this->customViewData = $data;

        return $this;
    }

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
