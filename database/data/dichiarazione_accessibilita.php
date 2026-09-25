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
 * nessuna dichiarazione.
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
<p>Pallavolo Scandicci Savino Del Bene S.S.D. a r.l. si impegna a rendere il proprio sito e lo shop online accessibili a tutte le persone, comprese le persone con disabilità, in conformità al decreto legislativo 27 maggio 2022, n. 82, che recepisce la direttiva (UE) 2019/882 (European Accessibility Act).</p>

<h2>Il servizio</h2>
<p>Questa dichiarazione riguarda il sito della Savino Del Bene Volley e il suo shop online, dove si acquistano prodotti ufficiali e si partecipa alle aste di beneficenza: consultazione del catalogo, carrello, checkout, pagamento, area personale, recesso online e aste.</p>

<h2>Norma di riferimento e stato di conformità</h2>
<p>Il riferimento tecnico è la norma europea EN 301 549, che per i siti web coincide con le Web Content Accessibility Guidelines (WCAG) 2.1, livello AA. Il sito è <strong>parzialmente conforme</strong>: la maggior parte dei requisiti è rispettata, con le eccezioni elencate più avanti.</p>

<h2>Come il servizio soddisfa i requisiti</h2>
<ul>
<li>Tutte le funzioni, compresi carrello, checkout e recesso, si usano con la sola tastiera; il focus è sempre visibile e i pannelli a comparsa lo trattengono finché sono aperti.</li>
<li>Un collegamento «Vai al contenuto» permette di saltare il menu; a ogni cambio di pagina il titolo nuovo viene annunciato agli screen reader.</li>
<li>Testi e controlli rispettano il contrasto minimo di 4,5:1: i colori del brand sono stati adattati dove servivano.</li>
<li>Le immagini hanno un testo alternativo; le icone senza testo hanno un'etichetta.</li>
<li>Nei moduli ogni campo ha la sua etichetta, gli errori sono collegati al campo a cui si riferiscono e il cursore si sposta sul primo campo da correggere.</li>
<li>Video e presentazioni della homepage si possono mettere in pausa, e restano fermi se nel sistema è attiva l'opzione «riduci movimento».</li>
<li>Ogni pagina dichiara la propria lingua (italiano o inglese) e lo zoom del browser non è bloccato.</li>
<li>Le informazioni su prezzi, spese di spedizione, recesso e garanzia sono in pagine di testo, non in immagini.</li>
</ul>

<h2>Contenuti non ancora accessibili</h2>
<ul>
<li><strong>Documenti PDF</strong> caricati negli anni (cartelle stampa, regolamenti, documenti societari): non sono stati tutti verificati e alcuni potrebbero non essere leggibili con uno screen reader. Su richiesta ne forniamo una versione accessibile.</li>
<li><strong>Archivio fotografico storico</strong>: le descrizioni di parte delle foto sono generate in automatico (nome dell'evento e delle atlete riconosciute) e possono essere poco descrittive.</li>
<li><strong>Contenuti di terze parti</strong>: video di YouTube, mappe di Google e le pagine di pagamento di PayPal e Stripe dipendono dai rispettivi fornitori.</li>
<li><strong>Verifica con tecnologie assistive</strong>: la verifica è stata fatta con strumenti automatici e con la tastiera; la prova completa con gli screen reader è in corso.</li>
</ul>

<h2>Come è stata fatta la valutazione</h2>
<p>Autovalutazione del 26 settembre 2026, con lo strumento automatico axe-core sulle pagine principali del sito e dello shop e con prove manuali da tastiera. Lo stesso controllo automatico gira ogni settimana sul sito pubblicato.</p>

<h2>Segnalazioni e richieste</h2>
<p>Se incontri un ostacolo, o ti serve un contenuto in un formato accessibile, scrivi a <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a> indicando la pagina e il problema. Rispondiamo entro 30 giorni; se il problema riguarda un acquisto in corso, ti aiutiamo a completarlo per email o per telefono (+39 055 721503).</p>

<h2>Autorità di vigilanza</h2>
<p>Se la risposta non è soddisfacente o non arriva entro 30 giorni, puoi rivolgerti all'Agenzia per l'Italia Digitale (AgID), che vigila sull'accessibilità dei servizi di commercio elettronico: <a href="https://www.agid.gov.it" target="_blank" rel="noopener noreferrer">www.agid.gov.it</a>.</p>
HTML,
        'en' => <<<'HTML'
<p><em>Last updated 26 September 2026.</em></p>
<p>Pallavolo Scandicci Savino Del Bene S.S.D. a r.l. is committed to making its website and online shop accessible to everyone, including people with disabilities, in accordance with Italian Legislative Decree 82/2022, which transposes Directive (EU) 2019/882 (European Accessibility Act).</p>

<h2>The service</h2>
<p>This statement covers the Savino Del Bene Volley website and its online shop, where official products are sold and charity auctions are held: product catalogue, cart, checkout, payment, personal area, online withdrawal and auctions.</p>

<h2>Standard and compliance status</h2>
<p>The technical reference is the European standard EN 301 549, which for websites corresponds to the Web Content Accessibility Guidelines (WCAG) 2.1, level AA. The site is <strong>partially compliant</strong>: most requirements are met, with the exceptions listed below.</p>

<h2>How the service meets the requirements</h2>
<ul>
<li>All functions, including cart, checkout and withdrawal, can be used with the keyboard alone; focus is always visible and pop-up panels keep it inside while open.</li>
<li>A "Skip to content" link bypasses the menu; on every page change the new title is announced to screen readers.</li>
<li>Text and controls meet the minimum contrast of 4.5:1: brand colours have been adjusted where needed.</li>
<li>Images have alternative text; icons without text have a label.</li>
<li>In forms every field has a label, errors are linked to the field they refer to and the cursor moves to the first field to correct.</li>
<li>Videos and slideshows on the homepage can be paused, and stay still if "reduce motion" is enabled on your device.</li>
<li>Every page declares its language (Italian or English) and browser zoom is not blocked.</li>
<li>Information on prices, shipping costs, withdrawal and guarantee is provided as text, not images.</li>
</ul>

<h2>Content that is not yet accessible</h2>
<ul>
<li><strong>PDF documents</strong> uploaded over the years (press kits, regulations, club documents) have not all been checked and some may not be readable with a screen reader. We provide an accessible version on request.</li>
<li><strong>Historical photo archive</strong>: the descriptions of some photos are generated automatically (event name and recognised players) and may not be very descriptive.</li>
<li><strong>Third-party content</strong>: YouTube videos, Google maps and the PayPal and Stripe payment pages depend on their providers.</li>
<li><strong>Testing with assistive technologies</strong>: testing was carried out with automated tools and with the keyboard; full testing with screen readers is in progress.</li>
</ul>

<h2>How the assessment was carried out</h2>
<p>Self-assessment of 26 September 2026, with the automated tool axe-core on the main pages of the site and shop and with manual keyboard testing. The same automated check runs every week on the live site.</p>

<h2>Feedback and requests</h2>
<p>If you encounter a barrier, or need content in an accessible format, write to <a href="mailto:info@savinodelbenevolley.it">info@savinodelbenevolley.it</a> stating the page and the problem. We reply within 30 days; if the problem concerns a purchase in progress, we will help you complete it by email or phone (+39 055 721503).</p>

<h2>Enforcement authority</h2>
<p>If the reply is not satisfactory or does not arrive within 30 days, you can contact the Agency for Digital Italy (AgID), which supervises the accessibility of e-commerce services: <a href="https://www.agid.gov.it" target="_blank" rel="noopener noreferrer">www.agid.gov.it</a>.</p>
HTML,
    ],
];
