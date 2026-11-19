<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Creates editable policy/content pages with PLACEHOLDER text (needs_review = true).
 * The business must replace this text with real, reviewed policy content before launch;
 * `php artisan sudar:launch-check` fails while any page still needs review.
 */
class PageSeeder extends Seeder
{
    public function run(): void
    {
        $placeholder = "> **Placeholder** — replace this text with the business's reviewed policy before launch.\n\n";
        $pages = [
            'about' => ['About SUDAR JEWELS', "SUDAR JEWELS offers Korean-inspired, anti-tarnish jewellery designed for everyday wear.\n\n## Our story\n\nAdd the founder's story, design approach and sourcing details here."],
            'contact' => ['Contact', 'Add any additional contact information or a note about response times here.'],
            'delivery' => ['Delivery policy', "## Dispatch\n\nState dispatch timelines.\n\n## Delivery times and charges\n\nState delivery regions, typical delivery times and shipping charges.\n\n## Tracking\n\nExplain how customers receive tracking details."],
            'returns' => ['Returns policy', "## Return window\n\nState the return window in days (this must match the commission return window setting).\n\n## Eligible items\n\nList conditions for returns.\n\n## Refunds\n\nExplain refund methods and timelines."],
            'privacy' => ['Privacy policy', "## Information we collect\n\nDescribe personal data collected (name, phone, address, order details) and why.\n\n## How we use it\n\nTransactional messages (order and warranty updates) are sent regardless of marketing preferences. Marketing messages are sent only with consent.\n\n## Your choices\n\nExplain how customers update preferences or request deletion.\n\n## Contact\n\nAdd the grievance/contact officer details."],
            'terms' => ['Terms of sale', 'Add the terms of sale here.'],
            'affiliate-terms' => ['Affiliate terms', "## Commission\n\nThe default commission rate is set in business settings and may differ per product.\n\n## Eligibility\n\nCommission becomes payable after the order is fully paid, delivered and the return window has passed.\n\n## Payouts\n\nExplain payout frequency and methods (bank transfer / UPI).\n\n## Conduct\n\nAdd rules for promotion and customer communication."],
            'warranty-coverage' => ['Warranty coverage', "## Coverage period\n\nEvery eligible piece is covered for **three calendar months from delivery** (or from handover for in-person purchases), ending at 11:59 pm IST on the same date three months later. If that date does not exist in the final month, coverage ends on the last day of that month.\n\n## What is covered\n\nDescribe covered defects (for example plating or stone-setting defects under normal use).\n\n## Exclusions\n\nList exclusions (for example loss, theft, physical damage, misuse, normal wear).\n\n## Resolutions and replacements\n\nExplain how claims are resolved and how replacement coverage works.\n\n## How to claim\n\nRegister your warranty at /warranty, then submit a claim with photos from your account."],
        ];
        foreach ($pages as $slug => [$title, $body]) {
            Page::firstOrCreate(['slug' => $slug], [
                'title' => $title,
                'body' => $placeholder.$body,
                'is_published' => true,
                'needs_review' => true,
            ]);
        }
    }
}
