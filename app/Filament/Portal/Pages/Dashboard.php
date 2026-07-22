<?php

namespace App\Filament\Portal\Pages;

use App\Filament\Widgets\PerformanceChartWidget;
use App\Filament\Widgets\PerformanceStatsWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Meine Performance';

    public static function getNavigationLabel(): string
    {
        return 'Meine Performance';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('from')
                    ->label('Von')
                    ->native(false)
                    ->default(now()->subDays(30))
                    ->maxDate(now()),
                DatePicker::make('until')
                    ->label('Bis')
                    ->native(false)
                    ->default(now())
                    ->maxDate(now()),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            PerformanceStatsWidget::class,
            PerformanceChartWidget::class,
        ];
    }

    public function getColumns(): int | array
    {
        return 1;
    }
}
