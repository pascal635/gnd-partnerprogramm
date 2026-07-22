<?php

namespace App\Filament\Resources\WebhookEvents\Tables;

use App\Enums\WebhookSource;
use App\Enums\WebhookStatus;
use App\Models\WebhookEvent;
use App\Services\Ingest\ConversionIngestService;
use App\Services\Ingest\LeadIngestService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Throwable;

class WebhookEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('received_at')
                    ->label('Empfangen')
                    ->dateTime('d.m.Y H:i:s')
                    ->sortable(),
                TextColumn::make('source')
                    ->label('Quelle')
                    ->badge(),
                TextColumn::make('event_type')
                    ->label('Event')
                    ->placeholder('—'),
                TextColumn::make('external_event_id')
                    ->label('Referenz')
                    ->searchable()
                    ->placeholder('—'),
                IconColumn::make('signature_valid')
                    ->label('Signatur')
                    ->boolean(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('processing_error')
                    ->label('Fehler')
                    ->limit(40)
                    ->tooltip(fn (?string $state): ?string => $state)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                SelectFilter::make('source')->label('Quelle')->options(WebhookSource::class),
                SelectFilter::make('status')->label('Status')->options(WebhookStatus::class),
            ])
            ->recordActions([
                Action::make('payload')
                    ->label('Payload')
                    ->icon(Heroicon::OutlinedCodeBracket)
                    ->color('gray')
                    ->modalHeading('Webhook-Payload')
                    ->modalContent(fn (WebhookEvent $record): HtmlString => new HtmlString(
                        '<pre style="white-space:pre-wrap;word-break:break-word;font-size:.8rem;max-height:60vh;overflow:auto;">'
                        .e(json_encode($record->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))
                        .'</pre>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Schließen'),
                Action::make('replay')
                    ->label('Erneut verarbeiten')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (WebhookEvent $record): bool => $record->status === WebhookStatus::Failed)
                    ->action(function (WebhookEvent $record): void {
                        try {
                            $payload = $record->payload ?? [];
                            match ($record->source) {
                                WebhookSource::WpLead => app(LeadIngestService::class)->ingest($payload),
                                WebhookSource::ZapierConversion => app(ConversionIngestService::class)->ingest($payload),
                                default => null,
                            };
                            $record->update([
                                'status' => WebhookStatus::Processed,
                                'processed_at' => now(),
                                'processing_error' => null,
                            ]);
                            Notification::make()->title('Erneut verarbeitet')->success()->send();
                        } catch (Throwable $e) {
                            $record->update([
                                'status' => WebhookStatus::Failed,
                                'processing_error' => mb_substr($e->getMessage(), 0, 1000),
                            ]);
                            Notification::make()->title('Erneut fehlgeschlagen')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ]);
    }
}
