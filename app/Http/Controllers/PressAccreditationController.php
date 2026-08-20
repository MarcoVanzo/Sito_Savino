<?php

namespace App\Http\Controllers;

use App\Http\Requests\PressAccreditationRequest;
use App\Models\ContactMessage;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Richieste di accredito stampa inviate dalla pagina Comunicazione.
 *
 * Prima la pagina spiegava soltanto di scrivere una mail: la richiesta arrivava
 * in forma libera, spesso incompleta, e non restava traccia in nessun elenco.
 *
 * Le richieste si salvano come messaggi di contatto con oggetto "Stampa /
 * Media": è l'oggetto su cui filtra "Richieste Accrediti" nel pannello, che
 * esisteva già e finalmente riceve qualcosa. I campi propri dell'accredito
 * (testata, ruolo, gara) stanno in `extra_data`.
 */
class PressAccreditationController extends Controller
{
    /**
     * Oggetto su cui filtra App\Filament\Resources\PressAccreditationResource.
     * Cambiarlo qui senza cambiarlo là svuota l'elenco nel pannello.
     */
    public const SUBJECT = 'Stampa / Media';

    public function submit(PressAccreditationRequest $request)
    {
        $validated = $request->validated();

        // Anti-spam: se il honeypot è compilato, è un bot.
        if (! empty($validated['honeypot'])) {
            // Simula successo per non rivelare il meccanismo.
            return back()->with('success', __('messages.contact.success_bot'));
        }

        $nome = trim($validated['first_name'].' '.$validated['last_name']);

        $dettagli = [
            'outlet' => $validated['outlet'],
            'role' => $validated['role'],
            'match' => $validated['match'],
            'phone' => $validated['phone'],
            'notes' => $validated['notes'] ?? null,
        ];

        Log::channel('daily')->info('Nuova richiesta di accredito stampa', [
            'name' => $nome,
            'email' => $validated['email'],
            'outlet' => $validated['outlet'],
        ]);

        // Il salvataggio e l'invio non si bloccano a vicenda: una richiesta
        // registrata e non spedita si recupera dal pannello, una spedita e non
        // registrata arriva comunque a chi la deve leggere.
        try {
            ContactMessage::create([
                'name' => $nome,
                'email' => $validated['email'],
                'subject' => self::SUBJECT,
                'message' => $this->corpoMessaggio($dettagli),
                'status' => 'unread',
                'extra_data' => $dettagli,
            ]);
        } catch (\Throwable $e) {
            Log::error('Errore salvataggio richiesta accredito nel database', ['error' => $e->getMessage()]);
        }

        try {
            // SiteSetting::get() indicizza sulla sola colonna `key`: il gruppo
            // serve a raccogliere le impostazioni nel pannello, non a comporre
            // la chiave. Con "contact.press_email" si prendeva sempre il
            // mittente di sistema.
            $destinatario = SiteSetting::get('press_email')
                ?: SiteSetting::get('media_email')
                ?: config('mail.from.address');

            Mail::raw(
                "Richiesta di accredito stampa\n\n"
                ."Nome: {$nome}\n"
                ."Email: {$validated['email']}\n"
                ."Telefono: {$validated['phone']}\n\n"
                .$this->corpoMessaggio($dettagli),
                function ($mail) use ($validated, $nome, $destinatario) {
                    $mail->to($destinatario)
                        ->replyTo($validated['email'], $nome)
                        ->subject('Accredito stampa: '.$validated['outlet']);
                }
            );
        } catch (\Throwable $e) {
            Log::error('Errore invio email accredito stampa', ['error' => $e->getMessage()]);
        }

        return back()->with('success', __('messages.press_accreditation.success'));
    }

    /**
     * @param  array<string, string|null>  $dettagli
     */
    private function corpoMessaggio(array $dettagli): string
    {
        $righe = [
            "Testata: {$dettagli['outlet']}",
            "Ruolo: {$dettagli['role']}",
            "Gara: {$dettagli['match']}",
        ];

        if (! empty($dettagli['notes'])) {
            $righe[] = '';
            $righe[] = "Note:\n{$dettagli['notes']}";
        }

        return implode("\n", $righe);
    }
}
