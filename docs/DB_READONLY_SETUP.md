# Setup utente read-only `readonly_savino` — MySQL gestito DigitalOcean

> Da eseguire **una tantum** dalla tua macchina (Claude Code / terminale locale), il cui IP
> pubblico deve essere nelle **Trusted Sources** del cluster `sito-savino-db`.
> Host, porta e password admin si prendono da: DO → Databases → `sito-savino-db` →
> **Connection Details**. Sostituisci `<HOST>` sotto con quello reale (porta `25060`).

---

## 0. Prerequisiti

```bash
# a) Aggiungi il tuo IP pubblico ai Trusted Sources del DB (pannello DO)
#    Il tuo IP:  curl -s https://ifconfig.me
#
# b) Scarica il certificato CA:
#    DO → Databases → sito-savino-db → Connection Details → "Download CA certificate"
#    Salvalo nella cartella corrente come:  ca-certificate.crt
```

---

## 1. Crea l'utente read-only (una tantum, come admin `doadmin`)

Genera prima una password forte e tienila da parte (password manager):

```bash
openssl rand -base64 24
```

Connettiti come admin e crea l'utente:

```bash
mysql --host=<HOST> --port=25060 --user=doadmin --password \
      --ssl-mode=VERIFY_CA --ssl-ca=ca-certificate.crt defaultdb
```

Al prompt `mysql>` esegui (incolla la password generata sopra):

```sql
-- Crea l'utente
CREATE USER 'readonly_savino'@'%' IDENTIFIED BY 'INCOLLA_QUI_LA_PASSWORD_FORTE';

-- Concedi SOLO lettura sul database di produzione
-- (conferma il nome reale da Connection Details: di norma è `defaultdb`)
GRANT SELECT ON defaultdb.* TO 'readonly_savino'@'%';

FLUSH PRIVILEGES;

-- Verifica: deve mostrare solo GRANT SELECT ON `defaultdb`.*
SHOW GRANTS FOR 'readonly_savino'@'%';

EXIT;
```

---

## 2. Connessione read-only per query e test

Comando completo con SSL (usa questo in Claude Code o nel terminale):

```bash
mysql --host=<HOST> --port=25060 --user=readonly_savino --password \
      --ssl-mode=VERIFY_CA --ssl-ca=ca-certificate.crt defaultdb
```

Test di connessione minimo (protocollo §6 del CLAUDE.md):

```bash
# 1) Connessione
mysql --host=<HOST> --port=25060 --user=readonly_savino --password \
      --ssl-mode=VERIFY_CA --ssl-ca=ca-certificate.crt defaultdb \
      -e "SELECT 1+1;"

# 2) Schema
mysql --host=<HOST> --port=25060 --user=readonly_savino --password \
      --ssl-mode=VERIFY_CA --ssl-ca=ca-certificate.crt defaultdb \
      -e "SHOW TABLES;"
```

Se `SELECT 1+1;` risponde `2`, sei connesso e in sola lettura.
Una `INSERT`/`UPDATE`/`DELETE` con questo utente deve fallire con
`ERROR 1142 ... command denied` — è il comportamento corretto.

---

## 3. Per il connettore MCP

Nel blocco JSON del `CLAUDE.md` (§4) usa `readonly_savino`, `MYSQL_PORT=25060`,
`MYSQL_DB=defaultdb`, host reale, e password **da variabile d'ambiente** (mai in chiaro).
Per la verifica completa del certificato, se il server MCP lo supporta, passagli anche il
percorso di `ca-certificate.crt` (vedi la doc del pacchetto MCP scelto); in alternativa
`MYSQL_SSL=true` abilita almeno il TLS.

---

## Note

- `--ssl-mode=VERIFY_CA` verifica il certificato del server contro la CA scaricata: è la
  modalità più sicura. Se il client MySQL dà problemi col CA, `--ssl-mode=REQUIRED` cifra
  comunque il traffico ma senza validare la CA (ripiego, meno sicuro).
- Non creare l'utente read-only dalla UI "Users" del pannello DO: quella crea utenti con
  privilegi pieni. La strada SQL qui sopra è l'unica che garantisce il solo `SELECT`.
- Restringi i Trusted Sources al minimo indispensabile; se il tuo IP cambia spesso,
  aggiornalo quando serve invece di lasciare range ampi.
