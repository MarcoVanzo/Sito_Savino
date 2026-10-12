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

Sponsor → **Diretta e sponsor**: l'interruttore «Sponsor nella diretta».
Spento, la diretta sul sito è quella di sempre. Nella stessa pagina ci sono i
valori di partenza per ogni gara nuova (ogni quanti minuti compare la cornice,
per quanti secondi, quanti minuti dopo l'inizio della gara, durata degli spot
mandati dalla regia).

## 2. Caricare spot e cornici

Sponsor → **Spot nella diretta** → Nuova.

- **Dove va in onda**: «Prima della diretta» (pre-roll) o «Cornice durante la
  gara».
- **Tipo**: video o immagine (non si cambia dopo il caricamento: per cambiare
  tipo si crea un'altra creatività).
- **Testo alternativo**: cosa mostra la creatività, per chi non vede le
  immagini. Obbligatorio per le immagini.
- **Link dello sponsor**: si apre in una nuova scheda, senza fermare la diretta.
- **Validità**: attiva sì/no e date «dal/al»; fuori dalle date la creatività
  non va in onda.

Il pannello controlla i file al caricamento e dice cosa non va (misure, durata,
peso). Dall'elenco si scarica il **modello della cornice**
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
parte spento; chi chiede meno animazioni nel sistema vede lo spot fermo.

## 4. Preparare la diretta di una gara

Calendario e Risultati → la partita → **Diretta e sponsor** (in alto, compare
con l'interruttore acceso; serve il link della diretta nella gara):

- **Spot prima della diretta**: sì/no, quali spot (uno per visione, a
  rotazione) e dopo quanti secondi si può saltare (0 = non si salta);
- **Cornici in rotazione**: quelle che compaiono durante la gara, nell'ordine;
- **Ciclo automatico**: ogni quanti minuti, per quanti secondi, quanti minuti
  dopo l'inizio. L'anteprima dice a che ore cadranno i primi cicli. Il ciclo
  finisce tre ore dopo l'inizio della gara.

Salvando, il sito riceve le impostazioni entro una decina di secondi.

## 5. Regia dal telefono (facoltativa)

Sponsor → **Regia diretta**, dal telefono a bordo campo. Si apre sulla gara di
oggi; in alto lo stato, il conto alla rovescia dello spot in corso e l'ora del
prossimo ciclo.

- **Pausa tra i set**: le cornici in rotazione, per la durata predefinita.
  Intanto il ciclo automatico tace.
- **Mostra…**: una cornice scelta, per i secondi indicati.
- **Fine pausa**: chiude subito lo spot in corso.
- **Sospendi / Riprendi ciclo**: ferma o riavvia il ciclo automatico.
- **Riparti da adesso**: il ciclo riprende a contare da ora (il primo sponsor
  esce fra un intervallo).
- **Spegni tutto** (chiede conferma): nessuno sponsor durante la gara finché
  non si preme **Riaccendi**. Il pre-roll resta.

Il pubblico vede il cambio entro circa dieci secondi. Ogni azione resta nel
registro delle attività con il nome di chi l'ha fatta.

## 6. Report per lo sponsor

Sponsor → **Report diretta**: per gara e per creatività, pre-roll avviati,
visti fino in fondo e saltati, tasso di completamento, cornici mostrate (ciclo
e regia) e clic. Si filtra per gara e sponsor; **Esporta CSV** scarica il file
da mandare allo sponsor (si apre in Excel).

I numeri sono contatori anonimi: nessun dato di chi guarda, nessun cookie.

## Per chi sviluppa

Regole che non si rompono: `CLAUDE.md` §28. Pezzi principali:
`App\Support\Diretta\*`, `App\Services\Diretta\*`, `LiveCreative`,
`LiveSession`, `LiveAdStat`, `RegiaDiretta`, `ReportSponsorDiretta`,
`resources/js/Components/DirettaConSponsor.vue`,
`resources/js/Support/direttaConSponsor.js` e `cicloDellaCornice.js`.
