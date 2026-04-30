<?php

declare(strict_types=1);

namespace NyonCode\WireForms\Contracts;

/**
 * Contract for components that provide validation rules.
 */
interface HasValidation
{
    /**
     * @return array<int, mixed>
     */
    public function getValidationRules(): array;

    /**
     * @return array<string, string>
     */
    public function getValidationMessages(): array;

    public function getLabel(): ?string;

    public function getStatePath(): string;
}
