<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RichiestaDiRecessoResource\Pages;
use App\Models\RichiestaDiRecesso;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
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

    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';

    protected static ?string $navigationLabel = 'Richieste di recesso';

    protected static ?string $navigationGroup = 'Shop Ufficiale';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'shop/recessi';

    public static function getNavigationBadge(): ?string
    {
        $aperte = RichiestaDiRecesso::whereNull('gestita_il')->count();

        return $aperte > 0 ? (string) $aperte : null;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Dichiarazione del cliente')
                ->description('Inviata dal sito: non si modifica.')
                ->schema([
                    Forms\Components\TextInput::make('numero_ordine')->label('Numero d\'ordine')->disabled(),
                    Forms\Components\DateTimePicker::make('inviata_il')->label('Inviata il')->seconds()->disabled(),
                    Forms\Components\TextInput::make('nome')->label('Nome')->disabled(),
                    Forms\Components\TextInput::make('email')->label('Email')->disabled(),
                    Forms\Components\Textarea::make('articoli')->label('Articoli')->placeholder('Tutto l\'ordine')->disabled()->columnSpanFull(),
                ])->columns(2),
            Forms\Components\Section::make('Gestione')
                ->schema([
                    Forms\Components\DateTimePicker::make('gestita_il')
                        ->label('Gestita il')
                        ->helperText('Da compilare quando il rimborso è stato eseguito.'),
                    Forms\Components\Textarea::make('note_interne')->label('Note interne')->rows(4)->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('inviata_il', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('inviata_il')->label('Inviata il')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('numero_ordine')->label('Ordine')->searchable()
                    ->description(fn (RichiestaDiRecesso $r) => $r->order_id ? null : 'non trovato fra gli ordini'),
                Tables\Columns\TextColumn::make('nome')->label('Cliente')->searchable()
                    ->description(fn (RichiestaDiRecesso $r) => $r->email),
                Tables\Columns\TextColumn::make('scadenza')
                    ->label('Scadenza rimborso')
                    ->state(fn (RichiestaDiRecesso $r) => $r->inviata_il->copy()->addDays(14))
                    ->date('d/m/Y')
                    ->color(fn (RichiestaDiRecesso $r) => $r->gestita_il === null && $r->inviata_il->copy()->addDays(14)->isPast() ? 'danger' : null),
                Tables\Columns\IconColumn::make('gestita')
                    ->label('Gestita')
                    ->state(fn (RichiestaDiRecesso $r) => $r->gestita_il !== null)
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('gestita_il')
                    ->label('Stato')
                    ->nullable()
                    ->trueLabel('Gestite')
                    ->falseLabel('Da gestire')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('gestita_il'),
                        false: fn (Builder $q) => $q->whereNull('gestita_il'),
                    ),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Apri'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRichiesteDiRecesso::route('/'),
            'edit' => Pages\EditRichiestaDiRecesso::route('/{record}/edit'),
        ];
    }
}
