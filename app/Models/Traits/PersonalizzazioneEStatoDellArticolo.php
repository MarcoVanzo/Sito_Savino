<?php

namespace App\Models\Traits;

/**
 * Le due aggiunte della scheda prodotto che la riga d'ordine fotografa: la
 * personalizzazione (la firma della giocatrice) e lo stato di un articolo
 * indossato o autografato.
 */
trait PersonalizzazioneEStatoDellArticolo
{
    /**
     * Il prodotto offre la personalizzazione (di solito la firma della
     * giocatrice)? Si accende dando un nome all'aggiunta nel pannello.
     */
    public function offrePersonalizzazione(): bool
    {
        return trim((string) $this->getTranslation('personalizzazione_nome', config('app.fallback_locale'), false)) !== '';
    }

    /**
     * Lo stato dichiarato di un articolo indossato o autografato, per lingua
     * ({"it": "Indossata in gara il …", …}); null se il prodotto non e' di
     * quel tipo o lo stato manca. E' cio' che la riga d'ordine fotografa: le
     * condizioni lo vendono «nello stato descritto nella scheda».
     *
     * @return array<string, string>|null
     */
    public function statoArticoloDaFotografare(): ?array
    {
        if (! $this->usato_o_autografato) {
            return null;
        }

        $stati = array_filter(
            $this->getTranslations('stato_articolo'),
            fn ($testo): bool => is_string($testo) && trim($testo) !== '',
        );

        return $stati === [] ? null : $stati;
    }

    /**
     * Lo stato da mostrare nella scheda (shop e asta), nella lingua corrente
     * con ripiego sull'italiano; null se il prodotto non e' di quel tipo.
     */
    public function statoArticoloPerLaScheda(): ?string
    {
        if (! $this->usato_o_autografato) {
            return null;
        }

        $testo = trim((string) $this->getTranslation('stato_articolo', app()->getLocale()));

        return $testo === '' ? null : $testo;
    }
}
