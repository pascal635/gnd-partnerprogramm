<?php

namespace App\Filament\Pages;

use App\Services\Reporting\CommissionReport;
use App\Services\Reporting\MonthlyReportSender;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BuchhaltungsExport extends Page
{
    protected string $view = 'filament.pages.buchhaltungs-export';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    protected static string|\UnitEnum|null $navigationGroup = 'Verwaltung';

    protected static ?string $navigationLabel = 'Buchhaltungs-Export';

    protected static ?string $title = 'Buchhaltungs-Export';

    protected static ?int $navigationSort = 80;

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public function mount(): void
    {
        $last = Carbon::now()->subMonthNoOverflow();

        $this->form->fill([
            'from' => $last->copy()->startOfMonth()->toDateString(),
            'until' => $last->copy()->endOfMonth()->toDateString(),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Zeitraum')
                ->description('Standard: Vormonat. Maßgeblich ist das Beauftragungs-Datum.')
                ->columns(2)
                ->schema([
                    DatePicker::make('from')->label('Von')->native(false)->required(),
                    DatePicker::make('until')->label('Bis')->native(false)->required(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('detail')
                ->label('Detail-CSV')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->action(fn (): StreamedResponse => $this->downloadCsv('detail')),
            Action::make('summary')
                ->label('Summen-CSV')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->action(fn (): StreamedResponse => $this->downloadCsv('summary')),
            Action::make('sendNow')
                ->label('Monatsreport (Vormonat) jetzt senden')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Versendet die Partner-Mails und den Buchhaltungs-Report für den Vormonat sofort.')
                ->action(function (): void {
                    app(MonthlyReportSender::class)->sendForMonth(Carbon::now()->subMonthNoOverflow()->startOfMonth());
                    Notification::make()->title('Monatsreports versendet')->success()->send();
                }),
        ];
    }

    private function downloadCsv(string $which): StreamedResponse
    {
        [$from, $until] = $this->range();
        $report = new CommissionReport($from, $until);
        $csv = $which === 'detail' ? $report->detailCsv() : $report->summaryCsv();
        $name = "buchhaltung-{$which}-{$from->format('Y-m-d')}_{$until->format('Y-m-d')}.csv";

        return response()->streamDownload(
            fn () => print ($csv),
            $name,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(): array
    {
        $state = $this->form->getState();

        return [
            Carbon::parse($state['from'])->startOfDay(),
            Carbon::parse($state['until'])->endOfDay(),
        ];
    }
}
