# WordPress-Endpoint: Gutschein-Empfang vom Partner-Dashboard

**Für den Plugin-Entwickler.** Das Partner-Dashboard sendet neu angelegte oder geänderte
Gutscheincodes per Webhook an WordPress, damit sie automatisch in der Gutschein-Liste des
Lead-Formular-Plugins landen. Dafür muss im Plugin **ein REST-Endpoint** ergänzt werden.

---

## 1. Endpoint

```
POST  https://<eure-wp-domain>/wp-json/gnd/v1/vouchers
Content-Type: application/json
```

Ein einziger Endpoint reicht (Upsert). Es gibt keinen Bedarf für separate Create/Update-Routen.

---

## 2. Authentifizierung (HMAC-SHA256)

Jede Anfrage trägt zwei Header:

| Header | Inhalt |
|---|---|
| `X-GND-Timestamp` | Unix-Zeit in Sekunden, z. B. `1753086000` |
| `X-GND-Signature` | `sha256=<hmac>` |

Die Signatur wird so gebildet (auf **Dashboard-Seite**, hier nur zur Info):

```
hmac = HMAC_SHA256( key = GND_SYNC_SECRET,  message = "<timestamp>.<roher_request_body>" )
```

**Prüfung im Plugin – wichtig:**
1. Die Signatur wird über den **rohen, ungeparsten Body** gebildet → in WordPress `$request->get_body()` verwenden (nicht das geparste Array).
2. Anfrage ablehnen (`401`), wenn Header fehlen, der Timestamp **älter als 300 Sekunden** ist oder die Signatur nicht passt.
3. Vergleich immer mit `hash_equals()` (timing-sicher).

Das gemeinsame Geheimnis `GND_SYNC_SECRET` muss **identisch** zum Wert `WP_SYNC_SECRET` im Dashboard sein.
Empfehlung: in `wp-config.php` ablegen:

```php
define('GND_SYNC_SECRET', 'HIER_DAS_GEMEINSAME_SECRET');
```

---

## 3. Request-Body (JSON)

Beispiel, wie das Dashboard sendet:

```json
{
  "code": "PARTNER10",
  "typ": "prozent",
  "wert": "10",
  "partner": "PartnerGmbH",
  "provision": "150",
  "active": true,
  "valid_from": "2026-07-21",
  "valid_until": null,
  "voucher_line": "PARTNER10;prozent;10;PartnerGmbH;150"
}
```

| Feld | Bedeutung |
|---|---|
| `code` | Der Gutscheincode (eindeutiger Schlüssel, Groß-/Kleinschreibung wird großgeschrieben) |
| `typ` | `prozent` oder `fix` (Rabatt-Typ) |
| `wert` | Rabatt-Wert (z. B. `10`) |
| `partner` | Partner-Label (kann leer sein) |
| `provision` | Provision als Text (`150`, `10%` oder leer) – **nur fürs Dashboard**, nicht rabattrelevant |
| `active` | `true` = Code gültig, `false` = deaktivieren/entfernen |
| `valid_from` / `valid_until` | optionale Gültigkeit (ISO-Datum oder `null`) |
| **`voucher_line`** | **Der fertig formatierte String** `CODE;TYP;WERT;PARTNER;PROVISION` |

> **Der einfachste Weg:** Ihr speichert Gutscheine ohnehin als eine Zeile pro Code im Format
> `CODE;TYP;WERT;PARTNER;PROVISION`. Das Feld **`voucher_line` ist bereits genau dieser String** –
> ihr müsst also nur die Zeile mit diesem `code` in eurer bestehenden Liste **ersetzen bzw. anhängen**
> (bei `active: false` **entfernen**). Die Einzelfelder (`typ`, `wert`, …) sind nur als Beilage dabei.

---

## 4. Verhalten

- **Upsert nach `code`:** existiert der Code schon → aktualisieren, sonst neu anlegen.
- **Idempotent:** dieselbe Anfrage mehrfach → gleiches Ergebnis, keine Duplikate.
- **`active: false`:** Code aus der aktiven Liste entfernen (deaktivieren).

**Antwort:** `HTTP 200` mit JSON, z. B.:

```json
{ "status": "ok", "action": "created", "stored_line": "PARTNER10;prozent;10;PartnerGmbH;150" }
```

Fehler: `401` (Signatur), `422` (z. B. `code` fehlt).

---

## 5. Referenz-Implementierung (PHP)

> An **einer** Stelle anpassen: den Zugriff auf euren tatsächlichen Gutschein-Speicher
> (unten `GND_VOUCHER_OPTION` = Platzhalter für euren Options-Key / eure Tabelle).

```php
<?php
// In eurem Plugin (z. B. eine eigene Datei, per require eingebunden).

const GND_VOUCHER_OPTION = 'gnd_voucher_codes'; // <-- AN EUREN SPEICHER ANPASSEN

add_action('rest_api_init', function () {
    register_rest_route('gnd/v1', '/vouchers', [
        'methods'             => 'POST',
        'callback'            => 'gnd_receive_voucher',
        'permission_callback' => 'gnd_verify_voucher_signature',
    ]);
});

function gnd_verify_voucher_signature(WP_REST_Request $request) {
    $secret = defined('GND_SYNC_SECRET') ? GND_SYNC_SECRET : '';
    $ts     = $request->get_header('X-GND-Timestamp');
    $sig    = $request->get_header('X-GND-Signature');
    $body   = $request->get_body(); // roher Body – zwingend für die Signatur

    if (! $secret || ! $ts || ! $sig) {
        return new WP_Error('gnd_auth', 'Signatur fehlt', ['status' => 401]);
    }
    if (abs(time() - (int) $ts) > 300) {
        return new WP_Error('gnd_auth', 'Timestamp abgelaufen', ['status' => 401]);
    }
    $expected = 'sha256=' . hash_hmac('sha256', $ts . '.' . $body, $secret);
    if (! hash_equals($expected, (string) $sig)) {
        return new WP_Error('gnd_auth', 'Ungültige Signatur', ['status' => 401]);
    }
    return true;
}

function gnd_receive_voucher(WP_REST_Request $request) {
    $p    = $request->get_json_params();
    $code = strtoupper(trim($p['code'] ?? ''));
    if ($code === '') {
        return new WP_REST_Response(['status' => 'error', 'message' => 'code fehlt'], 422);
    }

    $line   = trim((string) ($p['voucher_line'] ?? ''));
    $active = (bool) ($p['active'] ?? true);

    // --- bestehende Liste laden (eine Zeile pro Code) ---
    $raw   = (string) get_option(GND_VOUCHER_OPTION, '');
    $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), 'strlen'));

    // vorhandene Zeile für diesen Code entfernen
    $existed = false;
    $lines = array_values(array_filter($lines, function ($l) use ($code, &$existed) {
        if (strtoupper(trim(strtok($l, ';'))) === $code) { $existed = true; return false; }
        return true;
    }));

    if ($active) {
        $lines[] = $line;                     // fertig formatierte Zeile anhängen
        $action  = $existed ? 'updated' : 'created';
    } else {
        $action  = 'deactivated';             // Zeile bleibt entfernt
    }

    update_option(GND_VOUCHER_OPTION, implode("\n", $lines));

    return new WP_REST_Response([
        'status'      => 'ok',
        'action'      => $action,
        'stored_line' => $active ? $line : null,
    ], 200);
}
```

---

## 6. Selbst testen (curl)

```bash
SECRET='DAS_GEMEINSAME_SECRET'
TS=$(date +%s)
BODY='{"code":"TESTABC","typ":"prozent","wert":"10","partner":"Test GmbH","provision":"150","active":true,"voucher_line":"TESTABC;prozent;10;Test GmbH;150"}'
SIG=$(printf '%s' "$TS.$BODY" | openssl dgst -sha256 -hmac "$SECRET" -r | awk '{print $1}')

curl -i -X POST "https://<eure-wp-domain>/wp-json/gnd/v1/vouchers" \
  -H "Content-Type: application/json" \
  -H "X-GND-Timestamp: $TS" \
  -H "X-GND-Signature: sha256=$SIG" \
  --data-binary "$BODY"
```

Erwartet: `HTTP/1.1 200` und `{"status":"ok","action":"created",...}`. Danach muss `TESTABC`
in eurer Gutschein-Liste stehen. Zweiter identischer Aufruf → `action: "updated"`, keine Dublette.
Falsche Signatur → `401`.

---

## 7. Checkliste

- [ ] `GND_SYNC_SECRET` in `wp-config.php` gesetzt (identisch zum Dashboard-Wert).
- [ ] `GND_VOUCHER_OPTION` auf euren echten Gutschein-Speicher angepasst.
- [ ] REST-API erreichbar (Permalinks aktiv, `/wp-json/` nicht blockiert).
- [ ] curl-Test grün (200, Code erscheint in der Liste, Duplikat-Test ok, 401 bei falscher Signatur).
- [ ] Dashboard-Seite: URL im Feld `WP_VOUCHER_ENDPOINT` eintragen.

---

### Zusammenhang (Gesamtbild)

```
Dashboard  --(POST /wp-json/gnd/v1/vouchers, HMAC)-->  WordPress-Plugin (dieser Endpoint)
     ^                                                        |
     |  Mitarbeiter legt Code an                              v
     +--------------------------------------------  Code steht in der Gutschein-Liste
```

Die **Gegenrichtung** (Lead-Daten ins Dashboard) läuft über euren bestehenden Zapier-Webhook und
benötigt **keine** Plugin-Änderung – dort wird lediglich eine Zapier-Action ergänzt, die die
Lead-Felder an die Dashboard-URL `…/api/webhooks/wp/lead` weitergibt.
