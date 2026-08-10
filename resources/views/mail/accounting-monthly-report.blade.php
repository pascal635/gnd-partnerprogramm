@component('mail::message')
# Provisions-Report {{ $periodLabel }}

anbei der Provisions-Report für **{{ $periodLabel }}**.

- **Beauftragungen im Zeitraum:** {{ $beauftragungen }}
- **Provision gesamt:** {{ $provisionGesamt }}

Im Anhang:

- **Detail-CSV** – eine Zeile je beauftragtem Lead (Lead-ID, Kunde, E-Mail, Gutscheincode, Deal-Wert, Provision, Status) zum Prüfen.
- **Summen-CSV** – eine Zeile je Partner (mit Gesamt-Zeile) zum Gutschreiben.

Beide Dateien öffnen in deutschem Excel korrekt (UTF-8, Semikolon-getrennt).

Freundliche Grüße
GND Partnerprogramm
@endcomponent
