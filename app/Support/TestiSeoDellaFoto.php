<?php

namespace App\Support;

use App\Models\GalleryImage;

/**
 * Titolo, alt, descrizione e parole chiave che l'analisi dei volti scrive su
 * una foto della gallery, a partire dalle persone taggate e dall'album.
 *
 * Stava dentro AnalyzeGalleryImageJob::optimizeForSeo. E' uscito da li'
 * perche' serve anche alla revoca del riconoscimento dei volti
 * (RevocaDelRiconoscimentoDeiVolti): tolti i tag automatici di una persona,
 * i testi delle sue foto vanno riscritti con le persone rimaste, con le stesse
 * regole con cui erano stati generati, o il nome resterebbe nell'alt e nella
 * descrizione pubblicati.
 */
class TestiSeoDellaFoto
{
    /** Separatore fra nomi, album e data nel titolo generato. */
    public const SEPARATORE = ' - ';

    /** Separatore fra i nomi delle persone nel titolo generato. */
    public const SEPARATORE_NOMI = ', ';

    /**
     * I testi per la foto, con le persone taggate in questo momento.
     *
     * @return array{titolo: string, alt: string, descrizione: string, parole_chiave: string}
     */
    public static function componi(GalleryImage $foto): array
    {
        $foto->loadMissing(['players', 'staffMembers', 'galleryEvent']);
        $persone = $foto->players->concat($foto->staffMembers);
        $album = $foto->galleryEvent;

        $nomi = $persone->map->full_name->toArray();
        $cognomi = $persone->map->last_name->toArray();
        $elencoNomi = implode(self::SEPARATORE_NOMI, $nomi);

        $titoloAlbum = $album->title ?? '';
        $categoria = $foto->category ?? $album->category ?? 'Partite';
        $data = $album?->event_date?->format('d/m/Y') ?? '';

        // Formato: "Bosetti, Gaspari - Partita vs Busto Arsizio - 01/07/2026"
        $titolo = implode(self::SEPARATORE, array_filter([$elencoNomi, $titoloAlbum, $data], fn ($parte) => $parte !== ''));

        $alt = implode(self::SEPARATORE, array_filter(['Savino Del Bene Volley', $elencoNomi, $titoloAlbum], fn ($parte) => $parte !== ''));

        $descrizione = 'Foto ';
        if ($nomi !== []) {
            $descrizione .= 'di '.$elencoNomi.' ';
        }
        $descrizione .= 'della Savino Del Bene Volley';
        if ($titoloAlbum !== '') {
            $descrizione .= ' durante '.$titoloAlbum;
        }
        if ($data !== '') {
            $descrizione .= ' ('.$data.')';
        }

        $paroleChiave = implode(', ', array_merge(['Savino Del Bene', 'Volley', 'Serie A'], $cognomi, [$categoria]));

        return [
            'titolo' => $titolo,
            'alt' => $alt,
            'descrizione' => $descrizione,
            'parole_chiave' => $paroleChiave,
        ];
    }

    /**
     * Scrive i testi sulla foto e sulla sua immagine.
     *
     * Il titolo si sostituisce solo se quello nuovo non e' vuoto, come ha
     * sempre fatto l'analisi. La media si salva qui; la foto la salva chi
     * chiama, una volta sola insieme agli altri campi che ha cambiato.
     */
    public static function applica(GalleryImage $foto): void
    {
        $testi = self::componi($foto);

        if ($testi['titolo'] !== '') {
            $foto->title = $testi['titolo'];
        }

        self::scriviSullaMedia($foto, $testi);
    }

    /**
     * Alt, descrizione e parole chiave sull'immagine della foto.
     *
     * @param  array{titolo: string, alt: string, descrizione: string, parole_chiave: string}  $testi
     */
    public static function scriviSullaMedia(GalleryImage $foto, array $testi): void
    {
        $media = $foto->getFirstMedia('gallery');

        if (! $media) {
            return;
        }

        $media->setCustomProperty('alt', $testi['alt']);
        $media->setCustomProperty('description', $testi['descrizione']);
        $media->setCustomProperty('keywords', $testi['parole_chiave']);
        $media->saveQuietly();
    }

    /**
     * Il titolo e' quello generato con questa persona fra i nomi?
     *
     * Il titolo generato comincia con l'elenco dei nomi, prima del primo
     * separatore. Un titolo riscritto a mano dalla redazione che nomina la
     * persona altrove non e' nostro: non si tocca, si segnala.
     */
    public static function eGeneratoConLaPersona(?string $titolo, string $nome): bool
    {
        if ($titolo === null || $titolo === '' || trim($nome) === '') {
            return false;
        }

        $primaParte = explode(self::SEPARATORE, $titolo, 2)[0];

        return in_array(trim($nome), array_map('trim', explode(self::SEPARATORE_NOMI, $primaParte)), true);
    }
}
