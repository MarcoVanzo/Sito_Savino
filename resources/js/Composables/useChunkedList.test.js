import { describe, expect, it } from 'vitest'
import { nextTick, ref } from 'vue'
import { useChunkedList } from './useChunkedList.js'

const lista = (n) => Array.from({ length: n }, (_, i) => ({ id: i }))

describe('useChunkedList', () => {
    it('mostra solo il primo blocco', () => {
        const source = ref(lista(150))
        const { visible, hasMore, remaining } = useChunkedList(source, 60)

        expect(visible.value).toHaveLength(60)
        expect(hasMore.value).toBe(true)
        expect(remaining.value).toBe(90)
    })

    it('showMore aggiunge un blocco alla volta', () => {
        const source = ref(lista(150))
        const { visible, hasMore, remaining, showMore } = useChunkedList(source, 60)

        showMore()
        expect(visible.value).toHaveLength(120)

        showMore()
        expect(visible.value).toHaveLength(150)
        expect(hasMore.value).toBe(false)
        expect(remaining.value).toBe(0)
    })

    it('con meno elementi del blocco non resta nulla da mostrare', () => {
        const source = ref(lista(10))
        const { visible, hasMore, remaining } = useChunkedList(source, 60)

        expect(visible.value).toHaveLength(10)
        expect(hasMore.value).toBe(false)
        expect(remaining.value).toBe(0)
    })

    it('un cambio della lista riparte dal primo blocco', async () => {
        const source = ref(lista(150))
        const { visible, showMore } = useChunkedList(source, 60)

        showMore()
        expect(visible.value).toHaveLength(120)

        // cambio filtro: la lista sorgente viene sostituita
        source.value = lista(90)
        await nextTick()

        expect(visible.value).toHaveLength(60)
    })
})
