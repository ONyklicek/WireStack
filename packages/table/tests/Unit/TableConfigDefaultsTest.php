<?php

declare(strict_types=1);

use NyonCode\WireCore\Notifications\Drivers\LivewireEventDriver;
use NyonCode\WireCore\Notifications\Drivers\NullDriver;
use NyonCode\WireTable\Columns\TextInputColumn;
use NyonCode\WireTable\Exceptions\TableConfigurationException;
use NyonCode\WireTable\Table;

/*
 * `config/wire-table.php` is the project-wide default for every table, and a
 * table's own fluent call always wins over it. Each default is read when it is
 * asked for, not when the table is built, so a config set after `make()` still
 * applies.
 */

it('takes its page size and the sizes it offers from config', function () {
    config()->set('wire-table.defaults.per_page', 50);
    config()->set('wire-table.defaults.per_page_options', [5, 50]);

    $table = Table::make();

    expect($table->getPerPage())->toBe(50)
        ->and($table->getPerPageOptions())->toBe([5, 50]);
});

it('accepts a configured page size given as a numeric string, as an env value is', function () {
    config()->set('wire-table.defaults.per_page', '25');

    expect(Table::make()->getPerPage())->toBe(25);
});

it('keeps the shipped page size when the config is missing', function () {
    config()->set('wire-table.defaults', []);

    $table = Table::make();

    expect($table->getPerPage())->toBe(10)
        ->and($table->getPerPageOptions())->toBe([10, 25, 50, 100]);
});

it('lets a table override the configured page size and sizes', function () {
    config()->set('wire-table.defaults.per_page', 50);
    config()->set('wire-table.defaults.per_page_options', [50, 100]);

    $table = Table::make()->perPage(20)->perPageOptions([20, 40]);

    expect($table->getPerPage())->toBe(20)
        ->and($table->getPerPageOptions())->toBe([20, 40]);
});

it('refuses a configured page size that is not a positive whole number', function (mixed $size) {
    config()->set('wire-table.defaults.per_page_options', [10, $size]);

    Table::make()->getPerPageOptions();
})->with(['all', 'many', 0, -1, '2.5'])->throws(TableConfigurationException::class, 'is not a positive whole number');

it('refuses a page size that is not positive', function () {
    Table::make()->perPage(0);
})->throws(TableConfigurationException::class, 'Page size [0] is not a positive whole number.');

it('refuses a page size that is not a number at all', function () {
    config()->set('wire-table.defaults.per_page', ['ten']);

    Table::make()->getPerPage();
})->throws(TableConfigurationException::class, 'Page size [array] is not a positive whole number.');

it('sorts the offered sizes, with the table\'s own size among them', function () {
    expect(Table::make()->perPageOptions([50, 10])->perPage(3)->getPerPageOptions())->toBe([3, 10, 50]);
});

it('takes searchable, sortable, striped and hoverable from config', function () {
    config()->set('wire-table.defaults.searchable', false);
    config()->set('wire-table.defaults.sortable', false);
    config()->set('wire-table.defaults.striped', true);
    config()->set('wire-table.defaults.hoverable', false);

    $table = Table::make();

    expect($table->isSearchable())->toBeFalse()
        ->and($table->isSortable())->toBeFalse()
        ->and($table->isStriped())->toBeTrue()
        ->and($table->isHoverable())->toBeFalse();
});

it('lets a table override the configured flags', function () {
    config()->set('wire-table.defaults.searchable', false);
    config()->set('wire-table.defaults.sortable', false);
    config()->set('wire-table.defaults.striped', true);
    config()->set('wire-table.defaults.hoverable', false);

    $table = Table::make()->searchable()->sortable()->striped(false)->hoverable();

    expect($table->isSearchable())->toBeTrue()
        ->and($table->isSortable())->toBeTrue()
        ->and($table->isStriped())->toBeFalse()
        ->and($table->isHoverable())->toBeTrue();
});

it('builds the notification driver class the config names', function () {
    config()->set('wire-table.notification_driver', NullDriver::class);

    expect(Table::make()->getNotificationDriver())->toBeInstanceOf(NullDriver::class);
});

it('leaves the notification driver to the global default when the config names none', function () {
    config()->set('wire-table.notification_driver', null);

    expect(Table::make()->getNotificationDriver())->toBeNull();
});

it('prefers a table\'s own notification driver over the configured one', function () {
    config()->set('wire-table.notification_driver', NullDriver::class);
    $own = new LivewireEventDriver;

    expect(Table::make()->notificationDriver($own)->getNotificationDriver())->toBe($own);
});

it('refuses a configured notification driver that is not one', function () {
    config()->set('wire-table.notification_driver', stdClass::class);

    Table::make()->getNotificationDriver();
})->throws(TableConfigurationException::class, 'is not a NotificationDriver');

it('takes a text input column\'s saving and validation defaults from config', function () {
    config()->set('wire-table.text_input', [
        'save_on_blur' => false,
        'save_on_enter' => false,
        'live_validation' => true,
        'live_debounce' => 250,
    ]);

    $column = TextInputColumn::make('name');

    expect($column->getSaveOnBlur())->toBeFalse()
        ->and($column->getSaveOnEnter())->toBeFalse()
        ->and($column->getLiveValidation())->toBeTrue()
        ->and($column->getLiveDebounce())->toBe(250);
});

it('lets a text input column override the configured defaults', function () {
    config()->set('wire-table.text_input', [
        'save_on_blur' => false,
        'save_on_enter' => false,
        'live_validation' => false,
        'live_debounce' => 250,
    ]);

    $column = TextInputColumn::make('name')->saveOnBlur()->saveOnEnter()->liveValidation(true, 900);

    expect($column->getSaveOnBlur())->toBeTrue()
        ->and($column->getSaveOnEnter())->toBeTrue()
        ->and($column->getLiveValidation())->toBeTrue()
        ->and($column->getLiveDebounce())->toBe(900);
});

it('debounces live validation by the configured delay when none is given', function () {
    config()->set('wire-table.text_input.live_debounce', 300);

    expect(TextInputColumn::make('name')->liveValidation()->getLiveDebounce())->toBe(300);
});
