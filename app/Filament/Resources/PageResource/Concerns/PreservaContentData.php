<?php

namespace App\Filament\Resources\PageResource\Concerns;

use App\Support\ContentData;
use Illuminate\Database\Eloquent\Model;

/**
 * Salva `content_data` senza cancellare quello che il modulo non ha in mano.
 *
 * I campi delle pagine si chiamano `content_data.hero_badge`,
 * `content_data.projects` e così via: un nome puntato che Filament ricompone in
 * un array unico. Il modulo però mostra solo i campi del modello della pagina:
 * salvando, le chiavi degli altri modelli — che nel modulo non compaiono
 * nemmeno — non devono sparire.
 *
 * Qui `content_data` si ricostruisce da tre pezzi, in quest'ordine:
 *
 *  1. quello che c'è già in archivio, così le chiavi degli altri modelli di
 *     pagina restano dove sono;
 *  2. i valori dei campi mostrati, presi dal modulo *deidratato*: un Repeater
 *     dà un elenco, un FileUpload dà il percorso. Una versione precedente li
 *     prendeva dallo stato grezzo di Livewire, che tiene le voci in una mappa
 *     `{uuid: voce}`: in archivio finivano oggetti al posto degli elenchi e i
 *     template, che chiedono un elenco, nascondevano la sezione intera
 *     (piani abbonamento, progetti sociali, cartelle stampa, documenti);
 *  3. niente altro: un campo che l'utente ha svuotato resta svuotato, perché
 *     il suo stato è vuoto e sovrascrive il valore in archivio.
 *
 * Per la lingua che non è quella attiva il plugin translatable passa lo stato
 * grezzo, quindi il modulo si deidrata di nuovo qui; il passaggio finale da
 * `ContentData::normalizza()` è la rete di sicurezza se un giorno cambiasse.
 */
trait PreservaContentData
{
    /**
     * Vero mentre il plugin delle traduzioni salva una lingua diversa da
     * quella attiva: i suoi file caricati non sono ancora passati dai ganci
     * di deidratazione, quindi non sono ancora su disco.
     */
    protected bool $salvandoUnAltraLingua = false;

    /**
     * La lingua che si sta salvando quando non è quella attiva: senza, la
     * base "in archivio" sarebbe quella della lingua attiva e le chiavi degli
     * altri modelli di pagina passerebbero da una lingua all'altra.
     */
    protected ?string $linguaInSalvataggio = null;

    /**
     * Le chiavi di primo livello che il modulo ha mostrato nell'ultimo
     * salvataggio: chi allinea le lingue deve sapere che cosa è stato davvero
     * toccato, e non riscrivere le chiavi degli altri modelli di pagina.
     *
     * @var list<string>
     */
    protected array $chiaviMostrate = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function contentDataPreservato(array $data): array
    {
        if (! is_array($this->data['content_data'] ?? null)) {
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

        $this->chiaviMostrate = array_keys($mostrati);

        // Per la lingua attiva i ganci (salvataggio dei file caricati,
        // relazioni) sono già stati eseguiti dalla prima deidratazione: qui
        // serve solo la forma finale. Per le altre lingue nessuno li ha
        // chiamati e un PDF caricato lì resterebbe nella cartella temporanea:
        // si chiamano solo quelli che portano i file su disco, non quelli
        // delle relazioni, che riguardano campi comuni a tutte le lingue.
        if ($this->salvandoUnAltraLingua) {
            $this->form->callBeforeStateDehydrated();
        }

        $deidratati = $this->form->getState(shouldCallHooksBefore: false)['content_data'] ?? [];
        $aggiornati = $this->contentDataInArchivio();

        foreach (array_keys($mostrati) as $chiave) {
            $aggiornati[$chiave] = ContentData::normalizza($deidratati[$chiave] ?? null);
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
        $record = $this->getRecord();

        // In creazione non c'è ancora niente in archivio.
        if (! $record instanceof Model || ! $record->exists) {
            return [];
        }

        $lingua = $this->linguaInSalvataggio ?? $this->activeLocale ?? app()->getLocale();
        $valore = $record->getTranslation('content_data', $lingua, false);

        return is_array($valore) ? $valore : [];
    }
}
