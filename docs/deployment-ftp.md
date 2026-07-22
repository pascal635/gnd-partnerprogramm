# Deployment: GitHub → Server per FTP

Ablauf: Du pushst nach `main` → GitHub Actions **baut** die App (Composer, Assets) →
lädt sie **per FTP** auf den Server → löst **Migrationen/Cache** über eine geschützte URL aus.
(Weil der Server nur FTP kann, gibt es kein SSH — Migrationen laufen deshalb über die Deploy-Route.)

---

## ① Wo du die FTP-Zugangsdaten einträgst  ⬅️ (deine Frage)

**NICHT in einer Datei im Repo!** (Die landet sonst in GitHub und ist ein Sicherheitsleck.)
Die Zugangsdaten kommen in die **GitHub-Secrets**:

> **GitHub → dein Repo → Settings → Secrets and variables → Actions → „New repository secret"**

Dort legst du diese Secrets an (Name genau so schreiben):

| Secret-Name | Wert / Beispiel | Pflicht |
|---|---|---|
| `FTP_SERVER` | FTP-Host, z. B. `ftp.deinhoster.de` (ohne `ftp://`) | ✅ |
| `FTP_USERNAME` | dein FTP-Benutzer | ✅ |
| `FTP_PASSWORD` | dein FTP-Passwort | ✅ |
| `FTP_REMOTE_DIR` | Zielordner auf dem Server, z. B. `/partner/` (siehe ③) | ✅ |
| `FTP_PROTOCOL` | `ftps` (verschlüsselt, empfohlen). Nur falls der Hoster kein FTPS kann: `ftp` | optional |
| `FTP_PORT` | Standard `21` | optional |
| `DEPLOY_URL` | öffentliche Dashboard-Adresse, z. B. `https://partner.deine-domain.de` | ✅ |
| `DEPLOY_TOKEN` | **derselbe** Wert wie in der `.env` auf dem Server (siehe ③) | ✅ |

Die Pipeline (`.github/workflows/deploy.yml`) liest diese Secrets automatisch — du musst
in keiner Datei etwas eintragen.

---

## ② Voraussetzungen beim Hoster

- **PHP 8.2+** (mit `intl`, `pdo_mysql`, `mbstring`, `bcmath`, `zip`, `gd`, `curl`).
- Eine **MySQL/MariaDB-Datenbank** (per Hosting-Panel anlegen).
- **Cron** (für die Warteschlange – siehe ⑤). Fast alle Hoster haben das im Panel.
- Möglichkeit, den **Document-Root der Subdomain** auf einen Unterordner zu setzen (siehe ③).

---

## ③ Einmalige Server-Einrichtung (ohne SSH, alles per FTP/Panel)

1. **Subdomain + Datenbank** im Panel anlegen (z. B. `partner.deine-domain.de`, DB + DB-User).

2. **Zielordner & Document-Root festlegen** — zwei gängige Fälle:
   - **A (empfohlen):** App liegt in `/partner/`, und du setzt den Document-Root der Subdomain
     im Panel auf **`/partner/public`**. Dann: `FTP_REMOTE_DIR = /partner/`.
   - **B (Docroot nicht änderbar):** Der Subdomain-Ordner *ist* der Document-Root. Dann die App
     eine Ebene darüber ablegen und den Inhalt von `public/` in den Docroot legen. Frag mich –
     dafür passe ich `public/index.php` einmal an. (`FTP_REMOTE_DIR` = der übergeordnete Ordner.)

3. **Produktions-`.env` per FTP hochladen** (einmalig, ins App-Wurzelverzeichnis, z. B. `/partner/.env`).
   Diese Datei liegt **nur auf dem Server**, nicht im Repo. Wichtige Werte:
   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:...        # lokal erzeugen: php artisan key:generate --show
   APP_URL=https://partner.deine-domain.de

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_DATABASE=...           # aus dem Panel
   DB_USERNAME=...
   DB_PASSWORD=...

   QUEUE_CONNECTION=database
   SESSION_DRIVER=database

   # Integration – FRISCHE Produktions-Secrets erzeugen (nicht die lokalen!):
   WP_VOUCHER_ENDPOINT=https://neuropage.de/wp-json/gnd-rnd/v1/vouchers
   WP_SYNC_SECRET=...        # muss in wp-config.php (GND_SYNC_SECRET) gleich sein
   WP_LEAD_SECRET=...        # muss im Lead-Plugin (GND_LEAD_SECRET) gleich sein
   CONVERSION_SECRET=...     # muss im Zapier-Code-Snippet gleich sein
   DEPLOY_TOKEN=...          # muss identisch zum GitHub-Secret DEPLOY_TOKEN sein
   ```
   > Secrets erzeugen: `openssl rand -hex 24`. Danach die Gegenstellen (wp-config.php, Zapier)
   > auf die **Produktions**-Werte umstellen.

4. **Schreibrechte** setzen (im FTP-Client per „chmod"): `storage/` und `bootstrap/cache/`
   rekursiv auf **0775**.

---

## ④ Deployen

1. Secrets aus ① gesetzt? Dann **Code nach `main` pushen** → die Action baut & lädt hoch.
2. Am Ende ruft die Action automatisch die **Deploy-Route** auf (Migrationen + Cache):
   `https://partner.deine-domain.de/gnd-deploy/<DEPLOY_TOKEN>`
   Du kannst diese URL auch **einmal manuell im Browser** aufrufen (z. B. beim ersten Mal).
   Antwort `{"status":"done", ...}` = erledigt.

> Die Deploy-Route ist per `DEPLOY_TOKEN` geschützt und ohne gesetzten Token deaktiviert (404).

---

## ⑤ Cron für die Warteschlange (dauerhafter Betrieb statt lokalem Worker)

Im Hosting-Panel einen Cronjob anlegen (Pfad zu `artisan` beim Hoster erfragen):

```
* * * * * php /pfad/zu/partner/artisan schedule:run >/dev/null 2>&1
```

Damit werden Gutschein-Syncs, Retries und (später) `deal_value`-Nachladen automatisch verarbeitet.
Falls dein Hoster **kein** Cron anbietet: sag mir Bescheid, dann baue ich eine web-basierte
Alternative (Ping-URL für einen Cron-Dienst).

---

## Ablauf-Übersicht

```
git push main
   → GitHub Actions: composer + npm build + filament:assets
   → FTP-Upload (ohne .env / Runtime-Daten)
   → Aufruf /gnd-deploy/<TOKEN>  →  migrate --force + config/route/view:cache
Cron (jede Minute): artisan schedule:run  →  Queue + Sweeps
```

## Checkliste

- [ ] GitHub-Secrets aus ① gesetzt.
- [ ] DB im Panel angelegt.
- [ ] Subdomain-Docroot auf `public/` gesetzt (oder Fall B geklärt).
- [ ] Produktions-`.env` per FTP hochgeladen (mit frischen Secrets + passendem `DEPLOY_TOKEN`).
- [ ] `storage/` + `bootstrap/cache/` auf 0775.
- [ ] Push nach `main` → Action grün → `/gnd-deploy/<TOKEN>` meldet `done`.
- [ ] Cron für `schedule:run` eingerichtet.
- [ ] Gegenstellen (wp-config, Zapier) auf Produktions-Secrets umgestellt.
