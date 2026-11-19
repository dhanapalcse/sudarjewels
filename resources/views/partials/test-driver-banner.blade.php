@if (app()->environment('local') && config('sudar.notifications.sms_driver') === 'test' && ! empty($testOtp))
    <div class="rounded-xl border border-dashed border-gold bg-pearl p-3 text-sm" role="note">
        <strong class="font-semibold">Local test notification driver.</strong>
        No SMS was sent. Development code: <code class="font-mono text-base tracking-widest">{{ $testOtp }}</code>
    </div>
@endif
