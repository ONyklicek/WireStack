@php
    use Illuminate\Database\Eloquent\Model;
    use NyonCode\WireCore\Actions\ActionGroup;

    assert($group instanceof ActionGroup);
    assert($record instanceof Model);
@endphp

{{-- Row-level action groups delegate to the canonical core group view so the
     dropdown markup, single-action collapse, dividers and badge stay unified. --}}
@include('wire-core::actions.group', ['group' => $group, 'record' => $record])
