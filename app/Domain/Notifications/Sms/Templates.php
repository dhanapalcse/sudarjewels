<?php

namespace App\Domain\Notifications\Sms;

final class Templates
{
    public const TEXT = [
        'order_confirmed' => 'SUDAR JEWELS: your order {order} is confirmed. Total {total}. Thank you!',
        'order_dispatched' => 'SUDAR JEWELS: order {order} has been dispatched{tracking}.',
        'warranty_registered' => 'SUDAR JEWELS: warranty registered for order {order}. View your card at {url}',
        'claim_updated' => 'SUDAR JEWELS: your warranty claim {claim} is now "{status}". Details: {url}',
    ];

    /** @param array<string, string> $params */
    public static function render(string $template, array $params): string
    {
        $text = self::TEXT[$template] ?? $template;
        foreach ($params as $k => $v) {
            $text = str_replace('{'.$k.'}', (string) $v, $text);
        }

        return preg_replace('/\{[a-z_]+\}/', '', $text);
    }
}
