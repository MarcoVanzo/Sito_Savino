images = [
    ("mockup_home_1781616069814.png", "1. Homepage", "Biglietto da visita del club, con un forte impatto visivo (hero image), conto alla rovescia per la prossima partita e focus immediato sulle call-to-action principali (Biglietti, Roster)."),
    ("mockup_roster_1781616081595.png", "2. Roster Serie A1", "Griglia dinamica in stile 'figurina/card' per ogni atleta. Utilizzo di sfondi scuri e glassmorphism per esaltare l'atleta e il suo numero di maglia."),
    ("mockup_risultati_1781616095013.png", "3. Risultati e Classifica", "Visualizzazione chiara e analitica dei dati sportivi. Ultime partite in evidenza e tabella classifica pulita, con il team sempre evidenziato con un bordo Magenta."),
    ("mockup_news_1781616156366.png", "4. News & Comunicati", "Layout editoriale moderno, con la notizia di punta a tutta larghezza e le altre organizzate in una griglia ordinata con badge di categoria."),
    ("mockup_gallery_1781616121867.png", "5. Foto Gallery (AI)", "Layout masonry immersivo, ideale per valorizzare gli scatti di gioco e del tifo. Filtri rapidi superiori per navigare tra gli eventi."),
    ("mockup_organigramma_1781616168046.png", "6. Organigramma Società", "Design corporate ed elegante che mostra la struttura dirigenziale e lo staff tecnico, bilanciando professionalità e anima sportiva."),
    ("mockup_storia_1781616179246.png", "7. Storia del Club", "Una timeline emozionale verticale che alterna foto storiche (con trattamenti vintage) a testi che celebrano i trofei e le milestone societarie."),
    ("mockup_contatti_1781616190722.png", "8. Contatti & Palazzetto", "Pagina funzionale divisa in due: a sinistra un modulo contatti ottimizzato, a destra mappa customizzata e orari di biglietteria."),
    ("mockup_abbonamenti_1781616203659.png", "9. Campagna Abbonamenti", "Struttura 'pricing' in stile e-commerce, con 3 tier chiari (Standard, Premium, VIP) e mappa interattiva dei settori del palazzetto."),
    ("mockup_accessibilita_1781616248071.png", "10. Accessibilità", "Layout pulito e inclusivo (font ad alta leggibilità, alto contrasto). Spiega in modo inequivocabile i servizi per disabili e policy cani guida."),
    ("mockup_sponsor_1781616258408.png", "11. I Nostri Sponsor", "Griglia gerarchica premium che dona la giusta visibilità ai partner (Title Sponsor, Gold, Official), utilizzando fondi puliti per far risaltare i loghi."),
    ("mockup_diventa_sponsor_1781616269203.png", "12. Diventa Sponsor (B2B)", "Landing page orientata alla conversione. Punta sull'emozione (tifosi) e su argomentazioni razionali (ROI, Visibilità), con CTA esplicite."),
    ("mockup_roster_b1_1781616518708.png", "13. Roster Serie B1 (Youth)", "Veste grafica leggermente più fresca e dinamica (accenti ciano/azzurro) pur rimanendo coerente, pensata per esaltare il talento giovanile."),
    ("mockup_giovanile_1781616456678.png", "14. Settore Giovanile", "Pagina accogliente e comunitaria, strutturata a fisarmonica per le fasce d'età, con una forte chiamata all'azione per iscriversi."),
    ("mockup_talent_1781616529314.png", "15. Talent Day & Recruiting", "Layout altamente motivazionale ed energetico ('Join the Future'). Include video promo e form multi-step per semplificare le candidature."),
    ("mockup_summercamp_1781616543489.png", "16. Summer Camp", "Declinazione estiva della palette, mantenendo un'architettura logica e pulita per presentare programmi, prezzi e location ai genitori."),
    ("mockup_sociale_1781616554824.png", "17. Progetti Sociali & Aste", "Pagina ibrida: sopra presenta le aste benefiche (timer, bid), sotto racconta l'impegno sociale sul territorio con grande impatto fotografico."),
    ("mockup_accrediti_1781616566163.png", "18. Accrediti Stampa", "Layout volutamente formale e B2B. Regole chiare e form strutturato per l'invio di richieste da parte dei giornalisti."),
    ("mockup_shop_catalogo_1781616577423.png", "19. Shop: Catalogo Prodotti", "E-commerce puro: barra filtri laterale e griglia prodotti pulita per massimizzare il focus sul merchandising ufficiale."),
    ("mockup_shop_checkout_1781616589807.png", "20. Shop: Checkout", "Flusso di pagamento isolato, privo di distrazioni. Focus su sicurezza (badge), chiarezza dei costi (sommario) e conversione rapida.")
]

html_output = "<!-- 12 — DETTAGLIO MOCKUP PAGINE -->\n"

for filename, title, desc in images:
    html_output += f"""
  <div class="section" style="page-break-before: always; margin-top: 60px;">
    <div class="section-label">Mockup Ufficiale</div>
    <h2 class="section-title">{title}</h2>
    <p class="section-intro">
      {desc}
    </p>
    <div style="margin-top: 30px; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid var(--border);">
      <img src="{filename}" style="width: 100%; display: block;" alt="{title}">
    </div>
  </div>
"""

with open("mockups_block.html", "w") as f:
    f.write(html_output)
