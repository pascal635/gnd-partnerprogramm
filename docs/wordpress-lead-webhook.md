# WordPress → Partner-Dashboard: Lead-Webhook (direkt)

**Für den Plugin-Entwickler.** Das Lead-Formular-Plugin sendet abgeschlossene
Ersteinschätzungen **direkt** an das Partner-Dashboard (nicht über Zapier), damit die
Kundendaten EU↔EU bleiben. Es wird nur ein zusätzlicher **ausgehender** Webhook ergänzt –
die Signatur-Technik ist dieselbe wie beim Gutschein-Empfang, nur auf der Sende-Seite.

> Zapier wird dadurch für Leads **nicht mehr gebraucht**. Zapier bleibt nur für die
> Conversion-Meldung (Pipedrive „beauftragt" → Dashboard) zuständig – und die enthält
> bewusst keine Kundendaten.

---

## 1. Ziel-Endpoint (Dashboard)

```
POST  https://partner.<eure-domain>/api/webhooks/wp/lead
Content-Type: application/json
```

> Muss die **öffentliche** Dashboard-Adresse sein (Subdomain). `localhost` ist vom
> WordPress-Server nicht erreichbar.

Das Dashboard **akzeptiert dieses Format bereits** – es sind **keine** Dashboard-Änderungen nötig.

---

## 2. Authentifizierung (HMAC-SHA256) – identisch zum Gutschein-Endpoint

Zwei Header pro Request:

| Header | Inhalt |
|---|---|
| `X-GND-Timestamp` | Unix-Zeit in Sekunden |
| `X-GND-Signature` | `sha256=<hmac>` |

```
hmac = HMAC_SHA256( key = GND_LEAD_SECRET, message = "<timestamp>.<roher_json_body>" )
```

Secret in `wp-config.php` (Wert kommt vom Dashboard-Team, `WP_LEAD_SECRET`):

```php
define('GND_LEAD_SECRET', 'HIER_DAS_LEAD_SECRET');
```

> Wichtig: exakt derselbe JSON-String, der gesendet wird, muss auch signiert werden
> (erst `wp_json_encode(...)`, dann signieren, dann genau diesen String als Body senden).

---

## 3. Wann senden

Beim Event **`contact_captured`** (fertige Ersteinschätzung inkl. Ergebnis). Erneutes
Senden bei Updates ist unkritisch – das Dashboard ist **idempotent** (Schlüssel `lead_id`).

---

## 4. Payload (Ziel-Format) + Feld-Zuordnung

```json
{
  "event": "lead.created",
  "lead_id": "2020",
  "created_at": "2026-07-20T19:26:46+02:00",
  "voucher_code": "PEVO15",
  "voucher_partner": "PeterVogt",
  "voucher_commission": "0%",
  "voucher_typ": "prozent",
  "voucher_wert": "15",
  "customer": { "name": "Peter Schrenker", "email": "…", "phone": "…" },
  "property": { "objektart": "Eigentumswohnung" },
  "source": "wp_ersteinschaetzung"
}
```

| Ziel-Feld | ← euer Plugin-Feld |
|---|---|
| `lead_id` | **Lead Id** (z. B. `2020`) – dieselbe ID, die auch zu Pipedrive geht |
| `voucher_code` | Voucher Code |
| `voucher_partner` | Voucher Partner |
| `voucher_commission` | Voucher Commission |
| `voucher_typ` | Voucher Discount Type |
| `voucher_wert` | Voucher Discount Value |
| `customer.name` | Contact Vorname + " " + Contact Nachname |
| `customer.email` | Contact Email |
| `customer.phone` | Contact Telefon |
| `property.objektart` | Object Type |
| `created_at` | Created At (ISO-8601) |
| `source` | fester Wert, z. B. `wp_ersteinschaetzung` |

---

## 5. Referenz-Implementierung (PHP)

An der Stelle aufrufen, an der ihr aktuell schon den `contact_captured`-Webhook an Zapier schickt.

```php
<?php
// $lead = eure interne Lead-Datenstruktur.
function gnd_push_lead_to_dashboard(array $lead): void {
    $endpoint = 'https://partner.<eure-domain>/api/webhooks/wp/lead';
    $secret   = defined('GND_LEAD_SECRET') ? GND_LEAD_SECRET : '';
    if (! $secret) { return; }

    $payload = [
        'event'              => 'lead.created',
        'lead_id'            => (string) $lead['lead_id'],
        'created_at'         => $lead['created_at'] ?? gmdate('c'),
        'voucher_code'       => $lead['voucher_code'] ?? '',
        'voucher_partner'    => $lead['voucher_partner'] ?? '',
        'voucher_commission' => $lead['voucher_commission'] ?? '',
        'voucher_typ'        => $lead['voucher_discount_type'] ?? '',
        'voucher_wert'       => (string) ($lead['voucher_discount_value'] ?? ''),
        'customer' => [
            'name'  => trim(($lead['contact_vorname'] ?? '') . ' ' . ($lead['contact_nachname'] ?? '')),
            'email' => $lead['contact_email'] ?? '',
            'phone' => $lead['contact_telefon'] ?? '',
        ],
        'property' => [
            'objektart' => $lead['object_type'] ?? '',
        ],
        'source' => 'wp_ersteinschaetzung',
    ];

    $body = wp_json_encode($payload);
    $ts   = (string) time();
    $sig  = 'sha256=' . hash_hmac('sha256', $ts . '.' . $body, $secret);

    wp_remote_post($endpoint, [
        'timeout'  => 8,
        'blocking' => false, // fire-and-forget; Dashboard ist idempotent & antwortet schnell
        'headers'  => [
            'Content-Type'    => 'application/json',
            'X-GND-Timestamp' => $ts,
            'X-GND-Signature' => $sig,
        ],
        'body' => $body,
    ]);
}
```

---

## 6. Selbst testen (curl)

```bash
SECRET='DAS_LEAD_SECRET'
TS=$(date +%s)
BODY='{"event":"lead.created","lead_id":"TEST-2020","voucher_code":"PEVO15","voucher_partner":"PeterVogt","voucher_commission":"0%","voucher_typ":"prozent","voucher_wert":"15","customer":{"name":"Peter Schrenker","email":"test@example.com","phone":"0170"},"property":{"objektart":"Eigentumswohnung"},"source":"wp_ersteinschaetzung","created_at":"2026-07-20T19:26:46+02:00"}'
SIG=$(printf '%s' "$TS.$BODY" | openssl dgst -sha256 -hmac "$SECRET" -r | awk '{print $1}')

curl -i -X POST "https://partner.<eure-domain>/api/webhooks/wp/lead" \
  -H "Content-Type: application/json" \
  -H "X-GND-Timestamp: $TS" -H "X-GND-Signature: sha256=$SIG" \
  --data-binary "$BODY"
```

Erwartet: `HTTP/1.1 202` und `{"status":"ok","lead_id":"TEST-2020"}`. Falsche Signatur → `401`.
Gleicher `lead_id` erneut → `{"status":"duplicate_ignored"}` (kein Doppel-Eintrag).

---

## 7. Checkliste

- [ ] `GND_LEAD_SECRET` in `wp-config.php` (= `WP_LEAD_SECRET` aus dem Dashboard).
- [ ] Ausgehenden POST bei `contact_captured` ergänzt (Felder gemäß Tabelle).
- [ ] `lead_id` identisch zu der ID, die auch nach Pipedrive geschrieben wird.
- [ ] Dashboard öffentlich erreichbar (Subdomain live).
- [ ] curl-Test: 202, Lead erscheint im Dashboard-Portal des Partners; 401 bei falscher Signatur.

---

### Gesamtbild nach dieser Änderung

```
Ersteinschätzung  --(POST /api/webhooks/wp/lead, HMAC, MIT Kundendaten)-->  Dashboard
Pipedrive "beauftragt"  --> Zapier (OHNE Kundendaten) --> Dashboard (/api/webhooks/conversion)
Dashboard  --(Gutschein-Sync, HMAC)-->  WordPress-Plugin
```
