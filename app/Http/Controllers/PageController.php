<?php

namespace App\Http\Controllers;

use App\Enums\PostStatus;
use App\Models\Page;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class PageController extends Controller
{
    /**
     * Template consentiti per il rendering Inertia.
     * Previene code injection da valori malevoli nel database.
     * Deve corrispondere ai componenti Vue in resources/js/Pages/Public/.
     */
    private const ALLOWED_TEMPLATES = [
        'Public/ContentPage',
        'Public/Stagione',
        'Public/Home',
        'Public/Societa',
        'Public/Roster',
        'Public/Shop',
        'Public/ShopCheckout',
        'Public/Ticketing',
        'Public/Sponsor',
        'Public/Youth',
        'Public/SummerCamp',
        'Public/Sociale',
        'Public/Comunicazione',
        'Public/Risultati',
        'Public/Gallery',
        'Public/Staff',
        'Public/Contatti',
    ];

    public function show($slug)
    {
        $page = Cache::remember('public:page:' . $slug, now()->addMinutes(30), function () use ($slug) {
            return Page::where('slug', $slug)
                ->where('status', PostStatus::Published)
                ->first();
        });

        if (!$page) {
            abort(404);
        }

        // Se il template è nella whitelist, usalo. Altrimenti renderizza
        // la pagina generica con un layout che mostra il contenuto della page.
        $template = $page->template && in_array($page->template, self::ALLOWED_TEMPLATES)
            ? $page->template
            : 'Public/ContentPage'; // Fallback generico che renderizza il contenuto

        return Inertia::render($template, [
            'page' => $page,
        ]);
    }
}
