<?php

declare(strict_types=1);

use NyonCode\WireCore\Foundation\Schema\Grid;
use NyonCode\WireCore\Foundation\Schema\Step;
use NyonCode\WireCore\Foundation\Schema\Tab;
use NyonCode\WireCore\Foundation\Schema\Tabs;
use NyonCode\WireCore\Foundation\Schema\Wizard;

// ─── Tab ─────────────────────────────────────────────────────────

test('tab label defaults to a headline of its name and is overridable', function () {
    expect(Tab::make('contact_details')->getLabel())->toBe('Contact Details')
        ->and(Tab::make('x')->label('Custom')->getLabel())->toBe('Custom');
});

test('tab icon and columns are configurable', function () {
    $tab = Tab::make('info')->icon('user')->columns(2)->schema([Grid::make()]);

    expect($tab->getIcon())->toBe('user')
        ->and($tab->getColumns())->toBe(2)
        ->and($tab->getSchema())->toHaveCount(1);
});

test('tab renders the schema.tab view', function () {
    expect(Tab::make('info')->render()->name())->toBe('wire-core::schema.tab');
});

// ─── Tabs ────────────────────────────────────────────────────────

test('tabs default active tab is 0 and is settable', function () {
    expect(Tabs::make()->getActiveTab())->toBe(0)
        ->and(Tabs::make()->activeTab(2)->getActiveTab())->toBe(2);
});

test('getTabs returns only visible Tab children, re-indexed', function () {
    $tabs = Tabs::make()->schema([
        Tab::make('one'),
        Tab::make('hidden')->visible(false),
        Tab::make('three'),
    ]);

    $visible = $tabs->getTabs();

    expect($visible)->toHaveCount(2)
        ->and(array_keys($visible))->toBe([0, 1])
        ->and($visible[0]->getName())->toBe('one')
        ->and($visible[1]->getName())->toBe('three');
});

test('getTabs ignores non-Tab children', function () {
    $tabs = Tabs::make()->schema([Tab::make('one'), Grid::make()]);

    expect($tabs->getTabs())->toHaveCount(1);
});

test('tabs renders the schema.tabs view with tab labels', function () {
    $html = Tabs::make()->schema([
        Tab::make('details')->icon('user'),
        Tab::make('billing'),
    ])->toHtml();

    expect($html)
        ->toContain('activeTab: 0')
        ->toContain('Details')
        ->toContain('Billing')
        ->toContain('role="tablist"');
});

// ─── Step ────────────────────────────────────────────────────────

test('step label, description, icon and columns are configurable', function () {
    $step = Step::make('account')
        ->description('Login details')
        ->icon('key')
        ->columns(2);

    expect($step->getLabel())->toBe('Account')
        ->and($step->getDescription())->toBe('Login details')
        ->and($step->getIcon())->toBe('key')
        ->and($step->getColumns())->toBe(2);
});

test('step renders the schema.step view', function () {
    expect(Step::make('account')->render()->name())->toBe('wire-core::schema.step');
});

// ─── Wizard ──────────────────────────────────────────────────────

test('wizard default active step is 0 and is settable', function () {
    expect(Wizard::make()->getActiveStep())->toBe(0)
        ->and(Wizard::make()->activeStep(1)->getActiveStep())->toBe(1);
});

test('wizard skippable defaults to false and is settable', function () {
    expect(Wizard::make()->isSkippable())->toBeFalse()
        ->and(Wizard::make()->skippable()->isSkippable())->toBeTrue();
});

test('getSteps returns only visible Step children, re-indexed', function () {
    $wizard = Wizard::make()->schema([
        Step::make('one'),
        Step::make('hidden')->visible(false),
        Step::make('three'),
    ]);

    $visible = $wizard->getSteps();

    expect($visible)->toHaveCount(2)
        ->and(array_keys($visible))->toBe([0, 1])
        ->and($visible[1]->getName())->toBe('three');
});

test('getSteps ignores non-Step children', function () {
    $wizard = Wizard::make()->schema([Step::make('one'), Grid::make()]);

    expect($wizard->getSteps())->toHaveCount(1);
});

test('wizard renders the schema.wizard view with step indicator and navigation', function () {
    $html = Wizard::make()->schema([
        Step::make('account')->description('Login'),
        Step::make('profile'),
    ])->toHtml();

    expect($html)
        ->toContain('step: 0')
        ->toContain('total: 2')
        ->toContain('Account')
        ->toContain('Profile')
        ->toContain(__('wire-core::actions.wizard_next'))
        ->toContain(__('wire-core::actions.wizard_previous'));
});

test('wizard skippable flag reaches the rendered markup', function () {
    $html = Wizard::make()->skippable()->schema([Step::make('a'), Step::make('b')])->toHtml();

    expect($html)->toContain('skippable: true');
});
