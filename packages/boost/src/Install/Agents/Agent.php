<?php

declare(strict_types=1);

namespace NyonCode\WireBoost\Install\Agents;

use NyonCode\WireBoost\Contracts\SupportsGuidelines;
use NyonCode\WireBoost\Contracts\SupportsMcp;
use NyonCode\WireBoost\Contracts\SupportsSkills;

/**
 * Base class for an AI agent/IDE that wire-boost can configure. Subclasses
 * declare which capabilities they support by implementing the relevant
 * Supports* contracts.
 */
abstract class Agent
{
    /**
     * Stable identifier used on the command line (e.g. "claude").
     */
    abstract public function key(): string;

    /**
     * Human-readable name shown in the installer.
     */
    abstract public function name(): string;

    public function supportsMcp(): bool
    {
        return $this instanceof SupportsMcp;
    }

    public function supportsGuidelines(): bool
    {
        return $this instanceof SupportsGuidelines;
    }

    public function supportsSkills(): bool
    {
        return $this instanceof SupportsSkills;
    }
}
