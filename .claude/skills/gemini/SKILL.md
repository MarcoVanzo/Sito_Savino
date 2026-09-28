---
name: gemini
description: Delega a Gemini (API a pagamento di Marco) SOLO in 3 casi — secondo parere indipendente su diff critici (migrazioni DB, auth, deploy, sicurezza), analisi di video/audio, generazione di immagini. Usala direttamente senza chiedere, ma avvisa Marco con una riga «→ delego a Gemini: …». Non usarla per scrivere codice, esplorare codebase, leggere PDF/immagini o cercare sul web.
---

# Delega a Gemini

Script: `gemini.py` nella cartella di questa skill (solo stdlib, read-only: restituisce testo o un file immagine, non tocca repo).
- Mac: `G=~/.claude/skills/gemini/gemini.py` (chiave dal Keychain)
- Cloud: `G=.claude/skills/gemini/gemini.py` dalla root del repo (chiave da `GEMINI_API_KEY` dell'environment; se manca, dillo a Marco e non procedere)

## Regole
- Prima di chiamare, scrivi a Marco una riga: `→ delego a Gemini: <cosa>`.
- Gemini non ha il tuo contesto: passagli tutto ciò che serve (diff, file rilevanti, obiettivo).
- **Non mandare segreti**: niente `.env`, chiavi, dump DB con dati personali dei clienti. Per i diff, controlla prima che non contengano credenziali.
- La risposta di Gemini è un parere, non un ordine: valutala criticamente, riporta a Marco solo i punti fondati (e dove sei in disaccordo, dillo). Le modifiche le applichi tu.

## Casi d'uso

**1. Secondo parere su diff critico**
```bash
git diff main...HEAD > /tmp/review.diff   # usa lo scratchpad
python3 $G ask "Sei un revisore senior PHP/MySQL. Trova bug, rischi di sicurezza e problemi di migrazione in questo diff. Elenca solo problemi concreti con file e riga, niente stile." /path/review.diff
```

**2. Video / audio**
```bash
python3 $G ask "Descrivi/trascrivi …" /path/video.mp4
```
(file >15MB vanno automaticamente via Files API)

**3. Generazione immagini**
```bash
python3 $G image "prompt dettagliato" /path/out.png
```
Poi guarda l'immagine con Read prima di proporla.

## Modelli
Default in testa allo script; override con `GEMINI_TEXT_MODEL` / `GEMINI_IMAGE_MODEL`. `gemini.py models` elenca quelli disponibili.
