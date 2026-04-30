# Radio

Radio button group for single-choice selection.

```php
use NyonCode\WireForms\Components\Radio;
```

## Usage

```php
Radio::make('priority')
    ->options([
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
    ])
```

## Descriptions

```php
Radio::make('plan')
    ->options([
        'free' => 'Free',
        'pro' => 'Professional',
    ])
    ->descriptions([
        'free' => 'Limited features, no support',
        'pro' => 'All features, priority support',
    ])
```

## Inline Layout

```php
Radio::make('size')
    ->options(['s' => 'S', 'm' => 'M', 'l' => 'L'])
    ->inline()
```

## Boolean

```php
Radio::make('newsletter')
    ->boolean()      // Yes/No options
```
