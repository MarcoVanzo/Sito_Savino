"""Rigenera docs/INFRASTRUCTURE.html da docs/INFRASTRUCTURE.md.

Lo stile sta in docs/assets/infrastructure.css (impaginato per la stampa A4 da
Chrome: "Salva come PDF"), il logo di MV Consulting in docs/assets. Copertina,
scheda del documento, indice e diagramma li costruisce lo script; il resto lo
converte un convertitore Markdown minimo, che copre solo ciò che il documento usa.
"""
import html
import re
import sys

# Uso: python3 scripts/genera-infrastructure-html.py  (dalla radice del progetto)
ROOT = sys.argv[1] if len(sys.argv) > 1 else "."
md = open(f"{ROOT}/docs/INFRASTRUCTURE.md", encoding="utf-8").read()
md = re.sub(r"<!--.*?-->\n?", "", md, flags=re.S).splitlines()
LOGO_MV = open(f"{ROOT}/docs/assets/logo-mv-consulting.svg", encoding="utf-8").read()
LOGO_MV = re.sub(r"<title>.*?</title>", "", LOGO_MV, flags=re.S)
CSS = open(f"{ROOT}/docs/assets/infrastructure.css", encoding="utf-8").read()

# Data e versione stanno nel Markdown ("> Ultimo aggiornamento: …", "> Versione: …").
text = "\n".join(md)
DATA = re.search(r"Ultimo aggiornamento:\s*(.+)", text).group(1).strip()
VERSIONE = re.search(r"Versione:\s*(.+)", text).group(1).strip()

# Le emoji non stanno in un documento da stampare: i titoli le perdono,
# gli stati delle tabelle diventano segni tipografici.
EMOJI = re.compile("[\U0001F300-\U0001FAFF\u2600-\u27BF\uFE0F]")
STATI = {"✅": '<span class="stato stato-ok">✓</span>', "⚠️": '<span class="stato stato-att">!</span>'}


def inline(text: str) -> str:
    # I link prima del codice: il testo di un link spesso è a sua volta `codice`.
    m = re.search(r"\[([^\]]+)\]\(([^)]+)\)", text)
    if m:
        return inline(text[: m.start()]) + f'<a href="{html.escape(m.group(2))}">{inline(m.group(1))}</a>' + inline(text[m.end() :])
    # Un grassetto che contiene codice va risolto prima di spezzare sul codice.
    m = re.search(r"\*\*(.+?)\*\*", text) if "`" in text else None
    if m:
        return inline(text[: m.start()]) + f"<strong>{inline(m.group(1))}</strong>" + inline(text[m.end() :])
    parts = re.split(r"(`[^`]*`)", text)
    out = []
    for p in parts:
        if p.startswith("`") and p.endswith("`") and len(p) > 1:
            out.append(f"<code>{html.escape(p[1:-1])}</code>")
            continue
        s = html.escape(p, quote=False)
        for k, v in STATI.items():
            s = s.replace(k, v)
        s = EMOJI.sub("", s).replace("  ", " ")
        s = re.sub(r"\[([^\]]+)\]\(([^)]+)\)", r'<a href="\2">\1</a>', s)
        s = re.sub(r"\*\*([^*]+)\*\*", r"<strong>\1</strong>", s)
        s = re.sub(r"(?<![\w*])\*([^*]+)\*(?!\w)", r"<em>\1</em>", s)
        out.append(s)
    return "".join(out)


def cells(line: str):
    line = line.strip().strip("|")
    return [c.strip().replace("\\|", "|") for c in re.split(r"(?<!\\)\|", line)]


def render_list(lines):
    """Liste a un livello con righe di continuazione indentate."""
    ordered = bool(re.match(r"\d+\.\s", lines[0].lstrip()))
    items = []
    for l in lines:
        m = re.match(r"^\s?(?:[-*]|\d+\.)\s+(.*)", l)
        if m:
            items.append([m.group(1)])
        else:
            items[-1].append(l.strip())
    tag = "ol" if ordered else "ul"
    return f"<{tag}>" + "".join(f"<li>{inline(' '.join(i))}</li>" for i in items) + f"</{tag}>"


def convert(lines):
    out, i = [], 0
    is_list = lambda l: re.match(r"^\s*(?:[-*]|\d+\.)\s", l)
    while i < len(lines):
        l = lines[i]
        if not l.strip() or l.strip() == "---":
            i += 1
            continue
        if l.startswith("```"):
            j = i + 1
            while not lines[j].startswith("```"):
                j += 1
            out.append("<pre>" + html.escape("\n".join(lines[i + 1 : j])) + "</pre>")
            i = j + 1
            continue
        m = re.match(r"^(#{2,4})\s+(.*)", l)
        if m:
            level, title = len(m.group(1)), EMOJI.sub("", m.group(2)).replace("  ", " ").strip()
            sec = re.match(r"^(\d+)\.\s+(.*)", title)
            if level == 2 and sec:
                n = int(sec.group(1))
                out.append(f'<h2 id="s{n}"><span class="section-number">{n:02d}</span> {inline(sec.group(2))}</h2>')
                if n == 1:
                    out.append(DIAGRAM)
            else:
                out.append(f"<h{level}>{inline(title)}</h{level}>")
            i += 1
            continue
        if l.startswith("|"):
            rows = []
            while i < len(lines) and lines[i].startswith("|"):
                rows.append(lines[i])
                i += 1
            head_cells = cells(rows[0])
            body = []
            for r in rows[2:]:
                c = cells(r)
                cls = ' class="table-total"' if any("TOTALE" in x for x in c) else ""
                body.append(f"<tr{cls}>" + "".join(f"<td>{inline(x)}</td>" for x in c) + "</tr>")
            out.append(
                "<table><thead><tr>" + "".join(f"<th>{inline(h)}</th>" for h in head_cells)
                + "</tr></thead><tbody>" + "".join(body) + "</tbody></table>"
            )
            continue
        if l.startswith(">"):
            block = []
            while i < len(lines) and lines[i].startswith(">"):
                block.append(lines[i][2:] if lines[i].startswith("> ") else "")
                i += 1
            kind = "callout-warning" if "⚠️" in " ".join(block) else "callout-info"
            block = [x.replace("⚠️ ", "").replace("⚠️", "") for x in block]
            out.append(f'<div class="callout {kind}">{convert(block)}</div>')
            continue
        if is_list(l):
            block = []
            while i < len(lines) and lines[i].strip() and (is_list(lines[i]) or lines[i].startswith("  ")):
                block.append(lines[i])
                i += 1
            out.append(render_list(block))
            continue
        para = []
        while (
            i < len(lines) and lines[i].strip() and not lines[i].startswith(("#", "|", ">", "```"))
            and not is_list(lines[i]) and lines[i].strip() != "---"
        ):
            para.append(lines[i].strip())
            i += 1
        out.append(f"<p>{inline(' '.join(para))}</p>")
    return "\n".join(out)


def box(x, y, w, h, title, cost, lines, color="#003063"):
    t = [
        f'<g transform="translate({x},{y})">',
        f'<rect width="{w}" height="{h}" rx="6" fill="white" stroke="{color}" stroke-width="1.2"/>',
        f'<path d="M0 6 a6 6 0 0 1 6 -6 h{w-12} a6 6 0 0 1 6 6 v20 h-{w} z" fill="{color}"/>',
        f'<text x="10" y="17.5" font-family="Montserrat" font-size="10" font-weight="700" fill="white">{html.escape(title)}</text>',
    ]
    if cost:
        t.append(f'<text x="{w-10}" y="17.5" text-anchor="end" font-family="Montserrat" font-size="9" font-weight="600" fill="white">{cost}</text>')
    for k, line in enumerate(lines):
        t.append(f'<text x="10" y="{43 + k*14}" font-family="Montserrat" font-size="8.5" fill="#3d4a5c">{html.escape(line)}</text>')
    t.append("</g>")
    return "\n".join(t)


def arrow(x1, y1, x2, y2, label="", color="#003063", lx=None, ly=None, dash=""):
    marker = {AZZURRO: "arrow-az", GREY: "arrow-gr"}.get(color, "arrow")
    d = f' stroke-dasharray="{dash}"' if dash else ""
    s = f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{color}" stroke-width="1.3" marker-end="url(#{marker})"{d}/>'
    if label:
        s += f'<text x="{lx}" y="{ly}" text-anchor="middle" font-family="Montserrat" font-size="7.5" font-weight="600" fill="{color}">{label}</text>'
    return s


# Blu Savino per i componenti dell'app, azzurro per dati e file, grigio per ciò che sta fuori.
BLUE, AZZURRO, GREY = "#003063", "#2F7FB5", "#66879E"
DIAGRAM = "\n".join([
    '<figure class="architecture-diagram">',
    '<figcaption>DigitalOcean — region Frankfurt (fra / fra1)</figcaption>',
    '<svg viewBox="0 0 760 505" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Schema dell\'infrastruttura">',
    '<defs>',
    f'<marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="{BLUE}"/></marker>',
    f'<marker id="arrow-az" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="{AZZURRO}"/></marker>',
    f'<marker id="arrow-gr" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="{GREY}"/></marker>',
    '</defs>',
    box(310, 10, 140, 34, "Visitatori", "", []),
    box(40, 110, 250, 92, "App Web", "$10", ["PHP + Apache • 1 vCPU, 1 GB", "Sito (Inertia/Vue) + pannello (Filament)", "health check /up"]),
    box(470, 110, 250, 92, "MySQL 8.4 managed", "$15.23", ["1 vCPU, 1 GB • Trusted Sources", "dati, sessioni, cache, code", "backup DO: ultimi 7 giorni"]),
    box(40, 270, 250, 77, "Spaces sito-savino-assets-2026", "$5", ["immagini e media (origine, niente CDN)", "S3 API • abbonamento 250 GB"], AZZURRO),
    box(330, 270, 180, 77, "Worker", "$5", ["queue:work default, ai", "0,5 GB • 1 istanza"]),
    box(540, 270, 180, 77, "Scheduler", "$5", ["schedule:work + battito", "0,5 GB • 1 istanza sola"]),
    box(290, 400, 260, 77, "Droplet CompreFace", "$24", ["2 vCPU, 4 GB • Ubuntu 24.04", "API :8000 solo dalla VPC 10.114.0.0/20"], AZZURRO),
    box(40, 400, 210, 92, "Backup (GitHub Actions)", "", ["dump GPG giornaliero, media settimanali", "→ sito-savino-backups (Spaces)", "→ Cloudflare R2, fuori da DO"], GREY),
    box(580, 400, 165, 92, "Servizi esterni", "", ["Lega Volley, CEV", "Stripe, PayPal, Resend", "GA4, Meta, Sentry"], GREY),
    arrow(350, 46, 200, 108, "HTTPS", BLUE, 260, 72),
    f'<polyline points="308,27 20,27 20,308 36,308" fill="none" stroke="{AZZURRO}" stroke-width="1.3" stroke-dasharray="4,3" marker-end="url(#arrow-az)"/>',
    f'<text x="120" y="21" text-anchor="middle" font-family="Montserrat" font-size="7.5" font-weight="600" fill="{AZZURRO}">immagini (dirette da Spaces)</text>',
    arrow(292, 150, 468, 150, "SQL", BLUE, 380, 144),
    arrow(165, 204, 165, 268, "upload S3", AZZURRO, 205, 240),
    arrow(420, 268, 540, 204, "coda jobs", BLUE, 505, 250),
    arrow(630, 268, 610, 204, "comandi", BLUE, 650, 240),
    arrow(420, 349, 420, 398, "HTTP (VPC)", AZZURRO, 460, 378),
    arrow(330, 310, 292, 310, "", AZZURRO),
    arrow(145, 398, 145, 351, "copia", GREY, 170, 380, dash="3,3"),
    arrow(700, 349, 680, 398, "", GREY, dash="3,3"),
    '</svg>',
    '</figure>',
])

# Prima della sezione 1: nota iniziale (che va nella scheda del documento) e,
# facoltativa, la "Sintesi per la direzione", che apre il documento dopo l'indice.
first = next(k for k, l in enumerate(md) if re.match(r"^## (\d+\.|Sintesi)", l))
intro = [l for l in md[1:first] if not l.startswith(("> Ultimo aggiornamento", "> Versione"))]
intro = intro[: next((k for k, l in enumerate(intro) if l.startswith("## Indice")), len(intro))]
while intro and intro[0].strip() in ("", ">"):
    intro.pop(0)
start = next(k for k, l in enumerate(md) if l.startswith("## 1."))
sintesi = md[first:start] if md[first].startswith("## Sintesi") else []
body = md[start:]
# Lo schema ASCII del §1 è sostituito dal diagramma SVG.
a = body.index("```")
b = body.index("```", a + 1)
body = body[:a] + body[b + 1 :]

titles = [EMOJI.sub("", re.match(r"^## \d+\.\s+(.*)", l).group(1)).strip() for l in body if re.match(r"^## \d+\.\s", l)]
toc = "".join(
    f'<li><a href="#s{n}"><span class="toc-n">{n:02d}</span><span class="toc-t">{inline(t)}</span></a></li>'
    for n, t in enumerate(titles, 1)
)
sintesi_html = ""
if sintesi:
    sintesi_html = '<section class="sintesi"><h2 class="sintesi-titolo">Sintesi per la direzione</h2>' + convert(sintesi[1:]) + "</section>"
    toc = f'<li><a href="#sintesi"><span class="toc-n">—</span><span class="toc-t">Sintesi per la direzione</span></a></li>' + toc
    sintesi_html = sintesi_html.replace('<section class="sintesi">', '<section class="sintesi" id="sintesi">')

titolo_pagina = f"Infrastruttura tecnica — Savino Del Bene Volley — v{VERSIONE}"
page = f"""<!DOCTYPE html>
<html lang="it">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{html.escape(titolo_pagina)}</title>
<!-- Generata da docs/INFRASTRUCTURE.md con scripts/genera-infrastructure-html.py: si modificano il Markdown e docs/assets/infrastructure.css, non questo file. -->
<style>
{CSS}
@page {{ @top-left {{ content: "Savino Del Bene Volley · Documentazione tecnica dell'infrastruttura"; }}
        @bottom-left {{ content: "MV Consulting · v{VERSIONE} · {DATA} · Riservato"; }} }}
</style>
</head>
<body>

<section class="cover-page">
    <header class="cover-header">
        <div class="cover-logo-mv">{LOGO_MV}</div>
        <span class="badge-riservato">Riservato</span>
    </header>
    <div class="cover-body">
        <p class="cover-kicker">Documentazione tecnica</p>
        <h1 class="cover-title">Infrastruttura<br>del sito e del CMS</h1>
        <p class="cover-subtitle">Architettura, servizi DigitalOcean, deploy, costi, backup e sicurezza di savinodelbenevolley.it</p>
    </div>
    <div class="cover-client">
        <img src="../public/images/logo.png" alt="Savino Del Bene Volley">
        <div>
            <span class="label">Cliente</span>
            <strong>Savino Del Bene Volley</strong>
            <span>Pallavolo Scandicci Savino Del Bene S.S.D. a r.l.</span>
        </div>
    </div>
    <footer class="cover-footer">
        <div><span class="label">Redatto da</span>MV Consulting</div>
        <div><span class="label">Data</span>{DATA}</div>
        <div><span class="label">Versione</span>{VERSIONE}</div>
    </footer>
</section>

<section class="scheda">
    <table class="scheda-doc">
        <tbody>
            <tr><th>Documento</th><td>Documentazione tecnica dell'infrastruttura</td></tr>
            <tr><th>Cliente</th><td>Pallavolo Scandicci Savino Del Bene S.S.D. a r.l.</td></tr>
            <tr><th>Fornitore</th><td>MV Consulting — Marco Vanzo</td></tr>
            <tr><th>Versione</th><td>{VERSIONE} del {DATA}</td></tr>
            <tr><th>Classificazione</th><td>Riservato: contiene dettagli di configurazione dei sistemi in produzione</td></tr>
            <tr><th>Fonte</th><td><code>docs/INFRASTRUCTURE.md</code> nel repository del sito</td></tr>
        </tbody>
    </table>
    {convert(intro)}
    <nav class="toc">
        <h2>Indice</h2>
        <ol>{toc}</ol>
    </nav>
</section>

{sintesi_html}

<main class="content">
{convert(body)}
</main>

</body>
</html>
"""
open(f"{ROOT}/docs/INFRASTRUCTURE.html", "w", encoding="utf-8").write(page)
print("ok", len(page))
