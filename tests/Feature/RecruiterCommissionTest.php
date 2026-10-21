<?php

namespace Tests\Feature;

use App\Enums\CommissionCalcStatus;
use App\Enums\CommissionStatus;
use App\Enums\CommissionTier;
use App\Models\Commission;
use App\Models\Partner;
use App\Models\Setting;
use App\Services\Attribution\ManualAttribution;
use App\Services\Commission\CommissionCalculator;
use App\Services\Partner\RecruitOnboarding;
use App\Services\Reporting\CommissionReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesPartners;
use Tests\TestCase;

class RecruiterCommissionTest extends TestCase
{
    use CreatesPartners;
    use RefreshDatabase;

    /** X recruited Z; Z's code pays 15 % direct. */
    private function recruiterAndRecruit(?string $rate = '5%'): array
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE', '10%');
        [$z] = $this->partnerWithCode('Z GmbH', 'ZCODE', '15%');
        $z->update(['recruited_by_partner_id' => $x->id, 'recruiter_commission_raw' => $rate]);

        return [$x, $z];
    }

    public function test_recruiter_earns_on_top_of_the_direct_provision(): void
    {
        [$x, $z] = $this->recruiterAndRecruit('5%');
        $this->ingestLead('1001', 'ZCODE', 'kunde@example.test');

        $conversion = $this->ingestConversion('d1', '1001', null, 1000.0);

        $this->assertSame($z->id, $conversion->commission->partner_id);
        $this->assertEquals(150.00, (float) $conversion->commission->amount);

        $recruiter = $conversion->recruiterCommission;
        $this->assertSame($x->id, $recruiter->partner_id);
        $this->assertSame($z->id, $recruiter->source_partner_id);
        $this->assertSame(CommissionTier::Recruiter, $recruiter->tier);
        $this->assertEquals(50.00, (float) $recruiter->amount);
    }

    public function test_global_default_applies_and_is_configurable(): void
    {
        $this->recruiterAndRecruit(null);
        $this->ingestLead('1001', 'ZCODE', 'a@example.test');
        $this->ingestLead('1002', 'ZCODE', 'b@example.test');

        $first = $this->ingestConversion('d1', '1001', null, 1000.0);
        $this->assertEquals(50.00, (float) $first->recruiterCommission->amount); // 5 % default

        Setting::put(CommissionCalculator::RECRUITER_DEFAULT_KEY, '3%');
        $second = $this->ingestConversion('d2', '1002', null, 1000.0);
        $this->assertEquals(30.00, (float) $second->recruiterCommission->amount);
    }

    public function test_fixed_recruiter_rate(): void
    {
        $this->recruiterAndRecruit('25');
        $this->ingestLead('1001', 'ZCODE', 'kunde@example.test');

        $conversion = $this->ingestConversion('d1', '1001', null, 1000.0);

        $this->assertEquals(25.00, (float) $conversion->recruiterCommission->amount);
    }

    public function test_missing_deal_value_keeps_recruiter_pending_until_filled_in(): void
    {
        $this->recruiterAndRecruit('5%');
        $this->ingestLead('1001', 'ZCODE', 'kunde@example.test');
        $conversion = $this->ingestConversion('d1', '1001', null, null);

        $this->assertSame(CommissionCalcStatus::PendingInput, $conversion->recruiterCommission->calc_status);

        app(ManualAttribution::class)->setDealValue($conversion, 2000);

        $conversion->refresh();
        $this->assertEquals(300.00, (float) $conversion->commission->amount);
        $this->assertEquals(100.00, (float) $conversion->recruiterCommission->amount);
    }

    public function test_only_one_level_is_paid(): void
    {
        [$x, $z] = $this->recruiterAndRecruit('5%');
        [$y] = $this->partnerWithCode('Y GmbH', 'YCODE');
        $x->update(['recruited_by_partner_id' => $y->id]); // Y recruited X

        $this->ingestLead('1001', 'ZCODE', 'kunde@example.test');
        $this->ingestConversion('d1', '1001', null, 1000.0);

        $this->assertSame(2, Commission::count());
        $this->assertFalse(Commission::where('partner_id', $y->id)->exists());
    }

    public function test_inactive_recruiter_earns_nothing(): void
    {
        [$x] = $this->recruiterAndRecruit('5%');
        $x->update(['status' => 'inactive']);
        $this->ingestLead('1001', 'ZCODE', 'kunde@example.test');

        $conversion = $this->ingestConversion('d1', '1001', null, 1000.0);

        $this->assertNull($conversion->recruiterCommission);
    }

    public function test_detach_removes_open_recruiter_commissions_but_keeps_paid_ones(): void
    {
        [, $z] = $this->recruiterAndRecruit('5%');
        $this->ingestLead('1001', 'ZCODE', 'a@example.test');
        $this->ingestLead('1002', 'ZCODE', 'b@example.test');
        $paid = $this->ingestConversion('d1', '1001', null, 1000.0)->recruiterCommission;
        $paid->update(['status' => CommissionStatus::Paid]);
        $this->ingestConversion('d2', '1002', null, 1000.0);

        app(RecruitOnboarding::class)->detach($z);

        $this->assertNull($z->refresh()->recruited_by_partner_id);
        $this->assertSame(1, Commission::where('tier', CommissionTier::Recruiter->value)->count());
        $this->assertTrue($paid->fresh()->exists);
    }

    public function test_moving_an_order_away_from_the_recruit_removes_the_recruiter_commission(): void
    {
        $this->recruiterAndRecruit('5%');
        [$w] = $this->partnerWithCode('W GmbH', 'WCODE', '10%');
        $this->ingestLead('1001', 'ZCODE', 'kunde@example.test');
        $conversion = $this->ingestConversion('d1', '1001', null, 1000.0);

        app(ManualAttribution::class)->assign($conversion, $w, null, true);

        $conversion->refresh();
        $this->assertSame($w->id, $conversion->commission->partner_id);
        $this->assertNull($conversion->recruiterCommission);
    }

    public function test_attaching_with_recalculation_backfills_open_orders(): void
    {
        [$x] = $this->partnerWithCode('X GmbH', 'XCODE');
        [$z] = $this->partnerWithCode('Z GmbH', 'ZCODE', '15%');
        $this->ingestLead('1001', 'ZCODE', 'kunde@example.test');
        $conversion = $this->ingestConversion('d1', '1001', null, 1000.0);
        $this->assertNull($conversion->recruiterCommission);

        $count = app(RecruitOnboarding::class)->attach($x, $z, '5%', true);

        $this->assertSame(1, $count);
        $this->assertEquals(50.00, (float) $conversion->refresh()->recruiterCommission->amount);
    }

    public function test_accounting_report_lists_recruiter_rows_and_recruiter_without_own_orders(): void
    {
        [$x] = $this->recruiterAndRecruit('5%');
        $this->ingestLead('1001', 'ZCODE', 'kunde@example.test');
        $this->ingestConversion('d1', '1001', null, 1000.0);

        $report = new CommissionReport(Carbon::parse('2026-10-01')->startOfMonth(), Carbon::parse('2026-10-31')->endOfDay());

        $summary = collect($report->partnerSummaryRows())->keyBy('partner');
        $this->assertEqualsWithDelta(50.0, $summary['X GmbH']['provision'], 0.001);
        $this->assertEqualsWithDelta(50.0, $summary['X GmbH']['recruiter_provision'], 0.001);
        $this->assertEqualsWithDelta(150.0, $summary['Z GmbH']['provision'], 0.001);

        $this->assertCount(2, $report->detailRows());
        $this->assertStringContainsString('Werber-Provision', $report->detailCsv());
    }
}
