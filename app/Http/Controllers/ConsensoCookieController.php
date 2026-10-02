<?php

namespace App\Http\Controllers;

use App\Models\ConsensoCookie;
use App\Models\VersioneTestiConsenso;
use App\Services\CatenaDeiConsensi;
use App\Support\TestiDelConsenso;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Registra le scelte fatte sul banner dei cookie.
 *
 * Il banner continua a governare il sito dal `localStorage` del visitatore:
 * questa chiamata non decide niente di ciò che si carica, serve solo a lasciare
 * la prova di che cosa è stato chiesto e che cosa è stato risposto. Se fallisce,
 * il sito si comporta comunque come il visitatore ha scelto — il frontend
 * riprova alla visita successiva.
 */
class ConsensoCookieController extends Controller
{
    public function registra(Request $request): JsonResponse
    {
        // Validazione a mano invece di `$request->validate()`: il sito rende in
        // JSON solo le rotte `api/*` (vedi bootstrap/app.php), perché i moduli
        // Inertia si aspettano il redirect con gli errori in sessione. Questa
        // chiamata invece arriva da axios e una risposta 302 la lascerebbe
        // senza risposta leggibile.
        $validatore = Validator::make($request->all(), [
            'statistiche' => ['required', 'boolean'],
            'marketing' => ['required', 'boolean'],
            // Chi torna e cambia idea manda il riferimento che ha già.
            'riferimento' => ['nullable', 'uuid'],
        ]);

        if ($validatore->fails()) {
            return response()->json([
                'message' => $validatore->errors()->first(),
                'errors' => $validatore->errors(),
            ], 422);
        }

        $dati = $validatore->validated();
        $primaVolta = empty($dati['riferimento']);

        // Che cosa diceva il sito quando il visitatore ha scelto: banner,
        // pannello, Cookie Policy ed elenco dei cookie, archiviati una volta
        // per versione (EDPB 05/2020 §108). Il consenso porta l'impronta.
        $improntaTesti = VersioneTestiConsenso::archivia(
            VersioneTestiConsenso::TIPO_COOKIE,
            TestiDelConsenso::perICookie(),
        );

        $consenso = CatenaDeiConsensi::registra([
            'riferimento' => $dati['riferimento'] ?? ConsensoCookie::nuovoRiferimento(),
            'statistiche' => (bool) $dati['statistiche'],
            'marketing' => (bool) $dati['marketing'],
            'azione' => ConsensoCookie::azionePer((bool) $dati['statistiche'], (bool) $dati['marketing'], $primaVolta),
            'versione' => ConsensoCookie::VERSIONE,
            'impronta_testi' => $improntaTesti,
            'locale' => app()->getLocale(),
            // La colonna sta a 255: un user agent più lungo di così è
            // un'anomalia, e troncarlo vale più che rifiutare il consenso.
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'impronta_ip' => ConsensoCookie::improntaDi($request->ip()),
        ]);

        return response()->json([
            'riferimento' => $consenso->riferimento,
            'registrato_il' => $consenso->created_at->toIso8601String(),
            'versione' => $consenso->versione,
        ]);
    }
}
