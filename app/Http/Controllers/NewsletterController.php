<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterRequest;
use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

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

    /**
     * Pagina di conferma della disiscrizione.
     *
     * La disiscrizione vera avviene in POST: un GET la eseguirebbe anche
     * quando il link viene aperto dai prefetcher dei client di posta, che
     * toglierebbero dalla lista chi non ha cliccato nulla.
     */
    public function showUnsubscribe(NewsletterSubscriber $subscriber): Response
    {
        return Inertia::render('Public/NewsletterUnsubscribe', [
            'email' => $subscriber->email,
            'alreadyUnsubscribed' => ! $subscriber->isSubscribed(),
            // L'URL firmato va ripassato alla vista: la firma vale per un URL
            // preciso e il form deve inviare esattamente quello.
            'confirmUrl' => URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $subscriber->id]),
        ]);
    }

    /**
     * Esegue la disiscrizione richiesta dal link firmato.
     */
    public function unsubscribe(NewsletterSubscriber $subscriber): RedirectResponse
    {
        $subscriber->unsubscribe();

        return redirect()
            ->to($subscriber->unsubscribeUrl())
            ->with('success', __('messages.newsletter.unsubscribed'));
    }
}
