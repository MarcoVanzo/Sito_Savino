<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\Page;
use App\Models\Player;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Archivio fotografico del sito, con il filtro per atleta.
 */
class GalleryController extends Controller
{
    /**
     * Foto incluse nel payload iniziale della gallery. Il resto dell'archivio
     * viene caricato dopo, da `galleryData()`.
     */
    private const GALLERY_INITIAL_CHUNK = 120;

    public function gallery()
    {
        return $this->renderGallery();
    }

    public function galleryAtleta(string $slug)
    {
        $id = explode('-', $slug)[0];
        $player = Player::findOrFail($id);

        return $this->renderGallery($player);
    }

    private function renderGallery(?Player $playerFilter = null)
    {
        $locale = app()->getLocale();
        $page = Cache::remember("public:page:gallery:{$locale}", now()->addMinutes(30), function () {
            return Page::where('slug', 'gallery')->published()->first();
        });

        $media = $this->galleryMedia($playerFilter);

        // Get all players that have at least one gallery image for the filter dropdown
        $athletes = Cache::remember("public:gallery_athletes:{$locale}", now()->addMinutes(30), function () {
            return Player::whereHas('galleryImages', fn ($q) => $q->where('gallery_images.is_active', true))
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->orderBy('id')
                ->get()->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->full_name,
                    'slug' => $p->id.'-'.Str::slug($p->full_name),
                ])->toArray();
        });

        return Inertia::render('Public/Gallery', [
            'page' => $page,
            // Solo il primo blocco: l'archivio è di ~900 foto e serializzarlo
            // tutto portava la pagina a mezzo megabyte di HTML. Il resto arriva
            // da /gallery/data appena la pagina è interattiva, così i filtri
            // continuano a lavorare sull'archivio completo.
            'media' => array_slice($media, 0, self::GALLERY_INITIAL_CHUNK),
            'mediaTotal' => count($media),
            'athletes' => $athletes,
            'currentAthlete' => $playerFilter ? [
                'id' => $playerFilter->id,
                'name' => $playerFilter->full_name,
                'slug' => $playerFilter->id.'-'.Str::slug($playerFilter->full_name),
            ] : null,
        ]);
    }

    /**
     * Archivio completo della gallery, normalizzato per il front-end.
     *
     * @return list<array<string, mixed>>
     */
    private function galleryMedia(?Player $playerFilter = null): array
    {
        $locale = app()->getLocale();

        $cacheKey = $playerFilter
            ? "public:gallery_images:player_{$playerFilter->id}:{$locale}"
            : "public:gallery_images:{$locale}";

        return Cache::remember($cacheKey, now()->addMinutes(30), function () use ($playerFilter, $locale) {
            $query = GalleryImage::active()->ordered()
                ->with(['media', 'players:id,first_name,last_name', 'galleryEvent:id,title,event_date']);

            if ($playerFilter) {
                $query->whereHas('players', function ($q) use ($playerFilter) {
                    $q->where('players.id', $playerFilter->id);
                });
            }

            return $query->get()
                ->map(function ($img) use ($locale) {
                    $decodeTitle = function ($text) use ($locale) {
                        if (! is_string($text) || ! str_starts_with($text, '{"it":')) {
                            return $text;
                        }
                        $decoded = json_decode($text, true);
                        if (is_array($decoded) && (isset($decoded[$locale]) || isset($decoded['it']))) {
                            return $decoded[$locale] ?? $decoded['it'];
                        }
                        // Attempt to fix truncated JSON
                        $decoded = json_decode($text.'"}', true);
                        if (is_array($decoded) && (isset($decoded[$locale]) || isset($decoded['it']))) {
                            return $decoded[$locale] ?? $decoded['it'];
                        }
                        $decoded = json_decode($text.'}', true);
                        if (is_array($decoded) && (isset($decoded[$locale]) || isset($decoded['it']))) {
                            return $decoded[$locale] ?? $decoded['it'];
                        }

                        return $text;
                    };

                    $altText = $decodeTitle($img->title ?? __('Immagine Galleria'));
                    $eventName = $decodeTitle($img->galleryEvent?->title);

                    return [
                        'id' => $img->id,
                        'url' => $img->getFirstMediaUrl('gallery', 'lightbox') ?: $img->getFirstMediaUrl('gallery'),
                        'thumb' => $img->getFirstMediaUrl('gallery', 'thumb') ?: $img->getFirstMediaUrl('gallery'),
                        'alt' => mb_substr($altText, 0, 255),
                        'category' => $img->category ?? 'Partite',
                        'tags' => $img->players->map(fn ($p) => $p->full_name)->values()->toArray(),
                        'event_name' => $eventName,
                        // La pagina raggruppa le foto per album: senza l'identificativo
                        // dovrebbe fidarsi del titolo, e due eventi omonimi in stagioni
                        // diverse finirebbero nella stessa cartella.
                        'event_id' => $img->gallery_event_id,
                        'event_date' => $img->galleryEvent?->event_date?->toDateString(),
                    ];
                })
                ->toArray();
        });
    }

    /**
     * Archivio completo della gallery in JSON, per il caricamento differito.
     */
    public function galleryData(?string $slug = null)
    {
        $playerFilter = null;

        if ($slug) {
            $playerId = (int) explode('-', $slug)[0];
            $playerFilter = Player::find($playerId);

            if (! $playerFilter) {
                abort(404);
            }
        }

        return response()->json([
            'media' => $this->galleryMedia($playerFilter),
        ]);
    }
}
