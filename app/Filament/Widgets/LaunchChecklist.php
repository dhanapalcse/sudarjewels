<?php

namespace App\Filament\Widgets;

use App\Support\LaunchChecks;
use Filament\Widgets\Widget;

class LaunchChecklist extends Widget
{
    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.launch-checklist';

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('settings.manage') && collect(LaunchChecks::run())->contains(fn ($c) => ! $c['ok']);
    }

    protected function getViewData(): array
    {
        return ['checks' => collect(LaunchChecks::run())->reject(fn ($c) => $c['ok'])];
    }
}
