<?php

namespace Tests\Feature;

use App\Filament\Resources\VoucherCodes\Pages\ListVoucherCodes;
use App\Models\Conversion;
use App\Models\User;
use App\Models\VoucherCode;
use App\Services\Attribution\LateCodeAttribution;
use App\Services\Ingest\ConversionIngestService;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesPartners;
use Tests\TestCase;

class LateCodeAttributionTest extends TestCase
{
    use CreatesPartners;
    use RefreshDatabase;

    private function convert(string $dealId, ?string $leadId, ?string $code, float $value = 1000.0): Conversion
    {
        return app(ConversionIngestService::class)->ingest(array_filter([
            'deal_id' => $dealId,
            'lead_id' => $leadId,
            'deal_value' => $value,
            'voucher_code' => $code,
            'converted_at' => '2026-10-01T10:00:00Z',
        ], fn ($v) => $v !== null))->refresh();
    }

    public function test_code_added_later_in_pipedrive_assigns_a_lead_without_partner(): void
    {
        [$gessner] = $this->partnerWithCode('Gessner', 'GESSNER', '10%');
        $this->ingestLead('2001', null, 'schumacher@example.test');

        $conversion = $this->convert('d1', '2001', 'gessner');

        $this->assertSame($gessner->id, $conversion->lead->partner_id);
        $this->assertEquals(100.00, (float) $conversion->commission->amount);
    }

    public function test_pipedrive_code_never_overrides_an_existing_partner(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');
        $this->partnerWithCode('W GmbH', 'WCODE');
        $this->ingestLead('2001', 'XCODE', 'kunde@example.test');

        $conversion = $this->convert('d1', '2001', 'WCODE');

        $this->assertSame($x->id, $conversion->lead->partner_id);
        $this->assertSame($x->id, $conversion->commission->partner_id);
    }

    public function test_form_codes_match_regardless_of_case(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');

        $lead = $this->ingestLead('2001', ' xcode ', 'kunde@example.test');

        $this->assertSame($x->id, $lead->partner_id);
    }

    public function test_code_created_later_backfills_leads_and_recruiter_commission(): void
    {
        [$groebel] = $this->partnerWithCode('Gröbel', 'SGROEBEL');
        $this->ingestLead('2001', 'RSchneider', 'wegert@example.test');
        $conversion = $this->convert('d1', '2001', null);
        $this->assertNull($conversion->lead->partner_id);

        // Code and recruiter relationship are set up afterwards.
        [$schneider, $voucher] = $this->partnerWithCode('R. Schneider', 'RSCHNEIDER', '15%');
        $schneider->update(['recruited_by_partner_id' => $groebel->id, 'recruiter_commission_raw' => '5%']);

        $this->assertSame(1, app(LateCodeAttribution::class)->backfill($voucher));

        $conversion->refresh();
        $this->assertSame($schneider->id, $conversion->lead->partner_id);
        $this->assertEquals(150.00, (float) $conversion->commission->amount);
        $this->assertSame($groebel->id, $conversion->recruiterCommission->partner_id);
        $this->assertEquals(50.00, (float) $conversion->recruiterCommission->amount);
    }

    public function test_unknown_pipedrive_code_is_remembered_for_a_later_backfill(): void
    {
        $this->ingestLead('2001', null, 'gerlach@example.test');
        $conversion = $this->convert('d1', '2001', 'SGROEBEL');
        $this->assertNull($conversion->lead->partner_id);
        $this->assertSame('SGROEBEL', $conversion->lead->voucher_code_raw);

        [$groebel, $voucher] = $this->partnerWithCode('Gröbel', 'SGROEBEL');
        app(LateCodeAttribution::class)->backfill($voucher);

        $this->assertSame($groebel->id, $conversion->refresh()->lead->partner_id);
        $this->assertNotNull($conversion->commission);
    }

    public function test_form_lead_without_code_keeps_partner_from_pipedrive_stub(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');
        $conversion = $this->convert('d1', '2001', 'XCODE'); // order before the form lead

        $lead = $this->ingestLead('2001', null, 'kunde@example.test');

        $this->assertSame($x->id, $lead->partner_id);
        $this->assertSame($x->id, $conversion->refresh()->commission->partner_id);
    }

    public function test_admin_button_backfills_all_codes(): void
    {
        $this->seed(RoleSeeder::class);
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('secret'), 'is_active' => true,
        ]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->ingestLead('2001', 'KRUEMPFER', 'kruempfer@example.test');
        [$partner] = $this->partnerWithCode('Krümpfer-Partner', 'KRUEMPFER');

        Livewire::test(ListVoucherCodes::class)
            ->callAction('backfillLeads')
            ->assertHasNoActionErrors();

        $this->assertSame($partner->id, VoucherCode::where('code', 'KRUEMPFER')->sole()->leads()->sole()->partner_id);
    }
}
