<?php

namespace App\Filament\Resources\VoucherCodes\Schemas;

use App\Enums\CommissionKind;
use App\Enums\VoucherType;
use App\Models\Partner;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class VoucherCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Gutschein-Code')
                ->description('Diese Angaben werden 1:1 an WordPress übergeben.')
                ->columns(2)
                ->schema([
                    TextInput::make('code')
                        ->label('Code')
                        ->required()
                        ->maxLength(64)
                        ->live(onBlur: true)
                        ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                        ->dehydrateStateUsing(fn (?string $state) => strtoupper(trim((string) $state)))
                        ->rule('regex:/^[A-Za-z0-9_-]+$/')
                        ->helperText('Nur Buchstaben, Ziffern, „-" und „_". Kein Semikolon.')
                        ->unique(ignoreRecord: true),
                    Select::make('type')
                        ->label('Rabatt-Typ')
                        ->options(VoucherType::class)
                        ->default(VoucherType::Prozent->value)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    TextInput::make('value')
                        ->label('Rabatt-Wert')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->suffix(fn (Get $get) => static::scalar($get('type')) === VoucherType::Prozent->value ? '%' : '€'),
                ]),

            Section::make('Partner & Provision')
                ->columns(2)
                ->schema([
                    Select::make('partner_id')
                        ->label('Partner')
                        ->relationship('partner', 'company_name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->helperText('Leer lassen für einen reinen Promo-Code ohne Partner.')
                        ->afterStateUpdated(function ($state, Set $set) {
                            if ($state) {
                                $set('partner_label', Partner::find($state)?->company_name);
                            }
                        }),
                    TextInput::make('partner_label')
                        ->label('Partner-Label (WordPress)')
                        ->maxLength(191)
                        ->live(onBlur: true)
                        ->helperText('Erscheint als PARTNER im Gutschein.'),
                    Select::make('commission_kind')
                        ->label('Provision')
                        ->options(CommissionKind::class)
                        ->default(CommissionKind::None->value)
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),
                    TextInput::make('commission_value')
                        ->label('Provisions-Wert')
                        ->numeric()
                        ->minValue(0)
                        ->live(onBlur: true)
                        ->visible(fn (Get $get) => static::scalar($get('commission_kind')) !== CommissionKind::None->value)
                        ->suffix(fn (Get $get) => static::scalar($get('commission_kind')) === CommissionKind::Percent->value ? '%' : '€')
                        ->helperText(fn (Get $get) => static::scalar($get('commission_kind')) === CommissionKind::Percent->value
                            ? 'Prozent vom Verkaufswert des Gutachtens (aus Pipedrive).'
                            : 'Fester Betrag pro beauftragtem Gutachten.'),
                ]),

            Section::make('Gültigkeit')
                ->columns(3)
                ->schema([
                    Toggle::make('is_active')->label('Aktiv')->default(true),
                    DatePicker::make('valid_from')->label('Gültig ab')->native(false),
                    DatePicker::make('valid_until')->label('Gültig bis')->native(false),
                ]),

            Section::make('Vorschau')
                ->description('Genau diese Zeile wird an WordPress gesendet.')
                ->schema([
                    Placeholder::make('preview')
                        ->label('CODE;TYP;WERT;PARTNER;PROVISION')
                        ->content(fn (Get $get) => new HtmlString(static::previewHtml($get))),
                ]),
        ]);
    }

    /**
     * Derive commission_raw from the kind + value inputs (dashboard is the
     * source of truth; WordPress receives the assembled string).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fillCommissionRaw(array $data): array
    {
        $kind = $data['commission_kind'] ?? CommissionKind::None->value;
        $kind = $kind instanceof \BackedEnum ? $kind->value : (string) $kind;
        $value = $data['commission_value'] ?? null;

        if ($kind === CommissionKind::None->value || ! is_numeric($value)) {
            $data['commission_kind'] = CommissionKind::None->value;
            $data['commission_value'] = null;
            $data['commission_raw'] = null;

            return $data;
        }

        $number = static::formatNumber($value);
        $data['commission_raw'] = $kind === CommissionKind::Percent->value ? "{$number}%" : $number;

        return $data;
    }

    protected static function previewHtml(Get $get): string
    {
        $code = strtoupper(trim((string) $get('code')));
        $type = static::scalar($get('type')) ?: VoucherType::Prozent->value;
        $wert = static::formatNumber($get('value'));
        $partner = trim((string) $get('partner_label'));
        $kind = static::scalar($get('commission_kind')) ?: CommissionKind::None->value;
        $cval = static::formatNumber($get('commission_value'));

        $provision = match ($kind) {
            CommissionKind::Fix->value => $cval,
            CommissionKind::Percent->value => $cval === '' ? '' : $cval.'%',
            default => '',
        };

        $line = implode(';', [$code, $type, $wert, $partner, $provision]);

        $rabatt = $type === VoucherType::Prozent->value ? "{$wert}%" : "{$wert} €";
        $prov = match ($kind) {
            CommissionKind::Fix->value => ($cval === '' ? '—' : "{$cval} € pro beauftragtem Gutachten"),
            CommissionKind::Percent->value => ($cval === '' ? '—' : "{$cval}% vom Verkaufswert"),
            default => 'keine',
        };

        return '<div style="font-family:ui-monospace,monospace;font-size:.95rem;padding:.55rem .75rem;background:rgba(120,120,120,.12);border-radius:.5rem;word-break:break-all;">'
            .e($line).'</div>'
            .'<div style="margin-top:.5rem;font-size:.85rem;opacity:.85;">Kundenrabatt: <strong>'.e($rabatt).'</strong> &middot; Provision: <strong>'.e($prov).'</strong></div>';
    }

    protected static function scalar(mixed $state): string
    {
        if ($state instanceof \BackedEnum) {
            return (string) $state->value;
        }

        return (string) ($state ?? '');
    }

    protected static function formatNumber(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
