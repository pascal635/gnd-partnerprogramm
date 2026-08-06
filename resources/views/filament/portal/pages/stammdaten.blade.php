<x-filament-panels::page>
    @php($partner = $this->getPartner())

    {{-- Gutscheincode(s) + Konditionen (nur Ansicht) --}}
    @if ($partner && $partner->voucherCodes->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Mein Gutscheincode</x-slot>
            <x-slot name="description">Diese Konditionen sind mit dir vereinbart.</x-slot>

            <div class="space-y-4">
                @foreach ($partner->voucherCodes as $code)
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Code</div>
                            <div class="font-mono text-lg font-bold">{{ $code->code }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Kundenrabatt</div>
                            <div class="text-lg font-semibold">{{ $code->discountLabel() }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">Deine Provision</div>
                            <div class="text-lg font-semibold">{{ $code->commission_raw ?: '—' }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @endif

    {{-- Bearbeitbare Stammdaten --}}
    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit">
                Speichern
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
