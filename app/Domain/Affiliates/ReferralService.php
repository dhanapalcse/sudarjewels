<?php

namespace App\Domain\Affiliates;

use App\Enums\AffiliateStatus;
use App\Enums\AttributionSource;
use App\Exceptions\BusinessRuleException;
use App\Models\Affiliate;
use App\Models\ReferralVisit;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;

/**
 * Referral attribution, reusable by the future checkout:
 *  - one affiliate per order
 *  - an explicit valid code beats the saved referral
 *  - the latest valid referral link within the window is retained (cookie)
 *  - inactive or unknown codes are rejected and never overwrite a saved referral
 */
class ReferralService
{
    public function __construct(private Settings $settings) {}

    public static function normalizeCode(?string $code): ?string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z0-9]{3,32}$/', $code) ? $code : null;
    }

    public function findActive(?string $code): ?Affiliate
    {
        $code = self::normalizeCode($code);
        if (! $code) {
            return null;
        }
        $affiliate = Affiliate::with('user')->where('code', $code)->where('status', AffiliateStatus::Active->value)->first();

        return $affiliate?->isActive() ? $affiliate : null;
    }

    /** Called by middleware on storefront requests carrying ?ref=. */
    public function captureFromRequest(Request $request): ?Affiliate
    {
        $param = config('sudar.referral.query_parameter');
        if (! $request->query->has($param)) {
            return null;
        }
        $affiliate = $this->findActive($request->query($param));
        if (! $affiliate) {
            return null;
        }
        $days = (int) $this->settings->get('referral.window_days');
        Cookie::queue(config('sudar.referral.cookie'), json_encode(['code' => $affiliate->code, 'at' => now()->timestamp]), $days * 24 * 60, null, null, null, true, false, 'lax');

        $visitorKey = $request->hasSession() ? $request->session()->getId() : $request->ip();
        $dedupe = 'refvisit:'.sha1($visitorKey.'|'.$affiliate->code.'|'.$request->path());
        if (cache()->add($dedupe, 1, now()->addHour())) {
            ReferralVisit::create([
                'affiliate_id' => $affiliate->id,
                'code' => $affiliate->code,
                'product_id' => $request->route('product')?->id ?? null,
                'landing_path' => mb_substr('/'.ltrim($request->path(), '/'), 0, 500),
                'visitor_hash' => hash('sha256', $visitorKey.config('app.key')),
            ]);
        }
        if ($request->hasSession()) {
            $request->session()->put('referral_code', $affiliate->code);
        }

        return $affiliate;
    }

    /** The referral currently applicable to this visitor, if any. */
    public function current(Request $request): ?Attribution
    {
        $param = config('sudar.referral.query_parameter');
        if ($request->query->has($param) && ($a = $this->findActive($request->query($param)))) {
            return new Attribution($a, AttributionSource::ReferralLink, now());
        }
        $raw = $request->cookie(config('sudar.referral.cookie'));
        $data = $raw ? json_decode($raw, true) : null;
        if (! is_array($data) || empty($data['code']) || empty($data['at'])) {
            return null;
        }
        $captured = Carbon::createFromTimestamp((int) $data['at']);
        if ($captured->lt(now()->subDays((int) $this->settings->get('referral.window_days')))) {
            return null;
        }
        $affiliate = $this->findActive($data['code']);

        return $affiliate ? new Attribution($affiliate, AttributionSource::ReferralLink, $captured) : null;
    }

    /** Resolution for an order/enquiry: explicit code first (must be valid), then saved referral. */
    public function resolve(Request $request, ?string $explicitCode = null): ?Attribution
    {
        if (filled($explicitCode)) {
            $affiliate = $this->findActive($explicitCode);
            if (! $affiliate) {
                throw new BusinessRuleException('That referral code is not valid or no longer active.');
            }

            return new Attribution($affiliate, AttributionSource::ExplicitCode, now());
        }

        return $this->current($request);
    }

    public function shareUrl(Affiliate $affiliate, string $url): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').config('sudar.referral.query_parameter').'='.urlencode($affiliate->code);
    }
}
