<?php

declare(strict_types=1);

namespace NyonCode\WireModuleTenants\Enums;

/** What a person is in a company: the one who runs it, or one who works in it. */
enum MemberRole: string
{
    case Owner = 'owner';
    case Member = 'member';

    public function label(): string
    {
        return __('wire-module-tenants::messages.role_'.$this->value);
    }
}
