<?php

namespace App\Support;

final class Seo
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public ?string $image = null,
        public bool $noindex = false,
        public string $type = 'website',
        /** @var array<int, array<string, mixed>> */
        public array $jsonLd = [],
    ) {}

    public static function make(string $title, ?string $description = null, array $options = []): self
    {
        $brand = app(Settings::class)->get('business.name');
        $full = str_contains($title, $brand) ? $title : "{$title} | {$brand}";

        return new self(
            title: $full,
            description: $description ? mb_strimwidth(strip_tags($description), 0, 160, '…') : null,
            canonical: $options['canonical'] ?? url()->current(),
            image: $options['image'] ?? null,
            noindex: $options['noindex'] ?? false,
            type: $options['type'] ?? 'website',
            jsonLd: $options['jsonLd'] ?? [],
        );
    }
}
