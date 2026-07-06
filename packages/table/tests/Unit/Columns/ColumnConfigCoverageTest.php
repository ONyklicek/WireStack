<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Enums\Alignment;
use NyonCode\WireCore\Foundation\Enums\Breakpoint;
use NyonCode\WireCore\Foundation\Enums\FontWeight;
use NyonCode\WireCore\Foundation\Enums\Size;
use NyonCode\WireTable\Columns\Column;
use NyonCode\WireTable\Columns\ImageColumn;
use NyonCode\WireTable\Columns\TextColumn;
use Workbench\App\Models\User;

function ccRecord(array $attributes = []): Model
{
    $record = new class extends Model
    {
        protected $guarded = [];
    };
    $record->forceFill($attributes + ['id' => 1]);

    return $record;
}

// ─── Responsive visibility ──────────────────────────────────────

it('builds responsive visibility classes for every breakpoint', function () {
    foreach (['sm', 'md', 'lg', 'xl', '2xl'] as $bp) {
        expect(TextColumn::make('a')->visibleFrom($bp)->getResponsiveClasses())->toBe("hidden {$bp}:table-cell")
            ->and(TextColumn::make('a')->hiddenFrom($bp)->getResponsiveClasses())->toBe("{$bp}:hidden");
    }

    expect(TextColumn::make('a')->visibleFrom('weird')->getResponsiveClasses())->toBe('hidden md:table-cell')
        ->and(TextColumn::make('a')->hiddenFrom('weird')->getResponsiveClasses())->toBe('md:hidden')
        ->and(TextColumn::make('a')->getResponsiveClasses())->toBe('');
});

it('accepts a Breakpoint enum interchangeably with its string token', function () {
    expect(TextColumn::make('a')->visibleFrom(Breakpoint::Lg)->getResponsiveClasses())
        ->toBe(TextColumn::make('a')->visibleFrom('lg')->getResponsiveClasses())
        ->and(TextColumn::make('a')->hiddenFrom(Breakpoint::Xl)->getResponsiveClasses())
        ->toBe(TextColumn::make('a')->hiddenFrom('xl')->getResponsiveClasses());

    expect(TextColumn::make('a')->mobileBreakpoint(Breakpoint::Lg))->toBeInstanceOf(TextColumn::class);
});

it('accepts a FontWeight enum on the weight setter', function () {
    expect(TextColumn::make('a')->weight(FontWeight::Bold)->getTextWeight())->toBe('bold');
});

it('accepts a Size enum on the ImageColumn size setter', function () {
    expect(ImageColumn::make('a')->size(Size::Xl)->getSizeClasses())
        ->toBe(ImageColumn::make('a')->size('xl')->getSizeClasses());
});

it('resolves the alignment class, accepting a string or Alignment enum', function () {
    expect(TextColumn::make('a')->getAlignmentClass())->toBe('text-left')
        ->and(TextColumn::make('a')->alignCenter()->getAlignmentClass())->toBe('text-center')
        ->and(TextColumn::make('a')->alignment('right')->getAlignmentClass())->toBe('text-right')
        ->and(TextColumn::make('a')->alignment(Alignment::Center)->getAlignmentClass())->toBe('text-center')
        ->and(TextColumn::make('a')->alignment(Alignment::Right)->getAlignment())->toBe('right');
});

it('reports responsive visibility and shorthand helpers', function () {
    expect(TextColumn::make('a')->hasResponsiveVisibility())->toBeFalse()
        ->and(TextColumn::make('a')->onlyOnTabletAndUp()->getResponsiveClasses())->toContain('sm:table-cell')
        ->and(TextColumn::make('a')->onlyOnLargeScreens()->getResponsiveClasses())->toContain('lg:table-cell')
        ->and(TextColumn::make('a')->mobileOnly()->getResponsiveClasses())->toContain('md:hidden')
        ->and(TextColumn::make('a')->onlyOnMobile()->getResponsiveClasses())->toContain('md:hidden')
        ->and(TextColumn::make('a')->desktopOnly()->getResponsiveClasses())->toContain('md:table-cell')
        ->and(TextColumn::make('a')->onlyOnDesktop()->getResponsiveClasses())->toContain('md:table-cell')
        ->and(TextColumn::make('a')->visibleFrom('md')->hasResponsiveVisibility())->toBeTrue();
});

// ─── Responsive display (mobile/desktop) ────────────────────────

it('renders the plain cell when there is no responsive display', function () {
    $column = TextColumn::make('name');

    expect($column->hasResponsiveDisplay())->toBeFalse()
        ->and($column->renderResponsiveCell(ccRecord(['name' => 'Ada'])))->toContain('Ada');
});

it('collapses to a single cell when mobile and desktop content match', function () {
    $column = TextColumn::make('name')
        ->mobileDisplayUsing(fn ($state) => "X{$state}")
        ->desktopDisplayUsing(fn ($state) => "X{$state}");

    expect($column->hasResponsiveDisplay())->toBeTrue()
        ->and($column->renderResponsiveCell(ccRecord(['name' => 'Y'])))->toBe('XY');
});

it('wraps distinct mobile/desktop content', function () {
    $column = TextColumn::make('name')
        ->mobileBreakpoint('lg')
        ->mobileDisplayUsing(fn () => 'MOBILE')
        ->desktopDisplayUsing(fn () => 'DESKTOP');

    $html = $column->renderResponsiveCell(ccRecord(['name' => 'Ada']));

    expect($html)->toContain('MOBILE')->toContain('DESKTOP');
});

it('renders mobile/desktop cells with html and escaped variants', function () {
    $escaped = TextColumn::make('n')->mobileDisplayUsing(fn () => '<b>m</b>');
    $raw = TextColumn::make('n')->html()->desktopDisplayUsing(fn () => '<b>d</b>');

    expect($escaped->renderMobileCell(ccRecord()))->toContain('&lt;b&gt;')
        ->and($raw->renderDesktopCell(ccRecord()))->toContain('<b>d</b>');

    // Falls back to renderCell when no per-surface closure is set.
    expect(TextColumn::make('name')->renderMobileCell(ccRecord(['name' => 'A'])))->toContain('A')
        ->and(TextColumn::make('name')->renderDesktopCell(ccRecord(['name' => 'A'])))->toContain('A');
});

// ─── State resolution ───────────────────────────────────────────

it('resolves state via callback, dot-notation, default and formatter', function () {
    expect(TextColumn::make('x')->state(fn () => 'cb')->getState(ccRecord()))->toBe('cb')
        ->and(TextColumn::make('name')->getState(ccRecord(['name' => 'Ada'])))->toBe('Ada')
        ->and(TextColumn::make('missing')->default('N/A')->getState(ccRecord()))->toBe('N/A')
        ->and(TextColumn::make('name')->formatStateUsing(fn ($v) => strtoupper($v))->getState(ccRecord(['name' => 'ada'])))->toBe('ADA');
});

it('resolves dot-notation relation state with data_get', function () {
    $record = ccRecord();
    $record->setRelation('profile', ccRecord(['city' => 'London']));

    expect(TextColumn::make('profile.city')->getState($record))->toBe('London');
});

// ─── formatValue / placeholder / prefix / suffix / limit ────────

it('formats values with limit, prefix, suffix and placeholder', function () {
    $record = ccRecord();

    expect(TextColumn::make('a')->formatValue('hello world', $record))->toBe('hello world')
        ->and(TextColumn::make('a')->limit(5)->formatValue('hello world', $record))->toContain('...')
        ->and(TextColumn::make('a')->prefix('$')->suffix(' USD')->formatValue('10', $record))->toBe('$10 USD')
        ->and(TextColumn::make('a')->formatValue(null, $record))->toBe('-')
        ->and(TextColumn::make('a')->placeholder('—')->formatValue('', $record))->toBe('—')
        ->and(TextColumn::make('a')->getLimit())->toBeNull()
        ->and(TextColumn::make('a')->limit(3)->getLimit())->toBe(3);
});

// ─── Text styling classes ───────────────────────────────────────

it('builds text size classes', function () {
    foreach (['xs', 'sm', 'base', 'md', 'lg', 'xl', '2xl', '4xl'] as $size) {
        expect(TextColumn::make('a')->textSize($size)->getTextClasses())->not->toBeEmpty();
    }
    expect(TextColumn::make('a')->textSize('lg')->getTextSize())->toBe('lg');
});

it('builds text weight classes', function () {
    foreach (['thin', 'light', 'normal', 'medium', 'semibold', 'bold', 'extrabold', 'black', 'custom'] as $w) {
        expect(TextColumn::make('a')->weight($w)->getTextClasses())->not->toBeEmpty();
    }
    expect(TextColumn::make('a')->weight('bold')->getTextWeight())->toBe('bold');
});

it('builds text color classes including the muted treatment', function () {
    expect(TextColumn::make('a')->textColor('muted')->getTextClasses())->toContain('text-gray-400')
        ->and(TextColumn::make('a')->textColor('danger')->getTextClasses())->not->toBeEmpty()
        ->and(TextColumn::make('a')->textColor(Color::Primary)->getTextColor())->toBe(Color::Primary->value);
});

// ─── URL / rendering extras ─────────────────────────────────────

it('resolves url via callback and renders icon/url/copy in a cell', function () {
    $record = ccRecord(['name' => 'Ada']);

    expect(TextColumn::make('a')->getUrl($record))->toBeNull()
        ->and(TextColumn::make('a')->actionUrl(fn ($r) => '/u/'.$r->id)->getUrl($record))->toBe('/u/1')
        ->and(TextColumn::make('a')->actionUrl(fn () => '/x', true)->shouldOpenUrlInNewTab())->toBeTrue();

    $html = TextColumn::make('name')
        ->icon('check')
        ->color('primary')
        ->copyable(true, 'Copied!')
        ->tooltip('hi')
        ->description('desc', 'above')
        ->renderCell($record);

    expect($html)->toContain('Ada');
});

it('uses displayUsing override in renderCell', function () {
    $html = TextColumn::make('name')->html()->displayUsing(fn ($s) => "<<{$s}>>")->renderCell(ccRecord(['name' => 'Ada']));

    expect($html)->toContain('<<Ada>>');
});

// ─── Misc fluent config + getters ───────────────────────────────

it('covers the remaining fluent configuration getters', function () {
    $column = TextColumn::make('a')
        ->toggleable()
        ->width('w-32')
        ->alignCenter()
        ->default('def')
        ->tooltip('tt')
        ->copyable()
        ->copyMessage('Copied')
        ->extraAttributes('data-x="1"')
        ->extraHeaderAttributes(['class' => 'th'])
        ->wrap()
        ->prefix('P')
        ->suffix('S')
        ->color('danger')
        ->html();

    expect($column->isToggleable())->toBeTrue()
        ->and($column->getWidth())->toBe('w-32')
        ->and($column->getAlignment())->toBe('center')
        ->and($column->getDefault())->toBe('def')
        ->and($column->getTooltip())->toBe('tt')
        ->and($column->isCopyable())->toBeTrue()
        ->and($column->getCopyMessage())->toBe('Copied')
        ->and($column->getExtraAttributes())->toBe('data-x="1"')
        ->and($column->getExtraHeaderAttributes())->toBe(['class' => 'th'])
        ->and($column->shouldWrap())->toBeTrue()
        ->and($column->getPrefix())->toBe('P')
        ->and($column->getSuffix())->toBe('S')
        ->and($column->getColor())->toBe('danger')
        ->and($column->isHtml())->toBeTrue();
});

it('covers alignment variants', function () {
    expect(TextColumn::make('a')->alignLeft()->getAlignment())->toBe('left')
        ->and(TextColumn::make('a')->alignRight()->getAlignment())->toBe('right')
        ->and(TextColumn::make('a')->alignment('justify')->getAlignment())->toBe('justify');
});

// ─── Inline edit / editable ─────────────────────────────────────

it('covers inline-edit authorization and editable configuration', function () {
    // No ability → always allowed.
    expect(TextColumn::make('a')->canInlineEdit())->toBeTrue()
        ->and(TextColumn::make('a')->authorizeInline('edit')->getInlineEditAbility())->toBe('edit');

    // With an ability the Gate decides.
    $this->actingAs(new User);
    Gate::define('edit-col', fn () => false);
    Gate::define('edit-col-ok', fn () => true);
    expect(TextColumn::make('a')->authorizeInline('edit-col')->canInlineEdit())->toBeFalse()
        ->and(TextColumn::make('a')->authorizeInline('edit-col-ok')->canInlineEdit())->toBeTrue();

    $editable = TextColumn::make('a')->editable(true, 'select', ['x' => 'X'])
        ->editableRules(fn () => ['required'])
        ->editableUsing(fn () => true);

    expect($editable->isEditable())->toBeTrue()
        ->and($editable->getEditableType())->toBe('select')
        ->and($editable->getEditableOptions())->toBe(['x' => 'X'])
        ->and($editable->getEditableRules(ccRecord()))->toBe(['required'])
        ->and($editable->getEditableCallback())->toBeInstanceOf(Closure::class);
});

// ─── Description / toHtml / label ───────────────────────────────

it('covers description, toHtml and label', function () {
    expect(TextColumn::make('a')->description('static')->getDescription())->toBe('static')
        ->and(TextColumn::make('full_name')->toHtml())->toBe('Full Name')
        ->and(TextColumn::make('a')->label('Custom')->getLabel())->toBe('Custom');
});

// ─── Aggregates & pivot ─────────────────────────────────────────

it('covers all aggregate variants and the derived attribute name', function () {
    expect(TextColumn::make('c')->counts('orders')->getAggregateAttribute())->toBe('orders_count')
        ->and(TextColumn::make('s')->sums('orders', 'total')->getAggregateAttribute())->toBe('orders_sum_total')
        ->and(TextColumn::make('a')->averages('reviews', 'rating')->getAggregateAttribute())->toBe('reviews_avg_rating')
        ->and(TextColumn::make('m')->mins('orders', 'total')->getAggregateFunction())->toBe('min')
        ->and(TextColumn::make('m')->maxes('orders', 'total')->getAggregateFunction())->toBe('max')
        ->and(TextColumn::make('plain')->getAggregateAttribute())->toBeNull()
        ->and(TextColumn::make('plain')->isAggregate())->toBeFalse();

    $agg = TextColumn::make('s')->sums('orders', 'total');
    expect($agg->getAggregateRelation())->toBe('orders')
        ->and($agg->getAggregateColumn())->toBe('total');
});

it('reads aggregate state from the model attribute with default fallback', function () {
    $column = TextColumn::make('orders_count')->counts('orders')->default(0);

    expect($column->getState(ccRecord(['orders_count' => 4])))->toBe(4)
        ->and($column->getState(ccRecord()))->toBe(0);
});

it('covers pivot configuration', function () {
    expect(TextColumn::make('a')->isPivot())->toBeFalse()
        ->and(TextColumn::make('a')->pivot()->isPivot())->toBeTrue();
});

// ─── Search & sort closures ─────────────────────────────────────

it('covers searchable and sortable configuration with callbacks', function () {
    $search = TextColumn::make('name')->searchable(['name', 'email'])->searchUsing(fn () => null);
    expect($search->isSearchable())->toBeTrue()
        ->and($search->getSearchColumns())->toBe(['name', 'email'])
        ->and($search->getSearchCallback())->toBeInstanceOf(Closure::class);

    $sort = TextColumn::make('name')->sortable(true, fn () => null)->sortUsing(fn () => null);
    expect($sort->isSortable())->toBeTrue()
        ->and($sort->getSortCallback())->toBeInstanceOf(Closure::class);

    expect(TextColumn::make('a')->searchable()->isSearchable())->toBeTrue()
        ->and(TextColumn::make('a')->hidden()->isHidden())->toBeTrue();
});

// ─── Remaining branch coverage ──────────────────────────────────

it('covers hidden/visible closures and editable removal', function () {
    // hidden() accepts a Closure (stored, not evaluated by isHidden).
    expect(TextColumn::make('a')->hidden(fn () => true)->isHidden())->toBeFalse();

    // visible() Closure drives isVisible().
    expect(TextColumn::make('a')->visible(fn () => false)->isVisible())->toBeFalse()
        ->and(TextColumn::make('a')->visible(fn () => true)->isVisible())->toBeTrue()
        ->and(TextColumn::make('a')->isVisible())->toBeTrue();

    // editable(false) removes the capability.
    expect(TextColumn::make('a')->editable()->editable(false)->isEditable())->toBeFalse();
});

it('reads pivot state via resolveValue', function () {
    $record = ccRecord();
    $record->setRelation('pivot', ccRecord(['level' => 'admin']));

    expect(TextColumn::make('level')->pivot()->getState($record))->toBe('admin');
});

it('formats aggregate state with a state formatter', function () {
    $column = TextColumn::make('orders_count')->counts('orders')->formatStateUsing(fn ($v) => "#{$v}");

    expect($column->getState(ccRecord(['orders_count' => 2])))->toBe('#2');
});

it('derives a headline label from a relation path', function () {
    expect(TextColumn::make('author.full_name')->getLabel())->toBe('Full Name')
        ->and(TextColumn::make('author.full_name')->getRelationshipAttribute())->toBe('full_name')
        ->and(TextColumn::make('plain')->getRelationshipAttribute())->toBeNull();
});

it('renders an empty cell when the column is not authorized', function () {
    Gate::define('view-x', fn () => false);

    expect(TextColumn::make('name')->authorize('view-x')->renderCell(ccRecord(['name' => 'A'])))->toBe('');
});

it('formats values through the base Column::formatValue', function () {
    $record = ccRecord();

    // TextColumn overrides formatValue, so exercise the base implementation directly.
    expect(Column::make('a')->formatValue(null, $record))->toBe('-')
        ->and(Column::make('a')->placeholder('—')->formatValue('', $record))->toBe('—')
        ->and(Column::make('a')->formatValue('plain', $record))->toBe('plain')
        ->and(Column::make('a')->limit(4)->formatValue('hello world', $record))->toContain('...')
        ->and(Column::make('a')->prefix('[')->suffix(']')->formatValue('x', $record))->toBe('[x]');
});
