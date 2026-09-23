"""Rigenera docs/INFRASTRUCTURE.html da docs/INFRASTRUCTURE.md.

Tiene il CSS della versione precedente (ritoccato: niente oro, font locali),
ricostruisce copertina, indice e diagramma, e converte il Markdown con un
convertitore minimo che copre solo ciò che il documento usa.
"""
import html
import re
import sys

# Uso: python3 scripts/genera-infrastructure-html.py  (dalla radice del progetto)
ROOT = sys.argv[1] if len(sys.argv) > 1 else "."
md = open(f"{ROOT}/docs/INFRASTRUCTURE.md", encoding="utf-8").read().splitlines()
old = open(f"{ROOT}/docs/INFRASTRUCTURE.html", encoding="utf-8").read()

head = old[: old.index("</head>")]
# Oro fuori palette (Brand & Digital Style Guide 2026-2027): diventa il fucsia ufficiale.
head = head.replace("--savino-gold: #C9A84C;", "--savino-fucsia: #F8269C;")
head = head.replace("var(--savino-gold)", "var(--savino-fucsia)")
# Niente Google Fonts: i woff2 sono quelli che serve il sito.
head = re.sub(r'\s*<link href="https://fonts\.googleapis\.com[^>]*>', "", head)
fonts = """
        @font-face { font-family: 'Montserrat'; font-weight: 100 900; font-style: normal;
            src: url('../public/fonts/montserrat-latin-wght-normal.woff2') format('woff2'); }
        @font-face { font-family: 'Playfair Display'; font-weight: 400 900; font-style: normal;
            src: url('../public/fonts/playfair-display-latin-wght-normal.woff2') format('woff2'); }
        ul, ol { margin: 0 0 12px 22px; }
        li { margin: 3px 0; line-height: 1.6; }
        .callout p strong, .callout li strong { display: inline; margin: 0; }
"""
if "@font-face" not in head:
    head = head.replace("<style>", "<style>" + fonts, 1)


def inline(text: str) -> str:
    # I link prima del codice: il testo di un link spesso è a sua volta `codice`.
    m = re.search(r"\[([^\]]+)\]\(([^)]+)\)", text)
    if m:
        return inline(text[: m.start()]) + f'<a href="{html.escape(m.group(2))}">{inline(m.group(1))}</a>' + inline(text[m.end() :])
    parts = re.split(r"(`[^`]*`)", text)
    out = []
    for p in parts:
        if p.startswith("`") and p.endswith("`") and len(p) > 1:
            out.append(f"<code>{html.escape(p[1:-1])}</code>")
            continue
        s = html.escape(p, quote=False)
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
            level, title = len(m.group(1)), m.group(2)
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
        f'<rect width="{w}" height="{h}" rx="10" fill="white" stroke="{color}" stroke-width="1.8" filter="url(#shadow)"/>',
        f'<path d="M0 10 a10 10 0 0 1 10 -10 h{w-20} a10 10 0 0 1 10 10 v18 h-{w} z" fill="{color}"/>',
        f'<text x="12" y="19" font-family="Montserrat" font-size="10.5" font-weight="700" fill="white">{html.escape(title)}</text>',
    ]
    if cost:
        t.append(f'<text x="{w-12}" y="19" text-anchor="end" font-family="JetBrains Mono, monospace" font-size="9" font-weight="700" fill="white">{cost}</text>')
    for k, line in enumerate(lines):
        t.append(f'<text x="12" y="{46 + k*15}" font-family="Montserrat" font-size="8.5" fill="#4a4a6a">{html.escape(line)}</text>')
    t.append("</g>")
    return "\n".join(t)


def arrow(x1, y1, x2, y2, label="", color="#003063", lx=None, ly=None, dash=""):
    marker = "arrow-pink" if color == "#ED028C" else "arrow"
    d = f' stroke-dasharray="{dash}"' if dash else ""
    s = f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{color}" stroke-width="1.6" marker-end="url(#{marker})"{d}/>'
    if label:
        s += f'<text x="{lx}" y="{ly}" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="7.5" fill="{color}">{label}</text>'
    return s


BLUE, PINK, GREY = "#003063", "#ED028C", "#7a7a9a"
DIAGRAM = "\n".join([
    '<div class="architecture-diagram" style="padding:20px 10px;">',
    '<div style="text-align:center; margin-bottom:12px; font-size:9pt; color:var(--text-light); text-transform:uppercase; letter-spacing:1.5px; font-weight:600;">DigitalOcean — Region Frankfurt (fra / fra1)</div>',
    '<svg viewBox="0 0 760 505" style="width:100%; max-width:760px; margin:0 auto; display:block;" xmlns="http://www.w3.org/2000/svg">',
    '<defs>',
    f'<marker id="arrow" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="{BLUE}"/></marker>',
    f'<marker id="arrow-pink" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse"><path d="M0 0 L10 5 L0 10 z" fill="{PINK}"/></marker>',
    '<filter id="shadow" x="-5%" y="-5%" width="115%" height="115%"><feDropShadow dx="0" dy="2" stdDeviation="4" flood-color="#003063" flood-opacity="0.08"/></filter>',
    '</defs>',
    box(310, 10, 140, 44, "👤 Visitatori", "", []),
    box(40, 110, 250, 92, "🌐 App Web", "$10", ["PHP + Apache • 1 vCPU, 1 GB", "Sito (Inertia/Vue) + pannello (Filament)", "health check /up"]),
    box(470, 110, 250, 92, "🗄️ MySQL 8.4 managed", "$15", ["1 vCPU, 1 GB • Trusted Sources", "dati, sessioni, cache, code", "backup DO: ultimi 7 giorni"]),
    box(40, 270, 250, 77, "📦 Spaces sito-savino-assets-2026", "$5", ["immagini e media (origine, niente CDN)", "S3 API • abbonamento 250 GB"], PINK),
    box(330, 270, 180, 77, "⚙️ Worker", "$5", ["queue:work default, ai", "0,5 GB • 1 istanza"]),
    box(540, 270, 180, 77, "⏱️ Scheduler", "$5", ["schedule:work + battito", "0,5 GB • 1 istanza sola"]),
    box(290, 400, 260, 77, "🖥️ Droplet CompreFace", "$24", ["2 vCPU, 4 GB • Ubuntu 24.04", "API :8000 solo dalla VPC 10.114.0.0/20"], PINK),
    box(40, 400, 210, 92, "🔒 Backup (GitHub Actions)", "", ["dump GPG giornaliero, media settimanali", "→ sito-savino-backups (Spaces)", "→ Cloudflare R2, fuori da DO"], GREY),
    box(590, 400, 150, 62, "🔗 Servizi esterni", "", ["Lega, PayPal, GA4, Meta"], GREY),
    arrow(350, 56, 200, 108, "HTTPS", BLUE, 260, 78),
    f'<polyline points="308,32 20,32 20,308 36,308" fill="none" stroke="{PINK}" stroke-width="1.6" stroke-dasharray="4,3" marker-end="url(#arrow-pink)"/>',
    f'<text x="120" y="26" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="7.5" fill="{PINK}">immagini (dirette da Spaces)</text>',
    arrow(292, 150, 468, 150, "SQL", BLUE, 380, 144),
    arrow(165, 204, 165, 268, "upload S3", PINK, 205, 240),
    arrow(420, 268, 540, 204, "coda jobs", BLUE, 505, 250),
    arrow(630, 268, 610, 204, "comandi", BLUE, 650, 240),
    arrow(420, 349, 420, 398, "HTTP (VPC)", PINK, 460, 378),
    arrow(330, 310, 292, 310, "", PINK),
    arrow(145, 398, 145, 351, "copia", GREY, 170, 380, dash="3,3"),
    arrow(720, 349, 690, 398, "", GREY, dash="3,3"),
    '</svg>',
    '</div>',
])

# Il documento parte dalla sezione 1: titolo, nota iniziale e indice li costruisce la copertina.
start = next(k for k, l in enumerate(md) if l.startswith("## 1."))
intro = []
for l in md[1:start]:
    if l.startswith("## Indice"):
        break
    intro.append(l)
body = md[start:]
# Lo schema ASCII del §1 è sostituito dal diagramma SVG.
a = body.index("```")
b = body.index("```", a + 1)
body = body[:a] + body[b + 1 :]

titles = [re.match(r"^## \d+\.\s+(.*)", l).group(1) for l in body if re.match(r"^## \d+\.\s", l)]
toc = "".join(f'<li><a href="#s{n}">{inline(t)}</a></li>' for n, t in enumerate(titles, 1))

page = f"""{head}</head>
<body>

<!-- Generata da docs/INFRASTRUCTURE.md con scripts/genera-infrastructure-html.py: si modifica il Markdown, non questo file. -->
<div class="cover-page">
    <div class="cover-logo">🏐</div>
    <h1 class="cover-title">Infrastruttura</h1>
    <p class="cover-subtitle">Documentazione Tecnica — DigitalOcean</p>
    <div class="cover-meta">
        <span>Savino Del Bene Volley</span>
        <span>•</span>
        <span>23 settembre 2026</span>
        <span>•</span>
        <span>Versione 2.0</span>
    </div>
    <div class="cover-bar"></div>
</div>

<div class="content">

{convert(intro)}

<div class="toc">
    <h2>Indice</h2>
    <ol class="toc-list">{toc}</ol>
</div>

{convert(body)}

<div class="doc-footer">
    <p><strong>Savino Del Bene Volley</strong> — Documentazione Infrastruttura v2.0</p>
    <p>Generata il 23 settembre 2026 da <code>docs/INFRASTRUCTURE.md</code> • DigitalOcean Frankfurt (fra/fra1)</p>
</div>

</div>
</body>
</html>
"""
open(f"{ROOT}/docs/INFRASTRUCTURE.html", "w", encoding="utf-8").write(page)
print("ok", len(page))
