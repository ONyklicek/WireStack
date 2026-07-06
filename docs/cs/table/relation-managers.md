---
order: 65
---

# Správci relací

Správce relací je tabulka zúžená na relaci vykreslená jako samostatná Livewire komponenta — protějšek relation managerů ve Filamentu. Podtřída pojmenuje vlastnickou relaci a definuje tabulku přesně jako jakákoli `WithTable` komponenta; základní třída připne dotaz k relaci vlastnického záznamu, takže seznam vždy zobrazuje jen související řádky.

## Definice správce

```php
use NyonCode\WireTable\Columns\TextColumn;
use NyonCode\WireTable\RelationManagers\RelationManager;
use NyonCode\WireTable\Table;

class PostsRelationManager extends RelationManager
{
    protected string $relationship = 'posts';

    protected ?string $title = 'Posts';

    public function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->searchable()->sortable(),
        ]);
    }
}
```

Vše, co umí `WithTable` komponenta, funguje i zde — sloupce, filtry, akce, exporty, hledání, řazení. `query()` je vynucen na vlastnickou relaci bez ohledu na to, co podtřída nastaví.

## Vykreslení

Předejte vlastnický záznam z rodičovského pohledu:

```blade
@livewire(App\Livewire\PostsRelationManager::class, ['ownerRecord' => $author])
```

Komponenta vykreslí volitelný nadpis (`$title`) nad zúženou tabulkou.

## Podporované relace

Pro **výpis** funguje jakákoli Eloquent relace. U belongs-to-many dotaz vybírá sloupce související tabulky (`related.*`), takže pivot sloupce nikdy nepřepíšou atributy související entity ani klíč řádku.

## Perzistenční helpery

Základní třída vystavuje helpery vědomé si relace pro akce create/attach/detach:

```php
// has-one / has-many: nastaví cizí klíč; belongs-to-many: vytvoří + připne
$this->createRelatedRecord(['title' => 'Hello']);

// jen belongs-to-many
$this->attachRelated($tag->id, ['note' => 'pivot attribute']);
$this->detachRelated($tag->id);   // null odpojí všechny
```

Nepodporované typy relací vyhodí jasnou `RuntimeException` (např. `createRelatedRecord()` na belongs-to nebo `attachRelated()` na has-many).

Typické zapojení z hlavičkové akce:

```php
->headerActions([
    HeaderAction::make('add_post')
        ->form([TextInput::make('title')->required()])
        ->action(fn (array $data) => $this->createRelatedRecord($data)),
])
```

## Přístupové metody

| Metoda | Popis |
|--------|-------------|
| `getOwnerRecord()` | Navázaný vlastnický model (vyhodí chybu, když chybí) |
| `getRelationshipName()` | Nakonfigurovaný název relace |
| `getRelationship()` | Čerstvá instance relace na vlastníkovi |
| `getTitle()` | Volitelný nadpis |

## Související dokumentace

- [Přehled tabulek](overview.md)
- [Akce](actions.md)
