images = [
    ("mockup_v3_roster_hub", "1. HUB SQUADRE E MATCH", "Accorpa il Roster (con foto di squadra in testata e giocatrici a seguire su griglia fissa) e lo Staff Medico e Tecnico. Include sdoppiamento della pagina Risultati e Classifiche."),
    ("mockup_v3_ticketing_hub", "2. HUB BIGLIETTERIA E TIFOSI (Ticketing)", "Riunisce in un'unica landing page la Campagna Abbonamenti (prezzi, piantina), la Biglietteria standard, l'elenco delle Convenzioni per abbonati e le informazioni su Palazzetto e Accessibilità."),
    ("mockup_v3_b2b_hub", "3. HUB CORPORATE E B2B (Club & Partner)", "Area dedicata al Business: in testata il Title Sponsor (storytelling dedicato), a seguire la griglia Sponsor (Gold, Silver), e infine i blocchi per l'Hospitality VIP e il Progetto Affiliazioni."),
    ("mockup_v3_academy_hub", "4. HUB GIOVANILE E TERRITORIO (Academy)", "Razionalizzazione del Settore Giovanile (foto di squadra U14-U18 e liste nominative), affiancato dai blocchi evento per Summer Camp & Experience, Talent Day (con focus immagine statica) e Progetto Scuola."),
    ("mockup_v3_sociale_hub", "5. HUB SOCIALE E SOSTENIBILITA'", "Il grande ombrello 'Volley 4 All'. Include i Comunicati Stampa filtrati per tematiche etiche, le infografiche del Bilancio di Sostenibilità, e un banner dedicato per l'attivazione temporanea delle Aste Benefiche."),
    ("mockup_v3_media_hub", "6. HUB MEDIA E COMMUNITY", "L'archivio digitale sfogliabile per il magazine 'Double Face', integrato con l'area download per le Cartelle Stampa B2B. A lato, la community si affida anche allo Shop, che riprenderà lo stile attuale."),
]

import os, glob
# We will find the latest png file for each prefix
real_files = []
for prefix, title, desc in images:
    files = glob.glob(prefix + "*.png")
    if files:
        # get the latest one
        files.sort()
        real_files.append((files[-1], title, desc))
    else:
        real_files.append((prefix + ".png", title, desc))

html_parts = []
html_parts.append("\n<!-- 12 — DETTAGLIO HUB GRAFICI v2.0 -->\n")

for filename, title, desc in real_files:
    html_parts.append(f"""
  <div class="section" style="page-break-before: always; margin-top: 60px;">
    <div class="section-label">Hub Architettura v2.0</div>
    <h2 class="section-title">{title}</h2>
    <p class="section-intro">
      {desc}
    </p>
    <div style="margin-top: 30px; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid var(--border);">
      <img src="{filename}" style="width: 100%; display: block;" alt="{title}">
    </div>
  </div>
""")

html_parts.append("</body>\n</html>")

with open("proposta_tecnica_sdb.html", "r") as f:
    lines = f.readlines()

new_lines = []
for line in lines:
    if "<!-- 12 — DETTAGLIO MOCKUP PAGINE -->" in line:
        break
    new_lines.append(line)

final_html = "".join(new_lines) + "".join(html_parts)

final_html = final_html.replace("Versione 1.9", "Versione 2.0")
final_html = final_html.replace("v1.9", "v2.0")

with open("proposta_tecnica_sdb.html", "w") as f:
    f.write(final_html)

print("Updated HTML with 6 Hubs and v2.0")
