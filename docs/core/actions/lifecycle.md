---
order: 30
summary: "What runs around the callback — before, after, on failure — how to stop a run from inside a hook, and what changes when the work belongs on a queue."
api_class: NyonCode\WireCore\Actions\ActionHalt
---

# Lifecycle And Queues

Between the click and the notification there is a fixed order of steps, and every
one of them is a hook you can take. This page is that order — what runs when,
what each hook is handed, how to stop the run from inside one, and what is
different once the work is long enough to belong on a queue.

## Lifecycle Hooks

The order is fixed, and every step is a place you can take:

| Step | What runs | Worth knowing |
| --- | --- | --- |
| 1. Modal | the confirmation, form, infolist or wizard, when the action declares one | its form is validated before the pipeline starts |
| 2. `before()` | your callbacks, in declaration order | **skipped on the re-run** after a halt was confirmed (`$confirmed` is true there) |
| 3. `action()` | the work itself | receives `$record` / `$records`, `$data`, `$confirmed`, `$halt` |
| 4. Redirect and notification | the result's redirect, then `successNotification()` or `failureNotification()` | recorded here, *before* step 5 |
| 5. `after()` | your callbacks | **skipped when the run halted**, so a halt's side effects cannot fire twice |

Three consequences worth having in mind before writing a hook:

- **A `before()` that halts stops everything after it.** The work does not run and
  neither do the after callbacks — which is the point, but it also means cleanup
  does not belong in `after()`.
- **The notification is decided before `after()` runs.** An after callback cannot
  change what the user is told; raise a notification of its own instead.
- **A queued action leaves before the pipeline is built.** `before()` and
  `after()` are browser work, so the job runs the callback and nothing else — see
  [Running On A Queue](#running-on-a-queue).

```php
Action::make('publish')
    ->before(fn ($record) => $record->validate())
    ->action(fn ($record) => $record->update(['status' => 'published']))
    ->after(fn ($record) => event(new Published($record)))
    ->successNotification('Published!')
    ->failureNotification('Publish failed.');
```

## Halt Execution

Halt pauses execution and shows a secondary modal for user confirmation:

```php
Action::make('process')
    ->before(function ($record, Action $action) {
        if ($record->has_warnings) {
            $action->halt()
                ->heading('Warnings Detected')
                ->description('There are unresolved warnings. Continue anyway?');
        }
    })
    ->action(fn ($record) => $record->process());
```

A halt may carry a form of its own — the question the action turned out to need
an answer to. The action is re-executed once it is confirmed, with `$confirmed`
true and the halt form's values merged into `$data`:

```php
Action::make('archive')
    ->action(function ($record, array $data, bool $confirmed, callable $halt) {
        if (! $confirmed) {
            return $halt()                                        // [tl! focus:start]
                ->heading('Why is this being archived?')
                ->form([
                    Select::make('reason')->options(ArchiveReason::class),
                    DateTimePicker::make('review_at'),
                ]);                                               // [tl! focus:end]
        }

        $record->archive($data['reason'], $data['review_at']);
    });
```

The halt form is validated before the action runs again — its fields' own rules
first, then any extra rules the halt declared over the bag as a whole:

```php
$halt()
    ->form([TextInput::make('reason')->required()->minLength(10)])
    ->validation(['reason' => 'not_in:test'], ['reason.not_in' => 'Pick a real reason.']);
```

A failure leaves the modal open with the message on the field, and the action is
not re-executed. Declared rules are written against bare field names and reported
on the matching field.

`$data` is dehydrated here too, on the same terms as an action modal's — the
halt form's fields shape their own values, and keys the halt carried over from
the first attempt pass through as they were.

### A halt that only tells you something

Not every halt is a question. `informative()` — spelled `noSubmit()` where that
reads better — drops the submit button, the form and its rules, leaving a modal
with one way out. The action is not re-executed, because there is nothing to
confirm:

```php
$action->halt()
    ->informative()                                  // [tl! focus]
    ->heading('Nothing to export')
    ->description('The filter you applied matches no rows.');
```

### Where a halt can be raised

Anywhere an action runs. The pipeline that raises one lives in `wire-core`, so a
halt works the same on a table, on a resource page, and on a plain Livewire
component composing `WithActions` — the last of which only became true in 2.0,
when the modal's rendering moved into core beside the engine. Before that a halt
outside a table set state nothing drew: the action stopped, and the screen said
nothing at all.

The only thing a host owes a halt is the modal host it already renders for
action modals:

```blade
<x-wire-actions::modal-host :component="$this" />
```

**A halt with fields needs a cache store that survives a request.** The schema is
declared inside a callback, so it cannot be rebuilt on the next render the way an
action's form can: it is parked in the cache under the component's id and read
back on every render until the halt closes. On a store that keeps nothing
(`array`, or none), the modal still opens and still validates — it simply comes
back without its fields after a failed validation. A halt with no form is
unaffected.
### A halt with no action at all

An action is not what makes a halt work. The object is a modal description, its
state is five keys, and its resume is a method name plus scalars — none of which
is about actions, and since 2.0 none of it is inside them either.
`InteractsWithHalt` is that mechanism on its own, so **any** Livewire component
can stop and ask:

```php
use NyonCode\WireCore\Actions\ActionHalt;
use NyonCode\WireCore\Actions\Concerns\InteractsWithHalt;

class OrderCard extends Component
{
    use InteractsWithHalt;                                       // [tl! focus]

    public function archive(): void
    {
        $this->halt(                                             // [tl! focus:4]
            ActionHalt::confirmDanger('Archive this order?', 'It leaves the active list.'),
            then: 'archiveConfirmed',
            arguments: ['id' => $this->order->id],
        );
    }

    public function archiveConfirmed(array $data, array $arguments): void
    {
        Order::findOrFail($arguments['id'])->archive();          // [tl! focus]
    }
}
```

```blade
{{-- Only for a component that raises halts without the action runtime; a host
     rendering the action modal host already draws them. --}}
<x-wire-actions::halt-host :component="$this" />
```

**The resume is a name, not a closure**, and that is not a limitation of the
implementation — it is what the request boundary allows. A halt is drawn, read
and answered on a *later* request than the one that raised it, and a closure does
not survive that trip. So a halt carries what the action pipeline has always
carried: a method name and scalars. `$then` is called as
`$then(array $data, array $arguments)` once the user confirms, `$data` being
whatever the halt collected.

`$then` must name a **public** method, and the rule is a boundary rather than a
convention: the halt's state is a public Livewire property, so the browser can
rewrite the name before confirming. A public method is one it could already call
directly, so naming it here grants nothing new — a private or protected one would
have been a way in, and is refused.

What a component without `wire-forms` gives up is fields: `form()` needs the form
layer to render, validate and dehydrate a schema. A halt there is a confirmation
— heading, description, two buttons — and rules it declares are still checked
against the data it is submitted with. A host composing `WithActions` or
`WithTable` has the form layer already and loses nothing.

### The halt API

A halt **is** a modal, so it speaks the vocabulary the modal classes own —
`heading()`, `description()`, `width()`, `closeOnEscape()`. An **action**
prefixes the same settings (`modalHeading()`, `modalWidth()`) because an action
is a button that *has* a modal, and its own `icon()` and `color()` belong to the
button. That is the rule behind what can look like two spellings of one thing.

```php
->heading(string|Closure|null $heading)              // the modal's title; a closure is resolved at once, see below
->description(string|Closure|null $description)      // the sentence under it
->icon(string|Icon|null $icon, string|Color|null $color = null)
->color(string|Color|null $color)                    // the accent, and the submit button's
->danger(bool $danger = true)                        // intent, not a hue: fills color only when nothing chose one
->width(string|ModalWidth $width)                    // 'sm'|'md'|'lg'|'xl'|'2xl'…'7xl'|'full' — default 'md'
->maxHeight(string $maxHeight)                       // a CSS length for the body before it scrolls
->closeOnClickAway(bool $close = true)               // default true
->closeOnEscape(bool $close = true)                  // false for a confirmation worth reading
->id(string $id)                                     // a stable DOM id, when something outside must address it
->submitLabel(?string $label)                        // default: the framework's "Confirm"
->cancelLabel(?string $label)
->informative(bool $informative = true)              // no submit, no form, no rules — a dead end with a Close
->noSubmit(bool $noSubmit = true)                    // the same thing, under the name that reads better at a call site
->form(array|ModalForm $fields)                      // fields answered before the action re-runs
->validation(array $rules, ?array $messages = null, ?array $attributes = null)
->fillForm(array $data)                              // prefill the halt form — keys are bare field names
->skipBeforeOnConfirm(bool $skip = true)             // default true; false re-runs before() on the confirmed pass
->redirectAfterConfirm(?string $url)                 // where to go once the confirmation goes through
```

Five presets set a heading, a description, an icon and a colour in one call:

```php
->confirmDelete(?string $recordName = null)
->confirmDanger(string $heading, ?string $description = null)
->confirmWarning(string $heading, ?string $description = null)
->info(string $heading, ?string $description = null)
->success(string $heading, ?string $description = null)
```

Three of these have a rule worth stating outright, because each was a trap:

- **A closure heading is resolved when you write it, not when the modal draws.**
  A halt is serialized into component state the moment it is raised, and the
  scope that could answer the closure is gone by the next request.
- **`form()` and `informative()` overrule each other, last call wins.**
  `informative()` drops the form and its rules; declaring a form afterwards takes
  the halt back out of informative. Before 2.0 the order decided it silently:
  `->informative()->form([...])` kept the instance, rendered no fields, and left
  the modal with no submit.
- **`skipBeforeOnConfirm(false)` really re-runs the hooks now.** It was read by
  nothing before 2.0. A `before()` that raises the halt must guard itself with
  `$confirmed`, or it will raise the same halt again — which is why the default
  skips them.

## Running On A Queue

Most actions should stay synchronous — a user who clicks Delete expects the row
gone when the page comes back, and moving that to a worker buys nothing but a
race. `->queue()` is for the long tail: a bulk action over ten thousand rows, a
recalculation that would time out.

```php
Action::make('recalculate')
    ->queue()                   // [tl! focus:3]
    ->onQueue('reports')        // naming a queue implies ->queue()
    ->onConnection('redis')
    ->action(fn ($records) => Report::rebuild($records));
```

The click dispatches a job and returns immediately; the user gets a "running in
the background" notification, and a second one when it finishes.

### What crosses the boundary

**Names and keys, never objects.** The job carries the host class, the action's
name, the record keys and the submitted form data — all scalars. Not the action,
which holds closures; not the models, which would be stale by the time a worker
picks them up and would make a ten-thousand-row bulk action a megabyte of
payload. The job rebuilds the host, asks it for the action by name, and reads the
records fresh.

That is worth knowing rather than hidden: a row edited between the click and the
run is acted on **as it is when the job runs**, not as it looked when queued.

### What does not cross it

A queued action has **no browser**. The bindings a synchronous callback receives
for the modal — `$set`, `$setParent`, `$close`, `$replace`, `$halt` — are bound
to throw, not to no-op:

```php
Action::make('recalculate')->queue()->action(function ($records, $close) {
    Report::rebuild($records);
    $close();   // QueuedActionException: needs the browser it no longer has
});
```

A no-op would look like it worked, and the developer would find out when a user
reported that the modal never closed. Report back with a notification instead —
which is what the [database driver](../notifications/index.md)
exists for, since by then the request that started the job is gone.

An action renamed or removed between dispatch and run throws for the same
reason: the job carries the name, so there is nothing left to execute and saying
so beats failing silently.

## Plugin Hooks: action.executing / action.executed

Beside the per-action callbacks above, two plugin hooks fire around **every**
action — which is what an installed package reaches for, because it never holds
the action's builder:

```php
$manager->hook(Hook::ActionExecuting, function (ActionExecutingPayload $payload) {
    Log::info('running', ['action' => $payload->actionName]);   // [tl! focus]

    return $payload;
}, for: 'invoices');
```

`for:` narrows a callback to one host — the registry key a page declares, or its
class. Without it the callback runs for every action in the application.

**A Laravel event fires at the same moment, ten lines apart, and the two are not
interchangeable.** `ActionExecuting` and `ActionExecuted` are the observation half:
audit trails, telemetry and metrics belong there and cannot change what runs. The
hook is the half that can. See [Hooks](../plugins/hooks.md).

## Related

- [Actions](index.md) — the callback these hooks surround
- [Action Modals](modals.md) — what may run before the callback does
- [Notifications](../notifications/index.md) — how a finished or failed run reports itself
- [Save Lifecycle](../../forms/save-lifecycle.md) — the same idea on the form side
