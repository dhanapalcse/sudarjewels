<?php

namespace App\Jobs;

use App\Domain\Commissions\CommissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class MarkCommissionsPayable implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function handle(CommissionService $commissions): void
    {
        $count = $commissions->markEligibleAsPayable();
        Log::info('Commission eligibility run', ['entries_made_payable' => $count]);
    }
}
