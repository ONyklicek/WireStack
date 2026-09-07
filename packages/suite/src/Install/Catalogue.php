<?php

declare(strict_types=1);

namespace NyonCode\Wire\Install;

/**
 * Everything `wire:install` knows how to set up.
 *
 * A list in code rather than a scan of `vendor/`: the installer must be able to
 * name a package that is **not** installed — that is the whole point of offering
 * it — and a scan can only ever find what is already there.
 *
 * The marker class is how "installed" is answered. A composer name cannot be
 * asked at runtime without reading `installed.json`, and a class that only
 * exists when the package does answers the same question with one autoload.
 */
class Catalogue
{
    /** @var array<int, Component>|null */
    private ?array $components = null;

    /**
     * Resolved from the container, so an application can bind a catalogue of its
     * own and have `wire:install` offer its parts beside these — the same
     * extension point every other list in this framework has, and what lets this
     * one be exercised with a part that is deliberately absent.
     *
     * @return array<int, Component>
     */
    public function components(): array
    {
        return $this->components ??= $this->shipped();
    }

    /** @return array<int, Component> */
    public function installed(): array
    {
        return array_values(array_filter($this->components(), static fn (Component $c): bool => $c->installed()));
    }

    /** @return array<int, Component> */
    public function missing(): array
    {
        return array_values(array_filter($this->components(), static fn (Component $c): bool => ! $c->installed()));
    }

    /**
     * @return array<int, Component>
     */
    protected function shipped(): array
    {
        return [
            new Component(
                package: 'nyoncode/wire-core',
                label: 'Core',
                description: 'Actions, modals, notifications, widgets, infolists, the engine.',
                marker: 'NyonCode\\WireCore\\WireCoreServiceProvider',
                command: 'wire-core:install',
                core: true,
            ),
            new Component(
                package: 'nyoncode/wire-forms',
                label: 'Forms',
                description: 'Schema-driven forms, fields, wizards, the save lifecycle.',
                marker: 'NyonCode\\WireForms\\WireFormsServiceProvider',
                command: 'wire-forms:install',
                core: true,
            ),
            new Component(
                package: 'nyoncode/wire-table',
                label: 'Tables',
                description: 'Tables, columns, filters, exports, gestures.',
                marker: 'NyonCode\\WireTable\\WireTableServiceProvider',
                command: 'wire-table:install',
                core: true,
            ),
            new Component(
                package: 'nyoncode/wire-sortable',
                label: 'Sortable',
                description: 'Drag-and-drop row and column reordering for tables.',
                marker: 'NyonCode\\WireSortable\\WireSortableServiceProvider',
                command: 'wire-sortable:install',
            ),
            new Component(
                package: 'nyoncode/wire-panels',
                label: 'Resources & pages',
                description: 'Resources, their pages, routing and zones.',
                marker: 'NyonCode\\WirePanels\\WirePanelsServiceProvider',
                core: true,
            ),
            new Component(
                package: 'nyoncode/wire-admin',
                label: 'Admin shell',
                description: 'The layout and the sidebar your pages render inside.',
                marker: 'NyonCode\\WireAdmin\\WireAdminServiceProvider',
                command: 'wire-admin:install',
                core: true,
            ),
            new Component(
                package: 'nyoncode/wire-module-auth',
                label: 'Sign in',
                description: 'Login, password reset, verification and the two-factor challenge, over Fortify.',
                marker: 'NyonCode\\WireModuleAuth\\WireModuleAuthServiceProvider',
                command: 'wire-module-auth:install',
            ),
            new Component(
                package: 'nyoncode/wire-module-users',
                label: 'Users',
                description: 'User administration, with roles where the application has them.',
                marker: 'NyonCode\\WireModuleUsers\\WireModuleUsersServiceProvider',
                command: 'wire-module-users:install',
            ),
            new Component(
                package: 'nyoncode/wire-module-settings',
                label: 'Settings',
                description: 'Typed application settings with a page to edit them.',
                marker: 'NyonCode\\WireModuleSettings\\WireModuleSettingsServiceProvider',
                command: 'wire-module-settings:install',
            ),
            new Component(
                package: 'nyoncode/wire-module-audit',
                label: 'Audit log',
                description: 'A screen for the trail wire-core already records.',
                marker: 'NyonCode\\WireModuleAudit\\WireModuleAuditServiceProvider',
                command: 'wire-module-audit:install',
            ),
            new Component(
                package: 'nyoncode/wire-module-notifications',
                label: 'Notifications',
                description: 'The history behind the notification bell.',
                marker: 'NyonCode\\WireModuleNotifications\\WireModuleNotificationsServiceProvider',
                command: 'wire-module-notifications:install',
            ),
            new Component(
                package: 'nyoncode/wire-module-media',
                label: 'Media library',
                description: 'Uploads, a browsable list and previews.',
                marker: 'NyonCode\\WireModuleMedia\\WireModuleMediaServiceProvider',
                command: 'wire-module-media:install',
            ),
        ];
    }
}
