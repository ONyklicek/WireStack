# Select

Dropdown select with static or dynamic options, search, and multi-select.

```php
use NyonCode\WireForms\Components\Select;
```

## Basic Usage

```php
Select::make('role')
    ->options([
        'admin' => 'Administrator',
        'editor' => 'Editor',
        'user' => 'User',
    ])
```

## Dynamic Options

```php
Select::make('category_id')
    ->options(fn () => Category::pluck('name', 'id')->toArray())
    ->placeholder('Choose category')
```

## Searchable

```php
Select::make('user_id')
    ->options(fn () => User::pluck('name', 'id')->toArray())
    ->searchable()
    ->noSearchResultsMessage('No users found')
    ->searchPrompt('Type to search...')
    ->loadingMessage('Loading...')
```

## Multi-Select

```php
Select::make('tags')
    ->multiple()
    ->maxItems(5)
    ->minItems(1)
    ->options([...])
```

## Relationship

```php
Select::make('author_id')
    ->relationship('author', 'name')
    ->searchable()
```

## Native vs Custom

```php
Select::make('country')
    ->native()          // browser-native <select>
    ->native(false)     // custom styled dropdown (default)
```

## Boolean Select

```php
Select::make('active')
    ->boolean()         // Yes/No options
```

## HTML in Options

```php
Select::make('color')
    ->allowHtml()
    ->options([
        'red' => '<span class="text-red-500">Red</span>',
    ])
```
