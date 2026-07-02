<?php

namespace App\Filament\Resources\GalleryEventResource\Pages;

use App\Filament\Resources\GalleryEventResource;
use App\Jobs\AnalyzeGalleryImageJob;
use App\Models\GalleryImage;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class ListGalleryEvents extends ListRecords
{
    protected static string $resource = GalleryEventResource::class;

    protected static string $view = 'filament.pages.list-gallery-events';

    /**
     * Cache key scoped per utente per evitare race condition multi-admin.
     */
    protected function batchCacheKey(): string
    {
        return 'gallery_ai_batch_id_user_' . auth()->id();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('analyzeAll')
                ->label('Analizza Tutti con AI')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Analizza tutte le foto')
                ->modalDescription('Verranno analizzate tutte le foto di tutti gli eventi con l\'Intelligenza Artificiale. L\'operazione potrebbe richiedere tempo.')
                ->modalSubmitActionLabel('Avvia Analisi')
                ->action(function () {
                    // Protezione contro doppio lancio
                    $existingBatchId = Cache::get($this->batchCacheKey());
                    if ($existingBatchId) {
                        $existingBatch = Bus::findBatch($existingBatchId);
                        if ($existingBatch && ! $existingBatch->finished() && ! $existingBatch->cancelled()) {
                            Notification::make()
                                ->title('Analisi già in corso')
                                ->body('C\'è già un\'analisi in corso. Attendi il completamento o annullala prima di avviarne una nuova.')
                                ->danger()
                                ->send();

                            return;
                        }
                    }

                    // Conta prima, senza caricare tutto in memoria
                    $totalCount = GalleryImage::whereHas('media')->count();

                    if ($totalCount === 0) {
                        Notification::make()
                            ->title('Nessuna foto trovata')
                            ->body('Non ci sono foto da analizzare.')
                            ->warning()
                            ->send();

                        return;
                    }

                    // Crea i job in chunk per evitare OOM
                    $jobs = [];
                    GalleryImage::whereHas('media')->chunkById(200, function ($images) use (&$jobs) {
                        foreach ($images as $image) {
                            $jobs[] = new AnalyzeGalleryImageJob($image);
                        }
                    });

                    $batch = Bus::batch($jobs)
                        ->name('Analisi AI Gallery - Tutti gli eventi')
                        ->allowFailures()
                        ->dispatch();

                    Cache::put($this->batchCacheKey(), $batch->id, now()->addHours(6));

                    // Notifica il frontend di avviare il polling
                    $this->dispatch('batch-started');

                    Notification::make()
                        ->title('Analisi AI avviata')
                        ->body("Analisi in corso per {$totalCount} foto di tutti gli eventi.")
                        ->success()
                        ->send();
                }),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Returns batch progress data for the view.
     */
    public function getBatchProgress(): ?array
    {
        $batchId = Cache::get($this->batchCacheKey());

        if (! $batchId) {
            return null;
        }

        $batch = Bus::findBatch($batchId);

        if (! $batch) {
            Cache::forget($this->batchCacheKey());

            return null;
        }

        $data = [
            'id' => $batch->id,
            'name' => $batch->name,
            'totalJobs' => $batch->totalJobs,
            'processedJobs' => $batch->processedJobs(),
            'failedJobs' => $batch->failedJobs,
            'pendingJobs' => $batch->pendingJobs,
            'progress' => $batch->progress(),
            'finished' => $batch->finished(),
            'cancelled' => $batch->cancelled(),
            'createdAt' => $batch->createdAt->format('H:i:s'),
        ];

        // If finished, clean up cache after a delay so user sees the 100%
        if ($batch->finished()) {
            Cache::put($this->batchCacheKey(), $batchId, now()->addMinutes(5));
        }

        return $data;
    }

    /**
     * Dismiss/clear the batch progress bar.
     */
    public function dismissBatch(): void
    {
        Cache::forget($this->batchCacheKey());
    }

    /**
     * Cancel the running batch.
     */
    public function cancelBatch(): void
    {
        $batchId = Cache::get($this->batchCacheKey());

        if ($batchId) {
            $batch = Bus::findBatch($batchId);
            if ($batch && ! $batch->finished()) {
                $batch->cancel();
            }
        }
    }
}
