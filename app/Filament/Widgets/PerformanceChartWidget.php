<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\ResolvesPerformanceScope;
use App\Models\Commission;
use App\Models\Conversion;
use App\Models\Lead;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class PerformanceChartWidget extends ChartWidget
{
    use InteractsWithPageFilters;
    use ResolvesPerformanceScope;

    protected ?string $heading = 'Verlauf';

    public ?string $filter = 'ersteinschaetzungen';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'ersteinschaetzungen' => 'Ersteinschätzungen',
            'beauftragt' => 'Beauftragte Gutachten',
            'provision' => 'Provision (€)',
        ];
    }

    protected function getData(): array
    {
        $from = $this->rangeFrom();
        $until = $this->rangeUntil();
        $partnerId = $this->scopePartnerId();
        $metric = $this->filter ?? 'ersteinschaetzungen';

        // Build an ordered day-bucket skeleton (cap to keep the axis readable).
        $days = [];
        $cursor = $from->copy()->startOfDay();
        $end = $until->copy()->startOfDay();
        $guard = 0;
        while ($cursor <= $end && $guard < 370) {
            $days[$cursor->format('Y-m-d')] = 0;
            $cursor->addDay();
            $guard++;
        }
        $labels = array_keys($days);

        $map = match ($metric) {
            'beauftragt' => Conversion::query()
                ->whereBetween('converted_at', [$from, $until])
                ->when($partnerId, fn (Builder $q) => $q->whereHas('lead', fn (Builder $l) => $l->where('partner_id', $partnerId)))
                ->selectRaw('DATE(converted_at) as d, COUNT(*) as c')
                ->groupBy('d')->pluck('c', 'd'),
            'provision' => Commission::query()
                ->when($partnerId, fn (Builder $q) => $q->where('commissions.partner_id', $partnerId))
                ->join('conversions', 'commissions.conversion_id', '=', 'conversions.id')
                ->whereBetween('conversions.converted_at', [$from, $until])
                ->selectRaw('DATE(conversions.converted_at) as d, SUM(commissions.amount) as c')
                ->groupBy('d')->pluck('c', 'd'),
            default => Lead::query()
                ->when($partnerId, fn (Builder $q) => $q->where('partner_id', $partnerId))
                ->whereBetween('submitted_at', [$from, $until])
                ->selectRaw('DATE(submitted_at) as d, COUNT(*) as c')
                ->groupBy('d')->pluck('c', 'd'),
        };

        $data = array_map(fn (string $d): float => (float) ($map[$d] ?? 0), $labels);

        return [
            'datasets' => [[
                'label' => $this->getFilters()[$metric] ?? '',
                'data' => $data,
                'borderColor' => '#14b8a6',
                'backgroundColor' => 'rgba(20, 184, 166, 0.15)',
                'fill' => 'start',
                'tension' => 0.3,
            ]],
            'labels' => array_map(fn (string $d): string => Carbon::parse($d)->format('d.m.'), $labels),
        ];
    }
}
