<?php

namespace Tests\Unit;

use Illuminate\Contracts\Queue\ShouldQueue;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Un job con un timeout più lungo del `retry_after` della coda viene dato per
 * perso mentre è ancora in corsa e ripreso da un altro processo: due import
 * della gallery in parallelo sugli stessi file. Con un solo worker il difetto
 * non si manifesta, ed è per questo che serve un test e non un'osservazione.
 */
class RetryAfterDellaCodaTest extends TestCase
{
    #[Test]
    public function il_retry_after_supera_il_timeout_di_ogni_job(): void
    {
        $retryAfter = (int) config('queue.connections.database.retry_after');
        $jobs = 0;

        foreach (Finder::create()->files()->in(app_path('Jobs'))->name('*.php') as $file) {
            $classe = 'App\\Jobs\\'.$file->getBasename('.php');

            if (! class_exists($classe) || ! is_subclass_of($classe, ShouldQueue::class)) {
                continue;
            }

            $jobs++;
            $proprieta = (new ReflectionClass($classe))->getDefaultProperties();
            $timeout = (int) ($proprieta['timeout'] ?? 60);

            $this->assertGreaterThan(
                $timeout,
                $retryAfter,
                "{$classe} ha un timeout di {$timeout} s, non inferiore al retry_after della coda ({$retryAfter} s): "
                .'alzare DB_QUEUE_RETRY_AFTER in config/queue.php.'
            );
        }

        $this->assertGreaterThan(0, $jobs, 'Nessun job trovato in app/Jobs: il test non sta controllando niente.');
    }
}
