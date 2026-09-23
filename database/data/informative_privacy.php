<?php

/**
 * I testi di Privacy Policy e Cookie Policy.
 *
 * Stanno qui e non dentro una migrazione perché sono contenuti, non logica, e
 * perché la redazione può riscriverli dal pannello: le migrazioni li applicano
 * una volta sola e a guardia, solo dove è rimasto un testo precedente. Lo legge
 * anche `PageSeeder`, così un ambiente nuovo nasce con l'informativa giusta
 * invece di quella vecchia ricopiata nel seeder.
 *
 * Le `firme` sono le frasi che riconoscono le versioni precedenti: finché una
 * di quelle è ancora nella pagina, il testo non è stato toccato dalla redazione
 * e si può sostituire. Sono cumulative, e quando si pubblica una revisione si
 * aggiunge la frase della versione che se ne va — non si toglie niente, o una
 * migrazione vecchia che gira su un database nuovo non riconoscerebbe più
 * niente da correggere.
 *
 * Il titolare è la **ragione sociale**, non il nome con cui la squadra gioca:
 * «Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a
 * Responsabilità Limitata». Fino al 23 settembre 2026 qui c'era «Savino Del
 * Bene Volley S.S.D. a r.l.», che non è la denominazione di nessuno: in
 * un'informativa il titolare va indicato per esteso, perché è la persona
 * giuridica verso cui si esercitano i diritti. Il copyright del footer usa
 * invece il nome d'uso, ed è giusto così.
 *
 * Revisione del 23 settembre 2026: la versione precedente descriveva il sito
 * fino ai cookie e si fermava lì. Mancavano il riconoscimento dei volti
 * sull'archivio fotografico (che è un trattamento biometrico e non era
 * dichiarato da nessuna parte), i contenuti di terze parti incorporati nelle
 * pagine, il fornitore della posta, il codice fiscale del checkout e metà delle
 * conservazioni che il sito applica davvero.
 *
 * NON è un parere legale: è la descrizione tecnica e verificata di quello che
 * il sito fa, da far leggere a chi segue la privacy della società.
 *
 * Due frasi qui dentro sono promesse che il codice non fa rispettare, e stanno
 * fra i punti aperti di `docs/PRIVACY.md`: il **consenso esplicito** al
 * riconoscimento dei volti, che la società raccoglie fuori di qui e che nessun
 * campo del pannello verifica prima di addestrare un volto, e i **contenuti di
 * terze parti** — mappa e video — che si caricano insieme alla pagina, prima
 * della scelta sui cookie. La seconda è detta al visitatore così com'è; la
 * prima no, quindi va mantenuta.
 */
$societa = 'Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a Responsabilità Limitata';
$sede = 'Via Benozzo Gozzoli, 5/6 — 50018 Scandicci (FI)';
// La casella a cui si esercitano i diritti: non e' `contact.email` del sito
// (`info@`), che risponde a tutt'altro, ed e' la stessa che indicano
// l'informativa fornitori e quella promozionale.
$email = 'privacy@savinodelbenevolley.it';
$pec = 'pallavoloscandicci@legalmail.it';
$piva = '06271460484';
$cf = '94217750481';

return [
    'privacy-policy' => [
        'firme' => [
            // Testo originale, fino al 22 settembre 2026.
            'esclusivamente dati tecnici necessari alla navigazione',
            'Via di Scandicci',
            // Prima riscrittura, 22 settembre 2026.
            'aggiornata al 22 settembre 2026',
            'Last updated 22 September 2026',
            // Ragione sociale sbagliata, fino al 23 settembre 2026.
            'Savino Del Bene Volley S.S.D. a r.l.',
            'mailto:info@savinodelbenevolley.it',
        ],
        'contenuto' => [
            'it' => <<<HTML
            <h2>Informativa sul trattamento dei dati personali</h2>
            <p>Ai sensi degli articoli 13 e 14 del Regolamento UE 2016/679 (GDPR), questa pagina descrive come il sito tratta i dati personali di chi lo visita. È aggiornata al 23 settembre 2026.</p>

            <h3>Titolare del trattamento</h3>
            <p>{$societa} — {$sede}<br />
            P. IVA {$piva} — C.F. {$cf}<br />
            Email: <a href="mailto:{$email}">{$email}</a> — PEC: <a href="mailto:{$pec}">{$pec}</a></p>

            <h3>Quali dati raccogliamo, perché, e con quale base giuridica</h3>
            <ul>
                <li><strong>Dati di navigazione</strong> (indirizzo IP, pagina richiesta, browser, momento della visita): li registra il server per far funzionare il sito e per la sicurezza. Finché la tua sessione resta aperta, indirizzo IP e browser restano anche nella tabella delle sessioni, che è ciò che tiene in piedi carrello e area riservata. Base giuridica: legittimo interesse del titolare a erogare e proteggere il servizio.</li>
                <li><strong>Modulo contatti e richieste di accredito stampa</strong> (nome, email, telefono, testo del messaggio, e per gli accrediti testata, ruolo e gara): servono a risponderti. Base giuridica: riscontro alla tua richiesta.</li>
                <li><strong>Iscrizione alla newsletter</strong> (email, e il nome se lo scrivi): solo se la chiedi. Base giuridica: consenso, revocabile in ogni momento dal link in fondo a ogni messaggio.</li>
                <li><strong>Acquisti nello shop e partecipazione alle aste</strong> (nome, indirizzo di spedizione e fatturazione, telefono, codice fiscale se lo indichi, ordini, offerte): servono a concludere e gestire l'acquisto e a rispettare gli obblighi fiscali. Base giuridica: esecuzione del contratto e obbligo di legge.</li>
                <li><strong>Account del negozio</strong> (email, password cifrata, storico ordini): solo se ti registri. Teniamo anche l'impronta delle password che hai usato prima, per impedire che tu ne riusi una. Base giuridica: esecuzione del contratto.</li>
                <li><strong>Statistiche di lettura del sito</strong> (Google Analytics 4): solo con il tuo consenso ai cookie statistici.</li>
                <li><strong>Misurazione delle campagne pubblicitarie</strong> (pixel di Meta): solo con il tuo consenso ai cookie di marketing.</li>
                <li><strong>Prova della tua scelta sui cookie</strong> (che cosa hai scelto, quando, su quale versione dell'informativa, e un'impronta non riconducibile a te al posto dell'indirizzo IP): è descritta nella <a href="/cookie-policy">Cookie Policy</a>. Base giuridica: obbligo di dimostrare il consenso, articolo 7 del GDPR.</li>
            </ul>

            <h3>Fotografie e riconoscimento dei volti</h3>
            <p>Il sito pubblica fotografie di gare, allenamenti, eventi e attività della società. Nelle immagini compaiono atlete, staff, ospiti e pubblico presente al palazzetto.</p>
            <p>Ogni fotografia che entra nell'archivio viene analizzata da un servizio di riconoscimento facciale che gira su un server della società, a Francoforte, raggiungibile solo dalla nostra rete interna: le immagini non vengono inviate a servizi di riconoscimento di terze parti. Il servizio individua i volti presenti nella fotografia e li confronta con le impronte numeriche delle persone che la redazione ha registrato — atlete e membri dello staff — per capire chi è ritratto. I nomi riconosciuti vengono associati alla fotografia, compaiono nell'archivio fotografico come filtro per persona e finiscono nel titolo e nella descrizione dell'immagine, che è anche ciò che leggono i motori di ricerca.</p>
            <p>Le impronte conservate sono soltanto quelle delle persone registrate dalla redazione. Per tutti gli altri volti che compaiono in una fotografia il confronto avviene sul momento e non lascia nulla: nessuna impronta viene creata o conservata per il pubblico ritratto.</p>
            <p><strong>Base giuridica</strong>: il consenso esplicito della persona interessata (articolo 9 §2 lettera a del GDPR), raccolto dalla società prima di registrarne il volto; per le atlete minorenni lo dà chi esercita la responsabilità genitoriale. Il consenso si può revocare in ogni momento, e alla revoca l'impronta viene cancellata insieme alle associazioni già fatte: le fotografie restano, senza il nome.</p>
            <p>Il riconoscimento propone, non decide: l'associazione fra una persona e una fotografia resta sempre modificabile dalla redazione, e non produce alcun effetto giuridico né decisione automatizzata su nessuno.</p>
            <p>Se compari in una fotografia e non vuoi comparire, o non vuoi che il tuo nome le resti associato, scrivi a <a href="mailto:{$email}">{$email}</a>: togliamo l'immagine o l'associazione.</p>

            <h3>A chi comunichiamo i dati</h3>
            <p>I dati restano alla società e ai fornitori che ci permettono di far funzionare il servizio, nominati responsabili del trattamento: DigitalOcean (hosting, database e archiviazione dei file, data center di Francoforte), PayPal e — quando è attivo — Stripe (pagamenti dello shop e delle aste), Resend (invio delle email di servizio: conferme d'ordine, spedizioni, rimborsi, aste, reimpostazione della password), ActiveCampaign (invio della newsletter), Google Ireland (statistiche del sito), Meta Platforms Ireland (misurazione delle inserzioni), oltre al corriere incaricato delle spedizioni. Non vendiamo e non cediamo i dati a nessun altro.</p>
            <p>Alcune pagine contengono inoltre contenuti ospitati altrove — la mappa del palazzetto, i video delle dirette: aprendole, il tuo indirizzo IP arriva a chi li ospita. È spiegato nella <a href="/cookie-policy">Cookie Policy</a>.</p>

            <h3>Trasferimenti fuori dall'Unione Europea</h3>
            <p>Google, Meta, ActiveCampaign, Resend, Stripe e PayPal possono trattare i dati anche negli Stati Uniti. Il trasferimento avviene sulla base delle clausole contrattuali standard della Commissione europea e, dove applicabile, dell'EU-US Data Privacy Framework. Il sito, il suo database, l'archivio fotografico e il riconoscimento dei volti restano invece su server europei.</p>

            <h3>Per quanto tempo li conserviamo</h3>
            <ul>
                <li>Messaggi e richieste di accredito: 24 mesi dall'ultimo contatto.</li>
                <li>Iscrizione alla newsletter: fino alla disiscrizione.</li>
                <li>Ordini e documenti fiscali: 10 anni, come impone la legge.</li>
                <li>Account del negozio: finché resta attivo; alla cancellazione restano solo i documenti fiscali.</li>
                <li>Carrelli lasciati a metà: 7 giorni.</li>
                <li>Sessione di navigazione: 2 ore dall'ultima pagina aperta.</li>
                <li>Prova del consenso ai cookie: 12 mesi.</li>
                <li>Statistiche di Google Analytics 4: 14 mesi.</li>
                <li>Registro di chi ha modificato che cosa nel pannello della redazione: 180 giorni.</li>
                <li>Fotografie e nomi a esse associati: finché l'immagine resta nell'archivio pubblico.</li>
                <li>Impronte dei volti di atlete e staff: finché il consenso resta valido — alla revoca, o quando la persona lascia la società, si cancellano.</li>
            </ul>

            <h3>I tuoi diritti</h3>
            <p>Puoi chiedere in ogni momento di accedere ai tuoi dati, correggerli, cancellarli, limitarne il trattamento, riceverli in formato leggibile o opporti al trattamento (articoli 15-22 del GDPR), e puoi revocare i consensi che hai dato. Scrivi a <a href="mailto:{$email}">{$email}</a>: rispondiamo entro un mese. Se ritieni che il trattamento violi il Regolamento, puoi rivolgerti al Garante per la protezione dei dati personali (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">garanteprivacy.it</a>).</p>

            <h3>Cookie</h3>
            <p>Quali cookie il sito usa davvero, e a cosa servono, è scritto nella <a href="/cookie-policy">Cookie Policy</a>: quell'elenco non è compilato a mano, lo aggiorna una scansione automatica del sito.</p>
            HTML,
            'en' => <<<HTML
            <h2>Privacy notice</h2>
            <p>Under Articles 13 and 14 of Regulation (EU) 2016/679 (GDPR), this page explains how the site handles the personal data of its visitors. Last updated 23 September 2026.</p>

            <h3>Data controller</h3>
            <p>{$societa} — {$sede}, Italy<br />
            VAT {$piva} — Tax code {$cf}<br />
            Email: <a href="mailto:{$email}">{$email}</a> — Certified email: <a href="mailto:{$pec}">{$pec}</a></p>

            <h3>What we collect, why, and on what legal basis</h3>
            <ul>
                <li><strong>Browsing data</strong> (IP address, requested page, browser, time of visit): recorded by the server to run and protect the site. While your session is open, IP address and browser are also held in the sessions table, which is what keeps the cart and the account area working. Legal basis: the controller's legitimate interest in providing and securing the service.</li>
                <li><strong>Contact form and press accreditation requests</strong> (name, email, phone, message, and for accreditations the outlet, role and match): used to reply to you. Legal basis: responding to your request.</li>
                <li><strong>Newsletter subscription</strong> (email, and your first name if you give it): only if you ask for it. Legal basis: consent, which you can withdraw at any time from the link at the bottom of every message.</li>
                <li><strong>Shop purchases and auction bids</strong> (name, shipping and billing address, phone, Italian tax code if you provide it, orders, bids): used to complete and manage the purchase and to meet tax obligations. Legal basis: performance of the contract and legal obligation.</li>
                <li><strong>Shop account</strong> (email, hashed password, order history): only if you register. We also keep a fingerprint of the passwords you used before, to stop you reusing one. Legal basis: performance of the contract.</li>
                <li><strong>Site statistics</strong> (Google Analytics 4): only with your consent to statistics cookies.</li>
                <li><strong>Advertising measurement</strong> (Meta pixel): only with your consent to marketing cookies.</li>
                <li><strong>Proof of your cookie choice</strong> (what you chose, when, against which version of the notice, and a fingerprint that cannot be traced back to you instead of your IP address): described in the <a href="/en/cookie-policy">Cookie Policy</a>. Legal basis: the obligation to demonstrate consent, Article 7 GDPR.</li>
            </ul>

            <h3>Photographs and face recognition</h3>
            <p>The site publishes photographs of matches, training sessions, events and club activities. Players, staff, guests and the crowd at the arena appear in them.</p>
            <p>Every photograph added to the archive is analysed by a face recognition service running on a club server in Frankfurt, reachable only from our internal network: images are not sent to any third-party recognition service. The service finds the faces in the photograph and compares them with the numeric templates of the people the editorial team has enrolled — players and staff members — in order to tell who is pictured. Recognised names are attached to the photograph, appear in the photo archive as a per-person filter, and end up in the image title and description, which is also what search engines read.</p>
            <p>The only templates we keep are those of the people enrolled by the editorial team. For every other face in a photograph the comparison happens there and then and leaves nothing behind: no template is created or kept for members of the public.</p>
            <p><strong>Legal basis</strong>: the explicit consent of the person concerned (Article 9(2)(a) GDPR), obtained by the club before enrolling their face; for players who are minors it is given by whoever holds parental responsibility. Consent can be withdrawn at any time, and on withdrawal the template is deleted along with the links already made: the photographs stay, without the name.</p>
            <p>Recognition suggests, it does not decide: the link between a person and a photograph can always be changed by the editorial team, and it produces no legal effect and no automated decision about anyone.</p>
            <p>If you appear in a photograph and would rather not, or would rather your name were not attached to it, write to <a href="mailto:{$email}">{$email}</a>: we will remove the image or the link.</p>

            <h3>Who we share data with</h3>
            <p>Data stays with the club and with the providers that keep the service running, appointed as data processors: DigitalOcean (hosting, database and file storage, Frankfurt data centre), PayPal and — when enabled — Stripe (shop and auction payments), Resend (service emails: order confirmations, shipments, refunds, auctions, password resets), ActiveCampaign (newsletter delivery), Google Ireland (site statistics), Meta Platforms Ireland (advertising measurement), and the courier handling shipments. We do not sell or otherwise pass data to anyone else.</p>
            <p>Some pages also carry content hosted elsewhere — the arena map, the live stream videos: opening them sends your IP address to whoever hosts them. This is explained in the <a href="/en/cookie-policy">Cookie Policy</a>.</p>

            <h3>Transfers outside the European Union</h3>
            <p>Google, Meta, ActiveCampaign, Resend, Stripe and PayPal may also process data in the United States, on the basis of the European Commission's standard contractual clauses and, where applicable, the EU-US Data Privacy Framework. The site itself, its database, the photo archive and the face recognition service stay on European servers.</p>

            <h3>How long we keep it</h3>
            <ul>
                <li>Messages and accreditation requests: 24 months from the last contact.</li>
                <li>Newsletter subscription: until you unsubscribe.</li>
                <li>Orders and tax documents: 10 years, as required by law.</li>
                <li>Shop account: as long as it is active; after deletion only tax documents remain.</li>
                <li>Carts left halfway: 7 days.</li>
                <li>Browsing session: 2 hours from the last page opened.</li>
                <li>Proof of cookie consent: 12 months.</li>
                <li>Google Analytics 4 statistics: 14 months.</li>
                <li>Record of who changed what in the editorial panel: 180 days.</li>
                <li>Photographs and the names attached to them: as long as the image stays in the public archive.</li>
                <li>Face templates of players and staff: as long as consent stands — on withdrawal, or when the person leaves the club, they are deleted.</li>
            </ul>

            <h3>Your rights</h3>
            <p>You may ask at any time to access your data, correct it, erase it, restrict its processing, receive it in a readable format or object to the processing (Articles 15-22 GDPR), and you may withdraw any consent you have given. Write to <a href="mailto:{$email}">{$email}</a>: we reply within one month. If you believe the processing breaches the Regulation, you may lodge a complaint with the Italian Data Protection Authority (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">garanteprivacy.it</a>).</p>

            <h3>Cookies</h3>
            <p>Which cookies the site actually uses, and what they are for, is set out in the <a href="/en/cookie-policy">Cookie Policy</a>: that list is not written by hand — an automated scan of the site keeps it up to date.</p>
            HTML,
        ],
    ],

    'cookie-policy' => [
        'firme' => [
            // Testo originale, fino al 22 settembre 2026.
            'non utilizza cookie di profilazione',
            'esclusivamente cookie tecnici',
            // Prima riscrittura, 22 settembre 2026.
            'quelli di marketing di Meta Platforms Ireland Ltd.',
            'marketing cookies to Meta Platforms Ireland Ltd.',
        ],
        'contenuto' => [
            'it' => <<<'HTML'
            <h2>Informativa sui cookie</h2>
            <p>I cookie sono piccoli file che un sito lascia nel browser di chi lo visita. Alcuni servono a far funzionare le pagine, altri a capire come vengono lette, altri ancora a misurare le campagne pubblicitarie. Questa pagina spiega quali usiamo e come decidi tu. È aggiornata al 23 settembre 2026.</p>

            <h3>Cosa puoi scegliere</h3>
            <ul>
                <li><strong>Necessari</strong>: tengono la sessione, il carrello e la sicurezza dei moduli. Senza, il sito non funziona, e per questo non si possono disattivare. Non servono a riconoscerti altrove.</li>
                <li><strong>Statistici</strong>: ci dicono quante persone leggono il sito e quali pagine. Partono solo se dai il consenso.</li>
                <li><strong>Marketing</strong>: misurano le inserzioni su Facebook e Instagram. Partono solo se dai il consenso.</li>
            </ul>
            <p>Finché non scegli, il sito carica soltanto i cookie necessari: nessuna statistica, nessuna misurazione pubblicitaria.</p>

            <h3>Come cambiare idea</h3>
            <p>L'icona in basso a sinistra, presente in ogni pagina, riapre le tue preferenze: puoi accettare, rifiutare o scegliere voce per voce, quando vuoi. La scelta vale 12 mesi e viene richiesta di nuovo se cambiamo questa informativa. Puoi anche cancellare i cookie dalle impostazioni del tuo browser.</p>

            <h3>La prova della tua scelta</h3>
            <p>Quando rispondi al banner registriamo che cosa hai scelto, quando, e su quale versione di questa informativa — insieme a un riferimento che trovi nel pannello delle preferenze. Non conserviamo il tuo indirizzo IP, ma solo un'impronta che non è riconducibile a te. Serve a dimostrare che il consenso è stato chiesto e dato come previsto dall'articolo 7 del GDPR, e si cancella dopo 12 mesi.</p>

            <h3>Contenuti ospitati da altri dentro le nostre pagine</h3>
            <p>Alcune pagine contengono un riquadro che non arriva dal nostro sito: la mappa del palazzetto, che è di Google Maps, e i video delle dirette, che stanno su YouTube o Vimeo. Sono parte del contenuto della pagina e si caricano insieme a essa: da quel momento chi li ospita vede il tuo indirizzo IP e può scrivere nel tuo browser i propri cookie, che seguono le sue regole e non le nostre. Riguarda soltanto le pagine che quei riquadri li hanno: se non vuoi che accada, puoi non aprirle, oppure bloccare i cookie di terze parti dalle impostazioni del browser.</p>
            <p>Tutto il resto di quello che vedi — i caratteri tipografici, le immagini, le fotografie dell'archivio — arriva invece dai nostri server e dal nostro spazio di archiviazione: su una pagina che non contenga uno di quei riquadri, e finché non scegli, nessun altro sito viene a sapere che sei passato di qui.</p>

            <h3>Chi riceve i dati</h3>
            <p>I cookie statistici sono di Google Ireland Ltd. (Google Analytics 4), quelli di marketing di Meta Platforms Ireland Ltd.; la mappa è di Google Ireland Ltd., i video di Google Ireland Ltd. (YouTube) o di Vimeo Inc. I loro trattamenti, e i trasferimenti fuori dall'Unione Europea, sono descritti nella <a href="/privacy-policy">Privacy Policy</a>.</p>

            <p>L'elenco qui sotto non è scritto a mano: lo aggiorna una scansione automatica che visita il sito ogni settimana con un browser vero e annota che cosa viene caricato prima e dopo il consenso.</p>
            HTML,
            'en' => <<<'HTML'
            <h2>Cookie notice</h2>
            <p>Cookies are small files a website leaves in the visitor's browser. Some make the pages work, some tell us how they are read, others measure advertising campaigns. This page explains which ones we use and what you get to decide. Last updated 23 September 2026.</p>

            <h3>What you can choose</h3>
            <ul>
                <li><strong>Necessary</strong>: they keep the session, the cart and the security of the forms. Without them the site does not work, which is why they cannot be turned off. They are not used to recognise you elsewhere.</li>
                <li><strong>Statistics</strong>: they tell us how many people read the site, and which pages. They only start if you consent.</li>
                <li><strong>Marketing</strong>: they measure the ads on Facebook and Instagram. They only start if you consent.</li>
            </ul>
            <p>Until you choose, the site loads necessary cookies only: no statistics, no advertising measurement.</p>

            <h3>Changing your mind</h3>
            <p>The icon at the bottom left, on every page, reopens your preferences: accept, refuse or choose item by item, whenever you like. Your choice lasts 12 months, and we ask again if we change this notice. You can also delete cookies from your browser settings.</p>

            <h3>Proof of your choice</h3>
            <p>When you answer the banner we record what you chose, when, and against which version of this notice — along with a reference you can find in the preferences panel. We do not keep your IP address, only a fingerprint that cannot be traced back to you. It exists to show that consent was asked for and given as Article 7 GDPR requires, and it is deleted after 12 months.</p>

            <h3>Content hosted by others inside our pages</h3>
            <p>Some pages carry a frame that does not come from our site: the arena map, which is Google Maps, and the live stream videos, which live on YouTube or Vimeo. They are part of the page's content and load with it: from that moment whoever hosts them can see your IP address and write their own cookies in your browser, under their rules and not ours. This only concerns the pages that carry those frames: if you would rather it did not happen, you can leave those pages alone, or block third-party cookies in your browser settings.</p>
            <p>Everything else you see — fonts, images, the photo archive — comes from our own servers and storage instead: on a page without one of those frames, and until you choose, no other website learns that you were here.</p>

            <h3>Who receives the data</h3>
            <p>Statistics cookies belong to Google Ireland Ltd. (Google Analytics 4), marketing cookies to Meta Platforms Ireland Ltd.; the map is Google Ireland Ltd.'s, the videos are Google Ireland Ltd.'s (YouTube) or Vimeo Inc.'s. Their processing, and transfers outside the European Union, are described in the <a href="/en/privacy-policy">Privacy Policy</a>.</p>

            <p>The list below is not written by hand: an automated scan visits the site every week with a real browser and notes what is loaded before and after consent.</p>
            HTML,
        ],
    ],
];
