{{--
    Menu laterale a fisarmonica: aprendo un gruppo, gli altri si chiudono.
    Filament non lo prevede, quindi si sostituisce il metodo dello store Alpine
    "sidebar" con una versione che, in apertura, marca come chiusi tutti gli
    altri gruppi di primo livello letti dal DOM (data-group-label).
--}}
<script>
    document.addEventListener('alpine:initialized', () => {
        const store = window.Alpine.store('sidebar')

        if (! store || store.accordionPatched) {
            return
        }

        store.accordionPatched = true

        const toggle = store.toggleCollapsedGroup.bind(store)

        store.toggleCollapsedGroup = function (group) {
            const wasCollapsed = this.groupIsCollapsed(group)

            toggle(group)

            // Stavamo chiudendo un gruppo: gli altri restano come sono.
            if (! wasCollapsed) {
                return
            }

            this.collapsedGroups = Array.from(
                document.querySelectorAll('.fi-main-sidebar [data-group-label]'),
            )
                .map((element) => element.dataset.groupLabel)
                .filter(
                    (label) =>
                        label &&
                        label !== group &&
                        ! label.startsWith('sub_navigation_'),
                )
        }
    })
</script>
