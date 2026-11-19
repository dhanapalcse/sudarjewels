<?php

namespace App\Filament\Resources\Affiliates\Pages;

use App\Enums\AffiliateStatus;
use App\Filament\Resources\Affiliates\AffiliateResource;
use App\Models\Affiliate;
use App\Models\User;
use App\Support\Audit;
use App\Support\Permissions;
use App\Support\Phone;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateAffiliate extends CreateRecord
{
    protected static string $resource = AffiliateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        if (User::where('email', $data['user_email'])->exists()) {
            throw ValidationException::withMessages(['data.user_email' => 'An account with this email already exists.']);
        }

        return DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['user_name'], 'email' => $data['user_email'], 'password' => $data['password'], 'phone' => Phone::normalize($data['phone'] ?? null)]);
            $user->assignRole(Permissions::AFFILIATE);
            $payout = AffiliateResource::canSeePayoutDetails() ? collect($data)->only(['payout_method', 'payout_upi_id', 'payout_account_name', 'payout_account_number', 'payout_ifsc'])->filter()->all() : [];
            $affiliate = Affiliate::create([
                'user_id' => $user->id,
                'code' => strtoupper($data['code']),
                'status' => AffiliateStatus::Active,
                'approved_at' => now(),
                'approved_by_id' => auth()->id(),
                'phone' => Phone::normalize($data['phone'] ?? null),
                'city' => $data['city'] ?? null,
                'social_handle' => $data['social_handle'] ?? null,
                'admin_notes' => $data['admin_notes'] ?? null,
            ] + $payout);
            Audit::log('affiliate.created', $affiliate, ['code' => $affiliate->code]);

            return $affiliate;
        });
    }
}
