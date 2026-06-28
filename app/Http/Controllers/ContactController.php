<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactController extends Controller
{
    public function submit(ContactRequest $request)
    {
        $validated = $request->validated();

        // Anti-spam: se il honeypot è compilato, è un bot
        if (! empty($validated['honeypot'])) {
            // Simula successo per non rivelare il meccanismo
            return back()->with('success', 'Messaggio inviato con successo!');
        }

        // Log del messaggio (sempre, anche se mail fallisce)
        Log::channel('daily')->info('Nuovo messaggio contatti', [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'] ?? '(nessun oggetto)',
        ]);

        // Invio email
        try {
            Mail::raw(
                "Nome: {$validated['name']}\nEmail: {$validated['email']}\nOggetto: ".($validated['subject'] ?? '(nessuno)')."\n\nMessaggio:\n{$validated['message']}",
                function ($mail) use ($validated) {
                    $mail->to(config('mail.from.address'))
                        ->replyTo($validated['email'], $validated['name'])
                        ->subject('Contatto dal sito: '.($validated['subject'] ?? 'Messaggio'));
                }
            );
        } catch (\Throwable $e) {
            Log::error('Errore invio email contatti', ['error' => $e->getMessage()]);
            // Non blocchiamo l'utente se la mail fallisce, il log c'è
        }

        return back()->with('success', 'Messaggio inviato con successo! Ti risponderemo il prima possibile.');
    }
}
