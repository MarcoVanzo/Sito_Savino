<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * I PDF di Corporate Governance caricati in Impostazioni → Documenti Legali.
 *
 * Li pubblicano due posti: la colonna del footer (voci di menu
 * `documento:<chiave>`) e la pagina Safeguarding. Entrambi chiedono il
 * documento per chiave e leggono il file al momento: sostituire il PDF dal
 * pannello aggiorna tutti e due. Prima Safeguarding aveva una copia propria
 * del file, e aggiornandone uno l'altro restava indietro senza avvisi.
 */
class DocumentiLegali
{
    /** @var array<string, string> chiave => etichetta nel pannello */
    public const ETICHETTE = [
        'modello_organizzativo' => 'Modello Organizzativo',
        'codice_tutela_minori' => 'Codice Tutela Minori',
        'protocollo_bullismo' => 'Protocollo Bullismo',
        'protocollo_razzismo' => 'Protocollo Razzismo',
    ];

    /**
     * Indirizzo pubblico del documento, o null se non è stato caricato.
     */
    public static function indirizzo(string $chiave): ?string
    {
        // In archivio c'è il percorso sul disco, non l'indirizzo: in
        // produzione i file stanno su Spaces.
        return CmsFile::url(self::percorso($chiave));
    }

    /**
     * Percorso sul disco del documento, come lo salva il pannello.
     */
    public static function percorso(string $chiave): ?string
    {
        $percorso = (SiteSetting::getAllGrouped()['legal'] ?? [])[$chiave] ?? null;

        return is_string($percorso) && trim($percorso) !== '' ? $percorso : null;
    }

    /**
     * I documenti di una pagina che rimandano a un documento legale prendono
     * il file da lì. Va chiamato prima di `CmsFile::resolveInContentData()`,
     * che trasforma il percorso in indirizzo.
     *
     * @param  array<string, mixed>  $contentData
     * @return array<string, mixed>
     */
    public static function risolviNeiDocumenti(array $contentData): array
    {
        if (! is_array($contentData['documents'] ?? null)) {
            return $contentData;
        }

        $contentData['documents'] = array_map(function ($voce) {
            $chiave = is_array($voce) ? ($voce['documento_legale'] ?? null) : null;

            if (! is_string($chiave) || ! array_key_exists($chiave, self::ETICHETTE)) {
                return $voce;
            }

            $voce['file'] = self::percorso($chiave);

            return $voce;
        }, $contentData['documents']);

        return $contentData;
    }
}
