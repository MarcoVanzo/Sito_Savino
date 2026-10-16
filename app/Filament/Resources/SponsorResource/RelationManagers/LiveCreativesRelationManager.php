<?php

namespace App\Filament\Resources\SponsorResource\RelationManagers;

use App\Enums\TipoCreativita;
use App\Enums\UsoCreativita;
use App\Models\LiveCreative;
use App\Support\Diretta\FormatiDelleCreativita;
use App\Support\Diretta\ImpostazioniDellaDiretta;
use Closure;
use Filament\Forms;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\Concerns\Translatable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Le grafiche dello sponsor per la diretta: spot prima della diretta
 * (pre-roll) e cornici accanto al player durante la gara. Stanno dentro lo
 * sponsor; quali vanno in onda in una partita si sceglie in Sponsor → Diretta.
 *
 * I formati si controllano al caricamento (FormatiDelleCreativita): un file
 * fuori misura verrebbe tagliato davanti allo sponsor. Il modello grafico
 * della cornice si scarica da qui.
 */
class LiveCreativesRelationManager extends RelationManager
{
    use Translatable;

    protected static string $relationship = 'liveCreatives';

    protected static ?string $title = 'Grafiche per la diretta';

    protected static ?string $modelLabel = 'grafica';

    protected static ?string $pluralModelLabel = 'grafiche';

    protected static ?string $recordTitleAttribute = 'nome';

    public const MODELLO_CORNICE = 'diretta/modello-cornice-1920x1080.png';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Creatività')
                    ->schema([
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
                            // Come il tipo: uno spot già agganciato al pre-roll
                            // di una gara, diventato cornice, restava nel
                            // pre-roll, e il file non si ricontrollava con le
                            // regole del nuovo uso.
                            ->disabledOn('edit')
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
                            // Si carica il file e poi si passa a «Video»: la durata va riletta.
                            ->afterStateUpdated(fn (Get $get, Set $set) => self::leggiLaDurataDelVideo($get('contenuto'), $get, $set))
                            ->disabledOn('edit'),
                    ])->columns(2),

                Forms\Components\Section::make('File')
                    ->schema([
                        Forms\Components\Placeholder::make('formato_richiesto')
                            ->hiddenLabel()
                            ->content(fn (Get $get): HtmlString => new HtmlString(view('filament.components.formato-richiesto', [
                                'voci' => FormatiDelleCreativita::requisiti(self::uso($get), self::tipo($get)),
                                'modello' => self::uso($get) === UsoCreativita::Cornice ? asset(self::MODELLO_CORNICE) : null,
                            ])->render()))
                            ->columnSpanFull(),
                        SpatieMediaLibraryFileUpload::make('contenuto')
                            ->label('File')
                            ->collection(LiveCreative::COLLEZIONE_CONTENUTO)
                            ->required()
                            // FilePond legge formati e limite una volta sola (`wire:ignore`),
                            // col tipo iniziale «Immagine»: passando a «Video» restava 2 MB e
                            // rifiutava i filmati (16/10/2026). Nel browser valgono i formati
                            // di entrambi e il limite piu' alto; il tipo scelto lo controlla
                            // `rules()` (FormatiDelleCreativita::erroriDelContenuto).
                            ->acceptedFileTypes([...FormatiDelleCreativita::MIME_IMMAGINE, ...FormatiDelleCreativita::MIME_VIDEO])
                            ->maxSize(FormatiDelleCreativita::PESO_MASSIMO_VIDEO_KB)
                            ->helperText(fn (Get $get): string => FormatiDelleCreativita::descrizione(self::uso($get), self::tipo($get)))
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $file, Closure $fail) use ($get): void {
                                    if (! $file instanceof TemporaryUploadedFile) {
                                        return;
                                    }

                                    foreach (FormatiDelleCreativita::erroriDelContenuto(self::tipo($get), $file->getRealPath(), $file->getMimeType(), self::uso($get)) as $errore) {
                                        $fail($errore);
                                    }
                                },
                            ])
                            ->afterStateUpdated(fn (mixed $state, Get $get, Set $set) => self::leggiLaDurataDelVideo($state, $get, $set))
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

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('anteprima')
                    ->label('Anteprima')
                    ->collection(LiveCreative::COLLEZIONE_CONTENUTO)
                    ->conversion('thumb')
                    // Un video non ha miniatura: senza filtro la colonna metteva
                    // l'MP4 in un `<img>` e l'anteprima usciva rotta.
                    ->filterMediaUsing(fn (Collection $media): Collection => $media->filter(
                        fn (Media $file): bool => str_starts_with((string) $file->mime_type, 'image/'),
                    ))
                    ->checkFileExistence(false),
                Tables\Columns\TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable(),
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
            ])
            ->headerActions([
                Tables\Actions\Action::make('modelloCornice')
                    ->label('Modello della cornice')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(asset(self::MODELLO_CORNICE))
                    ->extraAttributes(['download' => 'modello-cornice-1920x1080.png']),
                Tables\Actions\LocaleSwitcher::make(),
                Tables\Actions\CreateAction::make()
                    ->label('Nuova grafica'),
            ])
            ->actions([
                Tables\Actions\Action::make('anteprima')
                    ->label('Anteprima')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (LiveCreative $record): string => 'Anteprima · '.$record->nome)
                    ->modalDescription('Come la vede il tifoso sul sito.')
                    ->modalContent(fn (LiveCreative $record) => view('filament.components.anteprima-della-diretta', [
                        'grafica' => $record->perLAnteprima(),
                        'saltaDopo' => ImpostazioniDellaDiretta::perUnaGaraNuova()['preroll_salta_dopo_secondi'],
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Chiudi')
                    ->visible(fn (LiveCreative $record): bool => $record->getFirstMedia(LiveCreative::COLLEZIONE_CONTENUTO) !== null),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query->with('media'))
            ->defaultSort('created_at', 'desc');
    }

    /**
     * La durata di uno spot video viene dal file, non dall'operatore: col
     * valore predefinito il pre-roll si tagliava o restava fermo.
     */
    private static function leggiLaDurataDelVideo(mixed $file, Get $get, Set $set): void
    {
        if (self::tipo($get) !== TipoCreativita::Video) {
            return;
        }

        foreach (is_array($file) ? $file : [$file] as $caricato) {
            if ($caricato instanceof TemporaryUploadedFile) {
                $durata = FormatiDelleCreativita::durataDelVideo($caricato->getRealPath());

                if ($durata !== null) {
                    $set('durata_secondi', min($durata, FormatiDelleCreativita::DURATA_MASSIMA_SECONDI));
                }
            }
        }
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
