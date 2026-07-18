<div class="wire-stats-overview">
    {{-- Responsive: 1 col on mobile, growing toward the configured count (an
         inline repeat() ignored the viewport and crushed the cards on phones). --}}
    <div @class([
        'grid gap-4 grid-cols-1',
        'sm:grid-cols-2' => $columns === 2,
        'sm:grid-cols-2 lg:grid-cols-3' => $columns === 3,
        'sm:grid-cols-2 lg:grid-cols-4' => $columns >= 4,
    ])>
        @foreach($stats as $stat)
            <div class="wire-stat-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                 @if($stat->getExtraAttributes()) @foreach($stat->getExtraAttributes() as $attr => $val) {{ $attr }}="{{ $val }}" @endforeach @endif>
                <div class="flex items-center justify-between">
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                            {{ $stat->getLabel() }}
                        </p>
                        <p class="mt-1 text-2xl font-semibold {{ $stat->getValueColorClass() }}">
                            {{ $stat->getValue() }}
                        </p>
                        @if($stat->getDescription())
                            <p class="mt-1 flex items-center gap-1 text-sm {{ $stat->getDescriptionColorClass() }}">
                                @if($stat->getDescriptionIcon())
                                    {!! icon($stat->getDescriptionIcon(), 'w-4 h-4', 'h-4 w-4') !!}
                                @endif
                                {{ $stat->getDescription() }}
                            </p>
                        @endif
                    </div>

                    @if($stat->getIcon())
                        <div class="ml-4">
                            {!! icon($stat->getIcon(), 'w-4 h-4', 'h-8 w-8 text-gray-400 dark:text-gray-500') !!}
                        </div>
                    @endif
                </div>

                @if($stat->hasChart())
                    <div class="mt-3">
                        <svg viewBox="0 0 {{ count($stat->getChart()) * 10 }} 30" class="h-8 w-full" preserveAspectRatio="none">
                            @php
                                $chartData = $stat->getChart();
                                $max = max($chartData) ?: 1;
                                $min = min($chartData);
                                $range = $max - $min ?: 1;
                                $points = [];
                                foreach ($chartData as $i => $val) {
                                    $x = $i * 10;
                                    $y = 30 - (($val - $min) / $range * 28);
                                    $points[] = "$x,$y";
                                }
                            @endphp
                            <polyline
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.5"
                                class="{{ $stat->getChartColorClass() }}"
                                points="{{ implode(' ', $points) }}"
                            />
                        </svg>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
