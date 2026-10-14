<?php

namespace App\Filament\Actions;

use App\Enums\SponsorTier;
use App\Models\SiteSetting;
use App\Models\Sponsor;
use App\Observers\CacheInvalidationObserver;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

/**
 * «Nomi e ordine delle sezioni» sull'elenco degli sponsor: la redazione
 * rinomina i livelli della pagina Sponsor (Health Partner → Health &
 * Performance Partner) in italiano e in inglese e li mette in ordine
 * trascinandoli, senza passare dal codice.
 *
 * Un campo vuoto torna al nome predefinito (`SponsorTier::nomePredefinito()`).
 * Le misure dei loghi restano nel codice.
 */
class NomiDelleSezioniSponsorAction
{
    public static function make(string $name = 'nomiDelleSezioni'): Action
    {
        return Action::make($name)
            ->label('Nomi e ordine delle sezioni')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->modalHeading('Nomi e ordine delle sezioni della pagina Sponsor')
            ->modalDescription('Trascina le sezioni per cambiarne l\'ordine in pagina; aprile per rinominarle. Lasciando vuoto un nome resta quello predefinito, scritto in grigio.')
            ->modalSubmitActionLabel('Salva')
            ->authorize(fn (): bool => (bool) auth()->user()?->can('create', Sponsor::class))
            ->fillForm(fn (): array => ['sezioni' => self::sezioniSalvate()])
            ->form(fn (): array => [self::elenco()])
            ->action(function (array $data): void {
                self::salva(array_values($data['sezioni'] ?? []));

                Notification::make()->title('Nomi e ordine delle sezioni salvati')->success()->send();
            });
    }

    /**
     * Una voce per livello, nell'ordine di pubblicazione: si trascinano, non
     * si aggiungono né si tolgono.
     */
    private static function elenco(): Forms\Components\Repeater
    {
        $locali = config('app.supported_locales', ['it', 'en']);

        return Forms\Components\Repeater::make('sezioni')
            ->hiddenLabel()
            ->schema([
                Forms\Components\Hidden::make('tier'),
                Forms\Components\Grid::make(count($locali))
                    ->schema(array_map(
                        fn (string $locale): Forms\Components\TextInput => Forms\Components\TextInput::make($locale)
                            ->label('Nome ('.strtoupper($locale).')')
                            ->placeholder(fn (Forms\Get $get): string => SponsorTier::tryFrom((string) $get('tier'))?->nomePredefinito($locale) ?? '')
                            ->maxLength(80),
                        $locali,
                    )),
            ])
            ->itemLabel(fn (array $state): string => SponsorTier::tryFrom((string) ($state['tier'] ?? ''))?->getLabel() ?? '')
            ->addable(false)
            ->deletable(false)
            ->reorderable()
            ->reorderableWithButtons()
            ->collapsible()
            ->collapsed();
    }

    /**
     * @return list<array<string, string>>
     */
    private static function sezioniSalvate(): array
    {
        $nomi = SiteSetting::perLocale(SponsorTier::CHIAVE_DEI_NOMI);

        return array_map(function (SponsorTier $tier) use ($nomi): array {
            $voce = ['tier' => $tier->value];

            foreach ($nomi as $locale => $nomiDellaLingua) {
                $voce[$locale] = is_array($nomiDellaLingua) ? (string) ($nomiDellaLingua[$tier->value] ?? '') : '';
            }

            return $voce;
        }, SponsorTier::rinominabili());
    }

    /**
     * L'ordine è quello delle voci; dei nomi si tengono solo quelli scritti:
     * un livello senza nome usa il predefinito.
     *
     * @param  list<array<string, ?string>>  $sezioni
     */
    public static function salva(array $sezioni): void
    {
        $ordine = [];
        $nomi = [];

        foreach ($sezioni as $voce) {
            $tier = SponsorTier::tryFrom((string) ($voce['tier'] ?? ''));

            if ($tier === null || in_array($tier->value, $ordine, true)) {
                continue;
            }

            $ordine[] = $tier->value;

            foreach (config('app.supported_locales', ['it', 'en']) as $locale) {
                $nome = trim((string) ($voce[$locale] ?? ''));

                if ($nome !== '') {
                    $nomi[$locale][$tier->value] = $nome;
                }
            }
        }

        $valori = [];

        foreach (config('app.supported_locales', ['it', 'en']) as $locale) {
            $valori[$locale] = $nomi[$locale] ?? [];
        }

        // Insieme o niente: nomi nuovi con l'ordine vecchio non sono ciò che
        // la redazione ha visto nel modulo.
        DB::transaction(function () use ($valori, $ordine): void {
            SiteSetting::set(SponsorTier::CHIAVE_DEI_NOMI, $valori, 'sponsor');
            SiteSetting::set(SponsorTier::CHIAVE_DELL_ORDINE, $ordine, 'sponsor');
        });

        // Gli stessi elenchi che si svuotano salvando uno sponsor.
        app(CacheInvalidationObserver::class)->svuotaLeChiavi(['public:sponsor', 'public:sponsor:tiers']);
    }
}
