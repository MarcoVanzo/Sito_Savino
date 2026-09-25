<?php

/**
 * Informativa sulle comunicazioni promozionali e informativa fornitori.
 *
 * Fino al 26/09/2026 erano due PDF caricati in Documenti Legali
 * (`legal.informativa_promozionale`, `legal.informativa_fornitori`): nel
 * footer stavano accanto alle altre informative, ma erano le uniche due che
 * aprivano un file in una scheda nuova invece di una pagina del sito, e si
 * modificavano da un'altra parte del pannello. Ora sono pagine come Privacy e
 * Cookie Policy, e da qui in poi sono della redazione (Pagine).
 *
 * Il testo è quello dei PDF (`Informativa generale Privacy.pdf` e
 * `Informativa-Fornitori.pdf`, versione 01/2021), con tre sole correzioni:
 *
 * - il titolare è scritto con la ragione sociale per esteso (§21 del
 *   CLAUDE.md), non «ssdrl»;
 * - gli indirizzi email sono tutti `privacy@savinodelbenevolley.it`: il PDF
 *   promozionale indicava anche `privacy@savinodelbene.com`, che è il dominio
 *   della Spa, e quello fornitori aveva uno spazio dentro l'indirizzo;
 * - dall'informativa fornitori è tolto il modulo di consenso con «Luogo e
 *   data» e «Firma»: si firma su carta, su una pagina web non ha senso.
 *
 * NON è un parere legale: il contenuto resta quello dei documenti della
 * società, e le revisioni di sostanza le decide chi ne segue la privacy.
 *
 * @return array<string, array{titolo: array<string, string>, descrizione: array<string, string>, firme: list<string>, contenuto: array<string, string>}>
 */
$titolare = 'Pallavolo Scandicci Savino Del Bene Società Sportiva Dilettantistica a Responsabilità Limitata';
$email = 'privacy@savinodelbenevolley.it';
$posta = "<a href=\"mailto:{$email}\">{$email}</a>";

return [
    'informativa-comunicazioni-promozionali' => [
        'titolo' => [
            'it' => 'Informativa comunicazioni promozionali',
            'en' => 'Promotional communications notice',
        ],
        'descrizione' => [
            'it' => 'Informativa privacy (art. 13 GDPR) sull\'invio di informazioni e promozioni della Savino Del Bene Volley via email, social network e WhatsApp.',
            'en' => 'Privacy notice (art. 13 GDPR) on Savino Del Bene Volley information and promotions sent by email, social networks and WhatsApp.',
        ],
        'firme' => [],
        'contenuto' => [
            'it' => <<<HTML
<p>Informativa ai sensi dell'art. 13 del Regolamento UE 2016/679 (GDPR).</p>
<h2>Titolare del trattamento</h2>
<p>Titolare del trattamento è {$titolare}, con sede in Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI), in persona del suo legale rappresentante. Il titolare può essere contattato all'indirizzo {$posta}.</p>
<h2>Finalità del trattamento</h2>
<p>Il titolare tratterà i tuoi dati personali (tra cui nome, cognome, indirizzo email, numero di telefono, account Instagram/Facebook) nel rispetto del Regolamento UE 2016/679, esclusivamente per l'invio di informazioni e promozioni sulle attività della società attraverso email, social network (ad esempio Instagram e Facebook) e WhatsApp.</p>
<p>I trattamenti sono svolti e i dati conservati dal titolare e da incaricati autorizzati; le operazioni possono essere effettuate con mezzi cartacei e con l'ausilio di mezzi informatici e/o elettronici, compresi dispositivi portatili, in ogni caso con le sole modalità strettamente necessarie alle attività indicate.</p>
<h2>Base giuridica del trattamento</h2>
<p>Il trattamento avviene solo se presti il consenso, ai sensi dell'art. 6 comma 1 lett. a) del Regolamento UE 2016/679. Se non presti il consenso, i tuoi dati non saranno trattati per queste finalità.</p>
<h2>Comunicazione dei dati</h2>
<p>Per svolgere correttamente le attività di trattamento necessarie alle finalità di questa informativa, possono trattare i tuoi dati personali i seguenti destinatari:</p>
<ul>
<li>società dell'informazione e di assistenza informatica.</li>
</ul>
<h2>Trasferimento dei dati fuori dall'UE</h2>
<p>I dati sono conservati in archivi cartacei, informatici ed elettronici situati all'interno dello Spazio economico europeo, con misure di sicurezza specifiche. Per le finalità indicate è possibile che i dati vengano trasferiti fuori dall'UE; in quel caso il trasferimento avviene con queste garanzie:</p>
<ul>
<li>clausole contrattuali standard che assicurano garanzie adeguate, anche per i diritti degli interessati;</li>
<li>decisione di adeguatezza della Commissione europea ai sensi dell'art. 45 del Regolamento UE 2016/679.</li>
</ul>
<p>Puoi chiedere l'elenco dei Paesi extra SEE scrivendo a {$posta}.</p>
<h2>Conservazione dei dati</h2>
<p>I dati personali trattati per le finalità indicate sono conservati per 24 mesi dalla data in cui hai prestato il consenso.</p>
<h2>Diritti dell'interessato</h2>
<p>In ogni momento, gratuitamente e senza formalità particolari, puoi:</p>
<ul>
<li>ottenere conferma del trattamento operato dal titolare;</li>
<li>accedere ai tuoi dati personali e conoscerne l'origine (quando non sono stati ottenuti direttamente da te), le finalità e gli scopi del trattamento, i soggetti a cui sono comunicati, il periodo di conservazione o i criteri per determinarlo;</li>
<li>revocare il consenso in qualunque momento, quando è la base del trattamento; la revoca non pregiudica la liceità del trattamento svolto prima;</li>
<li>aggiornare o rettificare i tuoi dati personali perché siano sempre esatti;</li>
<li>cancellare i tuoi dati personali dalle banche dati e dagli archivi, anche di backup, del titolare quando, tra l'altro, non sono più necessari per le finalità del trattamento o il trattamento è illecito, sempre che ne sussistano le condizioni di legge e il trattamento non sia giustificato da un altro motivo legittimo;</li>
<li>limitare il trattamento dei tuoi dati in alcune circostanze, ad esempio se ne hai contestato l'esattezza, per il tempo necessario al titolare per verificarla; sarai informato di quando il periodo di sospensione si è concluso o la causa della limitazione è venuta meno;</li>
<li>ottenere i tuoi dati personali, se trattati con il tuo consenso o sulla base di un contratto e con strumenti automatizzati, in formato elettronico, anche per trasmetterli a un altro titolare.</li>
</ul>
<p>Il titolare risponde senza ritardo e comunque entro un mese dalla richiesta. Il termine può essere prorogato di due mesi se necessario, tenuto conto della complessità e del numero delle richieste; in quel caso, entro un mese dalla richiesta, il titolare ti informa della proroga e dei motivi.</p>
<p>Puoi esercitare i diritti con una comunicazione scritta a {$posta} o con lettera raccomandata A/R alla sede della società: {$titolare}, all'attenzione del legale rappresentante Sergio Bazzurro, Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI) — tel. 055 721503.</p>
<h2>Diritto di opposizione</h2>
<p>Per motivi legati alla tua situazione particolare puoi opporti in ogni momento al trattamento dei tuoi dati personali, se è fondato sul legittimo interesse. Hai diritto alla cancellazione dei tuoi dati se non esiste un motivo legittimo prevalente rispetto a quello che ha dato origine alla richiesta. Il diritto si esercita con le stesse modalità indicate sopra.</p>
<h2>Diritto di proporre reclamo</h2>
<p>Fatta salva ogni altra azione in sede amministrativa o giudiziale, puoi presentare reclamo all'autorità di controllo competente — in Italia il Garante per la protezione dei dati personali — o a quella dello Stato membro in cui risiedi abitualmente, lavori o in cui è avvenuta la violazione del Regolamento (UE) 2016/679.</p>
HTML,
            'en' => <<<HTML
<p>Notice pursuant to art. 13 of EU Regulation 2016/679 (GDPR).</p>
<h2>Data controller</h2>
<p>The data controller is {$titolare}, with registered office at Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI), Italy, represented by its legal representative. The controller can be contacted at {$posta}.</p>
<h2>Purposes of processing</h2>
<p>The controller will process your personal data (including first name, surname, email address, telephone number, Instagram/Facebook account) in accordance with EU Regulation 2016/679, solely to send you information and promotions about the club's activities by email, social networks (for example Instagram and Facebook) and WhatsApp.</p>
<p>Processing is carried out and data are stored by the controller and by authorised persons; operations may be carried out on paper and with computer and/or electronic means, including portable devices, in any case only in the ways strictly necessary for the activities described.</p>
<h2>Legal basis</h2>
<p>Processing takes place only if you give your consent, pursuant to art. 6(1)(a) of EU Regulation 2016/679. If you do not give your consent, your data will not be processed for these purposes.</p>
<h2>Disclosure of data</h2>
<p>To carry out the processing needed for the purposes of this notice, the following recipients may process your personal data:</p>
<ul>
<li>information society and IT support companies.</li>
</ul>
<h2>Transfers outside the EU</h2>
<p>Data are kept in paper, computer and electronic archives located within the European Economic Area, with specific security measures. Personal data may be transferred outside the EU for the purposes described; in that case transfers take place with the following safeguards:</p>
<ul>
<li>standard contractual clauses ensuring adequate safeguards, including for the rights of data subjects;</li>
<li>an adequacy decision of the European Commission under art. 45 of EU Regulation 2016/679.</li>
</ul>
<p>You can ask for the list of non-EEA countries by writing to {$posta}.</p>
<h2>Data retention</h2>
<p>Personal data processed for the purposes above are kept for 24 months from the date you gave your consent.</p>
<h2>Your rights</h2>
<p>At any time, free of charge and without particular formalities, you can:</p>
<ul>
<li>obtain confirmation of the processing carried out by the controller;</li>
<li>access your personal data and learn their source (when not obtained directly from you), the purposes of processing, the recipients, the retention period or the criteria used to determine it;</li>
<li>withdraw your consent at any time, where it is the basis of processing; withdrawal does not affect the lawfulness of processing carried out before it;</li>
<li>update or correct your personal data so that they are always accurate;</li>
<li>have your personal data erased from the controller's databases and archives, including backups, where, among other cases, they are no longer necessary for the purposes of processing or processing is unlawful, provided the legal conditions are met and processing is not justified by another legitimate reason;</li>
<li>restrict the processing of your data in certain circumstances, for example where you contest their accuracy, for the time the controller needs to verify it; you will be informed when the restriction ends;</li>
<li>receive your personal data, where processed on the basis of your consent or a contract and by automated means, in electronic format, including in order to transmit them to another controller.</li>
</ul>
<p>The controller will reply without delay and in any case within one month of the request. This period may be extended by two months where necessary, taking into account the complexity and number of requests; in that case the controller will inform you of the extension and the reasons within one month of the request.</p>
<p>You can exercise your rights by writing to {$posta} or by registered letter with return receipt to the club's registered office: {$titolare}, for the attention of the legal representative Sergio Bazzurro, Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI), Italy — tel. +39 055 721503.</p>
<h2>Right to object</h2>
<p>For reasons relating to your particular situation you can object at any time to the processing of your personal data where it is based on legitimate interest. You have the right to have your data erased if there is no overriding legitimate reason. This right is exercised in the same ways described above.</p>
<h2>Right to lodge a complaint</h2>
<p>Without prejudice to any other administrative or judicial remedy, you can lodge a complaint with the competent supervisory authority — in Italy the Garante per la protezione dei dati personali — or with the authority of the Member State where you habitually reside, work or where the infringement of Regulation (EU) 2016/679 took place.</p>
HTML,
        ],
    ],

    'informativa-fornitori' => [
        'titolo' => [
            'it' => 'Informativa Fornitori',
            'en' => 'Supplier privacy notice',
        ],
        'descrizione' => [
            'it' => 'Informativa privacy (art. 13 GDPR) per clienti e fornitori della Savino Del Bene Volley: finalità, destinatari, conservazione e diritti.',
            'en' => 'Privacy notice (art. 13 GDPR) for Savino Del Bene Volley customers and suppliers: purposes, recipients, retention and rights.',
        ],
        'firme' => [],
        'contenuto' => [
            'it' => <<<HTML
<p>{$titolare}, con sede legale in Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI), in qualità di titolare del trattamento, in persona del legale rappresentante pro tempore, informa gli interessati sulle finalità e modalità del trattamento dei dati personali raccolti, sul loro ambito di comunicazione e diffusione e sulla natura del loro conferimento.</p>
<h2>Modalità di rilascio dell'informativa</h2>
<p>Ai sensi del Regolamento UE 2016/679 (GDPR), del D.Lgs. 196/2003 e di ogni altra normativa sulla protezione dei dati personali applicabile in Italia, compresi i provvedimenti del Garante (di seguito «Normativa Privacy»), la società assolve all'obbligo dell'art. 13 del GDPR rilasciando questa informativa al cliente-fornitore. L'informativa è consultabile sul sito web della società.</p>
<h2>Oggetto del trattamento</h2>
<p>Il titolare tratta i dati personali identificativi (ad esempio nome, cognome, ragione sociale, indirizzo, telefono, email, riferimenti bancari e di pagamento, codice fiscale — di seguito «dati personali» o «dati») che comunichi quando instauri o esegui rapporti contrattuali con il titolare. Il titolare può trattare anche i dati particolari che conferisci volontariamente per l'esecuzione delle prestazioni richieste: ti invitiamo a comunicarli solo se necessario. Se trasmetti categorie particolari di dati senza manifestare uno specifico consenso al loro trattamento, il titolare non potrà esserne ritenuto responsabile, perché in quel caso il trattamento è consentito in quanto riguarda dati resi manifestamente pubblici dall'interessato (art. 9 comma 2 lett. e) del GDPR). Ti ricordiamo comunque l'importanza di manifestare il consenso esplicito al trattamento delle categorie particolari di dati, se decidi di condividerle.</p>
<p>Nella prestazione di alcuni servizi il titolare potrebbe trattare dati personali di terzi che gli trasmetti (ad esempio dei tuoi dipendenti, clienti o fornitori). In questi casi sei autonomo titolare del trattamento e ne assumi gli obblighi e le responsabilità di legge, manlevando il titolare da ogni contestazione, pretesa o richiesta di risarcimento che dovesse arrivargli da terzi i cui dati siano stati trattati dopo una tua comunicazione in violazione delle norme applicabili. Garantisci inoltre, assumendone la responsabilità, che il trattamento dei dati di terzi che fornisci si fonda su un'idonea base giuridica ai sensi dell'art. 6 del GDPR.</p>
<p>Il titolare può trattare anche dati non forniti da te ma acquisiti altrove, anche senza il tuo consenso, ad esempio dall'Anagrafe Tributaria o da pubblici registri (Catasto, Registro delle imprese).</p>
<h2>Titolare del trattamento</h2>
<p>Il titolare del trattamento è {$titolare}, con sede legale in Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI), in persona del legale rappresentante pro tempore, a cui puoi rivolgerti in ogni momento scrivendo a {$posta}.</p>
<h2>Finalità del trattamento e base giuridica</h2>
<p>1. Il trattamento, previo tuo specifico consenso ove necessario, ha le seguenti finalità:</p>
<ol type="a">
<li>concludere contratti con il titolare;</li>
<li>adempiere agli obblighi precontrattuali, contrattuali e fiscali derivanti dai rapporti in essere;</li>
<li>consentire l'erogazione dei servizi e delle forniture richiesti;</li>
<li>rispondere a richieste di assistenza o di informazioni;</li>
<li>assolvere eventuali obblighi di legge, contabili e fiscali;</li>
<li>tutelare il diritto di credito e gli altri diritti del titolare relativi al singolo rapporto contrattuale;</li>
<li>adempiere agli altri obblighi previsti dalla legge, da un regolamento, dalla normativa comunitaria o da un ordine dell'autorità;</li>
<li>esercitare i diritti del titolare (ad esempio il diritto di difesa in giudizio);</li>
<li>salvaguardare il funzionamento dei sistemi informatici, compresi: backup e ripristino dei dati; registrazione e controllo delle transazioni per accertare la funzionalità dei sistemi; rilevazione e prevenzione di accessi non autorizzati; gestione di incidenti e problemi.</li>
</ol>
<p>2. Solo previo tuo consenso espresso (art. 7 GDPR), per inviarti comunicazioni con strumenti automatizzati (sms, mms, email, notifiche push, fax) e non (posta cartacea, telefono con operatore). Il titolare raccoglie un unico consenso per queste comunicazioni. Puoi opporti al trattamento o revocare il consenso in qualunque momento scrivendo ai recapiti di questa informativa, senza pregiudicare la liceità del trattamento svolto prima della revoca.</p>
<h2>Modalità di trattamento</h2>
<p>I dati sono trattati nel rispetto della normativa citata e degli obblighi di riservatezza del titolare, con strumenti informatici, su supporti cartacei e su ogni altro supporto idoneo, adottando le misure tecniche, organizzative e di sicurezza previste dal GDPR.</p>
<h2>Destinatari dei dati</h2>
<p>Ferme restando le comunicazioni dovute per obblighi di legge e contrattuali, i dati trattati dal titolare (anche come responsabile del trattamento di dati di cui resti titolare) non sono diffusi, cioè non sono portati a conoscenza di soggetti indeterminati in nessuna forma, neppure mettendoli a disposizione o in consultazione.</p>
<p>Nei limiti delle finalità indicate, i dati possono essere comunicati a dipendenti e collaboratori del titolare; partner professionali; società di factoring; istituti di credito; società di recupero crediti; società di assicurazione del credito; società di informazioni commerciali; professionisti e consulenti; soggetti incaricati o autorizzati dal titolare che gli forniscono servizi di elaborazione dati, consulenza o attività strumentali al rapporto contrattuale; e a tutti i soggetti a cui la comunicazione è dovuta per legge.</p>
<p>I dati non sono diffusi se non in forma anonima e aggregata, per finalità statistiche o di ricerca (ad esempio questionari ISTAT).</p>
<p>Per le finalità indicate i dati possono inoltre essere resi conoscibili a terzi che operano per conto della società, ad esempio:</p>
<ul>
<li>corrieri e spedizionieri incaricati della consegna di prodotti (ad esempio resi) e di documenti a te destinati;</li>
<li>società, consulenti o professionisti incaricati dell'installazione, manutenzione, aggiornamento e gestione degli hardware e software della società o di quelli di cui si serve per i propri servizi;</li>
<li>società o provider Internet incaricati dell'invio di documentazione o materiale informativo;</li>
<li>società incaricate dell'elaborazione o dell'invio di materiale pubblicitario e informativo per conto della società.</li>
</ul>
<p>Questi soggetti trattano i dati esclusivamente come responsabili esterni del trattamento per conto della società. L'elenco aggiornato dei responsabili del trattamento è disponibile presso la sede della società.</p>
<h2>Comunicazione dei dati</h2>
<p>Senza necessità di un consenso espresso (art. 6 lett. b) e c) GDPR), il titolare può comunicare i dati trattati per le finalità del punto 1 a organismi di vigilanza, autorità giudiziarie, società di assicurazione per la prestazione di servizi assicurativi e ai soggetti a cui la comunicazione è obbligatoria per legge. Questi soggetti trattano i dati come autonomi titolari del trattamento.</p>
<h2>Trasferimento dei dati all'estero</h2>
<p>I dati sono conservati su supporti cartacei custoditi in Italia e su server e apparati elettronici in Italia o comunque nell'Unione europea. Un eventuale trasferimento fuori dall'UE avverrà solo verso Paesi per cui la Commissione europea ha riconosciuto un livello adeguato di protezione dei dati.</p>
<h2>Periodo di conservazione</h2>
<p>I dati sono conservati per un tempo non superiore a quello necessario alle finalità per cui sono trattati (principio di limitazione della conservazione, art. 5 GDPR) o in base alle scadenze previste dalla legge. Il titolare li tratta per il tempo necessario alle finalità indicate e, dopo, per l'adempimento degli obblighi legali connessi al contratto: fino a 11 anni dalla cessazione del rapporto, cioè non oltre un anno dai termini di prescrizione dei diritti, con una cancellazione annuale entro il 31 dicembre.</p>
<h2>Natura del conferimento e conseguenze del rifiuto</h2>
<p>Il conferimento dei dati per le finalità del punto 1 è obbligatorio: senza, non possiamo garantire l'adempimento di quanto previsto al punto 1. Il conferimento per le finalità del punto 2 è facoltativo: puoi non conferire alcun dato o negare in seguito il trattamento di quelli già forniti.</p>
<h2>Diritti dell'interessato</h2>
<p>Come interessato hai i diritti dell'art. 15 GDPR, cioè di:</p>
<ol type="a">
<li>ottenere la conferma dell'esistenza di dati personali che ti riguardano, anche se non ancora registrati, e la loro comunicazione in forma intelligibile;</li>
<li>ottenere l'indicazione dell'origine dei dati; delle finalità e modalità del trattamento; della logica applicata al trattamento con strumenti elettronici; degli estremi identificativi del titolare e dei responsabili; dei soggetti o delle categorie di soggetti a cui i dati possono essere comunicati o che possono venirne a conoscenza;</li>
<li>ottenere l'aggiornamento, la rettificazione o, se hai interesse, l'integrazione dei dati; la cancellazione, la trasformazione in forma anonima o il blocco dei dati trattati in violazione di legge, compresi quelli di cui non è necessaria la conservazione; l'attestazione che queste operazioni sono state portate a conoscenza di chi ha ricevuto i dati, salvo che ciò sia impossibile o sproporzionato;</li>
<li>opporti, in tutto o in parte, per motivi legittimi al trattamento dei dati che ti riguardano, e al trattamento finalizzato a comunicarti via email, posta, sms, WhatsApp o telefono conferme o modifiche di appuntamenti e altre informazioni sui rapporti in essere: puoi scegliere di ricevere solo comunicazioni tradizionali, solo automatizzate, o nessuna delle due.</li>
</ol>
<p>Ove applicabili hai anche i diritti degli artt. 16-21 GDPR (rettifica, oblio, limitazione del trattamento, portabilità dei dati, opposizione) e il diritto di proporre reclamo al Garante per la protezione dei dati personali.</p>
<h2>Modalità di esercizio dei diritti</h2>
<p>Per esercitare i tuoi diritti scrivi a {$posta}.</p>
<p><em>Informativa Privacy — versione 01/2021.</em></p>
HTML,
            'en' => <<<HTML
<p>{$titolare}, with registered office at Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI), Italy, as data controller, represented by its legal representative pro tempore, informs data subjects of the purposes and methods of processing of the personal data collected, the scope of their disclosure and the nature of their provision.</p>
<h2>How this notice is provided</h2>
<p>Pursuant to EU Regulation 2016/679 (GDPR), Legislative Decree 196/2003 and any other data protection law applicable in Italy, including the measures of the Italian Data Protection Authority («Privacy Law»), the club fulfils the obligation of art. 13 GDPR by providing this notice to customers and suppliers. The notice is available on the club's website.</p>
<h2>Data processed</h2>
<p>The controller processes the identification data (for example name, surname, company name, address, telephone, email, bank and payment details, tax code — «personal data» or «data») that you provide when entering into or performing contracts with the controller. The controller may also process special categories of data that you voluntarily provide for the services you request: please provide them only where necessary. If you send special categories of data without specifically consenting to their processing, the controller cannot be held liable, since processing is then allowed as it concerns data manifestly made public by the data subject (art. 9(2)(e) GDPR). We nevertheless remind you of the importance of giving explicit consent to the processing of special categories of data if you decide to share them.</p>
<p>In providing some services the controller may process personal data of third parties that you send (for example your employees, customers or suppliers). In these cases you are an independent controller and bear the related legal obligations and responsibilities, holding the controller harmless against any claim from third parties whose data were processed after you disclosed them in breach of applicable law. You also warrant, under your own responsibility, that the processing of third-party data you provide rests on an appropriate legal basis under art. 6 GDPR.</p>
<p>The controller may also process data not provided by you but obtained elsewhere, even without your consent, for example from the tax register or public registers (land registry, companies register).</p>
<h2>Data controller</h2>
<p>The data controller is {$titolare}, with registered office at Via Benozzo Gozzoli 5/6, 50018 Scandicci (FI), Italy, represented by its legal representative pro tempore, whom you can contact at any time at {$posta}.</p>
<h2>Purposes and legal basis</h2>
<p>1. Processing, subject to your specific consent where required, has the following purposes:</p>
<ol type="a">
<li>entering into contracts with the controller;</li>
<li>fulfilling pre-contractual, contractual and tax obligations arising from existing relationships;</li>
<li>providing the services and supplies requested;</li>
<li>answering requests for assistance or information;</li>
<li>fulfilling legal, accounting and tax obligations;</li>
<li>protecting the controller's credit and other rights relating to the individual contract;</li>
<li>fulfilling other obligations under law, regulation, EU legislation or an order of an authority;</li>
<li>exercising the controller's rights (for example the right of defence in court);</li>
<li>safeguarding the operation of IT systems, including: backup and restore of data; logging and checking transactions to verify system functionality; detecting and preventing unauthorised access; managing incidents and problems.</li>
</ol>
<p>2. Only with your express consent (art. 7 GDPR), to send you communications by automated means (sms, mms, email, push notifications, fax) and non-automated means (post, telephone with an operator). The controller collects a single consent for these communications. You can object or withdraw your consent at any time by writing to the contacts in this notice, without affecting the lawfulness of processing carried out before withdrawal.</p>
<h2>Processing methods</h2>
<p>Data are processed in accordance with the law cited and the controller's confidentiality obligations, with computer tools, on paper and on any other suitable medium, adopting the technical, organisational and security measures required by the GDPR.</p>
<h2>Recipients</h2>
<p>Without prejudice to disclosures required by law and contract, data processed by the controller (also as processor of data of which you remain controller) are not disseminated, that is, not made known to unspecified parties in any form, including making them available or open to consultation.</p>
<p>Within the limits of the purposes stated, data may be disclosed to the controller's employees and collaborators; professional partners; factoring companies; banks; debt collection companies; credit insurance companies; business information companies; professionals and consultants; parties appointed or authorised by the controller to provide data processing, consultancy or activities instrumental to the contract; and to all parties to whom disclosure is required by law.</p>
<p>Data are not disseminated except in anonymous and aggregate form, for statistical or research purposes (for example ISTAT surveys).</p>
<p>For the purposes stated, data may also be made available to third parties acting on behalf of the club, for example:</p>
<ul>
<li>couriers and forwarders delivering products (for example returns) and documents addressed to you;</li>
<li>companies, consultants or professionals installing, maintaining, updating and managing the club's hardware and software or those it uses for its services;</li>
<li>companies or Internet providers sending documentation or information material;</li>
<li>companies processing or sending advertising and information material on behalf of the club.</li>
</ul>
<p>These parties process data exclusively as external processors on behalf of the club. The up-to-date list of processors is available at the club's registered office.</p>
<h2>Disclosure of data</h2>
<p>Without express consent (art. 6(b) and (c) GDPR), the controller may disclose data processed for the purposes of point 1 to supervisory bodies, judicial authorities, insurance companies for the provision of insurance services and to parties to whom disclosure is mandatory by law. These parties process data as independent controllers.</p>
<h2>Transfers abroad</h2>
<p>Data are kept on paper in Italy and on servers and electronic equipment in Italy or in any case within the European Union. Any transfer outside the EU will only be to countries for which the European Commission has recognised an adequate level of data protection.</p>
<h2>Retention period</h2>
<p>Data are kept no longer than necessary for the purposes for which they are processed (storage limitation principle, art. 5 GDPR) or according to legal deadlines. The controller processes them for the time needed for the purposes stated and, afterwards, to fulfil the legal obligations connected with the contract: up to 11 years after the end of the relationship, that is no more than one year after the limitation periods, with annual erasure by 31 December.</p>
<h2>Nature of provision and consequences of refusal</h2>
<p>Providing data for the purposes of point 1 is mandatory: without them we cannot guarantee what is provided for in point 1. Providing data for the purposes of point 2 is optional: you may provide no data or later refuse processing of data already provided.</p>
<h2>Your rights</h2>
<p>As a data subject you have the rights of art. 15 GDPR, namely to:</p>
<ol type="a">
<li>obtain confirmation of whether personal data concerning you exist, even if not yet recorded, and their communication in intelligible form;</li>
<li>obtain information on the source of the data; the purposes and methods of processing; the logic applied to processing by electronic means; the identity of the controller and processors; the parties or categories of parties to whom data may be disclosed or who may learn of them;</li>
<li>obtain the updating, rectification or, where you have an interest, completion of the data; the erasure, anonymisation or blocking of data processed unlawfully, including data whose retention is not necessary; confirmation that these operations have been notified to those who received the data, unless this is impossible or disproportionate;</li>
<li>object, in whole or in part, on legitimate grounds to the processing of data concerning you, and to processing aimed at sending you, by email, post, sms, WhatsApp or telephone, confirmations or changes of appointments and other information about existing relationships: you may choose to receive only traditional communications, only automated ones, or neither.</li>
</ol>
<p>Where applicable you also have the rights of arts. 16-21 GDPR (rectification, erasure, restriction of processing, data portability, objection) and the right to lodge a complaint with the Italian Data Protection Authority (Garante per la protezione dei dati personali).</p>
<h2>How to exercise your rights</h2>
<p>To exercise your rights, write to {$posta}.</p>
<p><em>Privacy notice — version 01/2021.</em></p>
HTML,
        ],
    ],
];
