# Rebuild the mockup section with 31 pages in logical order

pages = [
    # SEZIONE SPORTIVA
    (1, "Homepage", "mockup_v2_home_1781617540180.png",
     "Biglietto da visita del club, con un forte impatto visivo (hero image a tutto schermo con Avery Skinner in azione), conto alla rovescia per la prossima partita e focus immediato sulle call-to-action principali (Biglietti, Roster)."),
    (2, "Roster Serie A1 (2026/2027)", "mockup_v3_roster_a1_1782214504319.png",
     "Foto di squadra a tutta larghezza come copertina, seguita dalla griglia delle atlete ufficiali per la nuova stagione: Ognjenović, Eze, Van Ryk, Van Hecke, Skinner, D'Odorico, Bosetti, Bergmann, Alberti, Graziani, Nwakalor, Armini, Sirressi. Ogni card mostra foto, nome, numero di maglia e ruolo."),
    (3, "Staff Tecnico e Medico", "mockup_v3_staff_1782214576676.png",
     "Pagina separata dal Roster dedicata esclusivamente allo staff, con struttura ispirata a Verona Volley Team: Staff Tecnico (head coach in evidenza, poi vice e assistenti) e Staff Medico (medico sociale e fisioterapisti)."),
    (4, "Roster Serie B1", "mockup_v2_roster_b1_1781700883750.png",
     "Stessa struttura del Roster A1: foto di squadra come copertina e griglia delle atlete tutte sulla stessa pagina. Lo staff di questa squadra è incluso nella pagina Staff generale."),
    (5, "Risultati e Classifica", "mockup_v2_risultati_1781617563737.png",
     "Doppia sezione: in alto gli ultimi 5 risultati del club e la classifica (con il team sempre evidenziato), in basso l'archivio completo di tutti i risultati della stagione."),
    (6, "News &amp; Comunicati", "mockup_v2_news_1781617766112.png",
     "Layout editoriale moderno, con la notizia di punta a tutta larghezza e le altre organizzate in una griglia ordinata con badge di categoria."),
    (7, "Foto Gallery", "mockup_v2_gallery_1781617729698.png",
     "Layout masonry immersivo, ideale per valorizzare gli scatti di gioco e del tifo. Filtri rapidi superiori per navigare tra gli eventi."),

    # CLUB E ISTITUZIONALE
    (8, "Organigramma Società", "mockup_v3_organigramma_1782214524130.png",
     "Layout a lista semplice e pulita, senza foto e senza struttura ad albero. Organizzazione per aree (Presidenza, Direzione Generale, Area Sportiva, Area Comunicazione, Area Commerciale) con nomi e ruoli."),
    (9, "Storia del Club", "mockup_v2_storia_1781617789545.png",
     "Una timeline emozionale verticale che alterna foto storiche (con trattamenti vintage) a testi che celebrano i trofei e le milestone societarie."),
    (10, "Contatti", "mockup_v3_contatti_1782214533231.png",
     "Elenco diretto dei referenti delle varie aree (Segreteria, Area Sportiva, Biglietteria, Comunicazione, Commerciale). Ogni scheda mostra nome, telefono, email e orari. Nessun modulo contatti: informazioni dirette e immediate."),
    (11, "Il Palazzetto", "mockup_v3_palazzetto_1782214545427.png",
     "Pagina dedicata al palazzetto, separata dai Contatti. Include foto della struttura, indirizzo e capienza, mappa Google integrata, indicazioni stradali (auto, bus, treno) e informazioni parcheggi."),

    # BIGLIETTERIA E TIFOSI
    (12, "Campagna Abbonamenti", "mockup_v3_abbonamenti_1782214588503.png",
     "Grafica di lancio della campagna in cima alla pagina. Segue la sezione pricing con 3 tier (Tribuna, Parterre, VIP), la pianta dei settori del palazzetto, i vantaggi per gli abbonati e le fasi di prelazione."),
    (13, "Biglietteria", "mockup_v3_biglietteria_1782214674702.png",
     "Acquisto biglietto singolo: elenco delle prossime partite in casa con data, avversario, orario e pulsante di acquisto diretto. In basso, tabella prezzi per settore."),
    (14, "Convenzioni", "mockup_v3_convenzioni_1782214664007.png",
     "Elenco delle convenzioni riservate agli abbonati: ogni partner commerciale è presentato con logo, nome, percentuale di sconto e categoria (Sport, Ristorazione, Salute, ecc.)."),
    (15, "Accessibilità", "mockup_v2_accessibilita_1781700849193.png",
     "Layout pulito e inclusivo (font ad alta leggibilità, alto contrasto). Spiega in modo inequivocabile i servizi per disabili e policy cani guida."),

    # SETTORE GIOVANILE E TERRITORIO
    (16, "Settore Giovanile", "mockup_v3_giovanile_1782214599225.png",
     "Layout semplificato: sezioni sequenziali per ogni fascia d'età (dal Promozionale all'U14). Ogni sezione ha la foto di squadra e l'elenco nominativo delle atlete. Nessun accordeon, nessuna complessità."),
    (17, "Talent Day &amp; Recruiting", "mockup_v3_talent_1782214608057.png",
     "Layout motivazionale con foto d'azione in testata (nessun video). Dettagli evento (data, sede, requisiti d'età) e form di iscrizione semplice."),
    (18, "Summer Camp &amp; Experience", "mockup_v3_summercamp_1782214797286.png",
     "Pagina unificata per Summer Camp e Experience: programmi settimanali, attività, prezzi, testimonianze di partecipanti precedenti e call-to-action per l'iscrizione."),
    (19, "Progetto Scuola", "mockup_v3_scuola_1782214765139.png",
     "Iniziativa per portare la pallavolo nelle scuole del territorio: descrizione del progetto, galleria fotografica delle visite scolastiche e modulo per richiedere un intervento nella propria scuola."),

    # SPONSOR E B2B
    (20, "I Nostri Sponsor", "mockup_v2_sponsor_1781700860336.png",
     "Griglia gerarchica premium che dona la giusta visibilità ai partner (Title Sponsor, Gold, Official), utilizzando fondi puliti per far risaltare i loghi."),
    (21, "Title Sponsor", "mockup_v3_title_sponsor_1782214685100.png",
     "Pagina dedicata allo sponsor principale: storytelling della partnership, timeline dei momenti chiave della collaborazione, citazione del CEO. Massima valorizzazione del partner di riferimento."),
    (22, "Diventa Sponsor (B2B)", "mockup_v2_diventa_sponsor_1781700872786.png",
     "Landing page orientata alla conversione. Punta sull'emozione (tifosi) e su argomentazioni razionali (ROI, Visibilità), con CTA esplicite."),
    (23, "Hospitality", "mockup_v3_hospitality_1782214698169.png",
     "Esperienza VIP al palazzetto: pacchetti Business e Premium con catering gourmet, posti migliori, meet &amp; greet giocatrici e parcheggio riservato. Elegante e corporate."),
    (24, "Progetto Affiliazioni", "mockup_v3_affiliazioni_1782214707414.png",
     "Programma di affiliazione per società sportive locali: vantaggi (metodologia, visibilità, eventi condivisi), griglia dei club già affiliati e call-to-action per richiedere l'affiliazione."),

    # SOCIALE E SOSTENIBILITÀ
    (25, "Volley 4 All", "mockup_v3_volley4all_1782214718133.png",
     "Progetto sociale ombrello del club: mission, iniziative di inclusione (pallavolo adattata, clinics gratuite, donazioni attrezzature). Tono caldo e ispirazionale."),
    (26, "Progetti Sociali", "mockup_v3_sociale_1782214618309.png",
     "Raccolta dei Comunicati Stampa con sistema di tag per categoria. In basso, banner di visibilità per gli sponsor etici che sostengono questi progetti. Le Aste benefiche appariranno come banner temporaneo quando attive."),
    (27, "Bilancio di Sostenibilità", "mockup_v3_sostenibilita_1782214755160.png",
     "Infografiche con i numeri chiave (materiali riciclati, ore di volontariato, progetti community) e sezione per scaricare il report PDF completo."),

    # MEDIA E COMUNICAZIONE
    (28, "Accrediti Stampa", "mockup_v2_accrediti_1781700960373.png",
     "Layout volutamente formale e B2B. Regole chiare e form strutturato per l'invio di richieste da parte dei giornalisti."),
    (29, "Cartelle Stampa", "mockup_v3_cartelle_stampa_1782214773897.png",
     "Area download per i media: loghi ufficiali (PNG/SVG), foto squadra ad alta risoluzione, Press Kit stagionale (PDF) e Brand Guidelines."),
    (30, "Double Face — Il Magazine", "mockup_v3_doubleface_1782214784026.png",
     "Archivio digitale sfogliabile del magazine ufficiale 'Double Face'. Griglia di copertine organizzate cronologicamente, con l'ultimo numero in evidenza nella sidebar. Flipbook integrato."),

    # SHOP
    (31, "Fan Shop", "mockup_v3_shop_1782214628315.png",
     "Atmosfera community, non e-commerce freddo: hero banner con i tifosi, griglia prodotti semplice a 3 colonne (maglie, sciarpe, cappellini). Focus sull'appartenenza al club, non sulla vendita."),
    (32, "Shop: Checkout", "mockup_v2_shop_checkout_1781700980708.png",
     "Flusso di pagamento isolato, privo di distrazioni. Focus su sicurezza (badge), chiarezza dei costi (sommario) e conversione rapida."),
]

html_parts = []

photo_html = """
  <!-- 11b — DIREZIONE ARTISTICA E SELEZIONE FOTOGRAFICA -->
  <div class="section" style="page-break-before: always; margin-top: 60px;">
    <div class="section-label">Art Direction</div>
    <h2 class="section-title">Selezione Fotografica (Versione 3.1)</h2>
    <p class="section-intro">
      Di seguito la selezione delle migliori 20 immagini scelte per il nuovo sito (provenienti dagli archivi del club), divise per categoria. Queste foto verranno utilizzate nei mockup, negli sfondi e per la comunicazione.
    </p>
    
    <div style="margin-bottom: 40px;">
        <h3 style="font-size: 20px; color: var(--blu); margin-bottom: 15px;">1. Azione di Gioco (Dinamismo e Potenza)</h3>
        <p style="font-size: 14px; color: var(--gray); margin-bottom: 15px;">Le foto catturano il cuore dello sport: schiacciate, palleggi, e momenti di alta tensione atletica.</p>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div>
              <img src="Upload_immagini/_D6A3554.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Avery Skinner - Schiacciata</p>
            </div>
            <div>
              <img src="Upload_immagini/_D6A4299.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Julia Bergmann - Schiacciata</p>
            </div>
            <div>
              <img src="Upload_immagini/_D6A3479.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Maja Ognjenović - Palleggio</p>
            </div>
            <div>
              <img src="Upload_immagini/BRE_6839.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Kiera Van Ryk sopra il muro</p>
            </div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-top: 15px;">
            <div>
              <img src="Upload_immagini/CAR_3824.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Caterina Bosetti - Azione</p>
            </div>
            <div>
              <img src="Upload_immagini/CAR_4060.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Ognjenović alla rete</p>
            </div>
            <div>
              <img src="Upload_immagini/savino-del-bene-vs-igor-novara_55047505667_o.jpg" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Azione dinamica Novara</p>
            </div>
        </div>
    </div>
    
    <div style="margin-bottom: 40px;">
        <h3 style="font-size: 20px; color: var(--blu); margin-bottom: 15px;">2. Emozione e Spirito di Squadra</h3>
        <p style="font-size: 14px; color: var(--gray); margin-bottom: 15px;">Il volley è uno sport di squadra. Queste foto mostrano l'unione e la grinta del gruppo.</p>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div>
              <img src="Upload_immagini/_D6A4143.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Esultanza in Cerchio</p>
            </div>
            <div>
              <img src="Upload_immagini/_D6A3644.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Huddle ed emozione</p>
            </div>
            <div>
              <img src="Upload_immagini/_D6A4203.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Abbraccio gioioso</p>
            </div>
            <div>
              <img src="Upload_immagini/BRE_2623.jpg" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Barbolini emozionato</p>
            </div>
        </div>
    </div>
    
    <div style="margin-bottom: 40px;">
        <h3 style="font-size: 20px; color: var(--blu); margin-bottom: 15px;">3. Ritratti, Staff e Dettagli</h3>
        <p style="font-size: 14px; color: var(--gray); margin-bottom: 15px;">Foto che raccontano i dettagli, la preparazione tecnica e la guida dello staff.</p>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px;">
            <div>
              <img src="Upload_immagini/KAR_6.jpg" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            </div>
            <div>
              <img src="Upload_immagini/_S6A5003.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            </div>
            <div>
              <img src="Upload_immagini/_D6A4383.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
            </div>
        </div>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px;">
            <div>
              <img src="Upload_immagini/Savino%20Del%20Bene%20SCANDICCI%20vs%20Fenerbahc%CC%A7e%20089.jpg" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Coach Barbolini sorridente</p>
            </div>
            <div>
              <img src="Upload_immagini/savino-del-bene-vs-fenerbahce-oguet_55047648347_o.jpg" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Indicazioni tattiche</p>
            </div>
        </div>
    </div>
    
    <div style="margin-bottom: 40px;">
        <h3 style="font-size: 20px; color: var(--blu); margin-bottom: 15px;">4. Atmosfera e Contesto</h3>
        <p style="font-size: 14px; color: var(--gray); margin-bottom: 15px;">Immagini che contestualizzano l'evento e aggiungono profondità visiva.</p>
        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
            <div>
              <img src="Upload_immagini/_D6A3907.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Il tifo e le bandiere</p>
            </div>
            <div>
              <img src="Upload_immagini/_D6A3402.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Ingresso in campo</p>
            </div>
            <div>
              <img src="Upload_immagini/_D6A3951.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Dettaglio pallone mani</p>
            </div>
            <div>
              <img src="Upload_immagini/BRE_6995.JPG" style="width: 100%; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
              <p style="font-size: 12px; color: var(--gray); margin-top: 5px; text-align: center;">Pallone ufficiale CEV</p>
            </div>
        </div>
    </div>
  </div>
"""

html_parts.append(photo_html)

# Add dynamic Roster Grid
html_parts.append("""
  <div class="section" style="page-break-before: always; margin-top: 60px;">
    <div class="section-label">Anteprima Interattiva</div>
    <h2 class="section-title">Roster Serie A1 2026/2027</h2>
    <p class="section-intro">
      Oltre al mockup statico, ecco una simulazione di come apparirà la griglia del Roster sul nuovo sito, popolata con i dati reali della prossima stagione.
    </p>
    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 30px;">
""")

roster = [
    (10, "Maja Ognjenović", "Serbia", "Palleggiatrice", "185", "1984", "_D6A3479.JPG"),
    (20, "Chidera Eze", "Italia", "Palleggiatrice", "182", "2003", "BRE_3217.jpg"),
    (3, "Kiera Van Ryk", "Canada", "Opposta", "188", "1999", "BRE_6839.JPG"),
    (7, "Lise Van Hecke", "Belgio", "Opposta", "191", "1992", "CAR_3858.JPG"),
    (4, "Avery Skinner", "Stati Uniti", "Schiacciatrice", "186", "1999", "_D6A3554.JPG"),
    (6, "Sofia D'Odorico", "Italia", "Schiacciatrice", "186", "1997", "CAR_4009.JPG"),
    (9, "Caterina Bosetti", "Italia", "Schiacciatrice", "180", "1994", "CAR_3824.JPG"),
    (17, "Julia Bergmann", "Brasile", "Schiacciatrice", "192", "2001", "_D6A4299.JPG"),
    (2, "Sara Alberti", "Italia", "Centrale", "187", "1993", "BRE_2602.jpg"),
    (13, "Emma Graziani", "Italia", "Centrale", "194", "2002", "BRE_2623.jpg"),
    (14, "Linda Nwakalor", "Italia", "Centrale", "188", "2002", "CAR_3870.JPG"),
    (15, "Martina Armini", "Italia", "Libero", "175", "2002", "CAR_4060.JPG"),
    ("N°", "Imma Sirressi", "Italia", "Libero", "175", "1990", "BRE_3025.jpg")
]

for player in roster:
    num, name, nation, role, height, year, img = player
    html_parts.append(f'''
      <div style="background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #eee;">
        <div style="height: 250px; background: url('Upload_immagini/{img}') center/cover; position: relative;">
          <div style="position: absolute; top: 15px; left: 15px; background: var(--blu); color: white; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; font-weight: 700; border-radius: 50%; font-size: 18px;">
            {num}
          </div>
        </div>
        <div style="padding: 20px;">
          <div style="font-size: 12px; font-weight: 600; color: var(--rosa); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px;">{role}</div>
          <h3 style="margin: 0 0 10px 0; font-size: 22px; color: var(--blu);">{name}</h3>
          <div style="display: flex; gap: 15px; font-size: 14px; color: var(--gray);">
            <div><strong>Naz:</strong> {nation}</div>
            <div><strong>Alt:</strong> {height} cm</div>
            <div><strong>Anno:</strong> {year}</div>
          </div>
        </div>
      </div>
    ''')

html_parts.append("</div></div>")

html_parts.append('\n<!-- 12 — DETTAGLIO MOCKUP PAGINE v3.1 -->\n')

for num, title, img, desc in pages:
    html_parts.append(f'''
  <div class="section" style="page-break-before: always; margin-top: 60px;">
    <div class="section-label">Mockup Ufficiale</div>
    <h2 class="section-title">{num}. {title}</h2>
    <p class="section-intro">
      {desc}
    </p>
    <div style="margin-top: 30px; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid var(--border);">
      <img src="{img}" style="width: 100%; display: block;" alt="{num}. {title}">
    </div>
  </div>
''')

# Read the HTML
with open("proposta_tecnica_sdb.html", "r") as f:
    content = f.read()

# Find the old mockup section and replace it
marker = "<!-- 12 — DETTAGLIO MOCKUP PAGINE"
if marker in content:
    # Handle versioned markers like "<!-- 12 — DETTAGLIO MOCKUP PAGINE v3.0 -->"
    before = content.split(marker)[0]
else:
    # Try the v2.1 structure - find the section before the first mockup
    import re
    match = re.search(r'\n<!-- 12 —.*?-->\n', content)
    if match:
        before = content[:match.start()]
    else:
        # Last resort: find the mockup sections by looking at the first one
        idx = content.find('<h2 class="section-title">1. Homepage</h2>')
        # go back to find the section div
        section_start = content.rfind('<div class="section"', 0, idx)
        before = content[:section_start]

final = before + "".join(html_parts) + "\n</body>\n</html>"

# Update version
final = final.replace("Versione 3.0", "Versione 3.1")
final = final.replace("Versione 2.1", "Versione 3.1")
final = final.replace("Versione 2.0", "Versione 3.1")

with open("proposta_tecnica_sdb.html", "w", encoding="utf-8") as f:
    f.write(final)

print("Proposta tecnica rigenerata con successo come proposta_tecnica_sdb.html!")
