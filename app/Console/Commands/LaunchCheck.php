<?php

namespace App\Console\Commands;

use App\Support\LaunchChecks;
use Illuminate\Console\Command;

class LaunchCheck extends Command
{
    protected $signature = 'sudar:launch-check';

    protected $description = 'Verify business configuration required before public launch';

    public function handle(): int
    {
        $failed = 0;
        foreach (LaunchChecks::run() as $check) {
            $this->line(($check['ok'] ? '<fg=green>✔</>' : '<fg=red>✘</>').' '.$check['label'].($check['ok'] ? '' : ' — '.$check['help']));
            $failed += $check['ok'] ? 0 : 1;
        }
        $failed ? $this->error("{$failed} item(s) need attention.") : $this->info('Ready for launch.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
