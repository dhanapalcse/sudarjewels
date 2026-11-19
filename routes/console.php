<?php

use App\Jobs\MarkCommissionsPayable;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Schedule;

/*
| Scheduler — run `php artisan schedule:work` locally or add the cron entry
| `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1` in production.
*/
Schedule::job(new MarkCommissionsPayable)->dailyAt('02:30')->timezone(config('sudar.business.timezone'))->withoutOverlapping();
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('model:prune')->daily();
Schedule::call(fn () => OtpCode::where('created_at', '<', now()->subDays(2))->delete())->daily()->name('prune-otp-codes');
