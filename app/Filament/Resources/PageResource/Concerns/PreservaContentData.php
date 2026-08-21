<?php

namespace App\Filament\Resources\PageResource\Concerns;

/**
 * Salva `content_data` senza cancellare quello che il modulo non ha in mano.
 *
 * I campi delle pagine si chiamano `content_data.hero_badge`,
 * `content_data.projects` e così via: un nome puntato che Filament ricompone in
 * un array unico. Sui campi semplici funziona, ma un Repeater con lo stesso
 * schema di nome riscrive `content_data` per intero invece di aggiungerci la
 * propria chiave, e si porta via tutti i fratelli.
 *
 * L'effetto era che bastava aprire una pagina e premere Salva — senza toccare
 * niente — per svuotarla: testi, elenchi dei progetti, statistiche d'impatto,
 * materiale stampa. In redazione si vedeva come "modifico una cosa e sparisce
 * tutto".
 *
 * Qui `content_data` si ricostruisce da tre pezzi, in quest'ordine:
 *
 *  1. quello che c'è già in archivio, così le chiavi degli altri modelli di
 *     pagina — che il modulo non mostra nemmeno — restano dove sono;
 *  2. i valori veri dei campi mostrati, presi dallo stato del componente
 *     Livewire, che è l'unico posto in cui sono rimasti integri;
 *  3. niente altro: un campo che l'utente ha svuotato resta svuotato, perché
 *     il suo stato è vuoto e sovrascrive il valore in archivio.
 *
 * Così un salvataggio può aggiungere e correggere, ma non può cancellare per
 * conto suo quello che nessuno ha toccato.
 */
trait PreservaContentData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentDataPreservato(array $data): array
    {
        $daiCampi = $this->data['content_data'] ?? null;

        // Senza lo stato del componente non c'è niente da recuperare: meglio
        // lasciare il salvataggio com'è che indovinare.
        if (! is_array($daiCampi)) {
            return $data;
        }

        $mostrati = [];

        foreach (array_keys($this->form->getFlatFields(withHidden: false)) as $nome) {
            if (! str_starts_with($nome, 'content_data.')) {
                continue;
            }

            // Solo il primo livello: `content_data.projects.0.title` appartiene
            // comunque alla chiave `projects`, che si scrive tutta insieme.
            $chiave = explode('.', substr($nome, strlen('content_data.')))[0];
            $mostrati[$chiave] = true;
        }

        $inArchivio = $this->contentDataInArchivio();
        $aggiornati = $inArchivio;

        foreach (array_keys($mostrati) as $chiave) {
            $aggiornati[$chiave] = $daiCampi[$chiave] ?? null;
        }

        $data['content_data'] = $aggiornati;

        return $data;
    }

    /**
     * `content_data` così com'è oggi in archivio, per la lingua che si sta
     * modificando.
     *
     * @return array<string, mixed>
     */
    private function contentDataInArchivio(): array
    {
        $lingua = $this->activeLocale ?? app()->getLocale();
        $valore = $this->getRecord()->getTranslation('content_data', $lingua, false);

        return is_array($valore) ? $valore : [];
    }
}
