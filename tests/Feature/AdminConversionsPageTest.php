<?php

namespace Tests\Feature;

use App\Enums\MatchedBy;
use App\Filament\Resources\Conversions\Pages\ListConversions;
use App\Models\Conversion;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesPartners;
use Tests\TestCase;

class AdminConversionsPageTest extends TestCase
{
    use CreatesPartners;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]));

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_orders_page_renders_with_records(): void
    {
        $this->partnerWithCode('X GmbH', 'XCODE');
        $this->ingestLead('1001', 'XCODE', 'kunde@example.test');
        $this->ingestConversion('d1', '1001', '77');
        $this->ingestConversion('d9', null, null); // unassigned stub

        Livewire::test(ListConversions::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(Conversion::all());
    }

    public function test_assign_action_attributes_the_order(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE', '150');
        $conversion = $this->ingestConversion('d9', null, null);

        Livewire::test(ListConversions::class)
            ->callTableAction('assign', $conversion, data: [
                'partner_id' => $x->id,
                'voucher_code_id' => null,
                'reassign_customer' => true,
            ])
            ->assertHasNoTableActionErrors();

        $conversion->refresh();
        $this->assertSame(MatchedBy::Manual, $conversion->matched_by);
        $this->assertEquals(150.00, (float) $conversion->commission->amount);
    }

    public function test_deal_value_action_fills_pending_commission(): void
    {
        $this->partnerWithCode('X GmbH', 'XCODE', '10%');
        $this->ingestLead('1001', 'XCODE', 'kunde@example.test');
        $conversion = $this->ingestConversion('d1', '1001', null, null);

        Livewire::test(ListConversions::class)
            ->callTableAction('dealValue', $conversion, data: ['deal_value' => 900])
            ->assertHasNoTableActionErrors();

        $this->assertEquals(90.00, (float) $conversion->refresh()->commission->amount);
    }

    public function test_manual_order_header_action_creates_order_and_commission(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE', '10%');

        Livewire::test(ListConversions::class)
            ->callAction('manualCreate', data: [
                'partner_id' => $x->id,
                'voucher_code_id' => null,
                'customer_name' => 'Erika Muster',
                'customer_email' => 'erika@example.test',
                'deal_value' => 1300,
                'converted_at' => '2026-10-02',
                'deal_id' => 'pd-42',
            ])
            ->assertHasNoActionErrors();

        $conversion = Conversion::where('external_deal_id', 'pd-42')->sole();
        $this->assertSame($x->id, $conversion->lead->partner_id);
        $this->assertEquals(130.00, (float) $conversion->commission->amount);
    }
}
