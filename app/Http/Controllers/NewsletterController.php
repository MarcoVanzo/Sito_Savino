<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterRequest;
use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

class NewsletterController extends Controller
{
    public function subscribe(NewsletterRequest $request)
    {
        $validated = $request->validated();

        // Cerca qualsiasi record con questa email (attivo O disiscritto)
        $existing = NewsletterSubscriber::where('email', $validated['email'])->first();

        if ($existing) {
            // Se è attivo → già iscritto
            if ($existing->unsubscribed_at === null) {
                // Self-healing: se per qualche motivo era fallita la sincronizzazione, la riavviamo
                if (! $existing->synced_to_ac) {
                    SyncNewsletterToActiveCampaign::dispatch($existing);
                }

                return back()->with('newsletter_info', __('messages.newsletter.already_subscribed'));
            }

            // Se era disiscritto → riattiva
            $existing->update([
                'unsubscribed_at' => null,
                'first_name' => $validated['first_name'] ?? $existing->first_name,
                'ip_address' => $request->ip(),
                'synced_to_ac' => false,
                // Manteniamo il vecchio ac_contact_id se presente
                'subscribed_at' => now(),
            ]);

            Log::channel('daily')->info('Re-iscrizione newsletter', [
                'email' => $existing->email,
                'ip' => $request->ip(),
            ]);

            SyncNewsletterToActiveCampaign::dispatch($existing);

            return back()->with('success', __('messages.newsletter.success'));
        }

        // Nuovo iscritto — try/catch per race condition su UNIQUE constraint
        try {
            $subscriber = NewsletterSubscriber::create([
                'email' => $validated['email'],
                'first_name' => $validated['first_name'] ?? null,
                'ip_address' => $request->ip(),
                'source' => 'website',
                'subscribed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Race condition: un'altra request ha inserito la stessa email tra il WHERE e il CREATE
            return back()->with('newsletter_info', __('messages.newsletter.already_subscribed'));
        }

        Log::channel('daily')->info('Nuova iscrizione newsletter', [
            'email' => $subscriber->email,
            'ip' => $subscriber->ip_address,
        ]);

        SyncNewsletterToActiveCampaign::dispatch($subscriber);

        return back()->with('success', __('messages.newsletter.success'));
    }
}
