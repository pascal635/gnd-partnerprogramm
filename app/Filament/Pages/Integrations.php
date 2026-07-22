<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Secrets;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Integrations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static string|\UnitEnum|null $navigationGroup = 'Integrationen';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Integrationen';

    protected static ?string $title = 'Integrationen & Webhooks';

    protected string $view = 'filament.pages.integrations';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('settings')
                ->label('Einstellungen bearbeiten')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->modalWidth('2xl')
                ->fillForm(fn (): array => [
                    'wp_voucher_endpoint' => Secrets::wpVoucherEndpoint(),
                    'wp_sync_secret' => Secrets::wpSyncSecret(),
                    'wp_lead_secret' => Secrets::wpLeadSecret(),
                    'conversion_secret' => Secrets::conversionSecret(),
                    'pipedrive_lead_id_field' => Secrets::pipedriveLeadIdField(),
                ])
                ->schema([
                    TextInput::make('wp_voucher_endpoint')
                        ->label('WordPress-Gutschein-Endpoint')
                        ->helperText('Ausgehend: wohin neue Codes gesendet werden. z. B. https://…/wp-json/gnd/v1/vouchers')
                        ->url(),
                    TextInput::make('wp_sync_secret')
                        ->label('WP_SYNC_SECRET (Gutschein-Sync)')
                        ->helperText('Muss identisch in der wp-config.php stehen (GND_SYNC_SECRET).')
                        ->password()
                        ->revealable(),
                    TextInput::make('wp_lead_secret')
                        ->label('WP_LEAD_SECRET (Lead-Ingest)')
                        ->helperText('Zum Signieren der Lead-Webhooks (Zapier-Seite).')
                        ->password()
                        ->revealable(),
                    TextInput::make('conversion_secret')
                        ->label('CONVERSION_SECRET (Conversion-Ingest)')
                        ->helperText('Zum Signieren der Conversion-Webhooks (Zapier-Seite).')
                        ->password()
                        ->revealable(),
                    TextInput::make('pipedrive_lead_id_field')
                        ->label('Pipedrive-Feldname für die Lead-ID')
                        ->helperText('Nur informativ / für die Anleitung.'),
                ])
                ->action(function (array $data): void {
                    foreach ([
                        'wp_voucher_endpoint' => rtrim(trim((string) ($data['wp_voucher_endpoint'] ?? '')), '/'),
                        'wp_sync_secret' => trim((string) ($data['wp_sync_secret'] ?? '')),
                        'wp_lead_secret' => trim((string) ($data['wp_lead_secret'] ?? '')),
                        'conversion_secret' => trim((string) ($data['conversion_secret'] ?? '')),
                        'pipedrive_lead_id_field' => trim((string) ($data['pipedrive_lead_id_field'] ?? '')),
                    ] as $key => $value) {
                        Setting::put($key, $value !== '' ? $value : null);
                    }

                    Notification::make()->title('Einstellungen gespeichert')->success()->send();
                }),
        ];
    }

    protected function getViewData(): array
    {
        $pretty = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        $leadSample = json_encode([
            'event' => 'lead.created',
            'lead_id' => 'GND-2026-000123',
            'created_at' => '2026-07-21T09:14:00+02:00',
            'voucher_code' => 'PARTNER10',
            'voucher_partner' => 'PartnerGmbH',
            'voucher_commission' => '150',
            'voucher_typ' => 'prozent',
            'voucher_wert' => '10',
            'customer' => ['name' => '…', 'email' => '…', 'phone' => '…', 'plz' => '80331', 'ort' => 'München'],
            'property' => ['objektart' => 'Mehrfamilienhaus'],
            'source' => 'wp_ersteinschaetzung_v1',
        ], $pretty);

        $conversionSample = json_encode([
            'event' => 'deal.beauftragt',
            'lead_id' => 'GND-2026-000123',
            'deal_id' => 'pd_98765',
            'deal_value' => 890.00,
            'currency' => 'EUR',
            'voucher_code' => 'PARTNER10',
            'converted_at' => '2026-07-25T11:02:00+02:00',
        ], $pretty);

        $voucherSample = json_encode([
            'code' => 'PARTNER10',
            'typ' => 'prozent',
            'wert' => '10',
            'partner' => 'PartnerGmbH',
            'provision' => '150',
            'active' => true,
            'voucher_line' => 'PARTNER10;prozent;10;PartnerGmbH;150',
        ], $pretty);

        $zapierSnippet = <<<'JS'
// Zapier-Schritt "Code by Zapier" (JavaScript) – erzeugt Body + HMAC-Signatur
const crypto = require('crypto');
const SECRET = 'HIER_CONVERSION_SECRET_EINSETZEN';
const ts = Math.floor(Date.now() / 1000).toString();
const body = JSON.stringify({
  event: 'deal.beauftragt',
  lead_id: inputData.lead_id,
  deal_id: inputData.deal_id,
  deal_value: Number(inputData.deal_value),
  currency: 'EUR',
  voucher_code: inputData.voucher_code,
  converted_at: inputData.converted_at,
});
const sig = crypto.createHmac('sha256', SECRET).update(ts + '.' + body).digest('hex');
output = { ts: ts, body: body, signature: 'sha256=' + sig };
JS;

        return [
            'leadUrl' => url('/api/webhooks/wp/lead'),
            'conversionUrl' => url('/api/webhooks/conversion'),
            'wpVoucherEndpoint' => Secrets::wpVoucherEndpoint() ?: null,
            'leadSecret' => Secrets::wpLeadSecret(),
            'conversionSecret' => Secrets::conversionSecret(),
            'syncSecret' => Secrets::wpSyncSecret(),
            'replayWindow' => (int) config('gnd.webhooks.replay_window', 300),
            'pipedriveField' => Secrets::pipedriveLeadIdField(),
            'leadSample' => $leadSample,
            'conversionSample' => $conversionSample,
            'voucherSample' => $voucherSample,
            'zapierSnippet' => $zapierSnippet,
            'logUrl' => \App\Filament\Resources\WebhookEvents\WebhookEventResource::getUrl(),
        ];
    }
}
