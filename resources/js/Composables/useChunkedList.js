import { computed, ref, watch } from 'vue'

/**
 * Rendering incrementale di una lista lunga.
 *
 * Montare centinaia di nodi in un colpo solo (la gallery ne aveva ~900)
 * produce un DOM che i telefoni non reggono: si parte da un blocco e si
 * cresce su richiesta. Quando la lista sorgente cambia (un filtro), il
 * limite riparte dal primo blocco: senza, resterebbe il limite alto
 * ereditato dal filtro precedente.
 *
 * @param {import('vue').Ref<Array>} source lista completa (già filtrata)
 * @param {number} chunk elementi mostrati per blocco
 */
export function useChunkedList(source, chunk = 60) {
    const limit = ref(chunk)

    const visible = computed(() => source.value.slice(0, limit.value))
    const hasMore = computed(() => source.value.length > limit.value)
    const remaining = computed(() => Math.max(source.value.length - limit.value, 0))

    function showMore() {
        limit.value += chunk
    }

    watch(source, () => {
        limit.value = chunk
    })

    return { visible, hasMore, remaining, showMore }
}
