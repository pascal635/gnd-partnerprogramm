<?php

namespace Tests\Feature;

use App\Enums\AttributedVia;
use App\Enums\CommissionCalcStatus;
use App\Enums\CommissionStatus;
use App\Enums\LeadSource;
use App\Enums\MatchedBy;
use App\Models\Customer;
use App\Services\Attribution\ManualAttribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Concerns\CreatesPartners;
use Tests\TestCase;

class ManualAttributionTest extends TestCase
{
    use CreatesPartners;
    use RefreshDatabase;

    public function test_unassigned_order_can_be_attributed_afterwards(): void
    {
        [$x, $xCode] = $this->partnerWithCode('X GmbH', 'XCODE', '150');
        $this->ingestLead('1001', null, 'kunde@example.test');          // no code
        $conversion = $this->ingestConversion('d1', '1001', null);
        $this->assertNull($conversion->commission);

        app(ManualAttribution::class)->assign($conversion, $x, $xCode, true);

        $conversion->refresh();
        $this->assertSame(MatchedBy::Manual, $conversion->matched_by);
        $this->assertSame(AttributedVia::Manual, $conversion->lead->attributed_via);
        $this->assertSame($x->id, Customer::sole()->partner_id);
        $this->assertEquals(150.00, (float) $conversion->commission->amount);
    }

    public function test_reassignment_moves_the_open_commission_to_the_new_partner(): void
    {
        $this->partnerWithCode('W GmbH', 'WCODE', '10%');
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE', '20%');
        $this->ingestLead('1001', 'WCODE', 'kunde@example.test');
        $conversion = $this->ingestConversion('d1', '1001', null);

        app(ManualAttribution::class)->assign($conversion, $x, null, true);

        $commission = $conversion->refresh()->commission;
        $this->assertSame($x->id, $commission->partner_id);
        $this->assertEquals(200.00, (float) $commission->amount);
    }

    public function test_locked_commission_cannot_be_reassigned(): void
    {
        $this->partnerWithCode('W GmbH', 'WCODE');
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');
        $this->ingestLead('1001', 'WCODE', 'kunde@example.test');
        $conversion = $this->ingestConversion('d1', '1001', null);
        $conversion->commission->update(['status' => CommissionStatus::Paid]);

        $this->expectException(RuntimeException::class);
        app(ManualAttribution::class)->assign($conversion->refresh(), $x, null, true);
    }

    public function test_later_webhook_does_not_override_a_manual_attribution(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE', '10%');
        $this->partnerWithCode('W GmbH', 'WCODE', '10%');

        $manual = app(ManualAttribution::class)->createManual([
            'partner_id' => $x->id,
            'customer_email' => 'kunde@example.test',
            'deal_value' => 1000,
            'converted_at' => '2026-10-01',
            'deal_id' => 'pd-1',
        ]);
        $this->assertSame(LeadSource::Manual, $manual->lead->source);

        // Zapier fires later for the same deal with another partner's code.
        $after = $this->ingestConversion('pd-1', null, null, 1500.0);

        $this->assertSame($manual->id, $after->id);
        $this->assertSame($x->id, $after->lead->partner_id);
        $this->assertEquals(150.00, (float) $after->commission->amount); // value refreshed, partner kept
    }

    public function test_missing_deal_value_can_be_filled_in(): void
    {
        $this->partnerWithCode('X GmbH', 'XCODE', '10%');
        $this->ingestLead('1001', 'XCODE', 'kunde@example.test');
        $conversion = $this->ingestConversion('d1', '1001', null, null);
        $this->assertSame(CommissionCalcStatus::PendingInput, $conversion->commission->calc_status);

        app(ManualAttribution::class)->setDealValue($conversion, 1200);

        $this->assertEquals(120.00, (float) $conversion->refresh()->commission->amount);
    }
}
