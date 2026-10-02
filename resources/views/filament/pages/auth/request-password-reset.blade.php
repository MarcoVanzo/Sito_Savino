<x-filament-panels::page.simple>
    @if (filament()->hasLogin() && ! $inviataA)
        <x-slot name="subheading">
            {{ $this->loginAction }}
        </x-slot>
    @endif

    @if ($inviataA)
        <div class="space-y-4 text-center" aria-live="polite">
            <x-filament::icon
                icon="heroicon-o-envelope"
                class="mx-auto h-12 w-12 text-primary-600 dark:text-primary-400"
            />

            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                Controlla la tua email
            </h2>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Se <strong class="text-gray-950 dark:text-white">{{ $inviataA }}</strong>
                ha un account nel pannello, ti abbiamo appena inviato il link per
                scegliere una nuova password. Il link vale {{ $this->minutiDiValidita() }} minuti.
            </p>

            <p class="text-sm text-gray-600 dark:text-gray-400">
                Non lo trovi? Guarda nello spam, oppure
                <x-filament::link tag="button" wire:click="altroIndirizzo">
                    prova con un altro indirizzo
                </x-filament::link>.
            </p>

            <x-filament::button tag="a" :href="filament()->getLoginUrl()" class="w-full">
                Torna all'accesso
            </x-filament::button>
        </div>
    @else
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

        <x-filament-panels::form id="form" wire:submit="request">
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_PASSWORD_RESET_REQUEST_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
    @endif
</x-filament-panels::page.simple>
