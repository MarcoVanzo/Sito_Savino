<footer class="mt-8 mb-4 border-t border-gray-200 dark:border-white/10 pt-4 flex flex-col sm:flex-row justify-between items-center text-sm text-gray-500 dark:text-gray-400 gap-2">
    <div class="flex items-center gap-2">
        <span>&copy; {{ date('Y') }} <strong>Savino Del Bene Volley</strong>.</span>
        <span>Tutti i diritti riservati.</span>
    </div>
    <div class="flex items-center gap-2">
        <span>Developed by</span>
        <span class="font-bold">MV Consulting Srl</span>
    </div>
</footer>

<script>
    (function () {
        // 1. Disattiva lo scrollIntoView automatico solo ed esclusivamente per gli elementi della barra laterale.
        // Impedisce a Filament di forzare il centramento verticale della voce di menu attiva.
        const originalScrollIntoView = HTMLElement.prototype.scrollIntoView;
        HTMLElement.prototype.scrollIntoView = function (options) {
            if (this.closest('.fi-sidebar') || this.closest('.fi-sidebar-nav')) {
                return;
            }
            return originalScrollIntoView.call(this, options);
        };

        // 2. Funzione per gestire il salvataggio e il ripristino dello scroll del menu laterale
        function handleSidebarScroll() {
            const nav = document.querySelector('.fi-sidebar-nav');
            if (!nav) return;

            // Ripristina lo scroll salvato in localStorage
            const savedScrollTop = localStorage.getItem('sidebarScrollTop');
            if (savedScrollTop !== null) {
                const scrollTopVal = parseInt(savedScrollTop, 10);
                
                // Usiamo sia requestAnimationFrame che un piccolo setTimeout di sicurezza
                // per assicurarci che il DOM sia completamente renderizzato ed abbia calcolato le altezze corrette.
                requestAnimationFrame(() => {
                    nav.scrollTop = scrollTopVal;
                });
                setTimeout(() => {
                    nav.scrollTop = scrollTopVal;
                }, 15);
            }

            // Salva la posizione dello scroll ogni volta che l'utente effettua uno scroll nel menu
            nav.addEventListener('scroll', () => {
                localStorage.setItem('sidebarScrollTop', String(nav.scrollTop));
            }, { passive: true });
        }

        // Inizializza lo stato dello scroll al caricamento della pagina e ad ogni navigazione Livewire SPA
        document.addEventListener('DOMContentLoaded', handleSidebarScroll);
        document.addEventListener('livewire:navigated', handleSidebarScroll);
    })();
</script>


