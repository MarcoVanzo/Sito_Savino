<?php

namespace Tests\Feature\Gallery;

use App\Enums\UserRole;
use App\Filament\Actions\RevocaRiconoscimentoVoltiAction;
use App\Filament\Resources\PlayerResource\Pages\EditPlayer;
use App\Filament\Resources\StaffMemberResource\Pages\EditStaffMember;
use App\Models\ActivityLog;
use App\Models\GalleryEvent;
use App\Models\GalleryImage;
use App\Models\Player;
use App\Models\StaffMember;
use App\Models\User;
use App\Services\RevocaDelRiconoscimentoDeiVolti;
use App\Support\TestiSeoDellaFoto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La revoca del consenso al riconoscimento dei volti (art. 9 GDPR).
 *
 * Dopo la revoca della persona non deve restare niente di cio' che la
 * macchina ha prodotto: il volto su CompreFace, i tag automatici, il nome
 * nei testi generati delle foto. Il lavoro della redazione (tag manuali,
 * titoli scritti a mano) resta.
 */
class RevocaRiconoscimentoVoltiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Storage::fake(config('media-library.disk_name'));
        config(['services.compreface.host' => 'http://compreface.test:8000', 'services.compreface.key' => 'chiave-di-prova']);
    }

    private function amministratore(): User
    {
        $utente = User::factory()->create();
        $utente->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();

        return $utente->refresh();
    }

    private function compreFaceRisponde(): void
    {
        Http::fake(['compreface.test*' => Http::response(['deleted' => 1], 200)]);
    }

    private function album(): GalleryEvent
    {
        return GalleryEvent::factory()->create(['title' => 'Savino vs Conegliano', 'event_date' => '2026-09-20']);
    }

    private function tagga(GalleryImage $foto, Player|StaffMember $persona, ?float $confidenza): void
    {
        DB::table('gallery_image_person')->insert([
            'gallery_image_id' => $foto->id,
            'person_type' => $persona::class,
            'person_id' => $persona->id,
            'confidence_score' => $confidenza,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** La foto come la lascia l'analisi: tag e testi generati. */
    private function fotoAnalizzata(GalleryEvent $album, array $persone, bool $conMedia = false): GalleryImage
    {
        $foto = GalleryImage::factory()->create(['gallery_event_id' => $album->id]);

        if ($conMedia) {
            $foto->addMedia(UploadedFile::fake()->image('foto.jpg', 40, 40))->toMediaCollection('gallery');
        }

        foreach ($persone as [$persona, $confidenza]) {
            $this->tagga($foto, $persona, $confidenza);
        }

        $foto = $foto->fresh();
        TestiSeoDellaFoto::applica($foto);
        $foto->saveQuietly();

        return $foto->fresh();
    }

    private function righe(Player|StaffMember $persona)
    {
        return DB::table('gallery_image_person')->where('person_type', $persona::class)->where('person_id', $persona->id);
    }

    #[Test]
    public function cancella_il_volto_su_compreface_e_i_tag_automatici_ma_non_quelli_manuali(): void
    {
        $this->compreFaceRisponde();
        $album = $this->album();
        $bosetti = Player::factory()->create(['first_name' => 'Lucia', 'last_name' => 'Bosetti', 'ai_face_examples' => 4]);

        $automatica = $this->fotoAnalizzata($album, [[$bosetti, 99.1]]);
        $manuale = $this->fotoAnalizzata($album, [[$bosetti, null]]);

        $esito = app(RevocaDelRiconoscimentoDeiVolti::class)->revoca($bosetti);

        $this->assertSame(RevocaDelRiconoscimentoDeiVolti::COMPREFACE_CANCELLATO, $esito['compreface']);
        $this->assertSame(1, $esito['tag_tolti']);
        $this->assertSame([$manuale->id], $this->righe($bosetti)->pluck('gallery_image_id')->all());
        $this->assertSame(0, $bosetti->fresh()->ai_face_examples);

        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_contains($r->url(), '/faces?subject=player_'.$bosetti->id));
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/subjects/player_'.$bosetti->id));
        $this->assertNotNull($automatica);
    }

    #[Test]
    public function toglie_il_nome_dai_testi_generati_e_lascia_le_altre_persone(): void
    {
        $this->compreFaceRisponde();
        $album = $this->album();
        $bosetti = Player::factory()->create(['first_name' => 'Lucia', 'last_name' => 'Bosetti']);
        $gaspari = Player::factory()->create(['first_name' => 'Marco', 'last_name' => 'Gaspari']);

        $foto = $this->fotoAnalizzata($album, [[$bosetti, 99.2], [$gaspari, 99.0]], conMedia: true);
        $this->assertStringContainsString('Lucia Bosetti', $foto->title);

        app(RevocaDelRiconoscimentoDeiVolti::class)->revoca($bosetti);

        $foto = $foto->fresh();
        $media = $foto->getFirstMedia('gallery');

        $this->assertSame('Marco Gaspari - Savino vs Conegliano - 20/09/2026', $foto->title);
        foreach (['alt', 'description', 'keywords'] as $proprieta) {
            $this->assertStringNotContainsString('Bosetti', (string) $media->getCustomProperty($proprieta), $proprieta);
        }
        $this->assertStringContainsString('Marco Gaspari', $media->getCustomProperty('alt'));
    }

    #[Test]
    public function un_titolo_scritto_a_mano_non_si_tocca_ma_si_segnala(): void
    {
        $this->compreFaceRisponde();
        $album = $this->album();
        $bosetti = Player::factory()->create(['first_name' => 'Lucia', 'last_name' => 'Bosetti']);

        $foto = $this->fotoAnalizzata($album, [[$bosetti, 99.5]]);
        $foto->update(['title' => 'Il muro di Lucia Bosetti in gara 3']);

        $esito = app(RevocaDelRiconoscimentoDeiVolti::class)->revoca($bosetti);

        $this->assertSame('Il muro di Lucia Bosetti in gara 3', $foto->fresh()->title);
        $this->assertSame([$foto->id], $esito['titoli_da_rivedere']);
    }

    #[Test]
    public function senza_piu_persone_ne_album_il_titolo_generato_si_svuota(): void
    {
        $this->compreFaceRisponde();
        $album = GalleryEvent::factory()->create(['title' => null, 'event_date' => null]);
        $bosetti = Player::factory()->create(['first_name' => 'Lucia', 'last_name' => 'Bosetti']);

        $foto = $this->fotoAnalizzata($album, [[$bosetti, 99.5]]);
        $this->assertSame('Lucia Bosetti', $foto->title);

        app(RevocaDelRiconoscimentoDeiVolti::class)->revoca($bosetti);

        $this->assertNull($foto->fresh()->title);
    }

    #[Test]
    public function lascia_traccia_nel_registro(): void
    {
        $this->compreFaceRisponde();
        $bosetti = Player::factory()->create(['first_name' => 'Lucia', 'last_name' => 'Bosetti']);
        $this->fotoAnalizzata($this->album(), [[$bosetti, 99.5]]);

        app(RevocaDelRiconoscimentoDeiVolti::class)->revoca($bosetti);

        $riga = ActivityLog::where('action', RevocaDelRiconoscimentoDeiVolti::AZIONE)->sole();

        $this->assertSame(Player::class, $riga->model_type);
        $this->assertSame($bosetti->id, $riga->model_id);
        $this->assertSame(1, $riga->changes['new']['tag_tolti']);
        $this->assertSame('cancellato', $riga->changes['new']['compreface']);
        $this->assertSame('Revoca riconoscimento volti', $riga->action_label);
    }

    #[Test]
    public function con_compreface_irraggiungibile_ripulisce_il_database_e_lo_dice(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Connection timeout'));
        $bosetti = Player::factory()->create(['ai_face_examples' => 3]);
        $this->fotoAnalizzata($this->album(), [[$bosetti, 99.5]]);

        $esito = app(RevocaDelRiconoscimentoDeiVolti::class)->revoca($bosetti);

        $this->assertSame(RevocaDelRiconoscimentoDeiVolti::COMPREFACE_NON_RAGGIUNGIBILE, $esito['compreface']);
        $this->assertSame(0, $this->righe($bosetti)->count());
        $this->assertSame(0, $bosetti->fresh()->ai_face_examples);
    }

    #[Test]
    public function un_soggetto_che_compreface_non_conosce_e_gia_cancellato(): void
    {
        Http::fake(['compreface.test*' => Http::response(['message' => 'Subject staff_9 not found', 'code' => 42], 404)]);
        $persona = StaffMember::factory()->create();

        $esito = app(RevocaDelRiconoscimentoDeiVolti::class)->revoca($persona);

        $this->assertSame(RevocaDelRiconoscimentoDeiVolti::COMPREFACE_CANCELLATO, $esito['compreface']);
    }

    #[Test]
    public function cancellare_la_scheda_dello_staff_toglie_anche_i_tag_manuali(): void
    {
        $this->compreFaceRisponde();
        $album = $this->album();
        $staff = StaffMember::factory()->create(['first_name' => 'Massimo', 'last_name' => 'Barbolini']);
        $this->fotoAnalizzata($album, [[$staff, 99.5]]);
        $this->fotoAnalizzata($album, [[$staff, null]]);

        $staff->delete();

        $this->assertSame(0, $this->righe($staff)->count());
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/subjects/staff_'.$staff->id));
        $this->assertSame(1, ActivityLog::where('action', RevocaDelRiconoscimentoDeiVolti::AZIONE)->count());
    }

    #[Test]
    public function l_atleta_nel_cestino_perde_i_tag_automatici_e_tiene_quelli_manuali(): void
    {
        $this->compreFaceRisponde();
        $album = $this->album();
        $bosetti = Player::factory()->create();
        $this->fotoAnalizzata($album, [[$bosetti, 99.5]]);
        $manuale = $this->fotoAnalizzata($album, [[$bosetti, null]]);

        $bosetti->delete();

        $this->assertSoftDeleted($bosetti);
        $this->assertSame([$manuale->id], $this->righe($bosetti)->pluck('gallery_image_id')->all());

        $bosetti->forceDelete();

        $this->assertSame(0, $this->righe($bosetti)->count());
    }

    #[Test]
    public function un_errore_di_compreface_non_blocca_la_cancellazione_della_scheda(): void
    {
        Http::fake(['compreface.test*' => Http::response('boom', 500)]);
        $staff = StaffMember::factory()->create();

        $staff->delete();

        $this->assertModelMissing($staff);
    }

    #[Test]
    public function dalla_scheda_dell_atleta_la_revoca_chiede_conferma_ed_esegue(): void
    {
        $this->compreFaceRisponde();
        $this->actingAs($this->amministratore());
        $bosetti = Player::factory()->create(['ai_face_examples' => 2]);
        $this->fotoAnalizzata($this->album(), [[$bosetti, 99.5]]);

        Livewire::test(EditPlayer::class, ['record' => $bosetti->getRouteKey()])
            ->callAction(RevocaRiconoscimentoVoltiAction::NOME)
            ->assertNotified('Consenso revocato');

        $this->assertSame(0, $this->righe($bosetti)->count());
        $this->assertSame(0, $bosetti->fresh()->ai_face_examples);
    }

    #[Test]
    public function dalla_scheda_dello_staff_con_compreface_giu_avvisa_che_la_revoca_e_incompleta(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Connection timeout'));
        $this->actingAs($this->amministratore());
        $staff = StaffMember::factory()->create();

        Livewire::test(EditStaffMember::class, ['record' => $staff->getRouteKey()])
            ->callAction(RevocaRiconoscimentoVoltiAction::NOME)
            ->assertNotified('Revoca incompleta: CompreFace non ha confermato');
    }
}
