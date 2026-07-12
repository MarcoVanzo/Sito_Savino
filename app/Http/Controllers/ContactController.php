<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Models\ContactMessage;
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
            return back()->with('success', __('messages.contact.success_bot'));
        }

        // Log del messaggio (sempre, anche se mail fallisce)
        Log::channel('daily')->info('Nuovo messaggio contatti', [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'] ?? '(nessun oggetto)',
        ]);

        // Salvataggio nel database
        try {
            ContactMessage::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'subject' => $validated['subject'] ?? null,
                'message' => $validated['message'],
                'status' => 'unread',
            ]);
        } catch (\Throwable $e) {
            Log::error('Errore salvataggio messaggio contatti nel database', ['error' => $e->getMessage()]);
            // Non blocchiamo l'utente se il salvataggio fallisce, procediamo con l'invio email
        }

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
            // Non blocchiamo l'utente se la mail fallisce, il log e il DB ci sono
        }

        return back()->with('success', __('messages.contact.success_human'));
    }
}
