<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class ContentDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── SOCIETÀ ──────────────────────────────────────────────────
        Page::where('slug', 'societa')->update(['content_data' => [
            'hero' => [
                'badge' => 'Dal 1982',
                'subtitle' => 'Oltre quarant\'anni di passione, tradizione e successi nel panorama della pallavolo femminile italiana. Una storia costruita con determinazione e visione.',
            ],
            'storia' => [
                'badge' => 'La Nostra Storia',
                'title' => 'Una Tradizione di Eccellenza',
                'paragraphs' => [
                    'Fondata nel 1982 a Scandicci, la Savino Del Bene Volley è diventata una delle realtà più importanti della pallavolo femminile italiana. Dalle origini nel campionato regionale alla Serie A1, il percorso del club è stato segnato da una crescita costante.',
                    'Con la partnership strategica del Gruppo Savino Del Bene, il club ha raggiunto traguardi storici: la Finale Scudetto, la partecipazione alla CEV Champions League e la conquista di un posto stabile tra le migliori squadre d\'Europa.',
                    'Oggi la Savino Del Bene Volley rappresenta un modello di gestione sportiva, con un settore giovanile d\'eccellenza, un impegno sociale concreto e una visione proiettata verso il futuro.',
                ],
                'highlight_value' => '40+',
                'highlight_label' => 'Anni di Storia',
            ],
            'organigramma' => [
                'badge' => 'Organigramma',
                'title' => 'Il Nostro Team Dirigenziale',
                'roles' => [
                    ['title' => 'Presidente', 'name' => 'Presidenza', 'desc' => 'Guida strategica e visione del club'],
                    ['title' => 'Direttore Generale', 'name' => 'Direzione', 'desc' => 'Gestione operativa e coordinamento'],
                    ['title' => 'Direttore Sportivo', 'name' => 'Area Tecnica', 'desc' => 'Pianificazione sportiva e roster'],
                    ['title' => 'Head Coach', 'name' => 'Staff Tecnico', 'desc' => 'Guida tecnica della prima squadra'],
                ],
            ],
            'palazzetto' => [
                'badge' => 'La Nostra Casa',
                'title' => 'PalaBigmat',
                'description' => 'Il PalaBigmat di Firenze è la casa della Savino Del Bene Volley. Con una capienza di oltre 4.000 posti, l\'impianto offre un\'esperienza unica per tifosi e appassionati di pallavolo.',
                'stats' => [
                    ['value' => '4.000+', 'label' => 'Posti a Sedere'],
                    ['value' => 'Serie A1', 'label' => 'Omologazione'],
                ],
                'address' => 'Via del Cavallaccio, 18/20/22/24 — 50142 Firenze (FI)',
            ],
            'contatti' => [
                'badge' => 'Resta in Contatto',
                'title' => 'Contattaci',
                'items' => [
                    ['title' => 'Email', 'value' => 'info@savinodelbenevolley.com'],
                    ['title' => 'Telefono', 'value' => '+39 055 XXX XXXX'],
                    ['title' => 'Sede', 'value' => 'Scandicci (FI), Toscana'],
                ],
            ],
        ]]);

        // Le altre pagine leggono i contenuti dal file dati condiviso, nella
        // stessa struttura piatta usata dai template e dai form del pannello.
        foreach (require database_path('data/page_content_data.php') as $slug => $contentData) {
            Page::where('slug', $slug)->update(['content_data' => $contentData]);
        }

    }
}
