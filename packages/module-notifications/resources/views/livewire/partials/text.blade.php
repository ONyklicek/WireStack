{{-- The row's text. Its own partial because the row wraps it in an anchor when
     the notification has somewhere to go and leaves it bare when it has not —
     and the alternative is the same six lines written twice. --}}
<p class="truncate pr-8 text-[15px] font-semibold text-gray-900 dark:text-white">{{ $item['title'] }}</p>

@if($item['message'] !== null)
    <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">{{ $item['message'] }}</p>
@endif

<p class="mt-1 text-xs text-gray-400 dark:text-gray-500">{{ $item['when'] }}</p>
