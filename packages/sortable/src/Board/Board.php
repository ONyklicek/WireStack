<?php

declare(strict_types=1);

namespace NyonCode\WireSortable\Board;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NyonCode\WireCore\Foundation\Colors\Color;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasColor;
use NyonCode\WireCore\Foundation\Contracts\Enum\HasLabel;
use NyonCode\WireSortable\Exceptions\BoardConfigurationException;

/**
 * Records in lanes: a column that says which lane a record is in, and cards
 * that are dragged between them.
 *
 *   Board::make()
 *       ->model(Task::class)
 *       ->groupBy('status')
 *       ->lanes(TaskStatus::class)              // or [Lane::make('todo'), …]
 *       ->cardTitle('title')
 *       ->cardDescription(fn (Task $task) => $task->owner?->name)
 *       ->orderColumn('position')
 *       ->cardUrl(fn (Task $task) => route('tasks.edit', $task));
 *
 * A declaration and nothing more: `WithBoard` is what renders it and what a
 * drop lands in. Moving a card writes its lane into `groupBy()`'s column and,
 * when there is an `orderColumn()`, renumbers the lane it lands in — so the
 * order a person drags is the order the next person sees.
 */
final class Board
{
    /** @var class-string<Model>|null */
    private ?string $model = null;

    private ?Closure $query = null;

    private ?string $groupBy = null;

    /** @var array<string, Lane> */
    private array $lanes = [];

    private string|Closure $cardTitle = 'id';

    private string|Closure|null $cardDescription = null;

    private ?Closure $cardUrl = null;

    private ?string $orderColumn = null;

    public static function make(): self
    {
        return new self;
    }

    /**
     * The model whose records the board holds.
     *
     * @param  class-string<Model>  $model
     */
    public function model(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /** Narrow the records: `fn (Builder $query) => $query->where(…)`, returning the builder or nothing. */
    public function query(?Closure $callback): static
    {
        $this->query = $callback;

        return $this;
    }

    /** The column whose value says which lane a record is in. */
    public function groupBy(string $column): static
    {
        $this->groupBy = $column;

        return $this;
    }

    /**
     * The lanes, in the order they are drawn: `Lane`s, `value => label` pairs,
     * or a backed enum whose cases are the lanes — labelled and coloured by
     * the enum's own `HasLabel` / `HasColor` when it has them.
     *
     * @param  array<int|string, Lane|string>|class-string<BackedEnum>  $lanes
     */
    public function lanes(array|string $lanes): static
    {
        $this->lanes = [];

        foreach (is_string($lanes) ? $this->lanesFromEnum($lanes) : $lanes as $key => $lane) {
            $lane = $lane instanceof Lane ? $lane : Lane::make((string) $key)->label($lane);
            $this->lanes[$lane->getName()] = $lane;
        }

        return $this;
    }

    /** What a card is headed with: an attribute, or `fn (Model $record) => string`. */
    public function cardTitle(string|Closure $title): static
    {
        $this->cardTitle = $title;

        return $this;
    }

    /** The line under a card's title: an attribute, `fn (Model $record) => ?string`, or none. */
    public function cardDescription(string|Closure|null $description): static
    {
        $this->cardDescription = $description;

        return $this;
    }

    /** Where a card leads: `fn (Model $record) => ?string`. */
    public function cardUrl(?Closure $url): static
    {
        $this->cardUrl = $url;

        return $this;
    }

    /** The column the order inside a lane is kept in; without one a drop only changes the lane. */
    public function orderColumn(?string $column): static
    {
        $this->orderColumn = $column;

        return $this;
    }

    /** @return array<string, Lane> */
    public function getLanes(): array
    {
        return $this->lanes;
    }

    public function getGroupBy(): string
    {
        return $this->groupBy ?? throw BoardConfigurationException::noColumn();
    }

    public function getOrderColumn(): ?string
    {
        return $this->orderColumn;
    }

    /**
     * The board's records, ordered, before they are sorted into lanes.
     *
     * @return Builder<Model>
     */
    public function getQuery(): Builder
    {
        $model = $this->model ?? throw BoardConfigurationException::noModel();
        $query = $model::query();

        if ($this->query !== null) {
            $query = ($this->query)($query) ?? $query;
        }

        return $this->orderColumn !== null ? $query->orderBy($this->orderColumn) : $query;
    }

    /** The lane a record is in: its column's value as the lane's name. */
    public function laneOf(Model $record): string
    {
        $value = $record->getAttribute($this->getGroupBy());

        return (string) ($value instanceof BackedEnum ? $value->value : $value);
    }

    public function titleOf(Model $record): string
    {
        return (string) $this->read($this->cardTitle, $record);
    }

    public function descriptionOf(Model $record): ?string
    {
        $description = $this->cardDescription === null ? null : $this->read($this->cardDescription, $record);

        return $description === null || $description === '' ? null : (string) $description;
    }

    public function urlOf(Model $record): ?string
    {
        return $this->cardUrl === null ? null : ($this->cardUrl)($record);
    }

    private function read(string|Closure $source, Model $record): mixed
    {
        $value = $source instanceof Closure ? $source($record) : $record->getAttribute($source);

        return $value instanceof BackedEnum ? $value->value : $value;
    }

    /**
     * @param  class-string<BackedEnum>  $enum
     * @return array<int, Lane>
     */
    private function lanesFromEnum(string $enum): array
    {
        if (! is_subclass_of($enum, BackedEnum::class)) {
            throw BoardConfigurationException::notAnEnum($enum);
        }

        return array_map(function (BackedEnum $case): Lane {
            $lane = Lane::make((string) $case->value)
                ->label($case instanceof HasLabel ? $case->getLabel() : Str::headline($case->name));

            if ($case instanceof HasColor && ($color = $case->getColor()) !== null) {
                $lane->color($color instanceof Color ? $color->value : $color);
            }

            return $lane;
        }, $enum::cases());
    }
}
