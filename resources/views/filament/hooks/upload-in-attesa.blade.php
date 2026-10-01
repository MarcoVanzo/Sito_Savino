{{--
    Un file trascinato su un campo di upload non ancora pronto (FilePond non
    ancora creato, vedi theme.css) verrebbe aperto dal browser al posto del
    pannello, perdendo il modulo. Qui il drop si ferma e il cursore lo dice.
--}}
<script>
    (() => {
        if (window.uploadInAttesaPatched) {
            return
        }

        window.uploadInAttesaPatched = true

        const campoInAttesa = (event) => {
            const campo = event.target.closest?.('.fi-fo-file-upload')

            return campo && ! campo.querySelector('.filepond--root')
        }

        document.addEventListener('dragover', (event) => {
            if (campoInAttesa(event)) {
                event.preventDefault()
                event.dataTransfer.dropEffect = 'none'
            }
        })

        document.addEventListener('drop', (event) => {
            if (campoInAttesa(event)) {
                event.preventDefault()
            }
        })
    })()
</script>
