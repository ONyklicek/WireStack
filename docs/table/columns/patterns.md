---
order: 29
nav: false
---

# Patterns & Recipes

## User Table with Avatar

```php
$table->columns([
    StackedColumn::make('user')
        ->avatar('avatar_url')
        ->primary('name')
        ->secondary('email')
        ->circular()
        ->searchable()
        ->searchColumns(['name', 'email']),

    BadgeColumn::make('role')
        ->colors(['primary' => 'admin', 'success' => 'editor', 'gray' => 'viewer']),

    TextColumn::make('department.name')
        ->sortable()
        ->searchable(),

    TextColumn::make('posts.count')
        ->label('Posts')
        ->sortable()
        ->alignCenter(),

    TextColumn::make('last_login')
        ->since()
        ->sortable()
        ->textSize('sm')
        ->textColor('gray'),

    BooleanColumn::make('is_active'),
]);
```

## Financial Table

```php
$table->columns([
    TextColumn::make('number')
        ->searchable()
        ->fontFamily('mono'),

    TextColumn::make('client.name')
        ->searchable()
        ->sortable(),

    TextColumn::make('issued_at')
        ->date('d.m.Y')
        ->sortable(),

    TextColumn::make('due_at')
        ->date('d.m.Y'),

    TextColumn::make('total')
        ->money('CZK')
        ->sortable()
        ->alignRight()
        ->weight('bold')
        ->summarize('sum', 'Total'),

    BadgeColumn::make('status')
        ->colors([
            'draft' => 'gray',
            'sent' => 'warning',
            'paid' => 'success',
            'overdue' => 'danger',
        ]),

    PollColumn::make('payment_status')
        ->intervalSeconds(30)
        ->badge()
        ->colors(['success' => 'received', 'warning' => 'pending', 'gray' => 'none'])
        ->pollWhile(fn ($state) => $state === 'pending'),
]);
```

## Task Board Table

```php
$table->columns([
    SelectColumn::make('status')
        ->options([
            'todo' => '📋 To Do',
            'in_progress' => '🔄 In Progress',
            'review' => '👀 Review',
            'done' => '✅ Done',
        ]),

    TextColumn::make('title')
        ->searchable()
        ->weight('semibold')
        ->description(fn ($r) => Str::limit($r->body, 60))
        ->actionUrl(fn ($r) => route('tasks.show', $r)),

    StackedColumn::make('assignee')
        ->avatar('assignee.avatar_url')
        ->primary('assignee.name')
        ->circular()
        ->avatarSize('sm'),

    BadgeColumn::make('priority')
        ->colors(['danger' => 'high', 'warning' => 'medium', 'gray' => 'low'])
        ->icons(['arrow-up' => 'high', 'minus' => 'medium', 'arrow-down' => 'low']),

    TextColumn::make('due_at')
        ->date('d.m.')
        ->textColor('gray')
        ->textSize('sm'),
]);
```
