<?php

namespace App\Filament\Pages\Settings;

use App\Services\Analytics\WebAnalyticsService;
use App\Services\Social\MetaOAuthService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Illuminate\Support\HtmlString;

/**
 * Le impostazioni di misurazione.
 *
 * Qui sta solo il Measurement ID, che il tag di Google espone comunque in
 * chiaro nel browser. Le vere credenziali — il service account di Google e i
 * segreti dell'app Meta — restano nelle variabili d'ambiente: il repository è
 * pubblico e un campo di testo nel pannello finirebbe in un backup del database.
 *
 * La pagina serve anche a rispondere alle due domande che si fanno ogni volta
 * che si configura questa roba: quale indirizzo autorizzare su Google Analytics
 * e quale URI dichiarare nell'app Meta.
 */
class AnalyticsSettingsPage extends BaseSettingsPage
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-pie';

    protected static ?string $navigationLabel = 'Analytics';

    protected static ?string $title = 'Impostazioni Analytics';

    protected static ?int $navigationSort = 63;

    protected static ?string $slug = 'settings/analytics';

    /**
     * La chiave dell'impostazione è piatta (`ga4_measurement_id`, non
     * `analytics.ga4_measurement_id`) perché `SiteSetting::getAllGrouped()`
     * raggruppa sulla colonna `group` e usa la chiave così com'è: con un nome
     * puntato il front-end dovrebbe leggere
     * `siteSettings.analytics['analytics.ga4_measurement_id']`.
     * La riga con il gruppo giusto la crea la migrazione di configurazione:
     * `SiteSetting::set()` da sola scriverebbe `group = general` e il valore
     * non arriverebbe mai al sito pubblico.
     */
    public function form(Form $form): Form
    {
        $serviceAccount = app(WebAnalyticsService::class)->serviceAccountEmail();
        $oauth = app(MetaOAuthService::class);

        return $form
            ->schema([
                Section::make('Misurazione del sito')
                    ->description('Il tag che raccoglie i dati sulle pagine pubbliche.')
                    ->schema([
                        TextInput::make('ga4_measurement_id')
                            ->label('Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('Google Analytics → Amministrazione → Flussi di dati. Lasciando il campo vuoto la misurazione non viene caricata.')
                            ->rule('regex:/^(G-[A-Z0-9]{4,20})?$/i')
                            ->maxLength(30),

                        TextInput::make('meta_pixel_id')
                            ->label('ID Pixel di Meta')
                            ->placeholder('123456789012345')
                            ->helperText('Meta Gestione eventi → Dataset. Misura il funnel pubblicitario, non il traffico: è indipendente da Google Analytics. Vuoto = pixel non caricato.')
                            ->rule('regex:/^\d*$/')
                            ->maxLength(30),

                        Placeholder::make('consenso')
                            ->label('Consenso')
                            ->content(
                                'Il tag di Google si carica solo per chi accetta i cookie di statistica, quindi i numeri sono più bassi del traffico reale. '
                                .(config('services.meta.pixel_requires_consent')
                                    ? 'Il Pixel di Meta segue la stessa regola sul consenso di marketing.'
                                    : 'Il Pixel di Meta invece si carica per tutti: si subordina al consenso di marketing impostando META_PIXEL_REQUIRES_CONSENT=true.')
                            ),
                    ]),

                Section::make('Lettura dei dati (Google)')
                    ->description('Serve a mostrare i numeri nel pannello, ed è indipendente dal tag.')
                    ->schema([
                        Placeholder::make('service_account')
                            ->label('Service account')
                            ->content(new HtmlString(
                                $serviceAccount === null
                                    ? '<span class="text-danger-600">Non configurato: manca <code>GA4_SERVICE_ACCOUNT_JSON</code> nelle variabili d\'ambiente.</span>'
                                    : 'Aggiungi <code>'.e($serviceAccount).'</code> come <strong>Visualizzatore</strong> su ogni proprietà GA4, in Google Analytics → Amministrazione → Gestione accessi.'
                            )),

                        Placeholder::make('proprieta')
                            ->label('Proprietà collegate')
                            ->content('Si gestiscono in Amministrazione → Siti Analytics, dove c\'è anche il pulsante per verificare l\'accesso.'),
                    ]),

                Section::make('Meta (Instagram e Facebook)')
                    ->schema([
                        Placeholder::make('app_meta')
                            ->label('App Meta')
                            ->content(new HtmlString(
                                $oauth->isConfigured()
                                    ? 'Configurata. Gli account si collegano da Marketing → Analytics Social.'
                                    : '<span class="text-danger-600">Non configurata: mancano <code>META_APP_ID</code> e <code>META_APP_SECRET</code>.</span>'
                            )),

                        Placeholder::make('redirect_uri')
                            ->label('URI di reindirizzamento')
                            ->content(new HtmlString(
                                'Da dichiarare identico nell\'app Meta: <code>'.e($oauth->redirectUri()).'</code>'
                            )),

                        Placeholder::make('permessi')
                            ->label('Permessi richiesti')
                            ->content('instagram_basic, instagram_manage_insights, pages_show_list, pages_read_engagement, read_insights, business_management. Senza read_insights le metriche della Pagina arrivano vuote e Meta non segnala alcun errore.'),
                    ]),
            ])
            ->statePath('data');
    }
}
