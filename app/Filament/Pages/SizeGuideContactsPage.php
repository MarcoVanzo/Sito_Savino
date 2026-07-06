<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Cache;

class SizeGuideContactsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationLabel = 'Guida Taglie & Contatti';
    protected static ?string $title = 'Guida Taglie & Contatti Shop';
    protected static ?string $navigationGroup = 'Shop Ufficiale';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'shop/guida-taglie-contatti';
    protected static string $view = 'filament.pages.size-guide-contacts';

    public ?array $data = [];

    public function mount(): void
    {
        $sizeGuides = SiteSetting::get('shop.size_guides');
        if (is_string($sizeGuides)) {
            $sizeGuides = json_decode($sizeGuides, true);
        }

        $this->form->fill([
            'size_guides' => $sizeGuides ?? [],
            'support_email' => SiteSetting::get('shop.support_email', ''),
            'support_phone' => SiteSetting::get('shop.support_phone', ''),
            'support_hours' => SiteSetting::get('shop.support_hours', ''),
            'support_notes' => SiteSetting::get('shop.support_notes', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Documenti Guida Taglie')
                    ->description('Carica i documenti PDF della guida taglie Errea. Saranno visibili nella pagina pubblica dello shop.')
                    ->icon('heroicon-o-document-arrow-up')
                    ->schema([
                        Forms\Components\FileUpload::make('size_guides')
                            ->label('File PDF Guida Taglie')
                            ->multiple()
                            ->acceptedFileTypes(['application/pdf'])
                            ->directory('size-guides')
                            ->disk('public')
                            ->maxSize(10240)
                            ->reorderable()
                            ->openable()
                            ->downloadable()
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Contatti Assistenza Shop')
                    ->description('Informazioni di contatto per il servizio assistenza clienti dello shop.')
                    ->icon('heroicon-o-phone')
                    ->schema([
                        Forms\Components\TextInput::make('support_email')
                            ->label('Email Assistenza')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('support_phone')
                            ->label('Telefono Assistenza')
                            ->tel()
                            ->maxLength(30),
                        Forms\Components\TextInput::make('support_hours')
                            ->label('Orari di Disponibilità')
                            ->maxLength(255)
                            ->placeholder('Es: Lun-Ven 9:00-18:00'),
                        Forms\Components\Textarea::make('support_notes')
                            ->label('Note Aggiuntive')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        SiteSetting::set('shop.size_guides', json_encode($data['size_guides'] ?? []));
        SiteSetting::set('shop.support_email', $data['support_email'] ?? '');
        SiteSetting::set('shop.support_phone', $data['support_phone'] ?? '');
        SiteSetting::set('shop.support_hours', $data['support_hours'] ?? '');
        SiteSetting::set('shop.support_notes', $data['support_notes'] ?? '');

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();

        // Invalida cache shop per forzare il refresh delle pagine pubbliche
        Cache::forget('public:shop:it');
        Cache::forget('public:shop:en');
    }

    protected function getFormActions(): array
    {
        return [
            \Filament\Actions\Action::make('save')
                ->label('Salva Impostazioni')
                ->submit('save'),
        ];
    }
}
