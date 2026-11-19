<?php

namespace App\Http\Controllers\Account;

use App\Domain\Warranty\ClaimService;
use App\Http\Controllers\Controller;
use App\Models\ClaimAttachment;
use App\Models\Customer;
use App\Models\PurchasedUnit;
use App\Models\WarrantyClaim;
use App\Support\Seo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ClaimController extends Controller
{
    public const PHOTO_RULES = ['nullable', 'array', 'max:5'];

    public const PHOTO_ITEM_RULES = ['image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:min_width=200,min_height=200,max_width=8000,max_height=8000'];

    private function customer(Request $request): Customer
    {
        return $request->user('customer');
    }

    private function ownClaim(Request $request, WarrantyClaim $claim): WarrantyClaim
    {
        abort_unless($claim->customer_id === $this->customer($request)->id, 404);

        return $claim;
    }

    public function index(Request $request): View
    {
        return view('account.claims', [
            'claims' => $this->customer($request)->claims()->with('unit.orderItem')->latest()->get(),
            'seo' => Seo::make('My claims', null, ['noindex' => true]),
        ]);
    }

    public function create(Request $request): View
    {
        $customer = $this->customer($request);
        $units = PurchasedUnit::with(['orderItem', 'order', 'claims'])
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id))
            ->where('warranty_status', 'registered')
            ->where('warranty_ends_at', '>=', now())
            ->get()
            ->reject(fn (PurchasedUnit $u) => $u->claims->contains(fn ($c) => $c->status->isOpen()));

        return view('account.claim-create', [
            'units' => $units,
            'selected' => (int) $request->query('unit'),
            'seo' => Seo::make('Submit a claim', null, ['noindex' => true]),
        ]);
    }

    public function store(Request $request, ClaimService $claims): RedirectResponse
    {
        $data = $request->validate([
            'purchased_unit_id' => ['required', 'integer'],
            'issue_description' => ['required', 'string', 'min:20', 'max:2000'],
            'photos' => self::PHOTO_RULES,
            'photos.*' => self::PHOTO_ITEM_RULES,
        ], ['issue_description.min' => 'Please describe the issue in a little more detail (at least 20 characters).']);

        $unit = PurchasedUnit::with('order')->find($data['purchased_unit_id']);
        abort_unless($unit && $unit->order->customer_id === $this->customer($request)->id, 404);

        $claim = $claims->submit($this->customer($request), $unit, $data['issue_description'], $request->file('photos', []));

        return redirect()->route('account.claims.show', $claim)->with('success', "Claim {$claim->claim_number} submitted. We'll review it shortly.");
    }

    public function show(Request $request, WarrantyClaim $claim): View
    {
        $this->ownClaim($request, $claim)->load(['unit.orderItem', 'unit.order', 'updates' => fn ($q) => $q->where('visible_to_customer', true), 'updates.attachments', 'replacementUnit']);

        return view('account.claim', ['claim' => $claim, 'seo' => Seo::make('Claim '.$claim->claim_number, null, ['noindex' => true])]);
    }

    public function reply(Request $request, WarrantyClaim $claim, ClaimService $claims): RedirectResponse
    {
        $this->ownClaim($request, $claim);
        $data = $request->validate([
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'photos' => self::PHOTO_RULES,
            'photos.*' => self::PHOTO_ITEM_RULES,
        ]);
        $claims->customerReply($claim, $this->customer($request), $data['message'], $request->file('photos', []));

        return back()->with('success', 'Thanks — your information has been sent.');
    }

    /** Private attachments are streamed only to the owning customer. */
    public function attachment(Request $request, WarrantyClaim $claim, ClaimAttachment $attachment): StreamedResponse
    {
        $this->ownClaim($request, $claim);
        abort_unless($attachment->warranty_claim_id === $claim->id, 404);

        return Storage::disk($attachment->disk)->response($attachment->path, $attachment->original_name, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
