<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ResolvesPerformanceScope;
use App\Models\Commission;
use App\Models\Conversion;
use App\Models\Lead;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class PerformanceStatsWidget extends StatsOverviewWidget
{
    use InteractsWithPageFilters;
    use ResolvesPerformanceScope;

    protected function getStats(): array
    {
        $from = $this->rangeFrom();
        $until = $this->rangeUntil();
        $partnerId = $this->scopePartnerId();

        $ersteinschaetzungen = Lead::query()
            ->when($partnerId, fn (Builder $q) => $q->where('partner_id', $partnerId))
            ->whereBetween('submitted_at', [$from, $until])
            ->count();

        $beauftragt = Conversion::query()
            ->whereBetween('converted_at', [$from, $until])
            ->when($partnerId, fn (Builder $q) => $q->whereHas('lead', fn (Builder $l) => $l->where('partner_id', $partnerId)))
            ->count();

        $provision = (float) Commission::query()
            ->when($partnerId, fn (Builder $q) => $q->where('partner_id', $partnerId))
            ->whereHas('conversion', fn (Builder $c) => $c->whereBetween('converted_at', [$from, $until]))
            ->sum('amount');

        $rate = $ersteinschaetzungen > 0 ? round($beauftragt / $ersteinschaetzungen * 100) : 0;

        return [
            Stat::make('Ersteinschätzungen', (string) $ersteinschaetzungen)
                ->description('über den Gutscheincode')
                ->color('info'),
            Stat::make('Beauftragte Gutachten', (string) $beauftragt)
                ->description('daraus beauftragt')
                ->color('success'),
            Stat::make('Conversion-Rate', $ersteinschaetzungen > 0 ? $rate.' %' : '—')
                ->description('Beauftragt ÷ Ersteinschätzungen'),
            Stat::make('Provision', Number::currency($provision, 'EUR', 'de'))
                ->description('im Zeitraum verdient')
                ->color('warning'),
        ];
    }
}
