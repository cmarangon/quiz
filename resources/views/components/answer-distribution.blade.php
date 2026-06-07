@props([
    'options' => [],
    'distribution' => [],
    'correctAnswer' => null,
    'isTrueFalse' => false,
])

@php
    $letters = ['A', 'B', 'C', 'D'];
    $maxAvatars = 14;

    // Normalise every option to its label and attach the players who picked it,
    // preserving option order so zero-pick options still render an empty bar.
    $rows = collect($options)->map(function ($option) use ($distribution) {
        $label = is_array($option) ? ($option['label'] ?? '') : $option;
        $players = $distribution[$label] ?? [];

        return ['label' => $label, 'players' => $players, 'count' => count($players)];
    })->values();

    $total = $rows->sum('count');
@endphp

@if($total > 0 && $rows->isNotEmpty())
    <div class="qz-distribution">
        <h3 class="qz-distribution__title">{{ __('How everyone answered') }}</h3>

        @foreach($rows as $index => $row)
            @php
                $isCorrect = $row['label'] === $correctAnswer;
                $pct = (int) round($row['count'] / $total * 100);
                $shown = array_slice($row['players'], 0, $maxAvatars);
                $overflow = $row['count'] - count($shown);
            @endphp
            <div @class(['qz-distribution__row', 'is-correct' => $isCorrect])>
                <div class="qz-distribution__label">
                    <span class="qz-distribution__key">{{ $letters[$index] ?? '' }}</span>
                    <span>{{ $isTrueFalse ? __($row['label']) : $row['label'] }}</span>
                    @if($isCorrect)<span class="qz-distribution__check">&#10003;</span>@endif
                </div>

                <div class="qz-distribution__track">
                    <div class="qz-distribution__fill" style="width: {{ $pct }}%"></div>
                </div>

                <div class="qz-distribution__meta">
                    <span class="qz-distribution__avatars">
                        @foreach($shown as $player)
                            <span class="qz-distribution__avatar" title="{{ $player['nickname'] ?? '' }}">{{ $player['emoji'] ?? '🎮' }}</span>
                        @endforeach
                        @if($overflow > 0)<span class="qz-distribution__more">+{{ $overflow }}</span>@endif
                    </span>
                    <span class="qz-distribution__count">{{ $row['count'] }} ({{ $pct }}%)</span>
                </div>
            </div>
        @endforeach
    </div>
@endif
