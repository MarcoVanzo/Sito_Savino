<?php

/**
 * La dichiarazione di accessibilita' del sito e dello shop.
 *
 * Lo shop e' un "servizio di commercio elettronico" ai sensi dell'European
 * Accessibility Act (Dir. UE 2019/882, D.Lgs. 82/2022): la societa' fattura
 * circa 10 milioni di euro e non e' una microimpresa, quindi dal 28/06/2025
 * deve rispettare i requisiti (EN 301 549, cioe' WCAG 2.1 livello AA) e
 * pubblicare le informazioni dell'Allegato V: come il servizio li soddisfa,
 * i limiti noti, un recapito per le segnalazioni e l'autorita' di vigilanza
 * (AgID).
 *
 * I limiti elencati sono quelli veri al 26/09/2026. Quando se ne risolve uno
 * va tolto dalla pagina (dal pannello) e la data aggiornata: una
 * dichiarazione che promette piu' di quello che il sito fa e' peggio di
 * nessuna dichiarazione. Per questo ogni affermazione della prima lista dice
 * anche fin dove arriva la verifica.
 *
 * La pagina gia' pubblicata non si aggiorna da qui: la porta al testo nuovo
 * una migrazione a guardie, solo se la redazione non l'ha ancora toccata
 * (l'ultima: 2026_09_26_130000_dichiarazione_di_accessibilita_checkout_e_pdf).
 */

return [
    'titolo' => ['it' => 'Dichiarazione di accessibilità', 'en' => 'Accessibility statement'],
    'descrizione' => [
        'it' => 'Come il sito e lo shop ufficiale della Savino Del Bene Volley rispettano i requisiti di accessibilità, i limiti noti e come segnalare un problema.',
        'en' => 'How the Savino Del Bene Volley website and official shop meet accessibility requirements, known limitations and how to report a problem.',
    ],
    'contenuto' => [
        'it' => <<<'HTML'
<p><em>Aggiornata al 26 settembre 2026.</em></p>
<p>Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a Responsabilità Limitata (sede in Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI), P. IVA 06271460484) si impegna a rendere il proprio sito e lo shop online accessibili a tutte le persone, comprese le persone con disabilità, in conformità al decreto legislativo 27 maggio 2022, n. 82, che recepisce la direttiva (UE) 2019/882 (European Accessibility Act).</p>

<h2>Il servizio</h2>
<p>Questa dichiarazione riguarda il sito della Savino Del Bene Volley e il suo shop online, dove si acquistano prodotti ufficiali e si partecipa alle aste di beneficenza: consultazione del catalogo, carrello, checkout, pagamento, area personale, recesso online e aste.</p>

<h2>Norma di riferimento e stato di conformità</h2>
<p>Il riferimento tecnico è la norma europea EN 301 549, che per i siti web coincide con le Web Content Accessibility Guidelines (WCAG) 2.1, livello AA. Il sito è <strong>parzialmente conforme</strong>: buona parte dei requisiti è rispettata, con le eccezioni elencate più avanti.</p>

<h2>Come il servizio soddisfa i requisiti</h2>
<ul>
<li>Menu, carrello, checkout, registrazione e recesso sono pensati per l'uso con la sola tastiera; il menu del telefono, il carrello e le preferenze sui cookie trattengono il focus finché sono aperti e lo restituiscono alla chiusura.</li>
<li>Un collegamento «Vai al contenuto» permette di saltare il menu; a ogni cambio di pagina il titolo nuovo viene annunciato agli screen reader.</li>
<li>I colori del brand sono stati scuriti o schiariti dove serviva per arrivare al contrasto minimo di 4,5:1 del testo, e il controllo automatico settimanale segnala ogni testo che torna sotto quella soglia.</li>
<li>Le immagini di contenuto hanno un testo alternativo, quelle solo decorative sono nascoste agli screen reader e le icone senza testo hanno un'etichetta.</li>
<li>Nei moduli dello shop, nella registrazione e nel modulo contatti ogni campo ha la sua etichetta, gli errori sono collegati al campo a cui si riferiscono e il cursore si sposta sul primo campo da correggere.</li>
<li>Nel checkout il pulsante che chiude l'ordine resta sempre attivo: se manca qualcosa (metodo di pagamento, accettazione delle condizioni, un dato di spedizione) lo dice sotto il campo e porta lì il cursore. Il passaggio dai dati di spedizione al pagamento viene annunciato.</li>
<li>Nella scheda prodotto la taglia scelta viene annunciata come selezionata, l'avviso «scegli una taglia» resta a schermo finché non la scegli e i cambi di quantità vengono letti; i messaggi a comparsa si fermano quando ci passi sopra con il mouse o con la tastiera.</li>
<li>Le animazioni della homepage (presentazione, video, particelle, striscia delle foto) si fermano con il pulsante «Ferma le animazioni» e restano ferme se nel sistema è attiva l'opzione «riduci movimento».</li>
<li>Ogni pagina dichiara la propria lingua (italiano o inglese) e lo zoom del browser non è bloccato.</li>
<li>Le informazioni su prezzi, spese di spedizione, recesso e garanzia sono in pagine di testo, non in immagini.</li>
<li>Nelle notizie dell'archivio importate dal sito precedente i titoli interni seguono l'ordine corretto e le immagini informative (tabelle dei prezzi degli abbonamenti, gironi delle coppe, locandine, statistiche) hanno un testo alternativo che ne riporta il contenuto.</li>
</ul>

<h2>Contenuti non ancora accessibili</h2>
<ul>
<li><strong>Documenti PDF della società</strong>: il Modello organizzativo e il Bilancio di sostenibilità 2024/25 non hanno la struttura (tag) che serve agli screen reader; i Protocolli di Safeguarding sono strutturati e dichiarano la lingua, ma non hanno un titolo del documento. I loghi delle cartelle stampa sono file grafici. Su richiesta forniamo una versione accessibile di qualunque documento.</li>
<li><strong>PDF delle condizioni allegato alla conferma d'ordine</strong>: dichiara titolo e lingua ma non è strutturato; lo stesso testo è pubblicato come pagine accessibili, <a href="/condizioni-di-vendita">Condizioni di vendita</a> e <a href="/diritto-di-recesso">Diritto di recesso</a>.</li>
<li><strong>Archivio fotografico storico</strong>: le descrizioni di parte delle foto sono generate in automatico (nome dell'evento e delle atlete riconosciute) e possono essere poco descrittive.</li>
<li><strong>Testi sopra fotografie e sfumature</strong>: il controllo automatico non riesce a misurarne il contrasto; li verifichiamo a vista e alcuni potrebbero restare sotto la soglia.</li>
<li><strong>Mappe e contenuti di terze parti</strong>: le mappe di Google e i video incorporati (YouTube e simili) e le pagine di pagamento di PayPal e Stripe dipendono dai rispettivi fornitori e non sono sotto il nostro controllo.</li>
<li><strong>Verifica con tecnologie assistive</strong>: i percorsi principali (menu, scheda prodotto, carrello, checkout con errori, recesso) sono provati in automatico con uno screen reader simulato; la prova con VoiceOver e NVDA da parte di una persona non è ancora conclusa.</li>
</ul>

<h2>Come è stata fatta la valutazione</h2>
<p>Autovalutazione del 26 settembre 2026, con lo strumento automatico axe-core e con prove manuali da tastiera. Ogni settimana il controllo automatico gira sul sito pubblicato (pagine principali, scheda prodotto, carrello, recesso) e, su una copia di prova, percorre anche carrello pieno, checkout con gli errori a schermo, conferma d'ordine e checkout di un'asta. Un campione di 38 notizie dell'archivio, di anni diversi e in entrambe le lingue, e i documenti PDF pubblicati sono stati controllati a parte.</p>

<h2>Segnalazioni e richieste</h2>
<p>Se incontri un ostacolo, o ti serve un contenuto in un formato accessibile, scrivi a <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a> indicando la pagina e il problema. Rispondiamo entro 30 giorni; se il problema riguarda un acquisto in corso, ti aiutiamo a completarlo per email o per telefono (+39 055 721503).</p>

<h2>Autorità di vigilanza</h2>
<p>La vigilanza sull'accessibilità dei servizi di commercio elettronico spetta all'Agenzia per l'Italia Digitale (AgID). Puoi segnalarle in qualunque momento un servizio che non rispetta i requisiti, anche senza aver scritto prima a noi: <a href="https://www.agid.gov.it" target="_blank" rel="noopener noreferrer">www.agid.gov.it</a>.</p>
HTML,
        'en' => <<<'HTML'
<p><em>Last updated 26 September 2026.</em></p>
<p>Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a Responsabilità Limitata (registered office Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI), Italy, VAT number IT06271460484) is committed to making its website and online shop accessible to everyone, including people with disabilities, in accordance with Italian Legislative Decree 82/2022, which transposes Directive (EU) 2019/882 (European Accessibility Act).</p>

<h2>The service</h2>
<p>This statement covers the Savino Del Bene Volley website and its online shop, where official products are sold and charity auctions are held: product catalogue, cart, checkout, payment, personal area, online withdrawal and auctions.</p>

<h2>Standard and compliance status</h2>
<p>The technical reference is the European standard EN 301 549, which for websites corresponds to the Web Content Accessibility Guidelines (WCAG) 2.1, level AA. The site is <strong>partially compliant</strong>: a large part of the requirements is met, with the exceptions listed below.</p>

<h2>How the service meets the requirements</h2>
<ul>
<li>Menu, cart, checkout, registration and withdrawal are designed to be used with the keyboard alone; the mobile menu, the cart and the cookie preferences keep focus inside while open and give it back when closed.</li>
<li>A "Skip to content" link bypasses the menu; on every page change the new title is announced to screen readers.</li>
<li>Brand colours have been darkened or lightened where needed to reach the minimum text contrast of 4.5:1, and the weekly automated check flags any text that falls below that threshold.</li>
<li>Content images have alternative text, purely decorative images are hidden from screen readers and icons without text have a label.</li>
<li>In the shop forms, registration and the contact form every field has a label, errors are linked to the field they refer to and the cursor moves to the first field to correct.</li>
<li>At checkout the button that places the order is always active: if something is missing (payment method, acceptance of the terms, a shipping detail) it says so under the field and moves the cursor there. Moving from shipping details to payment is announced.</li>
<li>On the product page the chosen size is announced as selected, the "choose a size" message stays on screen until you choose one and quantity changes are read out; pop-up messages pause while the mouse or keyboard focus is on them.</li>
<li>Homepage animations (slideshow, video, particles, photo strip) stop with the "Pause animations" button and stay still if "reduce motion" is enabled on your device.</li>
<li>Every page declares its language (Italian or English) and browser zoom is not blocked.</li>
<li>Information on prices, shipping costs, withdrawal and guarantee is provided as text, not images.</li>
<li>In the archive news imported from the previous website internal headings follow the correct order and informative images (season ticket prices, cup pools, posters, statistics) have alternative text that reports their content.</li>
</ul>

<h2>Content that is not yet accessible</h2>
<ul>
<li><strong>Club PDF documents</strong>: the Organisational Model and the 2024/25 Sustainability Report lack the structure (tags) screen readers need; the Safeguarding protocols are structured and declare their language but have no document title. The press-kit logos are graphic files. We provide an accessible version of any document on request.</li>
<li><strong>Terms PDF attached to the order confirmation</strong>: it declares title and language but is not structured; the same text is published as accessible pages, <a href="/en/condizioni-di-vendita">Terms of sale</a> and <a href="/en/diritto-di-recesso">Right of withdrawal</a>.</li>
<li><strong>Historical photo archive</strong>: the descriptions of some photos are generated automatically (event name and recognised players) and may not be very descriptive.</li>
<li><strong>Text over photos and gradients</strong>: the automated check cannot measure its contrast; we check it visually and some may remain below the threshold.</li>
<li><strong>Maps and third-party content</strong>: Google maps, embedded videos (YouTube and similar) and the PayPal and Stripe payment pages depend on their providers and are outside our control.</li>
<li><strong>Testing with assistive technologies</strong>: the main journeys (menu, product page, cart, checkout with errors, withdrawal) are tested automatically with a simulated screen reader; testing with VoiceOver and NVDA by a person has not been completed yet.</li>
</ul>

<h2>How the assessment was carried out</h2>
<p>Self-assessment of 26 September 2026, with the automated tool axe-core and manual keyboard testing. Every week the automated check runs on the live site (main pages, product page, cart, withdrawal) and, on a test copy, also goes through a full cart, checkout with errors on screen, order confirmation and auction checkout. A sample of 38 archive news articles, from different years and in both languages, and the published PDF documents were checked separately.</p>

<h2>Feedback and requests</h2>
<p>If you encounter a barrier, or need content in an accessible format, write to <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a> stating the page and the problem. We reply within 30 days; if the problem concerns a purchase in progress, we will help you complete it by email or phone (+39 055 721503).</p>

<h2>Enforcement authority</h2>
<p>The Agency for Digital Italy (AgID) supervises the accessibility of e-commerce services. You can report a service that does not meet the requirements to AgID at any time, even without contacting us first: <a href="https://www.agid.gov.it" target="_blank" rel="noopener noreferrer">www.agid.gov.it</a>.</p>
HTML,
    ],
];
