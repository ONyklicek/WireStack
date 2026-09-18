<ul data-primary="{{ $primary ?? 'none' }}">@foreach ($zones as $zone => $url)<li data-zone="{{ $zone }}">{{ $url }}</li>@endforeach</ul>
