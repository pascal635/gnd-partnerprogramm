<?php

namespace App\Filament\Portal\Pages;

use App\Models\Partner;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Partner bearbeitet seine eigenen Stammdaten, Bankverbindung und den
 * Fachartikel-Link. Strikt auf den eingeloggten Partner beschränkt.
 */
class Stammdaten extends Page
{
    protected string $view = 'filament.portal.pages.stammdaten';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'Meine Daten';

    protected static ?string $title = 'Meine Daten';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public function mount(): void
    {
        $partner = $this->getPartner();

        if ($partner) {
            $this->form->fill($partner->attributesToArray());
        }
    }

    public function getPartner(): ?Partner
    {
        return auth()->user()?->partner;
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->model($this->getPartner())
            ->operation('edit')
            ->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Persönliche Daten')
                    ->columns(2)
                    ->schema([
                        TextInput::make('company_name')
                            ->label('Firmenname')
                            ->required()
                            ->maxLength(191),
                        Select::make('salutation')
                            ->label('Anrede')
                            ->options([
                                'Herr' => 'Herr',
                                'Frau' => 'Frau',
                                'Divers' => 'Divers',
                            ])
                            ->native(false),
                        TextInput::make('first_name')
                            ->label('Vorname')
                            ->maxLength(191),
                        TextInput::make('last_name')
                            ->label('Nachname')
                            ->maxLength(191),
                        TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(64),
                    ]),

                Section::make('Adresse')
                    ->columns(3)
                    ->schema([
                        TextInput::make('street')->label('Straße & Nr.')->columnSpan(3),
                        TextInput::make('zip')->label('PLZ')->maxLength(16),
                        TextInput::make('city')->label('Ort')->columnSpan(2),
                    ]),

                Section::make('Bankverbindung')
                    ->description('Damit wir die Provision überweisen können.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('account_holder')
                            ->label('Kontoinhaber')
                            ->maxLength(191),
                        TextInput::make('bic')
                            ->label('BIC')
                            ->maxLength(32),
                        TextInput::make('iban_encrypted')
                            ->label('IBAN')
                            ->maxLength(40)
                            ->columnSpanFull(),
                    ]),

                Section::make('Fachartikel')
                    ->description('Sobald du unseren Fachartikel auf deiner Website veröffentlicht hast, trage hier den Link ein.')
                    ->schema([
                        TextInput::make('article_url')
                            ->label('Link zum veröffentlichten Artikel')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://…'),
                    ]),
            ]);
    }

    public function save(): void
    {
        $partner = $this->getPartner();

        if (! $partner) {
            return;
        }

        // Beim Speichern durch den Partner nie die Prüf-Markierung ändern.
        $data = $this->form->getState();

        $partner->update($data);

        Notification::make()
            ->title('Gespeichert')
            ->body('Deine Daten wurden aktualisiert.')
            ->success()
            ->send();
    }
}
