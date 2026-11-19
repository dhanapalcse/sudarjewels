<?php

namespace Tests\Feature\Http;

use App\Domain\Orders\FulfilmentService;
use App\Domain\Orders\OrderService;
use App\Domain\Warranty\ClaimService;
use App\Domain\Warranty\WarrantyService;
use App\Enums\ClaimStatus;
use App\Models\ClaimAttachment;
use App\Models\Customer;
use App\Models\WarrantyClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClaimTest extends TestCase
{
    use RefreshDatabase;

    private function registeredUnitFor(string $phone)
    {
        $admin = $this->admin();
        $variant = $this->variant(50000, 5);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 1]], null, ['customer_phone' => $phone]), $admin);
        app(FulfilmentService::class)->handover($order, $order->units()->pluck('id')->all(), now(), $admin);
        app(WarrantyService::class)->register($order, 'X', $phone);

        return [$order->customer, $order->units()->first()];
    }

    public function test_customer_submits_claim_with_private_photos(): void
    {
        Storage::fake('local');
        [$customer, $unit] = $this->registeredUnitFor('9876543210');

        $this->actingAs($customer, 'customer')->post(route('account.claims.store'), [
            'purchased_unit_id' => $unit->id,
            'issue_description' => 'The plating on one side has started to peel off.',
            'photos' => [UploadedFile::fake()->image('peel.jpg', 800, 800)],
        ])->assertRedirect();

        $claim = WarrantyClaim::firstOrFail();
        $attachment = ClaimAttachment::firstOrFail();
        $this->assertSame(ClaimStatus::Submitted, $claim->status);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertStringNotContainsString('public', $attachment->path);

        // Owner can view; another customer, guests and non-claims staff cannot.
        $this->actingAs($customer, 'customer')->get(route('account.claims.attachment', [$claim, $attachment]))->assertOk();
        $other = Customer::factory()->create();
        $this->actingAs($other, 'customer')->get(route('account.claims.attachment', [$claim, $attachment]))->assertNotFound();
        $this->actingAs($other, 'customer')->get(route('account.claims.show', $claim))->assertNotFound();
        auth('customer')->logout();
        $this->get(route('admin.claim-attachment', $attachment))->assertRedirect();
        $this->actingAs($this->staff('content'))->get(route('admin.claim-attachment', $attachment))->assertForbidden();
        $this->actingAs($this->staff('support'))->get(route('admin.claim-attachment', $attachment))->assertOk();
    }

    public function test_customer_cannot_claim_someone_elses_unit(): void
    {
        [, $unit] = $this->registeredUnitFor('9876543210');
        $intruder = Customer::factory()->create();
        $this->actingAs($intruder, 'customer')->post(route('account.claims.store'), [
            'purchased_unit_id' => $unit->id, 'issue_description' => 'Trying to claim a unit that is not mine at all.',
        ])->assertNotFound();
        $this->assertSame(0, WarrantyClaim::count());
    }

    public function test_claim_workflow_and_replacement_links_to_original(): void
    {
        [$customer, $unit] = $this->registeredUnitFor('9876543210');
        $admin = $this->admin();
        $claims = app(ClaimService::class);
        $claim = $claims->submit($customer, $unit, 'Stone fell out after a week of wear.');

        $claims->transition($claim, ClaimStatus::MoreInfoRequired, $admin, 'Please send a close-up photo.');
        $claims->customerReply($claim->refresh(), $customer, 'Attached a photo.');
        $this->assertSame(ClaimStatus::UnderReview, $claim->refresh()->status);

        try {
            $claims->transition($claim, ClaimStatus::Approved, $admin, '');
            $this->fail('Decision requires a reason');
        } catch (\App\Exceptions\BusinessRuleException) {
        }
        $claims->transition($claim, ClaimStatus::Approved, $admin, 'Manufacturing defect', ['decision_reason' => 'Manufacturing defect', 'resolution' => 'replacement']);
        $replacement = $claims->issueReplacement($claim->refresh(), $unit->orderItem->variant, $admin);

        $this->assertSame($unit->id, $replacement->replacement_of_unit_id);
        $this->assertSame('remaining', $replacement->replacement_policy);
        $this->assertEquals($unit->warranty_ends_at, $replacement->warranty_ends_at);
        $this->assertSame('void', $unit->refresh()->warranty_status->value);

        $this->expectException(\App\Exceptions\InvalidTransition::class);
        $claims->transition($claim->refresh(), ClaimStatus::Submitted, $admin);
    }
}
