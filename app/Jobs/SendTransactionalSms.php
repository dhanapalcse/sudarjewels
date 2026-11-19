<?php

namespace App\Jobs;

use App\Domain\Notifications\Sms\SmsGateway;
use App\Models\NotificationLog;
use App\Support\Mask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use RuntimeException;
use Throwable;

/** Retry-safe: the dedupe key guarantees a message is delivered at most once per event. */
class SendTransactionalSms implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 4;

    /** @var array<int> */
    public array $backoff = [60, 300, 900];

    /** @param array<string, string> $params */
    public function __construct(
        public string $phone,
        public string $template,
        public array $params,
        public ?string $notifiableType,
        public ?int $notifiableId,
        public string $dedupeKey,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(SmsGateway $gateway): void
    {
        $log = NotificationLog::where('dedupe_key', $this->dedupeKey)->first();
        if ($log && in_array($log->status, ['sent', 'test'], true)) {
            return;
        }
        if (! $log) {
            try {
                $log = NotificationLog::create([
                    'channel' => 'sms',
                    'driver' => $gateway->name(),
                    'template' => $this->template,
                    'recipient_masked' => Mask::phone($this->phone),
                    'recipient_hash' => hash('sha256', $this->phone),
                    'status' => 'queued',
                    'notifiable_type' => $this->notifiableType,
                    'notifiable_id' => $this->notifiableId,
                    'dedupe_key' => $this->dedupeKey,
                ]);
            } catch (UniqueConstraintViolationException) {
                return;
            }
        }

        $result = $gateway->sendTransactional($this->phone, $this->template, $this->params);
        $log->update([
            'status' => $result->ok ? $result->status : 'failed',
            'provider_message_id' => $result->providerId,
            'error' => $result->error,
            'sent_at' => $result->ok ? now() : null,
        ]);

        if (! $result->ok) {
            throw new RuntimeException("SMS {$this->template} failed: {$result->error}");
        }
    }

    public function failed(?Throwable $e): void
    {
        NotificationLog::where('dedupe_key', $this->dedupeKey)->update(['status' => 'failed', 'error' => mb_substr((string) $e?->getMessage(), 0, 500)]);
    }
}
