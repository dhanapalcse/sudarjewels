@php
    $palette = [
        'gray' => 'bg-pearl text-charcoal ring-1 ring-line', 'info' => 'bg-sky-50 text-sky-900', 'primary' => 'bg-mint text-teal-900',
        'success' => 'bg-emerald-50 text-emerald-900', 'warning' => 'bg-amber-50 text-amber-900', 'danger' => 'bg-red-50 text-danger',
    ];
    $color = method_exists($status, 'color') ? $status->color() : 'gray';
@endphp
<span class="badge {{ $palette[$color] ?? $palette['gray'] }}">{{ $status->label() }}</span>
