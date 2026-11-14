<?php

declare(strict_types=1);

namespace NyonCode\WireTable\Testing;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Livewire\Component;
use Livewire\Features\SupportTesting\Testable;
use PHPUnit\Framework\Assert;

/**
 * Pest / PHPUnit helpers on `Livewire::test()` for a component using
 * `WithTable` — so a test says what it means ("search, then see these
 * records") instead of the state path it happens to live under.
 *
 * Every helper drives the component through the same endpoints the browser
 * does (a `tableState` write, `sortTable()`, `executeTableAction()` …), so a
 * passing test is evidence about the real request, not about a shortcut.
 *
 * Records are compared by key and may be given as models, keys, or a mix.
 * "Can see" means on the page the table would render now — the page size and
 * the current page apply, as they do for a user.
 */
final class TableTestingMacros
{
    public static function register(): void
    {
        Testable::macro('searchTable', function (?string $term): Testable {
            /** @var Testable<Component> $this */
            return $this->set('tableState.search', $term);
        });

        Testable::macro('sortTable', function (string $column, ?string $direction = null): Testable {
            /** @var Testable<Component> $this */
            $this->call('sortTable', $column);

            // sortTable() toggles; a requested direction is reached in at most one more call.
            if ($direction !== null && $this->get('tableState.sort.direction') !== $direction) {
                $this->call('sortTable', $column);
            }

            return $this;
        });

        Testable::macro('filterTable', function (string $filter, mixed $value): Testable {
            /** @var Testable<Component> $this */
            return $this->set('tableState.filters.'.$filter, $value);
        });

        Testable::macro('resetTableFilters', function (): Testable {
            /** @var Testable<Component> $this */
            return $this->call('resetTableFilters');
        });

        Testable::macro('selectTableRecords', function (iterable $records): Testable {
            /** @var Testable<Component> $testable */
            $testable = $this;

            foreach (TableTestingMacros::keys($records) as $key) {
                $testable->call('toggleRecordSelection', $key);
            }

            return $testable;
        });

        Testable::macro('callTableAction', function (string $action, mixed $record, array $data = []): Testable {
            /** @var Testable<Component> $this */
            $key = TableTestingMacros::keys([$record])[0];

            return $data === []
                ? $this->call('executeTableAction', $key, $action, true)
                : $this->call('executeTableActionWithData', $key, $action, $data, true);
        });

        Testable::macro('callTableBulkAction', function (string $action, iterable $records, array $data = []): Testable {
            /** @var Testable<Component> $this */
            $this->selectTableRecords($records);

            return $this->call('executeBulkActionWithData', $action, $data, true);
        });

        Testable::macro('assertCanSeeTableRecords', function (iterable $records, bool $inOrder = false): Testable {
            /** @var Testable<Component> $this */
            $expected = TableTestingMacros::keys($records);
            $shown = TableTestingMacros::shownKeys($this);

            foreach ($expected as $key) {
                Assert::assertContains($key, $shown, "Record [{$key}] is not on the table's current page.");
            }

            if ($inOrder) {
                Assert::assertSame($expected, array_values(array_intersect($shown, $expected)), 'The records are on the page, but not in the expected order.');
            }

            return $this;
        });

        Testable::macro('assertCanNotSeeTableRecords', function (iterable $records): Testable {
            /** @var Testable<Component> $this */
            $shown = TableTestingMacros::shownKeys($this);

            foreach (TableTestingMacros::keys($records) as $key) {
                Assert::assertNotContains($key, $shown, "Record [{$key}] is on the table's current page.");
            }

            return $this;
        });

        Testable::macro('assertCountTableRecords', function (int $count): Testable {
            /** @var Testable<Component> $this */
            $records = TableTestingMacros::records($this);
            $total = $records instanceof LengthAwarePaginator ? $records->total() : count(TableTestingMacros::items($records));

            Assert::assertSame($count, $total, 'The table matches a different number of records.');

            return $this;
        });
    }

    /**
     * Record keys as the table spells them — strings, the way they travel.
     *
     * @param  iterable<mixed>  $records
     * @return list<string>
     */
    public static function keys(iterable $records): array
    {
        $keys = [];

        foreach ($records as $record) {
            $keys[] = (string) ($record instanceof Model ? $record->getKey() : $record);
        }

        return $keys;
    }

    /**
     * The keys on the page the table would render now.
     *
     * @param  Testable<Component>  $testable
     * @return list<string>
     */
    public static function shownKeys(Testable $testable): array
    {
        return array_values(array_map(
            static fn (mixed $record): string => (string) ($record instanceof Model ? $record->getKey() : Arr::get((array) $record, 'id')),
            self::items(self::records($testable)),
        ));
    }

    /** @param  Testable<Component>  $testable */
    public static function records(Testable $testable): mixed
    {
        $component = $testable->instance();

        if (! method_exists($component, 'getTableRecords')) {
            Assert::fail($component::class.' is not a table: it does not use WithTable.');
        }

        return $component->getTableRecords();
    }

    /** @return array<int, mixed> */
    public static function items(mixed $records): array
    {
        return match (true) {
            $records instanceof Paginator, $records instanceof CursorPaginator => array_values($records->items()),
            $records instanceof \Traversable => array_values(iterator_to_array($records)),
            default => [],
        };
    }
}
