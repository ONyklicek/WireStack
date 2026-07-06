# MorphToSelect

`MorphToSelect` vykresluje selektor typu plus selektor záznamu pro polymorfní relace.

## Základní použití

```php
use App\Models\Post;
use App\Models\Video;
use NyonCode\WireForms\Components\MorphToSelect;
use NyonCode\WireForms\Components\MorphToSelect\Type;

MorphToSelect::make('commentable')
    ->types([
        Type::make(Post::class)->titleAttribute('title'),
        Type::make(Video::class)->titleAttribute('name'),
    ])
```

To se hodí, když může pole formuláře mířit na více než jeden typ modelu.

## Jak se ukládá stav

Ve výchozím stavu komponenta zapisuje dvě hodnoty:

- `commentable_type`
- `commentable_id`

Suffixy můžete přizpůsobit, pokud vaše schéma používá jiné názvy sloupců.

```php
MorphToSelect::make('subject')
    ->typeColumnSuffix('_model')
    ->idColumnSuffix('_key')
```

## Kdy ho použít

`MorphToSelect` použijte, když:

- model používá relaci `morphTo`
- uživatelé musí vybrat cílový typ i cílový záznam
- prostý `Select` by skryl příliš mnoho kontextu

## Související dokumentace

- [BelongsToSelect](belongs-to-select.md)
- [Select](select.md)
- [Přehled formulářů](../overview.md)
