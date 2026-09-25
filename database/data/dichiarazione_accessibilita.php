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
 * la migrazione 2026_09_26_110000_dichiarazione_di_accessibilita_rivista,
 * solo se la redazione non l'ha ancora toccata.
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
<li>Le animazioni della homepage (presentazione, video, particelle, striscia delle foto) si fermano con il pulsante «Ferma le animazioni» e restano ferme se nel sistema è attiva l'opzione «riduci movimento».</li>
<li>Ogni pagina dichiara la propria lingua (italiano o inglese) e lo zoom del browser non è bloccato.</li>
<li>Le informazioni su prezzi, spese di spedizione, recesso e garanzia sono in pagine di testo, non in immagini.</li>
</ul>

<h2>Contenuti non ancora accessibili</h2>
<ul>
<li><strong>Documenti PDF</strong> caricati negli anni (cartelle stampa, regolamenti, documenti societari): non sono stati verificati e alcuni potrebbero non essere leggibili con uno screen reader. Su richiesta ne forniamo una versione accessibile.</li>
<li><strong>Notizie dell'archivio</strong> importate dal sito precedente: alcune immagini dentro il testo possono non avere un testo alternativo e alcuni titoli interni possono non seguire l'ordine corretto.</li>
<li><strong>Archivio fotografico storico</strong>: le descrizioni di parte delle foto sono generate in automatico (nome dell'evento e delle atlete riconosciute) e possono essere poco descrittive.</li>
<li><strong>Testi sopra fotografie e sfumature</strong>: il controllo automatico non riesce a misurarne il contrasto; li verifichiamo a vista e alcuni potrebbero restare sotto la soglia.</li>
<li><strong>Mappe e contenuti di terze parti</strong>: le mappe di Google e i video incorporati (YouTube e simili) e le pagine di pagamento di PayPal e Stripe dipendono dai rispettivi fornitori e non sono sotto il nostro controllo.</li>
<li><strong>Checkout e pagamento</strong>: il controllo automatico settimanale non raggiunge le pagine che richiedono un carrello pieno o un'asta vinta; le verifichiamo a mano quando cambiano.</li>
<li><strong>Verifica con tecnologie assistive</strong>: la verifica è stata fatta con strumenti automatici e con la tastiera; la prova completa con gli screen reader non è ancora conclusa.</li>
</ul>

<h2>Come è stata fatta la valutazione</h2>
<p>Autovalutazione del 26 settembre 2026, con lo strumento automatico axe-core sulle pagine principali del sito e dello shop e con prove manuali da tastiera. Lo stesso controllo automatico gira ogni settimana sul sito pubblicato.</p>

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
<li>Homepage animations (slideshow, video, particles, photo strip) stop with the "Pause animations" button and stay still if "reduce motion" is enabled on your device.</li>
<li>Every page declares its language (Italian or English) and browser zoom is not blocked.</li>
<li>Information on prices, shipping costs, withdrawal and guarantee is provided as text, not images.</li>
</ul>

<h2>Content that is not yet accessible</h2>
<ul>
<li><strong>PDF documents</strong> uploaded over the years (press kits, regulations, club documents) have not been checked and some may not be readable with a screen reader. We provide an accessible version on request.</li>
<li><strong>Archive news</strong> imported from the previous website: some images in the text may lack alternative text and some internal headings may not follow the correct order.</li>
<li><strong>Historical photo archive</strong>: the descriptions of some photos are generated automatically (event name and recognised players) and may not be very descriptive.</li>
<li><strong>Text over photos and gradients</strong>: the automated check cannot measure its contrast; we check it visually and some may remain below the threshold.</li>
<li><strong>Maps and third-party content</strong>: Google maps, embedded videos (YouTube and similar) and the PayPal and Stripe payment pages depend on their providers and are outside our control.</li>
<li><strong>Checkout and payment</strong>: the weekly automated check does not reach pages that need a full cart or a won auction; we check them manually whenever they change.</li>
<li><strong>Testing with assistive technologies</strong>: testing was carried out with automated tools and with the keyboard; full testing with screen readers has not been completed yet.</li>
</ul>

<h2>How the assessment was carried out</h2>
<p>Self-assessment of 26 September 2026, with the automated tool axe-core on the main pages of the site and shop and with manual keyboard testing. The same automated check runs every week on the live site.</p>

<h2>Feedback and requests</h2>
<p>If you encounter a barrier, or need content in an accessible format, write to <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a> stating the page and the problem. We reply within 30 days; if the problem concerns a purchase in progress, we will help you complete it by email or phone (+39 055 721503).</p>

<h2>Enforcement authority</h2>
<p>The Agency for Digital Italy (AgID) supervises the accessibility of e-commerce services. You can report a service that does not meet the requirements to AgID at any time, even without contacting us first: <a href="https://www.agid.gov.it" target="_blank" rel="noopener noreferrer">www.agid.gov.it</a>.</p>
HTML,
    ],
];
