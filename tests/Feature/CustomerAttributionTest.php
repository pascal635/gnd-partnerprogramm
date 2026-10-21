<?php

namespace Tests\Feature;

use App\Enums\AttributedVia;
use App\Enums\ConversionStatus;
use App\Enums\LeadSource;
use App\Enums\MatchedBy;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPartners;
use Tests\TestCase;

class CustomerAttributionTest extends TestCase
{
    use CreatesPartners;
    use RefreshDatabase;

    public function test_first_lead_with_code_makes_the_partner_owner_of_the_customer(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');

        $lead = $this->ingestLead('1001', 'XCODE', ' Kunde@Example.test ');

        $customer = Customer::sole();
        $this->assertSame('kunde@example.test', $customer->email_norm);
        $this->assertSame($x->id, $customer->partner_id);
        $this->assertSame($lead->id, $customer->first_lead_id);
        $this->assertSame(AttributedVia::Voucher, $lead->attributed_via);
    }

    public function test_returning_customer_with_another_partners_code_stays_with_first_partner(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE', '10%');
        $this->partnerWithCode('W GmbH', 'WCODE', '20%');

        $this->ingestLead('1001', 'XCODE', 'kunde@example.test');
        $second = $this->ingestLead('1002', 'WCODE', 'kunde@example.test');

        $this->assertSame($x->id, $second->partner_id);
        $this->assertSame(AttributedVia::Customer, $second->attributed_via);
        $this->assertSame('10%', $second->voucher_commission_raw); // X's terms, not W's
        $this->assertSame('WCODE', $second->voucher_code_raw);     // what was typed stays visible
        $this->assertSame(1, Customer::count());
    }

    public function test_conversion_with_lead_id_learns_the_pipedrive_person(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE', '10%');
        $this->ingestLead('1001', 'XCODE', 'kunde@example.test');

        $conversion = $this->ingestConversion('d1', '1001', '77');

        $this->assertSame('77', Customer::sole()->pipedrive_person_id);
        $this->assertSame(MatchedBy::ExternalLeadId, $conversion->matched_by);
        $this->assertSame($x->id, $conversion->commission->partner_id);
        $this->assertEquals(100.00, (float) $conversion->commission->amount);
    }

    public function test_repeat_deal_with_known_person_is_attributed_to_the_owner(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE', '10%');
        $this->ingestLead('1001', 'XCODE', 'kunde@example.test');
        $this->ingestConversion('d1', '1001', '77');

        // Later deal in Pipedrive: no new form, no lead id — only the person.
        $repeat = $this->ingestConversion('d2', null, '77', 2000.0);

        $this->assertSame(MatchedBy::Customer, $repeat->matched_by);
        $this->assertSame(ConversionStatus::Matched, $repeat->status);
        $this->assertSame(LeadSource::Repeat, $repeat->lead->source);
        $this->assertSame($x->id, $repeat->lead->partner_id);
        $this->assertEquals(200.00, (float) $repeat->commission->amount);
    }

    public function test_repeat_orders_are_not_counted_as_assessments(): void
    {
        $this->partnerWithCode('X GmbH', 'XCODE');
        $this->ingestLead('1001', 'XCODE', 'kunde@example.test');
        $this->ingestConversion('d1', '1001', '77');
        $this->ingestConversion('d2', null, '77');

        $this->assertSame(2, Lead::count());
        $this->assertSame(1, Lead::query()->assessments()->count());
    }

    public function test_unknown_deal_becomes_a_stub_that_needs_review(): void
    {
        $conversion = $this->ingestConversion('d9', null, '999');

        $this->assertSame(ConversionStatus::NeedsReview, $conversion->status);
        $this->assertNull($conversion->lead->partner_id);
    }

    public function test_stub_person_customer_merges_into_email_customer_when_the_lead_arrives(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE', '10%');
        $this->ingestLead('1001', 'XCODE', 'kunde@example.test'); // email customer owned by X

        // Conversion for a new WP lead arrives before that lead (race) and carries a person id.
        $this->ingestConversion('d3', '2002', '55');
        $this->assertSame(2, Customer::count());

        $lead = $this->ingestLead('2002', null, 'kunde@example.test');

        $this->assertSame(1, Customer::count());
        $customer = Customer::sole();
        $this->assertSame('55', $customer->pipedrive_person_id);
        $this->assertSame($x->id, $lead->partner_id);
        $this->assertSame($x->id, $lead->conversions()->first()->commission->partner_id);
    }
}
