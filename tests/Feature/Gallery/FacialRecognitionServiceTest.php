<?php

namespace Tests\Feature\Gallery;

use App\Models\Player;
use App\Models\Post;
use App\Models\StaffMember;
use App\Services\FacialRecognitionException;
use App\Services\FacialRecognitionService;
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
        $this->servizio = new FacialRecognitionService;
    }

    private function immagineFinta(): string
    {
        $percorso = tempnam(sys_get_temp_dir(), 'volto').'.jpg';
        file_put_contents($percorso, 'contenuto binario finto');

        return $percorso;
    }

    private function rispostaConVolti(array $volti): void
    {
        Http::fake(['*/recognize*' => Http::response(['result' => $volti], 200)]);
    }

    /**
     * Un volto come lo descrive CompreFace: riquadro e miglior soggetto.
     *
     * @param  array<int, array{subject: string, similarity: float}>  $subjects
     */
    private function voltoAlto(int $altezza, array $subjects): array
    {
        return [
            'box' => ['x_min' => 0, 'y_min' => 0, 'x_max' => $altezza, 'y_max' => $altezza, 'probability' => 0.99],
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
        Http::fake(['*/recognize*' => Http::response([
            'message' => 'No face is found in the given image',
            'code' => 28,
        ], 400)]);

        $esito = $this->servizio->recognizeFaces($this->immagineFinta());

        $this->assertSame([], $esito['detected_persons']);
        $this->assertFalse($esito['has_unrecognized_faces']);
    }

    /**
     * Gli altri 400 (file illeggibile, estensione non supportata…) restano
     * errori veri: devono arrivare a chi chiama, non passare per "nessun volto".
     */
    #[Test]
    public function un_400_diverso_da_nessun_volto_resta_un_errore(): void
    {
        Http::fake(['*/recognize*' => Http::response([
            'message' => 'File has an unavailable extension',
            'code' => 21,
        ], 400)]);

        $this->expectException(FacialRecognitionException::class);

        $this->servizio->recognizeFaces($this->immagineFinta());
    }

    #[Test]
    public function un_errore_del_servizio_di_riconoscimento_non_passa_inosservato(): void
    {
        Http::fake(['*/recognize*' => Http::response('servizio non disponibile', 503)]);

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
            '*/recognize*' => Http::response(['result' => [$this->volto(180)]]),
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
            '*/recognize*' => Http::response(['result' => [$this->volto(44)]]),
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
            '*/recognize*' => Http::response(['result' => [$this->volto(200), $this->volto(150)]]),
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
            '*/recognize*' => Http::response(['message' => 'No face is found in the given image', 'code' => 28], 400),
            '*/faces*' => Http::response(['image_id' => 'mai']),
        ]);

        $esito = $this->servizio->addFaceExample($atleta, $this->immagineFinta());

        $this->assertFalse($esito['success']);
        $this->assertSame('Nessun volto trovato nella foto.', $esito['error']);
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/faces'));
    }
}
