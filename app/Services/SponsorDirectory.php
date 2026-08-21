<?php

namespace App\Services;

use App\Enums\SponsorTier;
use App\Models\Sponsor;
use Illuminate\Support\Facades\Cache;

/**
 * Gli sponsor raggruppati per livello, nell'ordine in cui vanno pubblicati.
 *
 * Sta qui e non nei controller perché la stessa pagina è raggiungibile sia da
 * /sponsor sia dalle pagine di sezione: due copie della stessa logica si
 * sarebbero disallineate al primo livello aggiunto.
 */
class SponsorDirectory
{
    public const CACHE_KEY = 'public:sponsor:tiers';

    /**
     * @return list<array{key: string, label: string, size: string, sponsors: list<array<string, mixed>>}>
     */
    public function tiers(): array
    {
        $locale = app()->getLocale();

        return Cache::remember(self::CACHE_KEY.":{$locale}", now()->addMinutes(30), function () {
            $sponsors = Sponsor::with('media')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (Sponsor $s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'tier' => $s->tier->value,
                    'website_url' => $s->url,
                    'logo_url' => self::indirizzoDelLogo($s),
                    'sort_order' => $s->sort_order,
                ])
                ->groupBy('tier');

            return collect(SponsorTier::ordered())
                ->map(fn (SponsorTier $tier) => [
                    'key' => $tier->value,
                    'label' => $tier->getLabel(),
                    'size' => $tier->size(),
                    'sponsors' => $sponsors->get($tier->value, collect())->values()->toArray(),
                ])
                // I livelli senza sponsor non compaiono: una fila di riquadri
                // "nessuno sponsor" non dice niente a chi guarda.
                ->filter(fn (array $group) => $group['sponsors'] !== [])
                ->values()
                ->toArray();
        });
    }

    /**
     * Indirizzo del logo, con la versione ridotta solo se esiste davvero.
     *
     * `getFirstMediaUrl($collezione, 'card')` restituisce l'indirizzo della
     * conversione anche quando il file non e' ancora stato generato — le
     * conversioni sono in coda — quindi il `?:` non faceva mai da rete: appena
     * caricato un logo, e per tutto il tempo che la coda impiegava a smaltire,
     * nella pagina restava un riquadro vuoto.
     */
    private static function indirizzoDelLogo(Sponsor $sponsor): string
    {
        $logo = $sponsor->getFirstMedia('sponsors');

        if (! $logo) {
            return '';
        }

        return $logo->hasGeneratedConversion('card')
            ? $logo->getUrl('card')
            : $logo->getUrl();
    }
}
