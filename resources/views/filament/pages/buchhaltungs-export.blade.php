<x-filament-panels::page>
    <form>
        {{ $this->form }}
    </form>

    <x-filament::section>
        <x-slot name="heading">Export</x-slot>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Wählen Sie oben den Zeitraum und nutzen Sie die Buttons oben rechts:
            <strong>Detail-CSV</strong> (eine Zeile je beauftragtem Lead) und
            <strong>Summen-CSV</strong> (eine Zeile je Partner). Beide öffnen in deutschem Excel.
        </p>
    </x-filament::section>
</x-filament-panels::page>
