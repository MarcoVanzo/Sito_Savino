#!/usr/bin/env python3
"""Client minimale e read-only per l'API Gemini (solo stdlib).

Uso:
  gemini.py ask "prompt" [file ...]      testo, con allegati opzionali (diff, video, audio, immagini)
  gemini.py image "prompt" out.png       genera un'immagine
  gemini.py models                       elenca i modelli disponibili

Chiave: env GEMINI_API_KEY se presente; in cloud (CLAUDE_CODE_REMOTE=true) nessuna,
la inietta la "Credenziale API" dell'environment; sul Mac dal Keychain ("mvc-gemini-api-key").
Modelli: env GEMINI_TEXT_MODEL / GEMINI_IMAGE_MODEL, altrimenti i default sotto.
"""
import base64, json, mimetypes, os, subprocess, sys, time, urllib.request

API = "https://generativelanguage.googleapis.com"
TEXT_MODEL = os.environ.get("GEMINI_TEXT_MODEL", "gemini-pro-latest")
IMAGE_MODEL = os.environ.get("GEMINI_IMAGE_MODEL", "gemini-3.1-flash-image")
INLINE_MAX = 15 * 1024 * 1024  # oltre: upload via Files API


def key():
    if os.environ.get("GEMINI_API_KEY"):
        return os.environ["GEMINI_API_KEY"]
    if os.environ.get("CLAUDE_CODE_REMOTE") == "true":
        return None  # cloud: x-goog-api-key lo inietta la "Credenziale API" dell'environment
    return subprocess.run(
        ["security", "find-generic-password", "-s", "mvc-gemini-api-key", "-w"],
        capture_output=True, text=True, check=True).stdout.strip()


def req(method, url, body=None, headers=None, raw=False):
    k = key()
    h = {**({"x-goog-api-key": k} if k else {}), **(headers or {})}
    data = body if raw else (json.dumps(body).encode() if body is not None else None)
    if data is not None and not raw:
        h["Content-Type"] = "application/json"
    r = urllib.request.Request(url, data=data, headers=h, method=method)
    try:
        with urllib.request.urlopen(r, timeout=600) as resp:
            return resp.headers, resp.read()
    except urllib.error.HTTPError as e:
        sys.exit(f"Errore API {e.code}: {e.read().decode()[:2000]}")


def upload(path, mime):
    size = os.path.getsize(path)
    hdrs, _ = req("POST", f"{API}/upload/v1beta/files", {"file": {"display_name": os.path.basename(path)}},
                  {"X-Goog-Upload-Protocol": "resumable", "X-Goog-Upload-Command": "start",
                   "X-Goog-Upload-Header-Content-Length": str(size),
                   "X-Goog-Upload-Header-Content-Type": mime})
    with open(path, "rb") as f:
        _, out = req("POST", hdrs["X-Goog-Upload-URL"], f.read(),
                     {"X-Goog-Upload-Command": "upload, finalize", "X-Goog-Upload-Offset": "0"}, raw=True)
    info = json.loads(out)["file"]
    while info.get("state") == "PROCESSING":  # video: attende l'elaborazione
        time.sleep(5)
        info = json.loads(req("GET", f"{API}/v1beta/{info['name']}")[1])
    return {"file_data": {"mime_type": mime, "file_uri": info["uri"]}}


def part(path):
    mime = mimetypes.guess_type(path)[0] or "text/plain"
    if mime.startswith("text/") or mime in ("application/json", "application/x-sh") or path.endswith(
            (".php", ".js", ".ts", ".sql", ".diff", ".patch", ".md", ".yml", ".yaml", ".blade.php")):
        return {"text": f"--- {path} ---\n" + open(path, encoding="utf-8", errors="replace").read()}
    if os.path.getsize(path) > INLINE_MAX:
        return upload(path, mime)
    return {"inline_data": {"mime_type": mime, "data": base64.b64encode(open(path, "rb").read()).decode()}}


def generate(model, parts, image=False):
    body = {"contents": [{"role": "user", "parts": parts}]}
    if image:
        body["generationConfig"] = {"responseModalities": ["TEXT", "IMAGE"]}
    _, out = req("POST", f"{API}/v1beta/models/{model}:generateContent", body)
    return json.loads(out)


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    cmd = sys.argv[1]
    if cmd == "models":
        for m in json.loads(req("GET", f"{API}/v1beta/models?pageSize=200")[1]).get("models", []):
            print(m["name"].removeprefix("models/"), "-", ",".join(m.get("supportedGenerationMethods", [])))
    elif cmd == "ask" and len(sys.argv) >= 3:
        parts = [part(p) for p in sys.argv[3:]] + [{"text": sys.argv[2]}]
        res = generate(TEXT_MODEL, parts)
        print("".join(p.get("text", "") for c in res.get("candidates", []) for p in c["content"].get("parts", [])))
    elif cmd == "image" and len(sys.argv) == 4:
        res = generate(IMAGE_MODEL, [{"text": sys.argv[2]}], image=True)
        for c in res.get("candidates", []):
            for p in c["content"].get("parts", []):
                d = p.get("inline_data") or p.get("inlineData")
                if d:
                    open(sys.argv[3], "wb").write(base64.b64decode(d["data"]))
                    print(f"Salvata: {sys.argv[3]}")
                    return
        sys.exit("Nessuna immagine nella risposta: " + json.dumps(res)[:2000])
    else:
        sys.exit(__doc__)


if __name__ == "__main__":
    main()
