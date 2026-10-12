<?php

namespace App\Filament\Resources;

use App\Enums\TipoCreativita;
use App\Enums\UsoCreativita;
use App\Filament\Resources\LiveCreativeResource\Pages;
use App\Models\LiveCreative;
use App\Support\Diretta\FormatiDelleCreativita;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * Le creatività sponsor della diretta: video e immagini da mandare in onda
 * prima della diretta (pre-roll) o accanto al player durante la gara.
 *
 * I formati si controllano al caricamento (FormatiDelleCreativita): un file
 * fuori misura verrebbe tagliato davanti allo sponsor. Il modello grafico
 * della cornice (`public/diretta/modello-cornice-1920x1080.png`) si scarica
 * dall'elenco.
 */
class LiveCreativeResource extends Resource
{
    use Translatable;

    protected static ?string $model = LiveCreative::class;

    protected static ?string $recordTitleAttribute = 'nome';

    protected static ?string $modelLabel = 'Creatività della diretta';

    protected static ?string $pluralModelLabel = 'Creatività della diretta';

    protected static ?string $navigationIcon = 'heroicon-o-play-circle';

    protected static ?string $navigationLabel = 'Spot nella diretta';

    protected static ?string $navigationGroup = 'Sponsor';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'creativita-diretta';

    public const MODELLO_CORNICE = 'diretta/modello-cornice-1920x1080.png';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Creatività')
                    ->schema([
                        Forms\Components\Select::make('sponsor_id')
                            ->label('Sponsor')
                            ->relationship('sponsor', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('nome')
                            ->label('Nome')
                            ->helperText('Uso interno, per riconoscerla nella regia e nel report.')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('uso')
                            ->label('Dove va in onda')
                            ->options(UsoCreativita::class)
                            ->default(UsoCreativita::Preroll->value)
                            ->required()
                            ->live(),
                        // Il tipo decide quale file si accetta: cambiarlo con
                        // il file già caricato lascerebbe un video segnato
                        // come immagine. Per cambiare tipo si crea un'altra
                        // creatività.
                        Forms\Components\Select::make('tipo')
                            ->label('Tipo')
                            ->options(TipoCreativita::class)
                            ->default(TipoCreativita::Immagine->value)
                            ->required()
                            ->live()
                            ->disabledOn('edit'),
                    ])->columns(2),

                Forms\Components\Section::make('File')
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('contenuto')
                            ->label('File')
                            ->collection(LiveCreative::COLLEZIONE_CONTENUTO)
                            ->required()
                            ->acceptedFileTypes(fn (Get $get): array => FormatiDelleCreativita::mimeAmmessi(self::tipo($get)))
                            ->maxSize(fn (Get $get): int => FormatiDelleCreativita::pesoMassimoKb(self::tipo($get)))
                            ->helperText(fn (Get $get): string => FormatiDelleCreativita::descrizione(self::uso($get), self::tipo($get)))
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $file, Closure $fail) use ($get): void {
                                    if (! $file instanceof TemporaryUploadedFile) {
                                        return;
                                    }

                                    foreach (FormatiDelleCreativita::erroriDelContenuto(self::tipo($get), $file->getRealPath(), $file->getMimeType()) as $errore) {
                                        $fail($errore);
                                    }
                                },
                            ])
                            ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                                if (self::tipo($get) !== TipoCreativita::Video) {
                                    return;
                                }

                                foreach (is_array($state) ? $state : [$state] as $file) {
                                    if ($file instanceof TemporaryUploadedFile) {
                                        $durata = FormatiDelleCreativita::durataDelVideo($file->getRealPath());

                                        if ($durata !== null) {
                                            $set('durata_secondi', min($durata, FormatiDelleCreativita::DURATA_MASSIMA_SECONDI));
                                        }
                                    }
                                }
                            })
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('fascia_mobile')
                            ->label('Fascia per il telefono (facoltativa)')
                            ->collection(LiveCreative::COLLEZIONE_FASCIA_MOBILE)
                            ->acceptedFileTypes(FormatiDelleCreativita::MIME_IMMAGINE)
                            ->maxSize(FormatiDelleCreativita::PESO_MASSIMO_FASCIA_MOBILE_KB)
                            ->helperText('JPG o PNG, 1080×300 px, al massimo 1 MB. Se manca, sul telefono si mostra la fascia in basso della cornice.')
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $file, Closure $fail): void {
                                    if (! $file instanceof TemporaryUploadedFile) {
                                        return;
                                    }

                                    foreach (FormatiDelleCreativita::erroriDellaFasciaMobile($file->getRealPath()) as $errore) {
                                        $fail($errore);
                                    }
                                },
                            ])
                            ->visible(fn (Get $get): bool => self::uso($get) === UsoCreativita::Cornice)
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('durata_secondi')
                            ->label('Durata (secondi)')
                            ->helperText(fn (Get $get): string => self::tipo($get) === TipoCreativita::Video
                                ? 'Letta dal video al caricamento; al massimo 30 secondi.'
                                : 'Quanto resta in onda l\'immagine; al massimo 30 secondi.')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(FormatiDelleCreativita::DURATA_MASSIMA_SECONDI)
                            ->default(FormatiDelleCreativita::DURATA_IMMAGINE_SECONDI)
                            ->required(),
                        Forms\Components\TextInput::make('testo_alternativo')
                            ->label('Testo alternativo')
                            ->helperText('Cosa mostra la creatività, per chi non vede le immagini (es. «Estra: energia per lo sport»). Obbligatorio per le immagini.')
                            ->required(fn (Get $get): bool => self::tipo($get) === TipoCreativita::Immagine)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('link_url')
                            ->label('Link dello sponsor')
                            ->helperText('Si apre in una nuova scheda, senza fermare la diretta.')
                            ->url()
                            ->rule('starts_with:http://,https://')
                            ->maxLength(2048),
                    ])->columns(2),

                Forms\Components\Section::make('Validità')
                    ->schema([
                        Forms\Components\Toggle::make('attiva')
                            ->label('Attiva')
                            ->default(true),
                        Forms\Components\DatePicker::make('valida_dal')
                            ->label('Dal'),
                        Forms\Components\DatePicker::make('valida_al')
                            ->label('Al')
                            ->afterOrEqual('valida_dal'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('anteprima')
                    ->label('Anteprima')
                    ->collection(LiveCreative::COLLEZIONE_CONTENUTO)
                    ->conversion('thumb')
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable(),
                Tables\Columns\TextColumn::make('sponsor.name')
                    ->label('Sponsor')
                    ->sortable(),
                Tables\Columns\TextColumn::make('uso')
                    ->label('Dove')
                    ->badge(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->badge(),
                Tables\Columns\TextColumn::make('durata_secondi')
                    ->label('Durata')
                    ->suffix(' s'),
                Tables\Columns\TextColumn::make('validita')
                    ->label('Validità')
                    ->getStateUsing(fn (LiveCreative $record): string => self::validita($record)),
                Tables\Columns\ToggleColumn::make('attiva')
                    ->label('Attiva'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('uso')
                    ->label('Dove')
                    ->options(UsoCreativita::class),
                Tables\Filters\SelectFilter::make('sponsor_id')
                    ->label('Sponsor')
                    ->relationship('sponsor', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLiveCreatives::route('/'),
            'create' => Pages\CreateLiveCreative::route('/create'),
            'edit' => Pages\EditLiveCreative::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['media', 'sponsor']);
    }

    private static function tipo(Get $get): TipoCreativita
    {
        $tipo = $get('tipo');

        return $tipo instanceof TipoCreativita ? $tipo : (TipoCreativita::tryFrom((string) $tipo) ?? TipoCreativita::Immagine);
    }

    private static function uso(Get $get): ?UsoCreativita
    {
        $uso = $get('uso');

        return $uso instanceof UsoCreativita ? $uso : UsoCreativita::tryFrom((string) $uso);
    }

    private static function validita(LiveCreative $record): string
    {
        $dal = $record->valida_dal?->format('d/m/Y');
        $al = $record->valida_al?->format('d/m/Y');

        return match (true) {
            $dal !== null && $al !== null => "dal {$dal} al {$al}",
            $dal !== null => "dal {$dal}",
            $al !== null => "fino al {$al}",
            default => 'sempre',
        };
    }
}
