<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsletterRequest;
use App\Jobs\SyncNewsletterToActiveCampaign;
use App\Mail\ConfermaIscrizioneNewsletter;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    /**
     * Richiesta d'iscrizione: registra l'indirizzo e manda il link di
     * conferma. L'iscrizione vale — e il contatto arriva ad ActiveCampaign —
     * solo dopo il click (doppio opt-in, NewsletterSubscriber::conferma).
     *
     * Nel log non finiscono né l'email né l'indirizzo IP: il log non ha la
     * conservazione dell'archivio, e fino al 25 settembre 2026 teneva
     * entrambi per ogni iscrizione.
     */
    public function subscribe(NewsletterRequest $request)
    {
        $validated = $request->validated();

        // Cerca qualsiasi record con questa email (attivo O disiscritto)
        $existing = NewsletterSubscriber::where('email', $validated['email'])->first();

        if ($existing) {
            // Attivo e confermato → già iscritto
            if ($existing->isSubscribed() && $existing->haConfermato()) {
                // Self-healing: se per qualche motivo era fallita la sincronizzazione, la riavviamo
                if (! $existing->synced_to_ac) {
                    SyncNewsletterToActiveCampaign::dispatch($existing);
                }

                return back()->with('newsletter_info', __('messages.newsletter.already_subscribed'));
            }

            // Disiscritto, o mai confermato: si riparte dalla richiesta, e la
            // conferma va chiesta di nuovo. Un "sì" di mesi fa non vale per
            // una lista da cui la persona era uscita.
            $existing->update([
                'unsubscribed_at' => null,
                'confermato_il' => null,
                'first_name' => $validated['first_name'] ?? $existing->first_name,
                'ip_address' => $request->ip(),
                'synced_to_ac' => false,
                // Manteniamo il vecchio ac_contact_id se presente
                'subscribed_at' => now(),
            ]);

            Log::channel('daily')->info('Nuova richiesta di iscrizione newsletter', [
                'subscriber_id' => $existing->id,
            ]);

            $this->mandaLaConferma($existing);

            return back()->with('success', __('messages.newsletter.confirm_sent'));
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

        Log::channel('daily')->info('Nuova richiesta di iscrizione newsletter', [
            'subscriber_id' => $subscriber->id,
        ]);

        $this->mandaLaConferma($subscriber);

        return back()->with('success', __('messages.newsletter.confirm_sent'));
    }

    /**
     * Pagina della conferma d'iscrizione, dal link ricevuto per email.
     *
     * Come per la disiscrizione, la conferma vera avviene in POST: un GET che
     * conferma verrebbe eseguito anche dai filtri dei client di posta che
     * aprono i link per controllarli, e l'iscrizione risulterebbe voluta da
     * chi non ha cliccato nulla.
     */
    public function showConferma(NewsletterSubscriber $subscriber): Response
    {
        return Inertia::render('Public/NewsletterConferma', [
            'email' => $subscriber->email,
            'giaConfermata' => $subscriber->haConfermato() && $subscriber->isSubscribed(),
            // Stesso indirizzo firmato del GET, verso la POST: la firma vale
            // per un URL preciso (e la sua scadenza viaggia con lui).
            'confermaUrl' => URL::temporarySignedRoute(
                $this->prefissoRotta().'newsletter.conferma',
                now()->addHour(),
                ['subscriber' => $subscriber->id],
            ),
        ]);
    }

    public function conferma(NewsletterSubscriber $subscriber): RedirectResponse
    {
        if ($subscriber->isSubscribed()) {
            $subscriber->conferma();
        }

        return back()->with('success', __('messages.newsletter.success'));
    }

    private function mandaLaConferma(NewsletterSubscriber $subscriber): void
    {
        Mail::to($subscriber->email)->queue(new ConfermaIscrizioneNewsletter($subscriber, app()->getLocale()));
    }

    private function prefissoRotta(): string
    {
        $locale = app()->getLocale();

        return $locale === config('app.fallback_locale', 'it') ? '' : $locale.'.';
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
