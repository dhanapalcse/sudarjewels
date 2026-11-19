<x-filament-widgets::widget>
    <x-filament::section heading="Before launch" description="Configuration still required. These items are also checked by `php artisan sudar:launch-check`." icon="heroicon-o-exclamation-triangle" icon-color="warning">
        <ul style="list-style: disc; padding-left: 1.25rem; display: grid; gap: .25rem;">
            @foreach ($checks as $check)
                <li><strong>{{ $check['label'] }}</strong> — {{ $check['help'] }}</li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
