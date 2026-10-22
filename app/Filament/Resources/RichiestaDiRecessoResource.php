<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RichiestaDiRecessoResource\Pages\EditRichiestaDiRecesso;
use App\Filament\Resources\RichiestaDiRecessoResource\Pages\ListRichiesteDiRecesso;
use App\Models\RichiestaDiRecesso;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le dichiarazioni di recesso arrivate dalla funzione online del sito
 * (art. 54-bis del Codice del Consumo). Il rimborso va fatto entro 14 giorni
 * dall'invio: la colonna "Scadenza rimborso" lo dice senza fare il conto.
 */
class RichiestaDiRecessoResource extends Resource
{
    protected static ?string $model = RichiestaDiRecesso::class;

    protected static ?string $modelLabel = 'Richiesta di recesso';

    protected static ?string $pluralModelLabel = 'Richieste di recesso';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Richieste di recesso';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'shop/recessi';

    public static function getNavigationBadge(): ?string
    {
        $aperte = RichiestaDiRecesso::whereNull('gestita_il')->count();

        return $aperte > 0 ? (string) $aperte : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dichiarazione del cliente')
                ->description('Inviata dal sito: non si modifica.')
                ->schema([
                    TextInput::make('numero_ordine')->label('Numero d\'ordine')->disabled(),
                    DateTimePicker::make('inviata_il')->label('Inviata il')->seconds()->disabled(),
                    TextInput::make('nome')->label('Nome')->disabled(),
                    TextInput::make('email')->label('Email')->disabled()
                        ->helperText(fn (?RichiestaDiRecesso $record) => $record?->emailDiversaDaQuellaDellOrdine()
                            ? 'Diversa da quella dell\'ordine ('.$record->emailDellOrdine().'): verificare chi ha inviato la dichiarazione prima di rimborsare.'
                            : null),
                    Textarea::make('articoli')->label('Articoli')->placeholder('Tutto l\'ordine')->disabled()->columnSpanFull(),
                ])->columns(2),
            Section::make('Gestione')
                ->schema([
                    DateTimePicker::make('gestita_il')
                        ->label('Gestita il')
                        ->helperText('Da compilare quando il rimborso è stato eseguito.'),
                    Textarea::make('note_interne')->label('Note interne')->rows(4)->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('order.user'))
            ->defaultSort('inviata_il', 'desc')
            ->columns([
                TextColumn::make('inviata_il')->label('Inviata il')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('numero_ordine')->label('Ordine')->searchable()
                    ->description(fn (RichiestaDiRecesso $r) => $r->order_id ? null : 'non trovato fra gli ordini'),
                TextColumn::make('nome')->label('Cliente')->searchable()
                    ->description(fn (RichiestaDiRecesso $r) => $r->emailDiversaDaQuellaDellOrdine()
                        ? $r->email.' — diversa da quella dell\'ordine ('.$r->emailDellOrdine().')'
                        : $r->email),
                TextColumn::make('scadenza')
                    ->label('Scadenza rimborso')
                    ->state(fn (RichiestaDiRecesso $r) => $r->inviata_il->copy()->addDays(14))
                    ->date('d/m/Y')
                    ->color(fn (RichiestaDiRecesso $r) => $r->gestita_il === null && $r->inviata_il->copy()->addDays(14)->isPast() ? 'danger' : null),
                IconColumn::make('gestita')
                    ->label('Gestita')
                    ->state(fn (RichiestaDiRecesso $r) => $r->gestita_il !== null)
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('gestita_il')
                    ->label('Stato')
                    ->nullable()
                    ->trueLabel('Gestite')
                    ->falseLabel('Da gestire')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('gestita_il'),
                        false: fn (Builder $q) => $q->whereNull('gestita_il'),
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('Apri'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRichiesteDiRecesso::route('/'),
            'edit' => EditRichiestaDiRecesso::route('/{record}/edit'),
        ];
    }
}
