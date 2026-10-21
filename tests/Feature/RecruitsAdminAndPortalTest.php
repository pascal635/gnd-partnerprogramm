<?php

namespace Tests\Feature;

use App\Filament\Portal\Pages\Dashboard as PortalDashboard;
use App\Filament\Portal\Widgets\RecruiterCommissionsWidget;
use App\Filament\Resources\Partners\Pages\EditPartner;
use App\Filament\Resources\Partners\RelationManagers\RecruitsRelationManager;
use App\Mail\PartnerInvitationMail;
use App\Mail\PartnerWelcomeMail;
use App\Models\Partner;
use App\Models\User;
use App\Models\VoucherCode;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\Concerns\CreatesPartners;
use Tests\TestCase;

class RecruitsAdminAndPortalTest extends TestCase
{
    use CreatesPartners;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        Mail::fake();
        Http::fake();
    }

    private function actAsAdmin(): void
    {
        $this->actingAs(User::create([
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => bcrypt('secret'), 'is_active' => true,
        ]));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_partner_edit_page_and_subpartner_tab_render(): void
    {
        $this->actAsAdmin();
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');
        [$z] = $this->partnerWithCode('Z GmbH', 'ZCODE');
        $z->update(['recruited_by_partner_id' => $x->id]);

        Livewire::test(EditPartner::class, ['record' => $z->getRouteKey()])->assertSuccessful();

        Livewire::test(RecruitsRelationManager::class, ['ownerRecord' => $x, 'pageClass' => EditPartner::class])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$z]);
    }

    public function test_create_subpartner_with_code_and_invitation(): void
    {
        $this->actAsAdmin();
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');

        Livewire::test(RecruitsRelationManager::class, ['ownerRecord' => $x, 'pageClass' => EditPartner::class])
            ->callTableAction('createRecruit', data: [
                'company_name' => 'Sub GmbH',
                'partner_type' => 'makler',
                'salutation' => 'Frau',
                'first_name' => 'Erika',
                'last_name' => 'Muster',
                'email' => 'erika@sub.test',
                'recruiter_commission_raw' => '5%',
                'create_voucher' => true,
                'code' => 'sub15',
                'type' => 'prozent',
                'value' => 10,
                'commission_kind' => 'percent',
                'commission_value' => 15,
                'invite' => true,
            ])
            ->assertHasNoTableActionErrors();

        $sub = Partner::where('company_name', 'Sub GmbH')->sole();
        $this->assertSame($x->id, $sub->recruited_by_partner_id);
        $this->assertSame('5%', $sub->recruiter_commission_raw);

        $voucher = VoucherCode::where('code', 'SUB15')->sole();
        $this->assertSame($sub->id, $voucher->partner_id);
        $this->assertSame('15%', $voucher->commission_raw);

        Mail::assertSent(PartnerInvitationMail::class);
        Mail::assertSent(PartnerWelcomeMail::class);
    }

    public function test_invalid_recruiter_rate_is_rejected(): void
    {
        $this->actAsAdmin();
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');

        Livewire::test(RecruitsRelationManager::class, ['ownerRecord' => $x, 'pageClass' => EditPartner::class])
            ->callTableAction('createRecruit', data: [
                'company_name' => 'Sub GmbH',
                'partner_type' => 'makler',
                'email' => 'erika@sub.test',
                'recruiter_commission_raw' => 'viel',
                'create_voucher' => false,
                'invite' => false,
            ])
            ->assertHasTableActionErrors(['recruiter_commission_raw']);
    }

    public function test_portal_shows_recruiter_provision_to_the_recruiter(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');
        [$z] = $this->partnerWithCode('Z GmbH', 'ZCODE', '15%');
        $z->update(['recruited_by_partner_id' => $x->id, 'recruiter_commission_raw' => '5%']);
        $this->ingestLead('1001', 'ZCODE', 'kunde@example.test');
        $this->ingestConversion('d1', '1001', null, 1000.0);

        $this->actingAs(User::create([
            'name' => 'X', 'email' => 'x@portal.test', 'password' => bcrypt('secret'), 'is_active' => true, 'partner_id' => $x->id,
        ]));
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        $this->assertTrue(RecruiterCommissionsWidget::canView());
        Livewire::test(PortalDashboard::class)->assertSuccessful();
        Livewire::test(RecruiterCommissionsWidget::class)
            ->assertSuccessful()
            ->assertSee('Z GmbH');
    }
}
