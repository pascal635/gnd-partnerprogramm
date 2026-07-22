<x-filament-panels::page>
    @php
        $card = 'rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10';
        $h2 = 'text-base font-semibold text-gray-950 dark:text-white';
        $label = 'text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400';
        $pre = 'mt-1 overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs leading-relaxed text-gray-800 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10';
        $btn = 'shrink-0 rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:hover:bg-white/20';
    @endphp

    <div class="space-y-6">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Diese Seite zeigt alles, was du brauchst, um WordPress, Pipedrive und Zapier korrekt mit dem
            Dashboard zu verbinden. Alle eingehenden Webhooks werden per HMAC-SHA256 signiert
            (Fenster: {{ $replayWindow }} Sek.) und sind idempotent.
        </p>

        {{-- ============ INBOUND ============ --}}
        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Lead ingest --}}
            <section class="{{ $card }}">
                <h2 class="{{ $h2 }}">① Lead-Ingest &mdash; WordPress &rarr; Dashboard</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Feuert bei jeder abgesendeten Ersteinschätzung.</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <div class="{{ $label }}">Endpoint (POST)</div>
                        <div x-data="{c:false}" class="mt-1 flex items-center gap-2">
                            <code class="grow break-all rounded bg-gray-50 px-2 py-1 text-xs dark:bg-gray-900" x-ref="u">{{ $leadUrl }}</code>
                            <button type="button" class="{{ $btn }}" x-on:click="navigator.clipboard.writeText($refs.u.innerText);c=true;setTimeout(()=>c=false,1500)" x-text="c?'Kopiert!':'Kopieren'"></button>
                        </div>
                    </div>
                    <div>
                        <div class="{{ $label }}">Header</div>
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300"><code>X-GND-Timestamp</code> · <code>X-GND-Signature: sha256=&lt;hmac&gt;</code></p>
                    </div>
                    <div>
                        <div class="{{ $label }}">Secret (<code>WP_LEAD_SECRET</code>)</div>
                        <div x-data="{s:false}" class="mt-1 flex items-center gap-2">
                            <code class="grow break-all rounded bg-gray-50 px-2 py-1 text-xs dark:bg-gray-900" x-text="s ? @js($leadSecret) : '••••••••••••••••••••'"></code>
                            <button type="button" class="{{ $btn }}" x-on:click="s=!s" x-text="s?'Verbergen':'Anzeigen'"></button>
                            <button type="button" class="{{ $btn }}" x-on:click="navigator.clipboard.writeText(@js($leadSecret))">Kopieren</button>
                        </div>
                    </div>
                    <div>
                        <div class="{{ $label }}">Idempotenz-Key</div>
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300"><code>wp_lead:&lt;lead_id&gt;</code></p>
                    </div>
                    <div>
                        <div class="{{ $label }}">Beispiel-Payload</div>
                        <div x-data="{c:false}" class="relative">
                            <pre class="{{ $pre }}"><code x-ref="p">{{ $leadSample }}</code></pre>
                            <button type="button" class="{{ $btn }} absolute right-2 top-2" x-on:click="navigator.clipboard.writeText($refs.p.innerText);c=true;setTimeout(()=>c=false,1500)" x-text="c?'Kopiert!':'Kopieren'"></button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Conversion ingest --}}
            <section class="{{ $card }}">
                <h2 class="{{ $h2 }}">② Conversion-Ingest &mdash; Pipedrive/Zapier &rarr; Dashboard</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Feuert, wenn ein Deal auf „beauftragt" gesetzt wird. <strong>Ohne Kundendaten</strong> (DSGVO).</p>

                <div class="mt-4 space-y-3">
                    <div>
                        <div class="{{ $label }}">Endpoint (POST)</div>
                        <div x-data="{c:false}" class="mt-1 flex items-center gap-2">
                            <code class="grow break-all rounded bg-gray-50 px-2 py-1 text-xs dark:bg-gray-900" x-ref="u">{{ $conversionUrl }}</code>
                            <button type="button" class="{{ $btn }}" x-on:click="navigator.clipboard.writeText($refs.u.innerText);c=true;setTimeout(()=>c=false,1500)" x-text="c?'Kopiert!':'Kopieren'"></button>
                        </div>
                    </div>
                    <div>
                        <div class="{{ $label }}">Secret (<code>CONVERSION_SECRET</code>)</div>
                        <div x-data="{s:false}" class="mt-1 flex items-center gap-2">
                            <code class="grow break-all rounded bg-gray-50 px-2 py-1 text-xs dark:bg-gray-900" x-text="s ? @js($conversionSecret) : '••••••••••••••••••••'"></code>
                            <button type="button" class="{{ $btn }}" x-on:click="s=!s" x-text="s?'Verbergen':'Anzeigen'"></button>
                            <button type="button" class="{{ $btn }}" x-on:click="navigator.clipboard.writeText(@js($conversionSecret))">Kopieren</button>
                        </div>
                    </div>
                    <div>
                        <div class="{{ $label }}">Idempotenz-Key</div>
                        <p class="mt-1 text-xs text-gray-700 dark:text-gray-300"><code>conversion:&lt;deal_id&gt;</code></p>
                    </div>
                    <div>
                        <div class="{{ $label }}">Beispiel-Payload</div>
                        <div x-data="{c:false}" class="relative">
                            <pre class="{{ $pre }}"><code x-ref="p">{{ $conversionSample }}</code></pre>
                            <button type="button" class="{{ $btn }} absolute right-2 top-2" x-on:click="navigator.clipboard.writeText($refs.p.innerText);c=true;setTimeout(()=>c=false,1500)" x-text="c?'Kopiert!':'Kopieren'"></button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        {{-- ============ ZAPIER ============ --}}
        <section class="{{ $card }}">
            <h2 class="{{ $h2 }}">Zapier einrichten (Pipedrive &rarr; Dashboard)</h2>
            <ol class="mt-3 list-decimal space-y-1 pl-5 text-sm text-gray-700 dark:text-gray-300">
                <li><strong>Trigger:</strong> Pipedrive → <em>Updated Deal</em>.</li>
                <li><strong>Filter:</strong> nur fortfahren wenn <em>Stage = beauftragt</em> UND vorherige Stage ≠ beauftragt UND <em>{{ $pipedriveField }}</em> gefüllt.</li>
                <li><strong>Code by Zapier (JavaScript):</strong> erzeugt Body + Signatur (Snippet unten, Secret einsetzen).</li>
                <li><strong>Webhooks by Zapier → POST</strong> an den Conversion-Endpoint; Body = <code>output.body</code> (raw), Header <code>X-GND-Timestamp = output.ts</code>, <code>X-GND-Signature = output.signature</code>.</li>
                <li><strong>Testen</strong> und im <em>Webhook-Protokoll</em> prüfen.</li>
            </ol>

            <div class="mt-4">
                <div class="{{ $label }}">HMAC-Snippet für „Code by Zapier"</div>
                <div x-data="{c:false}" class="relative">
                    <pre class="{{ $pre }}"><code x-ref="z">{{ $zapierSnippet }}</code></pre>
                    <button type="button" class="{{ $btn }} absolute right-2 top-2" x-on:click="navigator.clipboard.writeText($refs.z.innerText);c=true;setTimeout(()=>c=false,1500)" x-text="c?'Kopiert!':'Kopieren'"></button>
                </div>
            </div>

            <div class="mt-4">
                <div class="{{ $label }}">Feld-Zuordnung (Pipedrive → JSON)</div>
                <table class="mt-1 w-full text-left text-xs">
                    <thead class="text-gray-500 dark:text-gray-400">
                        <tr><th class="py-1 pr-4">Pipedrive-Feld</th><th class="py-1">JSON-Feld</th></tr>
                    </thead>
                    <tbody class="text-gray-700 dark:text-gray-300">
                        <tr><td class="py-1 pr-4">{{ $pipedriveField }} (Custom Field)</td><td><code>lead_id</code></td></tr>
                        <tr><td class="py-1 pr-4">Deal-ID</td><td><code>deal_id</code></td></tr>
                        <tr><td class="py-1 pr-4">Deal-Wert (netto)</td><td><code>deal_value</code></td></tr>
                        <tr><td class="py-1 pr-4">Gutscheincode</td><td><code>voucher_code</code></td></tr>
                        <tr><td class="py-1 pr-4">Beauftragt-Zeitpunkt</td><td><code>converted_at</code></td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        {{-- ============ OUTBOUND + WP ============ --}}
        <div class="grid gap-6 lg:grid-cols-2">
            <section class="{{ $card }}">
                <h2 class="{{ $h2 }}">③ Voucher-Sync &mdash; Dashboard &rarr; WordPress</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Neue/aktualisierte Codes werden hierhin (upsert, HMAC-signiert) gesendet.</p>
                <div class="mt-4 space-y-3">
                    <div>
                        <div class="{{ $label }}">Ziel-Endpoint (<code>WP_VOUCHER_ENDPOINT</code>)</div>
                        @if ($wpVoucherEndpoint)
                            <code class="mt-1 block break-all rounded bg-gray-50 px-2 py-1 text-xs dark:bg-gray-900">{{ $wpVoucherEndpoint }}</code>
                        @else
                            <p class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400">⚠ Noch nicht gesetzt – oben rechts über <strong>„Einstellungen bearbeiten"</strong> eintragen (z. B. <code>https://…/wp-json/gnd/v1/vouchers</code>).</p>
                        @endif
                    </div>
                    <div>
                        <div class="{{ $label }}">Secret (<code>WP_SYNC_SECRET</code>)</div>
                        <div x-data="{s:false}" class="mt-1 flex items-center gap-2">
                            <code class="grow break-all rounded bg-gray-50 px-2 py-1 text-xs dark:bg-gray-900" x-text="s ? @js($syncSecret) : '••••••••••••••••••••'"></code>
                            <button type="button" class="{{ $btn }}" x-on:click="s=!s" x-text="s?'Verbergen':'Anzeigen'"></button>
                            <button type="button" class="{{ $btn }}" x-on:click="navigator.clipboard.writeText(@js($syncSecret))">Kopieren</button>
                        </div>
                    </div>
                    <div>
                        <div class="{{ $label }}">Beispiel-Payload</div>
                        <pre class="{{ $pre }}"><code>{{ $voucherSample }}</code></pre>
                    </div>
                </div>
            </section>

            <section class="{{ $card }}">
                <h2 class="{{ $h2 }}">WordPress &amp; Pipedrive – Voraussetzungen</h2>
                <div class="mt-3 space-y-4 text-sm text-gray-700 dark:text-gray-300">
                    <div>
                        <div class="{{ $label }}">WordPress-Plugin</div>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            <li>Lead-Webhook auf den Lead-Endpoint (oben) mit <code>WP_LEAD_SECRET</code> zeigen; Payload muss <code>lead_id</code> enthalten.</li>
                            <li>Neuen Endpoint <code>POST /wp-json/gnd/v1/vouchers</code> bereitstellen (Upsert nach <code>code</code>, HMAC gegen <code>WP_SYNC_SECRET</code>).</li>
                        </ul>
                    </div>
                    <div>
                        <div class="{{ $label }}">Pipedrive</div>
                        <ul class="mt-1 list-disc space-y-1 pl-5">
                            <li>Custom-Deal-Feld <strong>„{{ $pipedriveField }}"</strong> anlegen und beim Anlegen des Deals mit der WP-<code>lead_id</code> füllen.</li>
                            <li>Ohne diese durchgereichte ID landet eine Conversion in der Prüf-Warteschlange.</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="{{ $logUrl }}" class="inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline dark:text-primary-400">
                        → Webhook-Protokoll öffnen
                    </a>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
