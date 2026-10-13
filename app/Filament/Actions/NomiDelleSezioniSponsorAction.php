<?php

namespace App\Filament\Actions;

use App\Enums\SponsorTier;
use App\Models\SiteSetting;
use App\Models\Sponsor;
use App\Observers\CacheInvalidationObserver;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

/**
 * «Nomi delle sezioni» sull'elenco degli sponsor: la redazione rinomina i
 * livelli della pagina Sponsor (Health Partner → Health & Performance
 * Partner) in italiano e in inglese, senza passare dal codice.
 *
 * Un campo vuoto torna al nome predefinito (`SponsorTier::nomePredefinito()`).
 * Ordine dei livelli e misure dei loghi restano nel codice.
 */
class NomiDelleSezioniSponsorAction
{
    public static function make(string $name = 'nomiDelleSezioni'): Action
    {
        return Action::make($name)
            ->label('Nomi delle sezioni')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->modalHeading('Nomi delle sezioni della pagina Sponsor')
            ->modalDescription('Lasciando vuoto un campo resta il nome predefinito, scritto in grigio.')
            ->modalSubmitActionLabel('Salva')
            ->authorize(fn (): bool => (bool) auth()->user()?->can('create', Sponsor::class))
            ->fillForm(fn (): array => ['nomi' => self::nomiSalvati()])
            ->form(fn (): array => self::campi())
            ->action(function (array $data): void {
                self::salva($data['nomi'] ?? []);

                Notification::make()->title('Nomi delle sezioni salvati')->success()->send();
            });
    }

    /**
     * @return list<Forms\Components\Component>
     */
    private static function campi(): array
    {
        $locali = config('app.supported_locales', ['it', 'en']);

        return array_map(
            fn (SponsorTier $tier): Forms\Components\Component => Forms\Components\Grid::make(count($locali))
                ->schema(array_map(
                    fn (string $locale): Forms\Components\TextInput => Forms\Components\TextInput::make("nomi.{$locale}.{$tier->value}")
                        ->label($tier->nomePredefinito().' ('.strtoupper($locale).')')
                        ->placeholder($tier->nomePredefinito())
                        ->maxLength(80),
                    $locali,
                )),
            SponsorTier::rinominabili(),
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function nomiSalvati(): array
    {
        return array_map(
            fn (mixed $nomi): array => is_array($nomi) ? $nomi : [],
            SiteSetting::perLocale(SponsorTier::CHIAVE_DEI_NOMI),
        );
    }

    /**
     * Si tengono solo i nomi scritti: un livello assente usa il predefinito.
     *
     * @param  array<string, array<string, ?string>>  $nomi
     */
    public static function salva(array $nomi): void
    {
        $valori = [];

        foreach (config('app.supported_locales', ['it', 'en']) as $locale) {
            $valori[$locale] = array_filter(
                array_map(fn (?string $nome): string => trim((string) $nome), $nomi[$locale] ?? []),
                fn (string $nome): bool => $nome !== '',
            );
        }

        SiteSetting::set(SponsorTier::CHIAVE_DEI_NOMI, $valori, 'sponsor');

        // Gli stessi elenchi che si svuotano salvando uno sponsor.
        app(CacheInvalidationObserver::class)->svuotaLeChiavi(['public:sponsor', 'public:sponsor:tiers']);
    }
}
