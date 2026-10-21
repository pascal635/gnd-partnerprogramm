<?php

namespace App\Filament\Portal\Widgets;

use App\Enums\CommissionStatus;
use App\Enums\CommissionTier;
use App\Models\Commission;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Portal: provision this partner earns from orders of partners they recruited.
 * Shows the subpartner and amounts only — never any customer data.
 */
class RecruiterCommissionsWidget extends TableWidget
{
    protected static ?string $heading = 'Provision aus geworbenen Partnern';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $partnerId = auth()->user()?->partner_id;

        return $partnerId !== null && Commission::query()
            ->where('partner_id', $partnerId)
            ->where('tier', CommissionTier::Recruiter->value)
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Commission::query()
                ->where('partner_id', auth()->user()?->partner_id)
                ->where('tier', CommissionTier::Recruiter->value)
                ->where('status', '!=', CommissionStatus::Cancelled->value)
                ->with(['conversion', 'sourcePartner']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('conversion.converted_at')
                    ->label('Beauftragt am')
                    ->dateTime('d.m.Y'),
                TextColumn::make('sourcePartner.company_name')
                    ->label('Geworbener Partner')
                    ->placeholder('—'),
                TextColumn::make('amount')
                    ->label('Ihre Provision')
                    ->money('EUR', locale: 'de')
                    ->placeholder('wird berechnet'),
            ])
            ->paginated([10, 25]);
    }
}
