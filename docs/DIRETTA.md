# Diretta con sponsor — guida per la redazione

Chi guarda la diretta di una partita dal sito vede gli sponsor in due momenti:

1. **Prima della diretta (pre-roll)**: dopo «Guarda la diretta» parte uno spot
   breve dello sponsor nel player del sito, poi la diretta. Si può saltare dopo
   qualche secondo; chi riapre la stessa gara nella stessa scheda non lo
   rivede.
2. **Durante la gara (cornice)**: ogni tanto il riquadro della diretta si
   riduce in alto a sinistra e accanto compare lo sponsor (colonna a destra e
   fascia sotto), poi la diretta torna grande. Sul telefono la diretta resta
   intera e lo sponsor compare in una fascia sotto.

La diretta di YouTube resta sempre visibile, udibile e comandabile: le regole
di YouTube vietano di coprirla, quindi lo sponsor sta **prima** o **accanto**,
mai sopra. Funziona tutto anche senza nessuno in regia.

## 1. Accendere la funzione

Sponsor → **Diretta**, in fondo alla pagina: l'interruttore «Sponsor nella
diretta».
Spento, la diretta sul sito è quella di sempre, anche per chi la sta già
guardando (gli sponsor spariscono entro una decina di secondi): è anche il
freno d'emergenza. Nella stessa pagina ci sono i
valori di partenza per ogni gara nuova (ogni quanti minuti compare la cornice,
per quanti secondi, quanti minuti dopo l'inizio della gara, durata degli spot
mandati dalla regia).

## 2. Caricare spot e cornici

Sponsor → I Nostri Sponsor → lo sponsor → sezione **Grafiche per la
diretta** → «Nuova grafica». Ogni sponsor ha le sue.

Per grafiche che non sono di uno sponsor (per esempio la cornice dello shop
ufficiale) si crea uno sponsor con **Solo diretta** acceso: va in onda come gli
altri ma non compare nella pagina Sponsor né nella striscia della home.

- **Dove va in onda**: «Prima della diretta» (pre-roll) o «Cornice durante la
  gara».
- **Tipo**: video o immagine. Tipo e «Dove va in onda» non si cambiano dopo il
  caricamento: per cambiarli si crea un'altra creatività.
- **Testo alternativo**: cosa mostra la creatività, per chi non vede le
  immagini. Obbligatorio per le immagini.
- **Link dello sponsor**: si apre in una nuova scheda, senza fermare la diretta.
- **Validità**: attiva sì/no e date «dal/al»; fuori dalle date la creatività
  non va in onda.

Il pannello controlla i file al caricamento e dice cosa non va (misure, durata,
peso, audio nelle cornici, video ruotati, MP4 «frammentati» di cui non si legge
la durata: vanno esportati come MP4 normali). Dalla stessa sezione si scarica il **modello della cornice**
(`modello-cornice-1920x1080.png`) con le zone segnate, da mandare allo sponsor.

## 3. Formati da chiedere allo sponsor

| Uso | Formato | Misure |
|---|---|---|
| Pre-roll | MP4 H.264/AAC fino a 30 s e 20 MB, oppure JPG/PNG fino a 2 MB | 1920×1080 px |
| Cornice | JPG/PNG fino a 2 MB, oppure MP4 senza audio fino a 30 s e 20 MB | 1920×1080 px |
| Fascia per il telefono (facoltativa, nella cornice) | JPG/PNG fino a 1 MB | 1080×300 px |

**Cornice**: l'area di 1440×810 px in alto a sinistra resta coperta dalla
diretta, va riempita di un colore neutro. Si vedono la **colonna a destra**
(480×810 px) e la **fascia sotto** (1920×270 px): marchio, messaggio e link
vanno lì. Senza fascia per il telefono, sul telefono si mostra la fascia sotto
della cornice.

Da evitare: lampeggi e cambi di colore rapidi, testi troppo piccoli (la
colonna sul sito è larga circa 240 px su un portatile). L'audio dello spot
parte spento; chi chiede meno animazioni nel sistema vede lo spot fermo (con
«Guarda lo spot» per farlo partire). Spot e cornici video hanno un pulsante di
pausa. Se il telefono non fa partire lo spot (risparmio energetico), dura come
un'immagine e poi parte la diretta: lo spot non blocca mai la partita.

## 4. Preparare la diretta di una gara

Sponsor → **Diretta**: in alto l'elenco delle partite della prima squadra (A1, da due
giorni fa in avanti; togliendo il filtro anche le passate). Sulla partita,
**Imposta**:

- **Link della diretta**: l'indirizzo YouTube (o Vimeo, Twitch, Dailymotion)
  della partita. Vale anche con gli sponsor spenti: è la diretta del sito.
  Da promuovere c'è l'indirizzo fisso **`/diretta`**: porta sempre alla gara
  della prima squadra (A1) con il link, in corso (fino a tre ore dall'inizio) o la
  prossima; senza, dice che non c'è una diretta e rimanda al calendario;
- **Sponsor**: «Aggiungi uno sponsor», sceglierlo e spuntare le sue grafiche:
  gli spot da mandare prima della diretta e le cornici per la gara. Si possono
  aggiungere più sponsor: ruotano nell'ordine dell'elenco (si trascinano);
- **Spot prima della diretta**: sì/no e dopo quanti secondi si può saltare
  (0 = non si salta). Uno spot per visione, a rotazione;
- **Ciclo automatico**: ogni quanti minuti, per quanti secondi, quanti minuti
  dopo l'inizio. L'anteprima dice a che ore cadranno i primi cicli. Il ciclo
  finisce tre ore dopo l'inizio della gara e segue la gara se cambia orario.

Salvando, il sito riceve le impostazioni entro una decina di secondi.

### Aprire la diretta da sola in home

Sponsor → Diretta, «Apri la diretta da sola»: a gara della prima squadra
cominciata (fino a tre ore dal fischio d'inizio, non a gara finita) chi apre
la homepage trova la diretta già aperta, senza audio. Una volta per scheda.
Vale solo se:

- il link è un video **YouTube** e YouTube conferma che non è bloccato in
  nessun paese (le dirette che la Lega limita per i diritti tv restano col
  pulsante, senza nemmeno il pop-up «Guarda la diretta»: chi sta in quei
  paesi vedrebbe «video non disponibile»). Il sito lo chiede a YouTube col service account di GA4: nel
  progetto Google va abilitata la «YouTube Data API v3»; se YouTube non
  risponde, la diretta non si apre da sola;
- il visitatore ha accettato i cookie di marketing (YouTube non si carica
  senza) e non ha chiesto al sistema meno movimento: altrimenti vede il pop-up
  di sempre.

Il sito non fa aspettare la home per chiedere a YouTube: ricontrolla ogni
dieci minuti, dopo aver risposto. All'inizio della gara la diretta comincia ad
aprirsi da sola entro un paio di minuti dalle prime visite.

## 5. Regia dal telefono (facoltativa)

Sponsor → **Regia diretta**, dal telefono a bordo campo. Si apre sulla prima
gara non ancora finita che ha la diretta impostata; in alto lo stato, il conto alla rovescia dello spot in corso e l'ora del
prossimo ciclo.

- **Pausa tra i set**: le cornici in rotazione, per la durata predefinita.
  Intanto il ciclo automatico tace. Spenta se nessuna cornice della gara è
  valida oggi.
- **Mostra…**: una cornice scelta, per i secondi indicati.
- **Fine pausa**: chiude subito lo spot in corso.
- **Sospendi / Riprendi ciclo**: ferma o riavvia il ciclo automatico.
- **Riparti da adesso**: il ciclo riprende a contare da ora (il primo sponsor
  esce fra un intervallo).
- **Spegni tutto** (chiede conferma): nessuno sponsor durante la gara finché
  non si preme **Riaccendi**, che riaccende anche il ciclo. Il pre-roll resta.

Il pubblico vede il cambio entro circa dieci secondi. Ogni azione resta nel
registro delle attività con il nome di chi l'ha fatta.

Se l'archivio dei file non risponde, il cambio si salva ma non arriva a chi
guarda: la regia lo dice in rosso con **Ripubblica sul sito**, e comunque
riprova da sola ogni minuto.

## 6. Report per lo sponsor

Sponsor → **Report diretta**: per gara e per creatività, pre-roll avviati,
visti fino in fondo e saltati, tasso di completamento, cornici mostrate (ciclo
e regia) e clic. Si filtra per gara e sponsor; **Esporta CSV** scarica il file
da mandare allo sponsor (si apre in Excel).

I numeri sono contatori anonimi: nessun dato di chi guarda, nessun cookie. Si
contano solo attorno alla gara (da un'ora prima a un'ora dopo la fine del
ciclo) e solo eventi coerenti con la grafica (un «completato» solo per uno
spot del pre-roll). Uno spot video che non parte (risparmio energetico,
«riduci movimento») non conta né come avviato né come completato. Cancellare una grafica o uno sponsor non cancella il
report: le righe restano con il loro nome. Meglio comunque spegnerle che
cancellarle.

## Per chi sviluppa

Regole che non si rompono: `CLAUDE.md` §28. Pezzi principali:
`App\Support\Diretta\*`, `App\Services\Diretta\*`, `LiveCreative`,
`LiveSession`, `LiveAdStat`, `RegiaDiretta`, `ReportSponsorDiretta`,
`resources/js/Components/DirettaConSponsor.vue`,
`resources/js/Support/direttaConSponsor.js` e `cicloDellaCornice.js`.
