@component('mail::message')
{{ $greeting }},

Ihr Zugang zum **GND Partnerportal** ist eingerichtet.

Bitte legen Sie zunächst Ihr persönliches Passwort fest:

@component('mail::button', ['url' => $url])
Passwort festlegen
@endcomponent

Danach melden Sie sich hier an:
[partner.gutachten-nutzungsdauer.com/portal](https://partner.gutachten-nutzungsdauer.com/portal)

Ihr **Benutzername** ist Ihre E-Mail-Adresse.

Der Link ist einige Tage gültig. Sollte er abgelaufen sein, klicken Sie auf der Login-Seite einfach auf **„Passwort vergessen?"** – dann erhalten Sie einen neuen Link.

Freundliche Grüße
gutachten-nutzungsdauer.com
@endcomponent
