<?php

/**
 * I testi di Privacy Policy e Cookie Policy.
 *
 * Stanno qui e non dentro la migrazione perché sono contenuti, non logica, e
 * perché la redazione può riscriverli dal pannello: la migrazione li applica
 * una volta sola e a guardia, solo dove è rimasto il testo vecchio.
 *
 * Il testo vecchio diceva cose non vere — "esclusivamente cookie tecnici",
 * "nessun cookie di profilazione o di terze parti" — mentre il sito caricava
 * Google Analytics e il pixel di Meta. I dati societari qui sotto sono quelli
 * delle impostazioni del sito (gruppo `contact`), non quelli che il testo
 * precedente riportava a memoria: l'indirizzo era sbagliato.
 *
 * NON è un parere legale: è la descrizione tecnica e verificata di quello che
 * il sito fa davvero, da far leggere a chi segue la privacy della società.
 */
$sede = 'Via Benozzo Gozzoli 5/6 — 50018 Scandicci (FI)';
$email = 'info@savinodelbenevolley.it';
$pec = 'pallavoloscandicci@legalmail.it';
$piva = '06271460484';
$cf = '94217750481';

return [
    'privacy-policy' => [
        'firme' => ['esclusivamente dati tecnici necessari alla navigazione', 'Via di Scandicci'],
        'contenuto' => [
            'it' => <<<HTML
            <h2>Informativa sul trattamento dei dati personali</h2>
            <p>Ai sensi degli articoli 13 e 14 del Regolamento UE 2016/679 (GDPR), questa pagina descrive come il sito tratta i dati personali di chi lo visita. È aggiornata al 22 settembre 2026.</p>

            <h3>Titolare del trattamento</h3>
            <p>Savino Del Bene Volley S.S.D. a r.l. — {$sede}<br />
            P. IVA {$piva} — C.F. {$cf}<br />
            Email: <a href="mailto:{$email}">{$email}</a> — PEC: <a href="mailto:{$pec}">{$pec}</a></p>

            <h3>Quali dati raccogliamo, perché, e con quale base giuridica</h3>
            <ul>
                <li><strong>Dati di navigazione</strong> (indirizzo IP, pagina richiesta, browser, momento della visita): li registra il server per far funzionare il sito e per la sicurezza. Base giuridica: legittimo interesse del titolare a erogare e proteggere il servizio.</li>
                <li><strong>Modulo contatti e richieste di accredito stampa</strong> (nome, email, telefono, testo del messaggio, testata e ruolo per gli accrediti): servono a risponderti. Base giuridica: riscontro alla tua richiesta.</li>
                <li><strong>Iscrizione alla newsletter</strong> (email): solo se la chiedi. Base giuridica: consenso, revocabile in ogni momento dal link in fondo a ogni messaggio.</li>
                <li><strong>Acquisti nello shop e partecipazione alle aste</strong> (dati di fatturazione e spedizione, ordini, offerte): servono a concludere e gestire l'acquisto e a rispettare gli obblighi fiscali. Base giuridica: esecuzione del contratto e obbligo di legge.</li>
                <li><strong>Account del negozio</strong> (email, password cifrata, storico ordini): solo se ti registri. Base giuridica: esecuzione del contratto.</li>
                <li><strong>Statistiche di lettura del sito</strong> (Google Analytics 4): solo con il tuo consenso ai cookie statistici.</li>
                <li><strong>Misurazione delle campagne pubblicitarie</strong> (pixel di Meta): solo con il tuo consenso ai cookie di marketing.</li>
            </ul>

            <h3>A chi comunichiamo i dati</h3>
            <p>I dati restano alla società e ai fornitori che ci permettono di far funzionare il servizio, nominati responsabili del trattamento: DigitalOcean (hosting e archiviazione dei file, data center di Francoforte), PayPal (pagamenti dello shop), ActiveCampaign (invio della newsletter), Google Ireland (statistiche del sito), Meta Platforms Ireland (misurazione delle inserzioni), oltre al corriere incaricato delle spedizioni. Non vendiamo e non cediamo i dati a nessun altro.</p>

            <h3>Trasferimenti fuori dall'Unione Europea</h3>
            <p>Google, Meta e ActiveCampaign possono trattare i dati anche negli Stati Uniti. Il trasferimento avviene sulla base delle clausole contrattuali standard della Commissione europea e, dove applicabile, dell'EU-US Data Privacy Framework. I dati del sito e dei suoi file restano invece su server europei.</p>

            <h3>Per quanto tempo li conserviamo</h3>
            <ul>
                <li>Messaggi e richieste di accredito: 24 mesi dall'ultimo contatto.</li>
                <li>Iscrizione alla newsletter: fino alla disiscrizione.</li>
                <li>Ordini e documenti fiscali: 10 anni, come impone la legge.</li>
                <li>Account del negozio: finché resta attivo; alla cancellazione restano solo i documenti fiscali.</li>
                <li>Prova del consenso ai cookie: 12 mesi.</li>
                <li>Statistiche di Google Analytics 4: 14 mesi.</li>
            </ul>

            <h3>I tuoi diritti</h3>
            <p>Puoi chiedere in ogni momento di accedere ai tuoi dati, correggerli, cancellarli, limitarne il trattamento, riceverli in formato leggibile o opporti al trattamento (articoli 15-22 del GDPR), e puoi revocare i consensi che hai dato. Scrivi a <a href="mailto:{$email}">{$email}</a>: rispondiamo entro un mese. Se ritieni che il trattamento violi il Regolamento, puoi rivolgerti al Garante per la protezione dei dati personali (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">garanteprivacy.it</a>).</p>

            <h3>Cookie</h3>
            <p>Quali cookie il sito usa davvero, e a cosa servono, è scritto nella <a href="/cookie-policy">Cookie Policy</a>: quell'elenco non è compilato a mano, lo aggiorna una scansione automatica del sito.</p>
            HTML,
            'en' => <<<HTML
            <h2>Privacy notice</h2>
            <p>Under Articles 13 and 14 of Regulation (EU) 2016/679 (GDPR), this page explains how the site handles the personal data of its visitors. Last updated 22 September 2026.</p>

            <h3>Data controller</h3>
            <p>Savino Del Bene Volley S.S.D. a r.l. — {$sede}, Italy<br />
            VAT {$piva} — Tax code {$cf}<br />
            Email: <a href="mailto:{$email}">{$email}</a> — Certified email: <a href="mailto:{$pec}">{$pec}</a></p>

            <h3>What we collect, why, and on what legal basis</h3>
            <ul>
                <li><strong>Browsing data</strong> (IP address, requested page, browser, time of visit): recorded by the server to run and protect the site. Legal basis: the controller's legitimate interest in providing and securing the service.</li>
                <li><strong>Contact form and press accreditation requests</strong> (name, email, phone, message, outlet and role): used to reply to you. Legal basis: responding to your request.</li>
                <li><strong>Newsletter subscription</strong> (email): only if you ask for it. Legal basis: consent, which you can withdraw at any time from the link at the bottom of every message.</li>
                <li><strong>Shop purchases and auction bids</strong> (billing and shipping details, orders, bids): used to complete and manage the purchase and to meet tax obligations. Legal basis: performance of the contract and legal obligation.</li>
                <li><strong>Shop account</strong> (email, hashed password, order history): only if you register. Legal basis: performance of the contract.</li>
                <li><strong>Site statistics</strong> (Google Analytics 4): only with your consent to statistics cookies.</li>
                <li><strong>Advertising measurement</strong> (Meta pixel): only with your consent to marketing cookies.</li>
            </ul>

            <h3>Who we share data with</h3>
            <p>Data stays with the club and with the providers that keep the service running, appointed as data processors: DigitalOcean (hosting and file storage, Frankfurt data centre), PayPal (shop payments), ActiveCampaign (newsletter delivery), Google Ireland (site statistics), Meta Platforms Ireland (advertising measurement), and the courier handling shipments. We do not sell or otherwise pass data to anyone else.</p>

            <h3>Transfers outside the European Union</h3>
            <p>Google, Meta and ActiveCampaign may also process data in the United States, on the basis of the European Commission's standard contractual clauses and, where applicable, the EU-US Data Privacy Framework. The site itself and its files stay on European servers.</p>

            <h3>How long we keep it</h3>
            <ul>
                <li>Messages and accreditation requests: 24 months from the last contact.</li>
                <li>Newsletter subscription: until you unsubscribe.</li>
                <li>Orders and tax documents: 10 years, as required by law.</li>
                <li>Shop account: as long as it is active; after deletion only tax documents remain.</li>
                <li>Proof of cookie consent: 12 months.</li>
                <li>Google Analytics 4 statistics: 14 months.</li>
            </ul>

            <h3>Your rights</h3>
            <p>You may ask at any time to access your data, correct it, erase it, restrict its processing, receive it in a readable format or object to the processing (Articles 15-22 GDPR), and you may withdraw any consent you have given. Write to <a href="mailto:{$email}">{$email}</a>: we reply within one month. If you believe the processing breaches the Regulation, you may lodge a complaint with the Italian Data Protection Authority (<a href="https://www.garanteprivacy.it" target="_blank" rel="noopener">garanteprivacy.it</a>).</p>

            <h3>Cookies</h3>
            <p>Which cookies the site actually uses, and what they are for, is set out in the <a href="/en/cookie-policy">Cookie Policy</a>: that list is not written by hand — an automated scan of the site keeps it up to date.</p>
            HTML,
        ],
    ],

    'cookie-policy' => [
        'firme' => ['non utilizza cookie di profilazione', 'esclusivamente cookie tecnici'],
        'contenuto' => [
            'it' => <<<'HTML'
            <h2>Informativa sui cookie</h2>
            <p>I cookie sono piccoli file che un sito lascia nel browser di chi lo visita. Alcuni servono a far funzionare le pagine, altri a capire come vengono lette, altri ancora a misurare le campagne pubblicitarie. Questa pagina spiega quali usiamo e come decidi tu.</p>

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

            <h3>Chi riceve i dati</h3>
            <p>I cookie statistici sono di Google Ireland Ltd. (Google Analytics 4), quelli di marketing di Meta Platforms Ireland Ltd. I loro trattamenti, e i trasferimenti fuori dall'Unione Europea, sono descritti nella <a href="/privacy-policy">Privacy Policy</a>.</p>

            <p>L'elenco qui sotto non è scritto a mano: lo aggiorna una scansione automatica che visita il sito ogni settimana con un browser vero e annota che cosa viene caricato prima e dopo il consenso.</p>
            HTML,
            'en' => <<<'HTML'
            <h2>Cookie notice</h2>
            <p>Cookies are small files a website leaves in the visitor's browser. Some make the pages work, some tell us how they are read, others measure advertising campaigns. This page explains which ones we use and what you get to decide.</p>

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

            <h3>Who receives the data</h3>
            <p>Statistics cookies belong to Google Ireland Ltd. (Google Analytics 4), marketing cookies to Meta Platforms Ireland Ltd. Their processing, and transfers outside the European Union, are described in the <a href="/en/privacy-policy">Privacy Policy</a>.</p>

            <p>The list below is not written by hand: an automated scan visits the site every week with a real browser and notes what is loaded before and after consent.</p>
            HTML,
        ],
    ],
];
