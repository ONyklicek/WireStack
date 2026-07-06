---
order: 32
nav: false
---

# Filtry relací a podřádků

## Filtrování podle relací

Použijte `->query()` s `whereHas()` pro filtrování podle atributů souvisejícího modelu:

```php
// BelongsTo — filtrovat podle souvisejícího modelu
SelectFilter::make('category')
    ->options(Category::orderBy('name')->pluck('name', 'id')->toArray())
    ->query(fn (Builder $query, string $value) =>
        $query->whereHas('category', fn ($q) => $q->where('id', $value))
    )

// BelongsToMany
SelectFilter::make('tags')
    ->options(Tag::orderBy('name')->pluck('name', 'id')->toArray())
    ->multiple()
    ->query(fn (Builder $query, array $values) =>
        $query->whereHas('tags', fn ($q) => $q->whereIn('id', $values))
    )

// HasMany (existence) — použijte TernaryFilter
TernaryFilter::make('has_comments')
    ->label('Has Comments')
    ->query(fn (Builder $q, $value) => $value === '1'
        ? $q->has('comments')
        : $q->doesntHave('comments'))
```

---

<a id="filtering-by-sub-row-values"></a>
## Filtrování podle hodnot podřádků

Když má tabulka [podřádky](../sub-rows.md), označte filtr pomocí `subRows()` pro
zacílení na **dětské** záznamy místo rodičovských sloupců. Jedno volání omezí
všechna tři místa najednou:

- **rodiče** — zredukováni na ty s aspoň jedním odpovídajícím dítětem (`whereHas`),
- **zobrazené podřádky** — v rozbaleném panelu se vykreslí jen odpovídající děti,
- **rollup agregáty** — buňky `->sums()` / `->counts()` (nad stejnou relací jako
  `subRows()`) a jejich celkové součty v patičce počítají jen odpovídající děti,
- **celkové součty podřádků** — souhrny v rozsahu `query` na sloupcích podřádků
  sečtou v hlavní patičce jen odpovídající děti.

Kompletní rozpracovaný příklad filtrů + rollupů + součtů viz
[Podřádky — Sestavení diagramu](../sub-rows.md#building-the-diagram).

```php
$table
    ->subRows('items')
    ->filters([
        // "Měsíc/Rok" — zobrazit jen faktury s položkami vyfakturovanými ten měsíc,
        // a jen ty položky uvnitř každé rozbalené faktury
        DateFilter::make('billed_at')->month()->subRows(),

        // Funguje jakýkoli typ filtru — tohle spáruje dětský sloupec rovností
        SelectFilter::make('status')
            ->options(['open' => 'Open', 'closed' => 'Closed'])
            ->subRows(),
    ])
```

Názvy sloupců filtru odkazují na **dětský** model. Callback `->query()` na filtru
zúženém na podřádky dostane query builder dítěte. Když tabulka nemá nakonfigurovanou
relaci podřádků, `subRows()` se ignoruje a filtr se chová jako běžný rodičovský filtr.

S více aktivními filtry zúženými na podřádky rodič přežije jen tehdy, když aspoň
jedno dítě odpovídá **všem** dohromady, takže každý přeživší rodič má děti k zobrazení.
