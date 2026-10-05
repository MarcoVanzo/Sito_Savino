<?php

namespace Tests\Feature\Gallery;

use App\Enums\EsitoAvviso;
use App\Models\Player;
use App\Models\Post;
use App\Models\StaffMember;
use App\Services\AvvisoTecnico;
use App\Services\FacialRecognitionException;
use App\Services\FacialRecognitionService;
use App\Support\VoltiDiSfondo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Il dialogo con CompreFace, senza CompreFace.
 *
 * La parte che conta non è la chiamata HTTP ma cosa il servizio decide con la
 * risposta: quando una foto va rivista in redazione, quando un volto diventa un
 * tag e quando invece si tace. La soglia di somiglianza è alta di proposito —
 * un tag sbagliato su una foto pubblica costa più di un tag mancante.
 */
class FacialRecognitionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FacialRecognitionService $servizio;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.compreface.host' => 'http://compreface.test:8000']);
        config(['services.compreface.key' => 'chiave-di-prova']);
        config(['services.compreface.detection_key' => 'chiave-del-rilevamento']);
        $this->servizio = new FacialRecognitionService;
    }

    private function immagineFinta(): string
    {
        $percorso = tempnam(sys_get_temp_dir(), 'volto').'.jpg';
        $immagine = imagecreatetruecolor(1200, 800);
        imagefill($immagine, 0, 0, (int) imagecolorallocate($immagine, 255, 255, 255));
        imagejpeg($immagine, $percorso);
        imagedestroy($immagine);

        return $percorso;
    }

    /**
     * Il rilevamento trova gli stessi volti che il riconoscimento descrive; un
     * volto senza riquadro si intende in primo piano (200 px).
     */
    private function rispostaConVolti(array $volti): void
    {
        $volti = array_map(fn (array $volto): array => $volto + ['box' => $this->riquadro(200)], $volti);

        Http::fake([
            '*/detection/detect*' => Http::response(['result' => array_map(fn (array $volto): array => ['box' => $volto['box']], $volti)], 200),
            '*/recognize*' => Http::response(['result' => $volti], 200),
        ]);
    }

    /** @return array<string, int|float> */
    private function riquadro(int $altezza, int $x = 0, int $y = 0): array
    {
        return ['x_min' => $x, 'y_min' => $y, 'x_max' => $x + $altezza, 'y_max' => $y + $altezza, 'probability' => 0.99];
    }

    /**
     * Un volto come lo descrive CompreFace: riquadro e miglior soggetto.
     *
     * @param  array<int, array{subject: string, similarity: float}>  $subjects
     */
    private function voltoAlto(int $altezza, array $subjects): array
    {
        return [
            'box' => $this->riquadro($altezza),
            'subjects' => $subjects,
        ];
    }

    #[Test]
    public function il_nome_del_soggetto_distingue_atlete_e_staff(): void
    {
        $atleta = Player::factory()->create();
        $membro = StaffMember::factory()->create();

        $this->assertSame('player_'.$atleta->id, $this->servizio->getSubjectName($atleta));
        $this->assertSame('staff_'.$membro->id, $this->servizio->getSubjectName($membro));
    }

    #[Test]
    public function un_modello_non_previsto_non_ha_un_nome_di_soggetto(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->servizio->getSubjectName(new Post);
    }

    #[Test]
    public function il_nome_del_soggetto_si_rilegge_al_contrario(): void
    {
        $this->assertSame(['type' => Player::class, 'id' => 12], $this->servizio->resolveSubject('player_12'));
        $this->assertSame(['type' => StaffMember::class, 'id' => 3], $this->servizio->resolveSubject('staff_3'));
        $this->assertNull($this->servizio->resolveSubject('qualcosa_altro'));
        $this->assertNull($this->servizio->resolveSubject('player_'));
    }

    #[Test]
    public function un_volto_riconosciuto_con_certezza_diventa_un_tag(): void
    {
        $atleta = Player::factory()->create();
        $this->rispostaConVolti([
            ['subjects' => [['subject' => 'player_'.$atleta->id, 'similarity' => 0.99]]],
        ]);

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertCount(1, $esito['detected_persons']);
        $this->assertSame(Player::class, $esito['detected_persons'][0]['person_type']);
        $this->assertSame($atleta->id, $esito['detected_persons'][0]['person_id']);
        $this->assertEqualsWithDelta(99.0, $esito['detected_persons'][0]['confidence'], 0.01);
        $this->assertFalse($esito['has_unrecognized_faces']);
    }

    /**
     * Sotto la soglia non si tagga: meglio un tag mancante che uno sbagliato
     * su una foto pubblica. Ma se il volto è grande e somiglia molto a
     * un'atleta, la foto va in redazione: è il caso che vale la pena guardare.
     */
    #[Test]
    public function un_volto_grande_quasi_riconosciuto_lascia_la_foto_da_rivedere(): void
    {
        $atleta = Player::factory()->create();
        $this->rispostaConVolti([
            $this->voltoAlto(200, [['subject' => 'player_'.$atleta->id, 'similarity' => 0.975]]),
        ]);

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertSame([], $esito['detected_persons']);
        $this->assertTrue($esito['has_unrecognized_faces']);
    }

    /**
     * A settembre 2026 il flag era acceso su 6.004 foto su 6.005: bastava un
     * volto in tribuna. Chi non somiglia a nessuno dei nostri non si rivede.
     */
    #[Test]
    public function un_volto_che_non_somiglia_a_nessuno_non_manda_la_foto_in_revisione(): void
    {
        $atleta = Player::factory()->create();
        $this->rispostaConVolti([
            $this->voltoAlto(200, []),
            $this->voltoAlto(200, [['subject' => 'player_'.$atleta->id, 'similarity' => 0.60]]),
            // Due volti qualunque si somigliano spesso al 91-96%: non basta.
            $this->voltoAlto(200, [['subject' => 'player_'.$atleta->id, 'similarity' => 0.95]]),
        ]);

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertSame([], $esito['detected_persons']);
        $this->assertFalse($esito['has_unrecognized_faces']);
    }

    #[Test]
    public function un_volto_piccolo_anche_se_somigliante_non_si_puo_rivedere(): void
    {
        $atleta = Player::factory()->create();
        $this->rispostaConVolti([
            $this->voltoAlto(40, [['subject' => 'player_'.$atleta->id, 'similarity' => 0.98]]),
        ]);

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertFalse($esito['has_unrecognized_faces']);
    }

    /**
     * Un soggetto addestrato per un'atleta poi cancellata non è un volto
     * ignoto: è una traccia vecchia, e non deve mandare la foto in revisione.
     */
    #[Test]
    public function un_soggetto_non_piu_in_anagrafica_non_manda_la_foto_in_revisione(): void
    {
        $this->rispostaConVolti([
            ['subjects' => [['subject' => 'sconosciuto_9', 'similarity' => 0.999]]],
            $this->voltoAlto(200, [['subject' => 'sconosciuto_9', 'similarity' => 0.975]]),
        ]);

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertSame([], $esito['detected_persons']);
        $this->assertFalse($esito['has_unrecognized_faces']);
    }

    #[Test]
    public function piu_volti_nella_stessa_foto_danno_piu_tag(): void
    {
        $prima = Player::factory()->create();
        $seconda = Player::factory()->create();
        $this->rispostaConVolti([
            ['subjects' => [['subject' => 'player_'.$prima->id, 'similarity' => 0.99]]],
            ['subjects' => [['subject' => 'player_'.$seconda->id, 'similarity' => 0.995]]],
            $this->voltoAlto(150, [['subject' => 'player_'.$prima->id, 'similarity' => 0.975]]),
        ]);

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertCount(2, $esito['detected_persons']);
        $this->assertTrue($esito['has_unrecognized_faces'], 'Il terzo volto, quasi riconosciuto, resta da rivedere.');
    }

    /**
     * CompreFace risponde 400 (code 28) quando nella foto non ci sono volti:
     * pubblico, palazzetto, dettagli di gioco. Non è un guasto — la foto è
     * analizzata, senza tag e senza revisione.
     */
    #[Test]
    public function una_foto_senza_volti_e_analizzata_senza_tag_ne_revisione(): void
    {
        Http::fake(['*/detection/detect*' => Http::response([
            'message' => 'No face is found in the given image',
            'code' => 28,
        ], 400)]);

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertSame([], $esito['detected_persons']);
        $this->assertFalse($esito['has_unrecognized_faces']);
        Http::assertNotSent(fn (Request $richiesta): bool => str_contains($richiesta->url(), '/recognize'));
    }

    /**
     * L'informativa dice che i volti del pubblico non si confrontano con
     * nessuno: se nella foto ci sono solo volti di sfondo il riconoscimento
     * non parte nemmeno.
     */
    #[Test]
    public function una_foto_con_soli_volti_di_sfondo_non_arriva_al_riconoscimento(): void
    {
        Http::fake(['*/detection/detect*' => Http::response(['result' => [
            ['box' => $this->riquadro(10, 10, 10)],
            ['box' => $this->riquadro(31, 200, 10)],
        ]], 200)]);

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertSame([], $esito['detected_persons']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn (Request $richiesta): bool => str_contains($richiesta->url(), '/recognize'));
    }

    /**
     * Con volti in primo piano e pubblico dietro, al riconoscimento arriva la
     * foto con i volti di sfondo coperti; quelli in primo piano restano.
     */
    #[Test]
    public function i_volti_di_sfondo_arrivano_al_riconoscimento_coperti(): void
    {
        $atleta = Player::factory()->create();
        $inviata = null;

        Http::fake(function (Request $richiesta) use (&$inviata, $atleta) {
            if (str_contains($richiesta->url(), '/detection/detect')) {
                return Http::response(['result' => [
                    ['box' => $this->riquadro(20, 500, 20)],
                    ['box' => $this->riquadro(20, 300, 330)],
                    ['box' => $this->riquadro(200, 100, 150)],
                ]], 200);
            }

            $inviata = $richiesta->data()[0]['contents'] ?? null;

            return Http::response(['result' => [
                ['box' => $this->riquadro(200, 100, 150), 'subjects' => [['subject' => 'player_'.$atleta->id, 'similarity' => 0.99]]],
            ]], 200);
        });

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertCount(1, $esito['detected_persons']);

        $foto = imagecreatefromstring(is_resource($inviata) ? (string) stream_get_contents($inviata, -1, 0) : (string) $inviata);
        $this->assertNotFalse($foto, 'Al riconoscimento deve arrivare una foto.');
        $grigio = imagecolorsforindex($foto, imagecolorat($foto, 510, 30));
        $bianco = imagecolorsforindex($foto, imagecolorat($foto, 200, 250));
        $bordo = imagecolorsforindex($foto, imagecolorat($foto, 297, 340));
        $this->assertEqualsWithDelta(128, $grigio['red'], 6, 'Il volto di sfondo deve essere coperto.');
        $this->assertGreaterThan(240, $bianco['red'], 'Il volto in primo piano deve restare com\'era.');
        $this->assertGreaterThan(240, $bordo['red'], 'Il margine della copertura non deve mangiare il volto in primo piano.');
    }

    /** Un riquadro che esce dalla foto non deve far perdere il volto in primo piano. */
    #[Test]
    public function un_volto_sul_bordo_resta_intero_anche_vicino_allo_sfondo(): void
    {
        $percorso = $this->immagineFinta();
        $copia = VoltiDiSfondo::copiaDiLavoro($percorso, 5 * 1024 * 1024);

        try {
            VoltiDiSfondo::copri(
                $copia,
                [['box' => $this->riquadro(20, 1080, 690)]],
                [['box' => $this->riquadro(200, 1100, 700)]],
                5 * 1024 * 1024,
            );

            $foto = imagecreatefromjpeg($copia);
            $this->assertGreaterThan(240, imagecolorsforindex($foto, imagecolorat($foto, 1105, 705))['red']);
        } finally {
            @unlink($copia);
        }
    }

    /**
     * Un pezzo di volto piccolo restituito dal riconoscimento (ai margini di
     * una copertura) non diventa un tag.
     */
    #[Test]
    public function un_volto_piccolo_nel_riconoscimento_non_diventa_un_tag(): void
    {
        $atleta = Player::factory()->create();
        Http::fake([
            '*/detection/detect*' => Http::response(['result' => [['box' => $this->riquadro(200)]]], 200),
            '*/recognize*' => Http::response(['result' => [
                ['box' => $this->riquadro(15), 'subjects' => [['subject' => 'player_'.$atleta->id, 'similarity' => 0.999]]],
            ]], 200),
        ]);

        $this->assertSame([], $this->servizio->recognizeFaces($this->immagineFinta())['detected_persons']);
    }

    #[Test]
    public function senza_chiave_del_rilevamento_non_si_riconosce_nessuno_e_si_avvisa(): void
    {
        config(['services.compreface.detection_key' => null]);
        Http::fake();
        $this->mock(AvvisoTecnico::class)->shouldReceive('invia')->once()
            ->withArgs(fn (string $oggetto): bool => str_contains($oggetto, 'COMPREFACE_DETECTION_KEY'))
            ->andReturn(EsitoAvviso::Inviato);

        try {
            $this->servizio->recognizeFaces($this->immagineFinta());
            $this->fail('Doveva fermarsi senza chiave del rilevamento.');
        } catch (FacialRecognitionException $e) {
            $this->assertStringContainsString('rilevamento', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    /**
     * La soglia è una quota del lato corto, non pixel: su una foto da 24 MP il
     * pubblico sfocato supera i 100 px e con 80 px fissi passava.
     */
    #[Test]
    public function la_soglia_del_primo_piano_e_relativa_alla_foto(): void
    {
        $volto = ['box' => $this->riquadro(120)];

        $this->assertFalse(VoltiDiSfondo::inPrimoPiano($volto, 4000), '120 px su 4000: pubblico.');
        $this->assertTrue(VoltiDiSfondo::inPrimoPiano($volto, 1000), '120 px su 1000: primo piano.');
        $this->assertFalse(VoltiDiSfondo::inPrimoPiano($volto, 0));
    }

    /** CompreFace rifiuta i file oltre 5 MB: la copia mandata deve starci. */
    #[Test]
    public function la_copia_di_lavoro_resta_sotto_il_peso_massimo(): void
    {
        $percorso = tempnam(sys_get_temp_dir(), 'rumore').'.jpg';
        $immagine = imagecreatetruecolor(2400, 1600);
        for ($i = 0; $i < 40000; $i++) {
            imagefilledrectangle($immagine, $x = random_int(0, 2399), $y = random_int(0, 1599), $x + 8, $y + 8, random_int(0, 0xFFFFFF));
        }
        imagejpeg($immagine, $percorso, 100);
        imagedestroy($immagine);

        $copia = VoltiDiSfondo::copiaDiLavoro($percorso, 3 * 1024 * 1024);

        try {
            clearstatcache();
            $this->assertLessThanOrEqual(3 * 1024 * 1024, filesize($copia));
        } finally {
            @unlink($copia);
            @unlink($percorso);
        }
    }

    #[Test]
    public function un_errore_del_rilevamento_non_passa_inosservato(): void
    {
        Http::fake(['*/detection/detect*' => Http::response('servizio non disponibile', 503)]);

        $this->expectException(FacialRecognitionException::class);

        $this->servizio->recognizeFaces($this->immagineFinta());
    }

    /**
     * Gli altri 400 (file illeggibile, estensione non supportata…) restano
     * errori veri: devono arrivare a chi chiama, non passare per "nessun volto".
     */
    #[Test]
    public function un_400_diverso_da_nessun_volto_resta_un_errore(): void
    {
        Http::fake([
            '*/detection/detect*' => Http::response(['result' => [['box' => $this->riquadro(200)]]], 200),
            '*/recognize*' => Http::response([
                'message' => 'File has an unavailable extension',
                'code' => 21,
            ], 400),
        ]);

        $this->expectException(FacialRecognitionException::class);

        $this->servizio->recognizeFaces($this->immagineFinta());
    }

    #[Test]
    public function un_errore_del_servizio_di_riconoscimento_non_passa_inosservato(): void
    {
        Http::fake([
            '*/detection/detect*' => Http::response(['result' => [['box' => $this->riquadro(200)]]], 200),
            '*/recognize*' => Http::response('servizio non disponibile', 503),
        ]);

        $this->expectException(FacialRecognitionException::class);

        $this->servizio->recognizeFaces($this->immagineFinta());
    }

    #[Test]
    public function senza_chiave_configurata_il_riconoscimento_si_ferma_subito(): void
    {
        config(['services.compreface.key' => '']);
        $servizio = new FacialRecognitionService;

        $this->expectException(FacialRecognitionException::class);
        $this->expectExceptionMessage('CompreFace API Key non configurata.');

        $servizio->recognizeFaces($this->immagineFinta());
    }

    #[Test]
    public function il_soggetto_si_crea_una_volta_sola_e_il_doppione_non_e_un_errore(): void
    {
        $atleta = Player::factory()->create();
        Http::fake(['*/subjects' => Http::response(['message' => 'già presente'], 400)]);

        $this->assertTrue($this->servizio->createSubject($atleta));

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/api/v1/recognition/subjects')
            && $r['subject'] === 'player_'.$atleta->id);
    }

    #[Test]
    public function se_il_servizio_e_giu_il_soggetto_non_viene_creato(): void
    {
        Http::fake(['*/subjects' => Http::response('errore', 500)]);

        $this->assertFalse($this->servizio->createSubject(Player::factory()->create()));
    }

    #[Test]
    public function senza_chiave_non_si_crea_nessun_soggetto(): void
    {
        config(['services.compreface.key' => '']);
        Http::fake();

        $this->assertFalse((new FacialRecognitionService)->createSubject(Player::factory()->create()));

        Http::assertNothingSent();
    }

    // ── Qualità degli esempi di addestramento ──────────────────────────────

    private function volto(int $altezza): array
    {
        return ['box' => ['x_min' => 100, 'y_min' => 100, 'x_max' => 100 + $altezza, 'y_max' => 100 + $altezza, 'probability' => 0.99], 'subjects' => []];
    }

    #[Test]
    public function un_primo_piano_viene_caricato_come_esempio(): void
    {
        $atleta = Player::factory()->create();
        Http::fake([
            '*/detection/detect*' => Http::response(['result' => [$this->volto(180)]]),
            '*/subjects' => Http::response(['subject' => 'player_'.$atleta->id]),
            '*/faces*' => Http::response(['image_id' => 'abc', 'subject' => 'player_'.$atleta->id]),
        ]);

        $esito = $this->servizio->addFaceExample($atleta, $this->immagineFinta());

        $this->assertTrue($esito['success']);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/faces?subject=player_'.$atleta->id));
    }

    #[Test]
    public function un_volto_troppo_piccolo_non_diventa_un_esempio(): void
    {
        config(['services.compreface.min_face_px' => 90]);
        $atleta = Player::factory()->create();
        Http::fake([
            '*/detection/detect*' => Http::response(['result' => [$this->volto(44)]]),
            '*/faces*' => Http::response(['image_id' => 'mai']),
        ]);

        $esito = $this->servizio->addFaceExample($atleta, $this->immagineFinta());

        $this->assertFalse($esito['success']);
        $this->assertStringContainsString('Volto troppo piccolo (44 px, minimo 90)', $esito['error']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/faces'));
    }

    #[Test]
    public function una_foto_con_piu_volti_viene_scartata_prima_di_caricarla(): void
    {
        $atleta = Player::factory()->create();
        Http::fake([
            '*/detection/detect*' => Http::response(['result' => [$this->volto(200), $this->volto(150)]]),
            '*/faces*' => Http::response(['image_id' => 'mai']),
        ]);

        $esito = $this->servizio->addFaceExample($atleta, $this->immagineFinta());

        $this->assertFalse($esito['success']);
        $this->assertSame('Trovati più volti nella foto (usa un primo piano).', $esito['error']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/faces'));
    }

    #[Test]
    public function una_foto_senza_volti_viene_scartata_prima_di_caricarla(): void
    {
        $atleta = Player::factory()->create();
        Http::fake([
            '*/detection/detect*' => Http::response(['message' => 'No face is found in the given image', 'code' => 28], 400),
            '*/faces*' => Http::response(['image_id' => 'mai']),
        ]);

        $esito = $this->servizio->addFaceExample($atleta, $this->immagineFinta());

        $this->assertFalse($esito['success']);
        $this->assertSame('Nessun volto trovato nella foto.', $esito['error']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/faces'));
    }
}
