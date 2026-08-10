@component('mail::message')
{{ $greeting }},

hier Ihr Partner-Report für **{{ $monthLabel }}**:

@component('mail::table')
| Kennzahl | Wert |
|:---------|-----:|
| Ersteinschätzungen | {{ $ersteinschaetzungen }} |
| Davon beauftragt | {{ $beauftragungen }} |
| Provision gesamt | {{ $provision }} |
@endcomponent

Die Details sehen Sie jederzeit in Ihrem Portal: [partner.gutachten-nutzungsdauer.com](https://partner.gutachten-nutzungsdauer.com/)

Bei Fragen melden Sie sich jederzeit.

Freundliche Grüße
gutachten-nutzungsdauer.com
@endcomponent
