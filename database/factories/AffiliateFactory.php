<?php

namespace Database\Factories;

use App\Enums\AffiliateStatus;
use App\Models\Affiliate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Affiliate> */
class AffiliateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'code' => 'SUDAR'.strtoupper(Str::random(5)),
            'status' => AffiliateStatus::Active,
            'approved_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(['status' => AffiliateStatus::Pending, 'approved_at' => null]);
    }
}
