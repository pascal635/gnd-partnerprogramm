@component('mail::message')
Hallo{{ $greetingName !== '' ? ' '.$greetingName : '' }},

dein Zugang zum **GND Partnerportal** ist eingerichtet.

Bitte lege zunächst dein persönliches Passwort fest:

@component('mail::button', ['url' => $url])
Passwort festlegen
@endcomponent

Danach meldest du dich hier an:
[partner.gutachten-nutzungsdauer.com/portal](https://partner.gutachten-nutzungsdauer.com/portal)

Dein **Benutzername** ist deine E-Mail-Adresse.

Der Link ist einige Tage gültig. Sollte er abgelaufen sein, klicke auf der Login-Seite einfach auf **„Passwort vergessen?"** – dann bekommst du einen neuen Link.

Liebe Grüße
gutachten-nutzungsdauer.com
@endcomponent
