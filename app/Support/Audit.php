<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Audit trail for sensitive changes. Never pass passwords, OTPs, tokens or payout numbers in $changes.
 */
final class Audit
{
    private const REDACT = ['password', 'otp', 'code', 'token', 'secret', 'payout_account_number', 'payout_upi_id', 'payout_ifsc', 'payout_account_name', 'app_authentication_secret', 'app_authentication_recovery_codes', 'remember_token'];

    /** @param array<string, mixed> $changes */
    public static function log(string $action, ?Model $subject = null, array $changes = [], ?string $reason = null, ?Model $actor = null): AuditLog
    {
        $actor ??= Auth::guard('web')->user() ?? Auth::guard('customer')->user();

        return AuditLog::create([
            'actor_type' => $actor?->getMorphClass(),
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'changes' => self::redact($changes) ?: null,
            'reason' => $reason ? mb_substr($reason, 0, 500) : null,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }

    /** @param array<string, mixed> $changes */
    private static function redact(array $changes): array
    {
        foreach ($changes as $key => $value) {
            foreach (self::REDACT as $needle) {
                if (is_string($key) && str_contains(strtolower($key), $needle)) {
                    $changes[$key] = '[redacted]';

                    continue 2;
                }
            }
            if (is_array($value)) {
                $changes[$key] = self::redact($value);
            }
        }

        return $changes;
    }

    /** Old/new diff of dirty attributes on a model about to be saved. */
    public static function diff(Model $model, array $only = []): array
    {
        $diff = [];
        foreach ($model->getDirty() as $key => $new) {
            if ($only && ! in_array($key, $only, true)) {
                continue;
            }
            $diff[$key] = ['from' => $model->getOriginal($key), 'to' => $new];
        }

        return $diff;
    }
}
