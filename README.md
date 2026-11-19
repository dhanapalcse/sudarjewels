# SUDAR JEWELS

Storefront, admin dashboard, affiliate dashboard and warranty system for **SUDAR JEWELS**, a Korean-inspired anti-tarnish jewellery business in India (`sudarjewels.com`, INR, Asia/Kolkata).

The current sales model is a catalogue with WhatsApp-assisted sales, plus orders created by staff and affiliates. Every sale, including WhatsApp, in-person and affiliate sales, is recorded as an order. Online checkout is a planned extension; referral attribution and analytics are already built for it to reuse.

---

## Stack

| | Version |
|---|---|
| PHP | 8.3+ (developed on 8.4) |
| Laravel | 13 |
| Filament (admin, `/admin`) | 5.11 (Livewire 4) |
| MySQL | 8+ (SQLite works for local development and tests) |
| Tailwind CSS | 4, built with Vite 8 |
| Alpine.js | 3 (collapse and focus plugins) |
| spatie/laravel-permission | 8 |
| intervention/image | 3 (GD driver) |

Fonts are Instrument Serif 400 (display) and DM Sans 400/500/600 (UI). They are self-hosted at build time by `laravel-vite-plugin` and loaded with the `@fonts` directive.

## Where things live

```
app/Domain/
  Catalogue/      CommissionRateResolver, ProductSearch, Images/ (Drive client, processor, import service)
  Orders/         QuoteCalculator, OrderService, PaymentService, FulfilmentService
  Inventory/      InventoryService (reservations, movements, restock)
  Customers/      CustomerService
  Affiliates/     ReferralService (attribution), Attribution
  Commissions/    CommissionService (ledger, reversals, eligibility), PayoutService
  Warranty/       WarrantyCalculator, WarrantyService, ClaimService
  Auth/           OtpService, OtpChallenge
  Notifications/  Notifier + Sms/ adapters (test, msg91, twilio)
app/Enums/        Status enums with allowed transitions
app/Filament/     Admin resources, pages and widgets
app/Http/Controllers/{Storefront,Warranty,Account,Affiliate,Admin}
app/Jobs/         ProcessProductImage, SendTransactionalSms, ReportPurchaseToAnalytics, MarkCommissionsPayable
app/Support/      Money, Phone, Settings, Audit, Seo, CatalogueCache, LaunchChecks, Permissions
```

Controllers and Filament actions are thin. Every business rule runs inside the `app/Domain` services, in database transactions.

---

## Local setup

```bash
composer install
cp .env.example .env                       # then set APP_ENV=local, APP_DEBUG=true, SMS_DRIVER=test, DB_*
php artisan key:generate
php artisan migrate --seed                 # local env also loads DevelopmentSeeder (sample data)
php artisan storage:link
npm install && npm run build               # or `npm run dev` while editing
php artisan serve
```

To use SQLite locally, set `DB_CONNECTION=sqlite` and remove the other `DB_*` lines.

### Sample data (development only)

`DevelopmentSeeder` runs only when `APP_ENV=local`, and refuses to run in production. It creates:

- 16 products, all flagged **Sample**. Their images are generated illustrations labelled **SAMPLE IMAGE**, and the hero image is labelled the same way.
- Placeholder contact details. The WhatsApp number `+91 90000 00000` is a placeholder; `sudar:launch-check` fails while it is set.
- Four orders in different states: delivered with warranty registered, an affiliate order with pending commission, an affiliate quote awaiting confirmation, and an in-person sale.

| Login | Password | URL |
|---|---|---|
| admin@sudarjewels.test (super admin) | `password` | `/admin` |
| manager@sudarjewels.test, packing@sudarjewels.test | `password` | `/admin` |
| affiliate@sudarjewels.test (code `SUDAR101`, active) | `password` | `/affiliate/login` |
| pending@sudarjewels.test (pending approval) | `password` | `/affiliate/login` |
| Customer: phone `98765 43210` | OTP | `/account/login` or `/warranty` (order `SJ000001`) |

**Admin 2FA:** on first sign-in, Filament asks the user to enrol an authenticator app (TOTP) and shows recovery codes. You can set `ADMIN_REQUIRE_2FA=false` in a local `.env` only.

**OTP in development:** with `SMS_DRIVER=test`, nothing is sent. The verification page shows the code in a banner labelled "Local test notification driver" (local environment only). Codes are never written to logs or the database; only a hash is stored.

---

## Background processing

| Queue | Work |
|---|---|
| `notifications` | Transactional SMS (order confirmed or dispatched, warranty registered, claim updates), deduplicated per event |
| `default` | GA4 purchase events, affiliate emails, commission eligibility |
| `media` | Image validation and optimisation (direct uploads and Google Drive imports) |

```bash
php artisan queue:work --queue=notifications,default,media --tries=3 --max-time=3600
php artisan schedule:work          # local; in production use cron (below)
```

Scheduled tasks (`routes/console.php`):

- **02:30 IST daily:** `MarkCommissionsPayable` moves pending commission to payable once the order is fully paid, delivered and past the return window.
- **Daily:** prune failed jobs, prune OTP rows older than 2 days, `model:prune`.

Every job can be retried safely:

- Stock movements carry unique references.
- Commission entries carry unique `source_key`s.
- SMS logs carry unique dedupe keys.
- GA4 purchase reporting is claimed atomically through `orders.analytics_reported_at`.
- Image imports only swap renditions after a full success.

---

## Tests

```bash
php artisan test
```

78 tests covering the critical business rules:

- **Commission:** inheriting the global rate, explicit 0%, disabled commission overriding the default, discount allocation, tax and shipping excluded from the basis.
- **Tampering:** affiliate IDs and prices sent by the client are ignored; affiliates cannot read or edit other affiliates' orders or customers.
- **Repeat safety:** duplicate order submission, repeated event processing, duplicate refund submission.
- **Stock:** reservation, last-unit races with stale reads, dispatch converting a reservation once, cancellation releasing stock, restocking only after explicit approval.
- **Refunds and payouts:** partial and full refunds, refund after payout (adjustment against future earnings), duplicate and failed payouts.
- **Warranty and OTP:** OTP verification, lookup privacy (matching and non-matching lookups return identical pages and messages), attempt limits and expiry, duplicate registration, partial deliveries with identical units, calendar-month boundaries (31st, 30 Nov into February, leap years, the IST day boundary).
- **Claims:** private attachments, the claim workflow, and replacements linked to the original unit.
- **Admin:** every admin page renders; staff permission scoping; 2FA enforcement; admin actions run through the domain services.

### Real concurrency check

PHPUnit runs in a single process. To race genuinely parallel processes against the configured database, run:

```bash
php artisan sudar:concurrency-check --workers=10 --stock=2
```

It creates temporary data, confirms N orders in parallel for limited stock, checks that exactly `stock` succeed, and then cleans up. **Run it against MySQL before launch.** On SQLite, some workers fail with "database locked" errors. That is expected: the failed confirmation rolls back and nothing oversells.

---

## Deployment (production)

1. Server: PHP 8.3+ with `gd, intl, bcmath, pdo_mysql, openssl, fileinfo, zip`; MySQL 8; Nginx or Apache with the document root at `public/`; HTTPS.
2. Copy `.env.example` to `.env` and fill in the database, mail and SMS settings. Then run `php artisan key:generate`. Keep `APP_DEBUG=false` and `SMS_DRIVER` set to a real provider; the app refuses `test` in production.
3. Build and migrate:
   ```bash
   composer install --no-dev --optimize-autoloader
   npm ci && npm run build
   php artisan migrate --force
   php artisan db:seed --force                # roles/permissions + placeholder policy pages only
   php artisan storage:link
   php artisan sudar:create-admin owner@sudarjewels.com --name="Owner"
   php artisan config:cache route:cache view:cache event:cache filament:optimize
   ```
4. Queue worker under Supervisor:
   ```ini
   [program:sudar-worker]
   command=php /var/www/sudarjewels/artisan queue:work --queue=notifications,default,media --tries=3 --max-time=3600
   autostart=true
   autorestart=true
   numprocs=2
   user=www-data
   stopwaitsecs=120
   ```
5. Cron: `* * * * * cd /var/www/sudarjewels && php artisan schedule:run >> /dev/null 2>&1`
6. After each deploy: `php artisan queue:restart`.
7. Before going public: `php artisan sudar:launch-check` must pass. The admin dashboard shows the same checklist.

---

## Configuration still required

Most items are tracked by `sudar:launch-check` and the admin dashboard checklist.

| Item | Where |
|---|---|
| Real WhatsApp number, phone, email, address, hours, GSTIN, social links | Admin → Business settings |
| **Commission return window (days):** commission never becomes payable until this is set | Admin → Business settings → Commission & referrals |
| Shipping fee and free-shipping threshold; GST rate and whether prices include tax | Business settings → Shipping & tax |
| Real policy text for About, Delivery, Returns, Privacy, Terms, Affiliate terms and Warranty coverage (currently placeholders flagged "needs review") | Admin → Pages & policies |
| Warranty exclusions, resolutions, and replacement policy (`remaining` vs `fresh`) | Business settings → Warranty |
| SMS provider credentials and DLT-approved templates (MSG91), or Twilio | `.env` |
| Hero photograph and real product photography; unpublish the sample products | Business settings → Homepage; Products |
| Google Drive import, if wanted: service-account JSON path, and the folder shared with that account | `.env` |
| GA4 measurement ID (and optional API secret for server-side purchase events) | Business settings / `.env` |
| SMTP for affiliate emails | `.env` |
| MySQL credentials. Local development currently uses SQLite because the local MySQL root password was not available | `.env` |

---

## Business rules and assumptions

**Money and rates**
- All amounts are integer **paise**. Rates are integer **basis points** (2000 = 20.00%).
- Percentages round half-up to the nearest paisa.
- An order-level discount is split across lines in proportion to each line's value after item discounts, using the largest-remainder method, so the parts always add up exactly.

**Tax**
- Prices are treated as GST-inclusive by default (setting `tax.prices_include_tax`).
- The GST rate defaults to 0 until configured. When it is set, each line stores its tax portion.

**Commission**
- Basis: item amount after allocated discounts, **excluding tax and shipping**, calculated per order item.
- The product override resolves as follows:
  - Commission disabled on the product → 0%, regardless of any override.
  - Override set → that rate, including an explicit 0%.
  - Override NULL → the global default (20%).
- The rate, eligible amount and commission are snapshotted on the order item. They are re-validated at confirmation.
- Lifecycle:
  - **Pending** at confirmation.
  - **Payable** once the order is fully paid, every active unit is delivered, and the return window has passed. The scheduled job does nothing until the window is set.
  - **Paid** through a completed payout.
- The ledger is append-only:
  - Cancelling or refunding before payout creates *reversal* entries. A fully offset pair is retired as `cancelled`.
  - A refund after payout creates a payable *adjustment* (negative) that is netted against future payouts.
  - Partial refunds reverse commission in proportion to the refunded share of the line total. Cumulative targets keep repeated partial refunds exact.
- Changing attribution after confirmation reverses the old earnings, creates new ones, and is audited.

**Payouts**
- Selected entries are claimed under row locks with a conditional `payout_id IS NULL` update. An entry therefore cannot be in two processing or completed payouts at once.
- A failed or cancelled payout releases its entries and keeps its history.
- The recorded amount must equal the payout total.

**Referral attribution**
- One affiliate per order. The window is 30 days (configurable). The latest valid `?ref=` link wins.
- An explicit valid code beats a saved referral. Invalid or inactive codes are rejected and never overwrite a saved referral.
- Direct affiliate orders always belong to the creating affiliate, set server-side.
- The attribution source is recorded on the order, and the referral code is included in WhatsApp enquiry messages.

**Orders**
- Status, payment status and fulfilment status are separate fields, each with validated transitions.
- Payment status is derived from the payment and refund ledgers. Fulfilment status is derived from the units, except "processing", which staff set manually.
- Order numbers are `SJ` plus a zero-padded ID (`SJ000123`).
- Cancelling is only allowed before dispatch and after any payments are refunded; after dispatch, use returns and refunds instead.

**Affiliate quotes**
- Submitted affiliate orders are quotes.
- At confirmation, prices, commission rates and availability are re-checked. If anything changed, confirmation is blocked until staff apply a **quote revision**. The revision is recorded with the before and after values, shown on the affiliate's order page, and emailed to the affiliate.
- Affiliates can edit their orders until confirmation. After that, they send a change request for staff to review.

**Inventory**
- `stock_on_hand` and `stock_reserved` are tracked per variant. Every change is a conditional atomic UPDATE.
- Confirmation reserves stock. Dispatch converts the reservation into a `sale` movement. Cancellation releases the reservation.
- Returns never add stock. Staff must approve each returned unit for restock.
- Manual adjustments cannot remove reserved stock.

**Warranty coverage**
- Coverage runs for N **calendar months** (default 3) from delivery, or from handover for in-person sales.
- It ends at **23:59:59 IST** on the same date N months later. If that date doesn't exist in the end month, coverage ends on the last day of that month: 31 Jan → 30 Apr, 30 Nov → 28/29 Feb.
- All arithmetic is done in Asia/Kolkata and stored in UTC.
- Coverage, terms and months are saved per unit at sale and delivery.
- Registering later does not change the coverage period. The registration time is stored separately.

**Warranty registration**
- There is one registration per order. Repeat attempts return the existing record, and units delivered later are attached automatically.
- The customer-entered name is stored on the registration; the order is never rewritten.
- Refunded or returned units are voided.
- Staff corrections to registration details or coverage require a reason and are audited.

**Replacements**
- A replacement is a new unit linked to the original. The original's warranty is voided.
- Its coverage follows the configured policy: `remaining` keeps the original end date; `fresh` starts a new full period. The applied policy is saved on the unit.

**Customers**
- Customers are identified by their normalised Indian mobile number (`+91XXXXXXXXXX`).
- When staff or an affiliate enters an existing phone number, the order links to that customer internally, but the existing profile is never overwritten.
- Affiliates see only what was entered on their own orders.

**Warranty lookup privacy**
- The pages, messages, cooldowns and rate limits are identical whether or not the order number and phone match. An OTP is sent only on a real match.
- Purchase details appear only after the OTP is verified.
- Limits: OTPs expire after 10 minutes, allow 5 attempts, have a 60-second resend cooldown and a cap of 5 per hour per phone, plus throttling per IP and per order number.

**Consent and notifications**
- Marketing consent is separate and opt-in, with a timestamp and source.
- Transactional messages are always sent.

**Analytics**
- GA4 receives `view_item`, `view_item_list` and `whatsapp_enquiry` events with catalogue data only (SKU, name, price, category).
- A `purchase` event is sent server-side once, when an order becomes fully paid, if `GA4_API_SECRET` is set.
- No names, phone numbers, addresses, OTPs or warranty codes are sent. Account, affiliate and admin pages don't load the analytics tag.

## Security notes

- **Authorisation:** spatie permissions are checked by Filament resources and actions (`->authorize(...)`), `OrderPolicy`, and the affiliate and customer controllers (server-side ownership checks).
- **Admin access:** super admins pass via `Gate::before`. Staff roles are editable under Admin → Staff permissions.
- **Credentials and private data:**
  - The admin requires TOTP 2FA.
  - Affiliate payout details are encrypted at rest, masked in the UI, and visible only with `affiliates.payout_details`.
  - Claim photos live on the private disk and are streamed only to the owning customer or to staff with `claims.manage`.
- **Logging:** the audit log redacts passwords, OTPs, tokens and payout numbers. OTPs are hashed and never logged.
- **Google Drive:** the server fetches only through the Drive API, using a file ID parsed from `drive.google.com`/`docs.google.com` links. It never fetches arbitrary URLs. Credentials stay on the server.
- **Headers:** security headers are set; `X-Robots-Tag: noindex` and `Cache-Control: private, no-store` apply on account, affiliate, warranty and admin pages. Robots.txt disallows everything outside production.
- **Cache safety:** the cache only unserializes the allow-listed catalogue classes (`config/cache.php`).

## Known limitations and next steps

- Online checkout and payment-gateway integration are not built yet. `ReferralService::resolve()` and the GA4 purchase job are ready for them to reuse.
- WhatsApp Business API messaging isn't integrated; enquiries use `wa.me` deep links, and SMS adapters handle transactional messages.
- The local MySQL run was not verified for lack of credentials; the test suite runs on SQLite. Run `php artisan test` and `sudar:concurrency-check` on MySQL before launch.
