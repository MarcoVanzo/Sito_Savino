<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\GalleryImage;
use App\Models\Player;
use App\Models\StaffMember;
use App\Observers\CacheInvalidationObserver;
use App\Support\TestiSeoDellaFoto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * La revoca del consenso al riconoscimento dei volti (art. 9 §2 a GDPR,
 * decisione di Marco del 2/10/2026), dal pannello o alla cancellazione della
 * scheda.
 *
 * Il consenso e' raccolto fuori dal sito; qui si toglie tutto cio' che il
 * riconoscimento ha prodotto:
 *
 * - il soggetto e le impronte su CompreFace;
 * - i tag automatici della gallery (`gallery_image_person` con
 *   `confidence_score` non nullo). Quelli manuali hanno confidence nullo: li
 *   ha messi la redazione, non la macchina, e restano;
 * - il nome nei testi che l'analisi aveva scritto sulle foto (alt,
 *   descrizione, parole chiave, e il titolo se e' ancora quello generato),
 *   riscritti con le persone rimaste;
 * - il contatore `players.ai_face_examples`.
 *
 * Resta una riga nel registro attivita'. Se CompreFace non risponde la
 * pulizia del database si fa lo stesso (e' la parte che si vede sul sito) e
 * l'esito lo dice: la revoca va ripetuta finche' CompreFace non conferma.
 */
class RevocaDelRiconoscimentoDeiVolti
{
    /** Azione nel registro attivita' (ActivityLog::getActionLabelAttribute). */
    public const AZIONE = 'revoca_volti';

    public const COMPREFACE_CANCELLATO = 'cancellato';

    public const COMPREFACE_NON_RAGGIUNGIBILE = 'non_raggiungibile';

    public const COMPREFACE_IN_ERRORE = 'errore';

    public function __construct(private FacialRecognitionService $volti) {}

    /**
     * @param  bool  $ancheITagManuali  alla cancellazione definitiva della scheda: i tag
     *                                  manuali resterebbero orfani di una persona che non esiste piu'
     * @return array{compreface: string, tag_tolti: int, foto_ripulite: int, titoli_da_rivedere: list<int>}
     */
    public function revoca(Player|StaffMember $persona, bool $ancheITagManuali = false, string $motivo = 'revoca dal pannello'): array
    {
        $compreface = $this->cancellaSuCompreFace($persona);

        $righe = DB::table('gallery_image_person')
            ->where('person_type', $persona::class)
            ->where('person_id', $persona->getKey())
            ->when(! $ancheITagManuali, fn ($query) => $query->whereNotNull('confidence_score'));

        $idFoto = (clone $righe)->pluck('gallery_image_id')->unique()->values()->all();
        $tagTolti = $righe->delete();

        $titoliDaRivedere = [];
        $ultimaFoto = null;

        GalleryImage::query()->whereIn('id', $idFoto)->with('media')->chunkById(100, function ($foto) use ($persona, &$titoliDaRivedere, &$ultimaFoto): void {
            foreach ($foto as $immagine) {
                if (! $this->ripulisciITesti($immagine, $persona->full_name)) {
                    $titoliDaRivedere[] = $immagine->id;
                }
                $ultimaFoto = $immagine;
            }
        });

        if ($persona instanceof Player && $persona->exists && ! $this->staPerSparire($persona)) {
            $persona->forceFill(['ai_face_examples' => 0])->saveQuietly();
        }

        // I salvataggi sopra sono silenziosi (centinaia di foto, una riga di
        // registro per ognuna): la cache della gallery si rigenera una volta.
        if ($ultimaFoto !== null) {
            app(CacheInvalidationObserver::class)->saved($ultimaFoto);
        }

        $esito = [
            'compreface' => $compreface,
            'tag_tolti' => $tagTolti,
            'foto_ripulite' => count($idFoto),
            'titoli_da_rivedere' => $titoliDaRivedere,
        ];

        $this->registra($persona, $esito, $motivo);

        return $esito;
    }

    /**
     * Alla cancellazione della scheda (eventi `deleted` di Player e
     * StaffMember). Non deve mai impedire la cancellazione: un errore si
     * registra e basta.
     */
    public function allaCancellazione(Player|StaffMember $persona): void
    {
        try {
            $this->revoca($persona, ancheITagManuali: $this->staPerSparire($persona), motivo: 'cancellazione della scheda');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Una scheda cancellata davvero (lo staff, o l'atleta eliminata
     * definitivamente) non torna: anche i suoi tag manuali non servono piu'.
     * L'atleta nel cestino si puo' ripristinare, e i tag della redazione con lei.
     */
    private function staPerSparire(Player|StaffMember $persona): bool
    {
        if (! method_exists($persona, 'isForceDeleting')) {
            return ! $persona->exists;
        }

        return $persona->isForceDeleting();
    }

    private function cancellaSuCompreFace(Player|StaffMember $persona): string
    {
        try {
            $this->volti->cancellaIlSoggetto($persona);

            return self::COMPREFACE_CANCELLATO;
        } catch (ConnectionException $e) {
            Log::warning('Revoca volti: CompreFace non raggiungibile', ['persona' => $persona::class.'#'.$persona->getKey(), 'errore' => $e->getMessage()]);

            return self::COMPREFACE_NON_RAGGIUNGIBILE;
        } catch (\Throwable $e) {
            Log::warning('Revoca volti: CompreFace in errore', ['persona' => $persona::class.'#'.$persona->getKey(), 'errore' => $e->getMessage()]);

            return self::COMPREFACE_IN_ERRORE;
        }
    }

    /**
     * Riscrive i testi generati della foto con le persone rimaste.
     *
     * Alt, descrizione e parole chiave sulla media li scrive solo l'analisi:
     * si rifanno se l'analisi li aveva scritti. Il titolo la redazione lo puo'
     * cambiare: si rifa' solo se e' ancora quello generato con questo nome.
     *
     * Pubblico: lo usa anche `volti:togli-fuori-stagione`.
     *
     * @return bool false se il titolo nomina ancora la persona e non e' nostro
     */
    public function ripulisciITesti(GalleryImage $foto, string $nome): bool
    {
        $foto->unsetRelation('players')->unsetRelation('staffMembers');
        $testi = TestiSeoDellaFoto::componi($foto);

        if (TestiSeoDellaFoto::eGeneratoConLaPersona($foto->title, $nome)) {
            $foto->title = $testi['titolo'] !== '' ? $testi['titolo'] : null;
            $foto->saveQuietly();
        }

        if ($foto->getFirstMedia('gallery')?->hasCustomProperty('alt')) {
            TestiSeoDellaFoto::scriviSullaMedia($foto, $testi);
        }

        return trim($nome) === '' || ! str_contains((string) $foto->title, $nome);
    }

    /**
     * @param  array{compreface: string, tag_tolti: int, foto_ripulite: int, titoli_da_rivedere: list<int>}  $esito
     */
    private function registra(Player|StaffMember $persona, array $esito, string $motivo): void
    {
        try {
            ActivityLog::create([
                'user_id' => Auth::id(),
                'action' => self::AZIONE,
                'model_type' => $persona::class,
                'model_id' => $persona->getKey(),
                'model_label' => $persona->full_name,
                'changes' => ['new' => ['motivo' => $motivo, ...$esito]],
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
