<?php

namespace Tests\Feature;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * Il comando ripara Content-Type e Cache-Control degli oggetti su Spaces
 * (caricati come `binary/octet-stream` senza cache, vedi il commento nella
 * classe). Qui si verifica che tocchi solo gli oggetti sbagliati, che il
 * dry-run non scriva nulla e che un disco non-S3 lo faccia uscire subito.
 */
class FixRemoteMediaMetadataTest extends TestCase
{
    use RefreshDatabase;

    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    #[Test]
    public function con_un_disco_non_s3_esce_senza_fare_nulla(): void
    {
        config(['media-library.disk_name' => 'public']);

        $this->artisan('media:fix-remote-metadata')
            ->expectsOutputToContain('non è S3')
            ->assertSuccessful();
    }

    #[Test]
    public function corregge_solo_gli_oggetti_con_metadati_sbagliati(): void
    {
        $giusto = $this->makeMedia('ok.jpg');
        $sbagliato = $this->makeMedia('rotto.jpg');

        $client = Mockery::mock(S3Client::class);

        // L'oggetto già a posto viene solo letto.
        $client->shouldReceive('headObject')
            ->with(['Bucket' => 'bucket-test', 'Key' => $giusto->getPathRelativeToRoot()])
            ->andReturn(['ContentType' => 'image/jpeg', 'CacheControl' => self::CACHE_CONTROL]);

        // Quello sbagliato viene letto e riscritto con i metadati giusti.
        $client->shouldReceive('headObject')
            ->with(['Bucket' => 'bucket-test', 'Key' => $sbagliato->getPathRelativeToRoot()])
            ->andReturn(['ContentType' => 'binary/octet-stream']);

        $client->shouldReceive('copyObject')->once()->with(Mockery::on(
            fn (array $args) => $args['Key'] === $sbagliato->getPathRelativeToRoot()
                && $args['ContentType'] === 'image/jpeg'
                && $args['CacheControl'] === self::CACHE_CONTROL
                && $args['MetadataDirective'] === 'REPLACE'
        ));

        $this->bindFakeS3($client);

        $this->artisan('media:fix-remote-metadata')
            ->expectsOutputToContain('controllati: 2 — corretti: 1 — assenti: 0')
            ->assertSuccessful();
    }

    #[Test]
    public function il_dry_run_elenca_senza_scrivere(): void
    {
        $media = $this->makeMedia('rotto.jpg');

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('headObject')
            ->andReturn(['ContentType' => 'binary/octet-stream']);
        $client->shouldNotReceive('copyObject');

        $this->bindFakeS3($client);

        $this->artisan('media:fix-remote-metadata --dry-run')
            ->expectsOutputToContain($media->getPathRelativeToRoot())
            ->expectsOutputToContain('da correggere: 1')
            ->assertSuccessful();
    }

    #[Test]
    public function l_oggetto_assente_viene_contato_e_saltato(): void
    {
        $this->makeMedia('sparito.jpg');

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('headObject')->andThrow(new \RuntimeException('404'));
        $client->shouldNotReceive('copyObject');

        $this->bindFakeS3($client);

        $this->artisan('media:fix-remote-metadata')
            ->expectsOutputToContain('assenti: 1')
            ->assertSuccessful();
    }

    #[Test]
    public function le_conversioni_generate_sono_incluse_nel_controllo(): void
    {
        $media = $this->makeMedia('foto.jpg', ['thumb' => true, 'card' => false]);

        $chiaveAttese = [
            $media->getPathRelativeToRoot(),
            $media->getPathRelativeToRoot('thumb'),
        ];

        $client = Mockery::mock(S3Client::class);
        $client->shouldReceive('headObject')
            ->times(2)
            ->with(Mockery::on(fn (array $args) => in_array($args['Key'], $chiaveAttese, true)))
            ->andReturn(['ContentType' => 'image/jpeg', 'CacheControl' => self::CACHE_CONTROL]);

        $this->bindFakeS3($client);

        $this->artisan('media:fix-remote-metadata')
            ->expectsOutputToContain('controllati: 2')
            ->assertSuccessful();
    }

    #[Test]
    public function il_filtro_since_esclude_i_media_vecchi(): void
    {
        $vecchio = $this->makeMedia('vecchio.jpg');
        Media::whereKey($vecchio->id)->update(['updated_at' => now()->subMonths(2)]);

        $client = Mockery::mock(S3Client::class);
        $client->shouldNotReceive('headObject');

        $this->bindFakeS3($client);

        $this->artisan('media:fix-remote-metadata --since="3 days ago"')
            ->expectsOutputToContain('controllati: 0')
            ->assertSuccessful();
    }

    private function makeMedia(string $fileName, array $conversions = []): Media
    {
        $media = new Media;
        $media->model_type = 'App\\Models\\Team';
        $media->model_id = 1;
        $media->collection_name = 'default';
        $media->name = pathinfo($fileName, PATHINFO_FILENAME);
        $media->file_name = $fileName;
        $media->mime_type = 'image/jpeg';
        $media->disk = 's3';
        $media->size = 1000;
        $media->manipulations = [];
        $media->custom_properties = [];
        $media->generated_conversions = $conversions;
        $media->responsive_images = [];
        $media->save();

        return $media->fresh();
    }

    private function bindFakeS3(S3Client $client): void
    {
        config([
            'media-library.disk_name' => 's3',
            'filesystems.disks.s3.driver' => 's3',
            'filesystems.disks.s3.bucket' => 'bucket-test',
        ]);

        $adapter = Mockery::mock(AwsS3V3Adapter::class);
        $adapter->shouldReceive('getClient')->andReturn($client);

        Storage::shouldReceive('disk')->with('s3')->andReturn($adapter);
    }
}
