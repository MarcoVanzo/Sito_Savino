<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Ripara Content-Type e Cache-Control degli oggetti su DigitalOcean Spaces.
 *
 * Laravel carica i file da un percorso temporaneo senza estensione, quindi il
 * rilevatore di Flysystem non riconosce il tipo e ogni immagine finisce su
 * Spaces come `binary/octet-stream`, senza Cache-Control. Il browser le mostra
 * lo stesso, ma il tipo sbagliato rompe le anteprime che si fidano dell'header
 * e la mancanza di cache fa riscaricare foto che non cambiano mai.
 *
 * Il comando è idempotente: tocca solo gli oggetti i cui metadati non sono già
 * corretti, e può essere rilanciato senza effetti collaterali.
 */
class FixRemoteMediaMetadata extends Command
{
    private const ESITO_ASSENTE = 'assente';

    private const ESITO_CORRETTO = 'corretto';

    private const ESITO_GIA_A_POSTO = 'gia-a-posto';

    protected $signature = 'media:fix-remote-metadata
                            {--since= : Considera solo i media aggiornati dopo questa data (es. "2 days ago")}
                            {--dry-run : Elenca le correzioni senza applicarle}';

    protected $description = 'Corregge Content-Type e Cache-Control dei media su Spaces';

    private const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    public function handle(): int
    {
        $disk = config('media-library.disk_name');

        if (config("filesystems.disks.{$disk}.driver") !== 's3') {
            $this->warn("Il disco dei media ({$disk}) non è S3: niente da correggere.");

            return self::SUCCESS;
        }

        $filesystem = Storage::disk($disk);

        if (! $filesystem instanceof AwsS3V3Adapter) {
            $this->warn("Il disco {$disk} non espone un client S3: niente da correggere.");

            return self::SUCCESS;
        }

        $client = $filesystem->getClient();
        $bucket = config("filesystems.disks.{$disk}.bucket");
        $dryRun = (bool) $this->option('dry-run');

        $query = Media::query()->orderBy('id');

        if ($since = $this->option('since')) {
            $query->where('updated_at', '>=', new \DateTimeImmutable($since));
        }

        $checked = 0;
        $fixed = 0;
        $missing = 0;

        $query->chunkById(200, function ($media) use ($client, $bucket, $dryRun, &$checked, &$fixed, &$missing) {
            foreach ($media as $item) {
                foreach ($this->objectKeysFor($item) as $key) {
                    $checked++;

                    match ($this->correggiOggetto($client, $bucket, $key, $item->mime_type, $dryRun)) {
                        self::ESITO_ASSENTE => $missing++,
                        self::ESITO_CORRETTO => $fixed++,
                        default => null,
                    };
                }
            }
        });

        $verbo = $dryRun ? 'da correggere' : 'corretti';
        $this->info("Oggetti controllati: {$checked} — {$verbo}: {$fixed} — assenti: {$missing}");

        return self::SUCCESS;
    }

    /**
     * Riscrive tipo e cache di un oggetto, se non sono gia' quelli giusti.
     *
     * Un oggetto puo' benissimo non esserci — conversione mai generata, file
     * tolto a mano dal bucket — e non e' compito di questo comando ricrearlo.
     */
    private function correggiOggetto(S3Client $client, string $bucket, string $key, ?string $mimeType, bool $dryRun): string
    {
        try {
            $head = $client->headObject(['Bucket' => $bucket, 'Key' => $key]);
        } catch (\Throwable) {
            return self::ESITO_ASSENTE;
        }

        $contentType = $head['ContentType'] ?? '';
        $cacheControl = $head['CacheControl'] ?? '';

        if ($contentType === $mimeType && $cacheControl === self::CACHE_CONTROL) {
            return self::ESITO_GIA_A_POSTO;
        }

        if ($dryRun) {
            $this->line("  da correggere: {$key} ({$contentType})");

            return self::ESITO_CORRETTO;
        }

        $client->copyObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'CopySource' => rawurlencode($bucket.'/'.$key),
            'MetadataDirective' => 'REPLACE',
            'ContentType' => $mimeType,
            'CacheControl' => self::CACHE_CONTROL,
            'ACL' => 'public-read',
        ]);

        return self::ESITO_CORRETTO;
    }

    /**
     * Chiavi S3 dell'originale e delle conversioni già generate.
     *
     * @return list<string>
     */
    private function objectKeysFor(Media $media): array
    {
        // I percorsi li conosce il PathGenerator di media-library: comporli a
        // mano significherebbe indovinare l'estensione delle conversioni.
        $keys = [$media->getPathRelativeToRoot()];

        foreach (($media->generated_conversions ?? []) as $conversion => $generated) {
            if ($generated) {
                $keys[] = $media->getPathRelativeToRoot($conversion);
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }
}
